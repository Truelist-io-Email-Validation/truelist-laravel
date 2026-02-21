<?php

namespace Truelist;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Truelist\Exceptions\ApiException;
use Truelist\Exceptions\AuthenticationException;
use Truelist\Exceptions\RateLimitException;

class TruelistClient
{
    public function validate(string $email): ValidationResult
    {
        $cached = $this->readCache($email);

        if ($cached !== null) {
            return $cached;
        }

        $result = $this->performRequest($email);

        if (! $result->isUnknown()) {
            $this->writeCache($email, $result);
        }

        return $result;
    }

    private function performRequest(string $email): ValidationResult
    {
        $baseUrl = rtrim(config('truelist.base_url', 'https://api.truelist.io'), '/');
        $apiKey = config('truelist.api_key');
        $timeout = config('truelist.timeout', 10);

        if (empty($apiKey)) {
            throw new AuthenticationException(
                'Truelist API key is not configured. Set TRUELIST_API_KEY in your .env file.'
            );
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout($timeout)
                ->retry(2, 100, function ($exception, $request) {
                    return $exception instanceof \Illuminate\Http\Client\RequestException
                        && ($exception->response->status() === 429 || $exception->response->status() >= 500);
                }, throw: false)
                ->acceptJson()
                ->post("{$baseUrl}/api/v1/verify_inline?" . http_build_query(['email' => $email]));

            return $this->handleResponse($email, $response);
        } catch (AuthenticationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $this->handleError($email, $e);
        }
    }

    private function handleResponse(string $email, \Illuminate\Http\Client\Response $response): ValidationResult
    {
        if ($response->status() === 401) {
            throw new AuthenticationException(
                'Invalid API key. Check your Truelist API key configuration.'
            );
        }

        if ($response->status() === 429) {
            return $this->handleError(
                $email,
                new RateLimitException('Rate limit exceeded')
            );
        }

        if ($response->status() !== 200) {
            return $this->handleError(
                $email,
                new ApiException("API returned {$response->status()}: {$response->body()}")
            );
        }

        return $this->parseSuccess($email, $response);
    }

    private function parseSuccess(string $email, \Illuminate\Http\Client\Response $response): ValidationResult
    {
        try {
            $data = $response->json();

            if (! is_array($data) || ! isset($data['emails'][0])) {
                return $this->handleError(
                    $email,
                    new ApiException('Invalid JSON response from API')
                );
            }

            $entry = $data['emails'][0];

            return new ValidationResult(
                email: $entry['address'] ?? $email,
                state: $entry['email_state'] ?? 'unknown',
                subState: $entry['email_sub_state'] ?? null,
                domain: $entry['domain'] ?? null,
                canonical: $entry['canonical'] ?? null,
                mxRecord: $entry['mx_record'] ?? null,
                firstName: $entry['first_name'] ?? null,
                lastName: $entry['last_name'] ?? null,
                verifiedAt: $entry['verified_at'] ?? null,
                suggestion: $entry['did_you_mean'] ?? null,
            );
        } catch (\Throwable $e) {
            return $this->handleError($email, $e);
        }
    }

    private function handleError(string $email, \Throwable $error): ValidationResult
    {
        if (config('truelist.raise_on_error', false)) {
            throw $error instanceof \Truelist\Exceptions\TruelistException
                ? $error
                : new ApiException($error->getMessage(), 0, $error);
        }

        return new ValidationResult(
            email: $email,
            state: 'unknown',
            error: true,
        );
    }

    private function cacheKey(string $email): string
    {
        $prefix = config('truelist.cache.prefix', 'truelist:');

        return $prefix . 'validation:' . mb_strtolower(trim($email));
    }

    private function readCache(string $email): ?ValidationResult
    {
        if (! config('truelist.cache.enabled', false)) {
            return null;
        }

        return Cache::get($this->cacheKey($email));
    }

    private function writeCache(string $email, ValidationResult $result): void
    {
        if (! config('truelist.cache.enabled', false)) {
            return;
        }

        $ttl = config('truelist.cache.ttl', 3600);

        Cache::put($this->cacheKey($email), $result, $ttl);
    }
}

<?php

namespace Truelist\Tests;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Truelist\Exceptions\ApiException;
use Truelist\Exceptions\AuthenticationException;
use Truelist\Exceptions\RateLimitException;
use Truelist\TruelistClient;
use Truelist\ValidationResult;

class TruelistClientTest extends TestCase
{
    private TruelistClient $client;

    private string $apiUrl = 'https://api.truelist.io/api/v1/verify_inline*';

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new TruelistClient;
    }

    // --- Successful responses ---

    public function test_validate_returns_ok_result(): void
    {
        Http::fake([
            $this->apiUrl => Http::response($this->apiResponse()),
        ]);

        $result = $this->client->validate('user@example.com');

        $this->assertInstanceOf(ValidationResult::class, $result);
        $this->assertSame('ok', $result->state);
        $this->assertSame('email_ok', $result->subState);
        $this->assertTrue($result->isValid());
        $this->assertFalse($result->isError());
    }

    public function test_validate_returns_invalid_result(): void
    {
        Http::fake([
            $this->apiUrl => Http::response($this->apiResponse([
                'address' => 'bad@example.com',
                'email_state' => 'email_invalid',
                'email_sub_state' => 'failed_no_mailbox',
            ])),
        ]);

        $result = $this->client->validate('bad@example.com');

        $this->assertSame('email_invalid', $result->state);
        $this->assertSame('failed_no_mailbox', $result->subState);
        $this->assertTrue($result->isInvalid());
    }

    public function test_validate_returns_accept_all_result(): void
    {
        Http::fake([
            $this->apiUrl => Http::response($this->apiResponse([
                'email_state' => 'accept_all',
                'email_sub_state' => 'accept_all',
            ])),
        ]);

        $result = $this->client->validate('info@example.com');

        $this->assertSame('accept_all', $result->state);
        $this->assertTrue($result->isAcceptAll());
    }

    public function test_validate_returns_result_with_all_fields(): void
    {
        Http::fake([
            $this->apiUrl => Http::response($this->apiResponse([
                'address' => 'user@example.com',
                'domain' => 'example.com',
                'canonical' => 'user',
                'mx_record' => 'mx.example.com',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email_state' => 'ok',
                'email_sub_state' => 'email_ok',
                'verified_at' => '2026-02-21T10:00:00.000Z',
                'did_you_mean' => 'user@gmail.com',
            ])),
        ]);

        $result = $this->client->validate('user@example.com');

        $this->assertSame('example.com', $result->domain);
        $this->assertSame('user', $result->canonical);
        $this->assertSame('mx.example.com', $result->mxRecord);
        $this->assertSame('John', $result->firstName);
        $this->assertSame('Doe', $result->lastName);
        $this->assertSame('2026-02-21T10:00:00.000Z', $result->verifiedAt);
        $this->assertSame('user@gmail.com', $result->suggestion);
    }

    public function test_validate_maps_disposable_sub_state(): void
    {
        Http::fake([
            $this->apiUrl => Http::response($this->apiResponse([
                'email_state' => 'email_invalid',
                'email_sub_state' => 'is_disposable',
            ])),
        ]);

        $result = $this->client->validate('temp@throwaway.com');

        $this->assertTrue($result->isDisposable());
    }

    public function test_validate_maps_role_sub_state(): void
    {
        Http::fake([
            $this->apiUrl => Http::response($this->apiResponse([
                'email_state' => 'ok',
                'email_sub_state' => 'is_role',
            ])),
        ]);

        $result = $this->client->validate('admin@example.com');

        $this->assertTrue($result->isRole());
    }

    // --- Error handling with raise_on_error disabled (default) ---

    public function test_401_always_throws_authentication_exception(): void
    {
        Http::fake([
            $this->apiUrl => Http::response('Unauthorized', 401),
        ]);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid API key');

        $this->client->validate('user@example.com');
    }

    public function test_401_throws_even_with_raise_on_error_disabled(): void
    {
        config()->set('truelist.raise_on_error', false);

        Http::fake([
            $this->apiUrl => Http::response('Unauthorized', 401),
        ]);

        $this->expectException(AuthenticationException::class);

        $this->client->validate('user@example.com');
    }

    public function test_429_returns_unknown_with_error_flag(): void
    {
        Http::fake([
            $this->apiUrl => Http::response('Rate limit exceeded', 429),
        ]);

        $result = $this->client->validate('user@example.com');

        $this->assertTrue($result->isUnknown());
        $this->assertTrue($result->isError());
        $this->assertSame('user@example.com', $result->email);
    }

    public function test_500_returns_unknown_with_error_flag(): void
    {
        Http::fake([
            $this->apiUrl => Http::response('Internal Server Error', 500),
        ]);

        $result = $this->client->validate('user@example.com');

        $this->assertTrue($result->isUnknown());
        $this->assertTrue($result->isError());
    }

    public function test_connection_error_returns_unknown_with_error_flag(): void
    {
        Http::fake([
            $this->apiUrl => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
            },
        ]);

        $result = $this->client->validate('user@example.com');

        $this->assertTrue($result->isUnknown());
        $this->assertTrue($result->isError());
    }

    public function test_malformed_json_returns_unknown_with_error_flag(): void
    {
        Http::fake([
            $this->apiUrl => Http::response('not json', 200, ['Content-Type' => 'text/plain']),
        ]);

        $result = $this->client->validate('user@example.com');

        $this->assertTrue($result->isUnknown());
        $this->assertTrue($result->isError());
    }

    // --- Error handling with raise_on_error enabled ---

    public function test_429_throws_rate_limit_exception_when_raise_on_error(): void
    {
        config()->set('truelist.raise_on_error', true);

        Http::fake([
            $this->apiUrl => Http::response('Rate limit exceeded', 429),
        ]);

        $this->expectException(RateLimitException::class);

        $this->client->validate('user@example.com');
    }

    public function test_500_throws_api_exception_when_raise_on_error(): void
    {
        config()->set('truelist.raise_on_error', true);

        Http::fake([
            $this->apiUrl => Http::response('Internal Server Error', 500),
        ]);

        $this->expectException(ApiException::class);

        $this->client->validate('user@example.com');
    }

    public function test_401_throws_authentication_exception_when_raise_on_error(): void
    {
        config()->set('truelist.raise_on_error', true);

        Http::fake([
            $this->apiUrl => Http::response('Unauthorized', 401),
        ]);

        $this->expectException(AuthenticationException::class);

        $this->client->validate('user@example.com');
    }

    public function test_connection_error_throws_when_raise_on_error(): void
    {
        config()->set('truelist.raise_on_error', true);

        Http::fake([
            $this->apiUrl => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
            },
        ]);

        $this->expectException(ApiException::class);

        $this->client->validate('user@example.com');
    }

    // --- Caching ---

    public function test_caches_successful_result(): void
    {
        config()->set('truelist.cache.enabled', true);

        $callCount = 0;
        Http::fake([
            $this->apiUrl => function () use (&$callCount) {
                $callCount++;

                return Http::response($this->apiResponse());
            },
        ]);

        $result1 = $this->client->validate('user@example.com');
        $result2 = $this->client->validate('user@example.com');

        $this->assertSame(1, $callCount);
        $this->assertSame($result1->state, $result2->state);
    }

    public function test_does_not_cache_unknown_error_results(): void
    {
        config()->set('truelist.cache.enabled', true);

        $callCount = 0;
        Http::fake([
            $this->apiUrl => function () use (&$callCount) {
                $callCount++;

                // Return 500 for the first 3 calls (initial + 2 retries)
                // so the first validate() exhausts retries and returns unknown
                if ($callCount <= 3) {
                    return Http::response('Internal Server Error', 500);
                }

                return Http::response($this->apiResponse());
            },
        ]);

        $result1 = $this->client->validate('user@example.com');
        $this->assertTrue($result1->isUnknown());
        $this->assertTrue($result1->isError());

        $result2 = $this->client->validate('user@example.com');
        $this->assertSame('ok', $result2->state);
        $this->assertFalse($result2->isError());

        // 3 calls for first validate (initial + 2 retries), 1 for second
        $this->assertSame(4, $callCount);
    }

    public function test_makes_separate_requests_for_different_emails(): void
    {
        config()->set('truelist.cache.enabled', true);

        $callCount = 0;
        Http::fake([
            $this->apiUrl => function () use (&$callCount) {
                $callCount++;

                return Http::response($this->apiResponse());
            },
        ]);

        $this->client->validate('user1@example.com');
        $this->client->validate('user2@example.com');

        $this->assertSame(2, $callCount);
    }

    public function test_normalizes_email_case_for_cache_key(): void
    {
        config()->set('truelist.cache.enabled', true);

        $callCount = 0;
        Http::fake([
            $this->apiUrl => function () use (&$callCount) {
                $callCount++;

                return Http::response($this->apiResponse());
            },
        ]);

        $this->client->validate('User@Example.com');
        $this->client->validate('user@example.com');

        $this->assertSame(1, $callCount);
    }

    public function test_does_not_use_cache_when_disabled(): void
    {
        config()->set('truelist.cache.enabled', false);

        $callCount = 0;
        Http::fake([
            $this->apiUrl => function () use (&$callCount) {
                $callCount++;

                return Http::response($this->apiResponse());
            },
        ]);

        $this->client->validate('user@example.com');
        $this->client->validate('user@example.com');

        $this->assertSame(2, $callCount);
    }

    // --- Request format ---

    public function test_sends_correct_request(): void
    {
        Http::fake([
            $this->apiUrl => Http::response($this->apiResponse()),
        ]);

        $this->client->validate('user@example.com');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/v1/verify_inline')
                && str_contains($request->url(), 'email=user%40example.com')
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer test_api_key');
        });
    }

    // --- Early API key validation ---

    public function test_throws_authentication_exception_when_api_key_is_null(): void
    {
        config()->set('truelist.api_key', null);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Truelist API key is not configured');

        $this->client->validate('user@example.com');
    }

    public function test_throws_authentication_exception_when_api_key_is_empty_string(): void
    {
        config()->set('truelist.api_key', '');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Truelist API key is not configured');

        $this->client->validate('user@example.com');
    }

    // --- Trailing slash on base_url ---

    public function test_handles_trailing_slash_on_base_url(): void
    {
        config()->set('truelist.base_url', 'https://api.truelist.io/');

        Http::fake([
            $this->apiUrl => Http::response($this->apiResponse()),
        ]);

        $result = $this->client->validate('user@example.com');

        $this->assertSame('ok', $result->state);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/v1/verify_inline');
        });
    }
}

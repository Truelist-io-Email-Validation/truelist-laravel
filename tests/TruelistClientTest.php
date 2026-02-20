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

    private string $apiUrl = 'https://api.truelist.io/api/v1/verify';

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new TruelistClient;
    }

    // --- Successful responses ---

    public function test_validate_returns_valid_result(): void
    {
        Http::fake([
            $this->apiUrl => Http::response([
                'state' => 'valid',
                'sub_state' => 'ok',
                'suggestion' => null,
                'free_email' => false,
                'role' => false,
                'disposable' => false,
            ]),
        ]);

        $result = $this->client->validate('user@example.com');

        $this->assertInstanceOf(ValidationResult::class, $result);
        $this->assertSame('valid', $result->state);
        $this->assertSame('ok', $result->subState);
        $this->assertTrue($result->isValid());
        $this->assertFalse($result->isError());
    }

    public function test_validate_returns_invalid_result(): void
    {
        Http::fake([
            $this->apiUrl => Http::response([
                'state' => 'invalid',
                'sub_state' => 'failed_no_mailbox',
                'suggestion' => null,
                'free_email' => false,
                'role' => false,
                'disposable' => false,
            ]),
        ]);

        $result = $this->client->validate('bad@example.com');

        $this->assertSame('invalid', $result->state);
        $this->assertSame('failed_no_mailbox', $result->subState);
        $this->assertTrue($result->isInvalid());
    }

    public function test_validate_returns_risky_result(): void
    {
        Http::fake([
            $this->apiUrl => Http::response([
                'state' => 'risky',
                'sub_state' => 'accept_all',
                'suggestion' => null,
                'free_email' => false,
                'role' => false,
                'disposable' => false,
            ]),
        ]);

        $result = $this->client->validate('info@example.com');

        $this->assertSame('risky', $result->state);
        $this->assertTrue($result->isRisky());
    }

    public function test_validate_returns_result_with_all_fields(): void
    {
        Http::fake([
            $this->apiUrl => Http::response([
                'state' => 'valid',
                'sub_state' => 'ok',
                'suggestion' => 'user@gmail.com',
                'free_email' => true,
                'role' => true,
                'disposable' => true,
            ]),
        ]);

        $result = $this->client->validate('user@example.com');

        $this->assertTrue($result->freeEmail);
        $this->assertTrue($result->role);
        $this->assertTrue($result->disposable);
        $this->assertSame('user@gmail.com', $result->suggestion);
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

                return Http::response([
                    'state' => 'valid',
                    'sub_state' => 'ok',
                    'free_email' => false,
                    'role' => false,
                    'disposable' => false,
                ]);
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

                if ($callCount === 1) {
                    return Http::response('Internal Server Error', 500);
                }

                return Http::response([
                    'state' => 'valid',
                    'sub_state' => 'ok',
                    'free_email' => false,
                    'role' => false,
                    'disposable' => false,
                ]);
            },
        ]);

        $result1 = $this->client->validate('user@example.com');
        $this->assertTrue($result1->isUnknown());
        $this->assertTrue($result1->isError());

        $result2 = $this->client->validate('user@example.com');
        $this->assertSame('valid', $result2->state);
        $this->assertFalse($result2->isError());

        $this->assertSame(2, $callCount);
    }

    public function test_makes_separate_requests_for_different_emails(): void
    {
        config()->set('truelist.cache.enabled', true);

        $callCount = 0;
        Http::fake([
            $this->apiUrl => function () use (&$callCount) {
                $callCount++;

                return Http::response([
                    'state' => 'valid',
                    'sub_state' => 'ok',
                    'free_email' => false,
                    'role' => false,
                    'disposable' => false,
                ]);
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

                return Http::response([
                    'state' => 'valid',
                    'sub_state' => 'ok',
                    'free_email' => false,
                    'role' => false,
                    'disposable' => false,
                ]);
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

                return Http::response([
                    'state' => 'valid',
                    'sub_state' => 'ok',
                    'free_email' => false,
                    'role' => false,
                    'disposable' => false,
                ]);
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
            $this->apiUrl => Http::response([
                'state' => 'valid',
                'sub_state' => 'ok',
                'free_email' => false,
                'role' => false,
                'disposable' => false,
            ]),
        ]);

        $this->client->validate('user@example.com');

        Http::assertSent(function ($request) {
            return $request->url() === $this->apiUrl
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer test_api_key')
                && $request['email'] === 'user@example.com';
        });
    }
}

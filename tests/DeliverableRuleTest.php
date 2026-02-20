<?php

namespace Truelist\Tests;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Truelist\Exceptions\AuthenticationException;
use Truelist\Rules\Deliverable;

class DeliverableRuleTest extends TestCase
{
    private string $apiUrl = 'https://api.truelist.io/api/v1/verify';

    public function test_passes_for_valid_email(): void
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

        $validator = Validator::make(
            ['email' => 'user@example.com'],
            ['email' => ['required', 'email', new Deliverable]]
        );

        $this->assertTrue($validator->passes());
    }

    public function test_fails_for_invalid_email(): void
    {
        Http::fake([
            $this->apiUrl => Http::response([
                'state' => 'invalid',
                'sub_state' => 'failed_no_mailbox',
                'free_email' => false,
                'role' => false,
                'disposable' => false,
            ]),
        ]);

        $validator = Validator::make(
            ['email' => 'bad@example.com'],
            ['email' => ['required', 'email', new Deliverable]]
        );

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_passes_for_risky_email_when_allow_risky_is_true(): void
    {
        config()->set('truelist.allow_risky', true);

        Http::fake([
            $this->apiUrl => Http::response([
                'state' => 'risky',
                'sub_state' => 'accept_all',
                'free_email' => false,
                'role' => false,
                'disposable' => false,
            ]),
        ]);

        $validator = Validator::make(
            ['email' => 'info@example.com'],
            ['email' => ['required', 'email', new Deliverable]]
        );

        $this->assertTrue($validator->passes());
    }

    public function test_fails_for_risky_email_when_allow_risky_is_false_via_option(): void
    {
        Http::fake([
            $this->apiUrl => Http::response([
                'state' => 'risky',
                'sub_state' => 'accept_all',
                'free_email' => false,
                'role' => false,
                'disposable' => false,
            ]),
        ]);

        $validator = Validator::make(
            ['email' => 'info@example.com'],
            ['email' => ['required', 'email', new Deliverable(allowRisky: false)]]
        );

        $this->assertFalse($validator->passes());
    }

    public function test_passes_for_unknown_result_from_api(): void
    {
        Http::fake([
            $this->apiUrl => Http::response([
                'state' => 'unknown',
                'sub_state' => 'unknown',
                'free_email' => false,
                'role' => false,
                'disposable' => false,
            ]),
        ]);

        $validator = Validator::make(
            ['email' => 'user@example.com'],
            ['email' => ['required', 'email', new Deliverable]]
        );

        $this->assertTrue($validator->passes());
    }

    public function test_passes_for_transient_api_errors_fail_open(): void
    {
        Http::fake([
            $this->apiUrl => Http::response('Internal Server Error', 500),
        ]);

        $validator = Validator::make(
            ['email' => 'user@example.com'],
            ['email' => ['required', 'email', new Deliverable]]
        );

        $this->assertTrue($validator->passes());
    }

    public function test_auth_errors_propagate(): void
    {
        Http::fake([
            $this->apiUrl => Http::response('Unauthorized', 401),
        ]);

        $this->expectException(AuthenticationException::class);

        $validator = Validator::make(
            ['email' => 'user@example.com'],
            ['email' => ['required', 'email', new Deliverable]]
        );

        $validator->passes();
    }

    public function test_skips_validation_for_empty_value(): void
    {
        $validator = Validator::make(
            ['email' => ''],
            ['email' => [new Deliverable]]
        );

        $this->assertTrue($validator->passes());
        Http::assertNothingSent();
    }

    public function test_skips_validation_for_null_value(): void
    {
        $validator = Validator::make(
            ['email' => null],
            ['email' => [new Deliverable]]
        );

        $this->assertTrue($validator->passes());
        Http::assertNothingSent();
    }

    public function test_string_rule_shorthand(): void
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

        $validator = Validator::make(
            ['email' => 'user@example.com'],
            ['email' => ['required', 'email', 'deliverable']]
        );

        $this->assertTrue($validator->passes());
    }

    public function test_string_rule_shorthand_fails_for_invalid(): void
    {
        Http::fake([
            $this->apiUrl => Http::response([
                'state' => 'invalid',
                'sub_state' => 'failed_no_mailbox',
                'free_email' => false,
                'role' => false,
                'disposable' => false,
            ]),
        ]);

        $validator = Validator::make(
            ['email' => 'bad@example.com'],
            ['email' => ['required', 'email', 'deliverable']]
        );

        $this->assertFalse($validator->passes());
    }

    public function test_error_message_includes_attribute_name(): void
    {
        Http::fake([
            $this->apiUrl => Http::response([
                'state' => 'invalid',
                'sub_state' => 'failed_no_mailbox',
                'free_email' => false,
                'role' => false,
                'disposable' => false,
            ]),
        ]);

        $validator = Validator::make(
            ['email' => 'bad@example.com'],
            ['email' => ['required', 'email', new Deliverable]]
        );

        $validator->passes();
        $errors = $validator->errors()->get('email');

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('email', $errors[0]);
        $this->assertStringContainsString('deliverable', $errors[0]);
    }
}

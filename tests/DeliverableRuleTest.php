<?php

namespace Truelist\Tests;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Truelist\Exceptions\AuthenticationException;
use Truelist\Rules\Deliverable;

class DeliverableRuleTest extends TestCase
{
    private string $apiUrl = 'https://api.truelist.io/api/v1/verify_inline*';

    public function test_passes_for_ok_email(): void
    {
        Http::fake([
            $this->apiUrl => Http::response($this->apiResponse()),
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
            $this->apiUrl => Http::response($this->apiResponse([
                'email_state' => 'email_invalid',
                'email_sub_state' => 'failed_no_mailbox',
            ])),
        ]);

        $validator = Validator::make(
            ['email' => 'bad@example.com'],
            ['email' => ['required', 'email', new Deliverable]]
        );

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_fails_for_accept_all_email(): void
    {
        Http::fake([
            $this->apiUrl => Http::response($this->apiResponse([
                'email_state' => 'accept_all',
                'email_sub_state' => 'accept_all',
            ])),
        ]);

        $validator = Validator::make(
            ['email' => 'info@example.com'],
            ['email' => ['required', 'email', new Deliverable]]
        );

        $this->assertFalse($validator->passes());
    }

    public function test_passes_for_unknown_result_from_api(): void
    {
        Http::fake([
            $this->apiUrl => Http::response($this->apiResponse([
                'email_state' => 'unknown',
                'email_sub_state' => 'unknown',
            ])),
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
            $this->apiUrl => Http::response($this->apiResponse()),
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
            $this->apiUrl => Http::response($this->apiResponse([
                'email_state' => 'email_invalid',
                'email_sub_state' => 'failed_no_mailbox',
            ])),
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
            $this->apiUrl => Http::response($this->apiResponse([
                'email_state' => 'email_invalid',
                'email_sub_state' => 'failed_no_mailbox',
            ])),
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

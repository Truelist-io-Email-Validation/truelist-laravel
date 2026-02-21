<?php

namespace Truelist\Tests;

use Truelist\ValidationResult;

class ValidationResultTest extends TestCase
{
    public function test_is_valid_returns_true_for_ok_state(): void
    {
        $result = new ValidationResult(email: 'user@example.com', state: 'ok');

        $this->assertTrue($result->isValid());
    }

    public function test_is_valid_returns_false_for_email_invalid_state(): void
    {
        $result = new ValidationResult(email: 'bad@example.com', state: 'email_invalid');

        $this->assertFalse($result->isValid());
    }

    public function test_is_valid_returns_false_for_accept_all_state(): void
    {
        $result = new ValidationResult(email: 'user@example.com', state: 'accept_all');

        $this->assertFalse($result->isValid());
    }

    public function test_is_valid_returns_false_for_unknown_state(): void
    {
        $result = new ValidationResult(email: 'user@example.com', state: 'unknown');

        $this->assertFalse($result->isValid());
    }

    public function test_is_invalid_returns_true_for_email_invalid_state(): void
    {
        $result = new ValidationResult(email: 'bad@example.com', state: 'email_invalid');

        $this->assertTrue($result->isInvalid());
    }

    public function test_is_invalid_returns_false_for_ok_state(): void
    {
        $result = new ValidationResult(email: 'user@example.com', state: 'ok');

        $this->assertFalse($result->isInvalid());
    }

    public function test_is_accept_all_returns_true_for_accept_all_state(): void
    {
        $result = new ValidationResult(email: 'user@example.com', state: 'accept_all');

        $this->assertTrue($result->isAcceptAll());
    }

    public function test_is_accept_all_returns_false_for_ok_state(): void
    {
        $result = new ValidationResult(email: 'user@example.com', state: 'ok');

        $this->assertFalse($result->isAcceptAll());
    }

    public function test_is_unknown_returns_true_for_unknown_state(): void
    {
        $result = new ValidationResult(email: 'user@example.com', state: 'unknown');

        $this->assertTrue($result->isUnknown());
    }

    public function test_is_unknown_returns_false_for_ok_state(): void
    {
        $result = new ValidationResult(email: 'user@example.com', state: 'ok');

        $this->assertFalse($result->isUnknown());
    }

    public function test_is_error_returns_true_when_error_flag_is_set(): void
    {
        $result = new ValidationResult(email: 'user@example.com', state: 'unknown', error: true);

        $this->assertTrue($result->isError());
    }

    public function test_is_error_returns_false_by_default(): void
    {
        $result = new ValidationResult(email: 'user@example.com', state: 'unknown');

        $this->assertFalse($result->isError());
    }

    public function test_is_disposable_returns_true_for_is_disposable_sub_state(): void
    {
        $result = new ValidationResult(email: 'temp@throwaway.com', state: 'email_invalid', subState: 'is_disposable');

        $this->assertTrue($result->isDisposable());
    }

    public function test_is_disposable_returns_false_for_other_sub_state(): void
    {
        $result = new ValidationResult(email: 'user@example.com', state: 'ok', subState: 'email_ok');

        $this->assertFalse($result->isDisposable());
    }

    public function test_is_role_returns_true_for_is_role_sub_state(): void
    {
        $result = new ValidationResult(email: 'admin@example.com', state: 'ok', subState: 'is_role');

        $this->assertTrue($result->isRole());
    }

    public function test_is_role_returns_false_for_other_sub_state(): void
    {
        $result = new ValidationResult(email: 'user@example.com', state: 'ok', subState: 'email_ok');

        $this->assertFalse($result->isRole());
    }

    public function test_attributes_are_accessible(): void
    {
        $result = new ValidationResult(
            email: 'user@example.com',
            state: 'ok',
            subState: 'email_ok',
            domain: 'example.com',
            canonical: 'user',
            mxRecord: 'mx.example.com',
            firstName: 'John',
            lastName: 'Doe',
            verifiedAt: '2026-02-21T10:00:00.000Z',
            suggestion: 'user@gmail.com',
        );

        $this->assertSame('user@example.com', $result->email);
        $this->assertSame('ok', $result->state);
        $this->assertSame('email_ok', $result->subState);
        $this->assertSame('example.com', $result->domain);
        $this->assertSame('user', $result->canonical);
        $this->assertSame('mx.example.com', $result->mxRecord);
        $this->assertSame('John', $result->firstName);
        $this->assertSame('Doe', $result->lastName);
        $this->assertSame('2026-02-21T10:00:00.000Z', $result->verifiedAt);
        $this->assertSame('user@gmail.com', $result->suggestion);
    }

    public function test_defaults_for_optional_fields(): void
    {
        $result = new ValidationResult(email: 'user@example.com', state: 'ok');

        $this->assertNull($result->subState);
        $this->assertNull($result->domain);
        $this->assertNull($result->canonical);
        $this->assertNull($result->mxRecord);
        $this->assertNull($result->firstName);
        $this->assertNull($result->lastName);
        $this->assertNull($result->verifiedAt);
        $this->assertNull($result->suggestion);
        $this->assertFalse($result->error);
    }

    public function test_to_array(): void
    {
        $result = new ValidationResult(
            email: 'user@example.com',
            state: 'ok',
            subState: 'email_ok',
            domain: 'example.com',
            canonical: 'user',
            mxRecord: null,
            firstName: null,
            lastName: null,
            verifiedAt: '2026-02-21T10:00:00.000Z',
            suggestion: null,
        );

        $expected = [
            'email' => 'user@example.com',
            'state' => 'ok',
            'sub_state' => 'email_ok',
            'domain' => 'example.com',
            'canonical' => 'user',
            'mx_record' => null,
            'first_name' => null,
            'last_name' => null,
            'verified_at' => '2026-02-21T10:00:00.000Z',
            'suggestion' => null,
            'error' => false,
        ];

        $this->assertSame($expected, $result->toArray());
    }
}

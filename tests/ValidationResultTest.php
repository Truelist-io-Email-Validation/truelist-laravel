<?php

namespace Truelist\Tests;

use Truelist\ValidationResult;

class ValidationResultTest extends TestCase
{
    public function test_is_valid_returns_true_for_valid_state(): void
    {
        $result = new ValidationResult(email: 'user@example.com', state: 'valid');

        $this->assertTrue($result->isValid());
    }

    public function test_is_valid_returns_true_for_risky_when_allow_risky_is_true(): void
    {
        config()->set('truelist.allow_risky', true);

        $result = new ValidationResult(email: 'user@example.com', state: 'risky');

        $this->assertTrue($result->isValid());
    }

    public function test_is_valid_returns_false_for_risky_when_allow_risky_is_false(): void
    {
        config()->set('truelist.allow_risky', false);

        $result = new ValidationResult(email: 'user@example.com', state: 'risky');

        $this->assertFalse($result->isValid());
    }

    public function test_is_valid_returns_false_for_invalid_state(): void
    {
        $result = new ValidationResult(email: 'bad@example.com', state: 'invalid');

        $this->assertFalse($result->isValid());
    }

    public function test_is_valid_returns_false_for_unknown_state(): void
    {
        $result = new ValidationResult(email: 'user@example.com', state: 'unknown');

        $this->assertFalse($result->isValid());
    }

    public function test_is_invalid_returns_true_for_invalid_state(): void
    {
        $result = new ValidationResult(email: 'bad@example.com', state: 'invalid');

        $this->assertTrue($result->isInvalid());
    }

    public function test_is_invalid_returns_false_for_valid_state(): void
    {
        $result = new ValidationResult(email: 'user@example.com', state: 'valid');

        $this->assertFalse($result->isInvalid());
    }

    public function test_is_risky_returns_true_for_risky_state(): void
    {
        $result = new ValidationResult(email: 'user@example.com', state: 'risky');

        $this->assertTrue($result->isRisky());
    }

    public function test_is_risky_returns_false_for_valid_state(): void
    {
        $result = new ValidationResult(email: 'user@example.com', state: 'valid');

        $this->assertFalse($result->isRisky());
    }

    public function test_is_unknown_returns_true_for_unknown_state(): void
    {
        $result = new ValidationResult(email: 'user@example.com', state: 'unknown');

        $this->assertTrue($result->isUnknown());
    }

    public function test_is_unknown_returns_false_for_valid_state(): void
    {
        $result = new ValidationResult(email: 'user@example.com', state: 'valid');

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

    public function test_attributes_are_accessible(): void
    {
        $result = new ValidationResult(
            email: 'user@example.com',
            state: 'valid',
            subState: 'ok',
            freeEmail: true,
            role: false,
            disposable: true,
            suggestion: 'user@gmail.com',
        );

        $this->assertSame('user@example.com', $result->email);
        $this->assertSame('valid', $result->state);
        $this->assertSame('ok', $result->subState);
        $this->assertTrue($result->freeEmail);
        $this->assertFalse($result->role);
        $this->assertTrue($result->disposable);
        $this->assertSame('user@gmail.com', $result->suggestion);
    }

    public function test_defaults_for_optional_fields(): void
    {
        $result = new ValidationResult(email: 'user@example.com', state: 'valid');

        $this->assertNull($result->subState);
        $this->assertFalse($result->freeEmail);
        $this->assertFalse($result->role);
        $this->assertFalse($result->disposable);
        $this->assertNull($result->suggestion);
        $this->assertFalse($result->error);
    }

    public function test_to_array(): void
    {
        $result = new ValidationResult(
            email: 'user@example.com',
            state: 'valid',
            subState: 'ok',
            freeEmail: true,
            role: false,
            disposable: false,
            suggestion: null,
        );

        $expected = [
            'email' => 'user@example.com',
            'state' => 'valid',
            'sub_state' => 'ok',
            'free_email' => true,
            'role' => false,
            'disposable' => false,
            'suggestion' => null,
            'error' => false,
        ];

        $this->assertSame($expected, $result->toArray());
    }
}

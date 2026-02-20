<?php

namespace Truelist;

class ValidationResult
{
    public function __construct(
        public readonly string $email,
        public readonly string $state,
        public readonly ?string $subState = null,
        public readonly bool $freeEmail = false,
        public readonly bool $role = false,
        public readonly bool $disposable = false,
        public readonly ?string $suggestion = null,
        public readonly bool $error = false,
    ) {
    }

    public function isValid(): bool
    {
        return $this->state === 'valid'
            || ($this->state === 'risky' && config('truelist.allow_risky', true));
    }

    public function isInvalid(): bool
    {
        return $this->state === 'invalid';
    }

    public function isRisky(): bool
    {
        return $this->state === 'risky';
    }

    public function isUnknown(): bool
    {
        return $this->state === 'unknown';
    }

    public function isError(): bool
    {
        return $this->error;
    }

    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'state' => $this->state,
            'sub_state' => $this->subState,
            'free_email' => $this->freeEmail,
            'role' => $this->role,
            'disposable' => $this->disposable,
            'suggestion' => $this->suggestion,
            'error' => $this->error,
        ];
    }
}

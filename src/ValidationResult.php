<?php

namespace Truelist;

class ValidationResult
{
    public function __construct(
        public readonly string $email,
        public readonly string $state,
        public readonly ?string $subState = null,
        public readonly ?string $domain = null,
        public readonly ?string $canonical = null,
        public readonly ?string $mxRecord = null,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly ?string $verifiedAt = null,
        public readonly ?string $suggestion = null,
        public readonly bool $error = false,
    ) {
    }

    public function isValid(): bool
    {
        return $this->state === 'ok';
    }

    public function isInvalid(): bool
    {
        return $this->state === 'email_invalid';
    }

    public function isAcceptAll(): bool
    {
        return $this->state === 'accept_all';
    }

    public function isUnknown(): bool
    {
        return $this->state === 'unknown';
    }

    public function isError(): bool
    {
        return $this->error;
    }

    public function isDisposable(): bool
    {
        return $this->subState === 'is_disposable';
    }

    public function isRole(): bool
    {
        return $this->subState === 'is_role';
    }

    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'state' => $this->state,
            'sub_state' => $this->subState,
            'domain' => $this->domain,
            'canonical' => $this->canonical,
            'mx_record' => $this->mxRecord,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'verified_at' => $this->verifiedAt,
            'suggestion' => $this->suggestion,
            'error' => $this->error,
        ];
    }
}

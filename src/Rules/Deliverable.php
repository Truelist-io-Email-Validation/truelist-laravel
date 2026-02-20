<?php

namespace Truelist\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Truelist\Exceptions\AuthenticationException;
use Truelist\TruelistClient;

class Deliverable implements ValidationRule
{
    private ?bool $allowRisky;

    public function __construct(?bool $allowRisky = null)
    {
        $this->allowRisky = $allowRisky;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return;
        }

        $client = app(TruelistClient::class);

        // Auth errors always propagate -- don't silently pass on bad credentials
        $result = $client->validate($value);

        // Fail open for transient errors so forms still work when the API is down
        if ($result->isError()) {
            return;
        }

        $allowRisky = $this->allowRisky ?? config('truelist.allow_risky', true);

        $deliverable = $result->state === 'valid'
            || ($result->state === 'risky' && $allowRisky)
            || $result->isUnknown();

        if (! $deliverable) {
            $fail('The :attribute is not a deliverable email address.');
        }
    }
}

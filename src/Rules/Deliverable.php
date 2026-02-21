<?php

namespace Truelist\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Truelist\Exceptions\AuthenticationException;
use Truelist\TruelistClient;

class Deliverable implements ValidationRule
{
    public function __construct()
    {
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

        $deliverable = $result->state === 'ok'
            || $result->isUnknown();

        if (! $deliverable) {
            $fail('The :attribute is not a deliverable email address.');
        }
    }
}

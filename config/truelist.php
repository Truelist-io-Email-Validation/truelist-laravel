<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Key
    |--------------------------------------------------------------------------
    |
    | Your Truelist API key. Get one at https://truelist.io.
    |
    */

    'api_key' => env('TRUELIST_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | The Truelist API base URL. You should only change this for testing
    | or if you're using a proxy.
    |
    */

    'base_url' => env('TRUELIST_BASE_URL', 'https://api.truelist.io'),

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    |
    | Request timeout in seconds.
    |
    */

    'timeout' => env('TRUELIST_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Allow Risky
    |--------------------------------------------------------------------------
    |
    | Whether "risky" emails (accept-all domains, etc.) should be considered
    | valid. Set to false to reject risky emails.
    |
    */

    'allow_risky' => env('TRUELIST_ALLOW_RISKY', true),

    /*
    |--------------------------------------------------------------------------
    | Raise on Error
    |--------------------------------------------------------------------------
    |
    | When true, API errors (timeouts, rate limits, server errors) will throw
    | exceptions. When false (default), errors return an "unknown" result,
    | allowing validation to pass gracefully (fail open).
    |
    | Note: Authentication errors (401) always throw regardless of this setting.
    |
    */

    'raise_on_error' => env('TRUELIST_RAISE_ON_ERROR', false),

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Cache validation results to avoid redundant API calls. Uses Laravel's
    | cache system. Only successful results are cached (not unknowns/errors).
    |
    */

    'cache' => [
        'enabled' => env('TRUELIST_CACHE_ENABLED', false),
        'ttl' => env('TRUELIST_CACHE_TTL', 3600),
        'prefix' => 'truelist:',
    ],

];

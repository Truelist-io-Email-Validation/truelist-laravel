# truelist/laravel

Email validation for Laravel, powered by [Truelist.io](https://truelist.io).

[![CI](https://github.com/Truelist-io-Email-Validation/truelist-laravel/actions/workflows/ci.yml/badge.svg)](https://github.com/Truelist-io-Email-Validation/truelist-laravel/actions/workflows/ci.yml)
[![Latest Stable Version](https://poser.pugx.org/truelist/laravel/v/stable)](https://packagist.org/packages/truelist/laravel)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

Validate email deliverability in your Laravel forms with a single line:

```php
$request->validate([
    'email' => ['required', 'email', new Deliverable],
]);
```

Truelist checks whether an email address actually exists and can receive mail, catching typos, disposable addresses, and invalid mailboxes before they hit your database.

## Installation

```bash
composer require truelist/laravel
```

The service provider is auto-discovered. To publish the config file:

```bash
php artisan vendor:publish --tag=truelist-config
```

Add your API key to `.env`:

```
TRUELIST_API_KEY=your_api_key_here
```

## Quick Start

```php
use Truelist\Rules\Deliverable;

// In a FormRequest or controller:
$request->validate([
    'email' => ['required', 'email', new Deliverable],
]);
```

That's it. Invalid emails will now fail validation with a clear error message.

## Configuration

Publish the config file to customize settings:

```bash
php artisan vendor:publish --tag=truelist-config
```

This creates `config/truelist.php`:

```php
return [
    // Your Truelist API key (required).
    'api_key' => env('TRUELIST_API_KEY'),

    // API base URL. Change only for testing or proxying.
    'base_url' => env('TRUELIST_BASE_URL', 'https://api.truelist.io'),

    // Request timeout in seconds.
    'timeout' => env('TRUELIST_TIMEOUT', 10),

    // Whether "risky" emails (accept-all domains, etc.) pass validation.
    'allow_risky' => env('TRUELIST_ALLOW_RISKY', true),

    // When true, raises exceptions on API failures.
    // When false (default), returns "unknown" on errors (fail open).
    // Note: Auth errors (401) always throw regardless of this setting.
    'raise_on_error' => env('TRUELIST_RAISE_ON_ERROR', false),

    // Cache settings to avoid redundant API calls.
    'cache' => [
        'enabled' => env('TRUELIST_CACHE_ENABLED', false),
        'ttl'     => env('TRUELIST_CACHE_TTL', 3600),
        'prefix'  => 'truelist:',
    ],
];
```

## Validation Rule

### Using the rule object (recommended)

```php
use Truelist\Rules\Deliverable;

$request->validate([
    'email' => ['required', 'email', new Deliverable],
]);
```

### Using the string shorthand

```php
$request->validate([
    'email' => ['required', 'email', 'deliverable'],
]);
```

To reject risky emails with the string shorthand, use the `strict` parameter:

```php
$request->validate([
    'email' => ['required', 'email', 'deliverable:strict'],
]);
```

### Rejecting risky emails

By default, risky emails (accept-all domains, etc.) pass validation. To reject them:

```php
// Per-rule override:
$request->validate([
    'email' => ['required', 'email', new Deliverable(allowRisky: false)],
]);

// Or globally via .env:
TRUELIST_ALLOW_RISKY=false
```

### Behavior on errors

The validation rule **fails open** by design. If the Truelist API is unreachable (timeout, 500, rate limit), the email passes validation so your forms keep working.

Authentication errors (invalid API key) always throw an exception -- you want to know immediately if your credentials are wrong.

## Using the Client Directly

```php
use Truelist\TruelistClient;

$client = app(TruelistClient::class);
$result = $client->validate('user@example.com');

$result->state;       // "valid", "invalid", "risky", or "unknown"
$result->subState;    // "ok", "failed_no_mailbox", "disposable_address", etc.
$result->isValid();   // true/false (respects allow_risky config)
$result->isInvalid(); // true/false
$result->isRisky();   // true/false
$result->isUnknown(); // true/false

$result->suggestion;  // Suggested correction, if available
$result->freeEmail;   // Whether it's a free email provider
$result->role;        // Whether it's a role address (info@, admin@, etc.)
$result->disposable;  // Whether it's a disposable/temporary address
```

## Facade Usage

```php
use Truelist\Facades\Truelist;

$result = Truelist::validate('user@example.com');

if ($result->isValid()) {
    // Good to go
}
```

## Sub-states

| Sub-state | Meaning |
|-----------|---------|
| `ok` | Email is valid and deliverable |
| `accept_all` | Domain accepts all emails (risky) |
| `disposable_address` | Disposable/temporary email |
| `role_address` | Role-based address (info@, admin@) |
| `failed_mx_check` | Domain has no mail server |
| `failed_spam_trap` | Known spam trap address |
| `failed_no_mailbox` | Mailbox does not exist |
| `failed_greylisted` | Server temporarily rejected (greylisting) |
| `failed_syntax_check` | Email format is invalid |
| `unknown` | Could not determine status |

## Caching

Enable caching to avoid redundant API calls for recently validated emails:

```env
TRUELIST_CACHE_ENABLED=true
TRUELIST_CACHE_TTL=3600
```

Caching uses Laravel's default cache driver. Only successful results are cached -- unknowns and errors are never cached, so a subsequent request can get the real result.

The cache key is based on the lowercase, trimmed email address.

## Error Handling

By default, API errors (timeouts, rate limits, server errors) return an `unknown` result with `isError()` returning `true`. This prevents your forms from breaking when the API is unreachable.

To raise exceptions instead:

```env
TRUELIST_RAISE_ON_ERROR=true
```

Exception classes:

| Exception | Trigger |
|-----------|---------|
| `Truelist\Exceptions\TruelistException` | Base exception class |
| `Truelist\Exceptions\AuthenticationException` | Invalid API key (401) -- **always thrown** |
| `Truelist\Exceptions\RateLimitException` | Rate limit exceeded (429) |
| `Truelist\Exceptions\ApiException` | Other API errors (500, timeouts, etc.) |

## Testing

Mock the HTTP client in your tests to avoid real API calls:

```php
use Illuminate\Support\Facades\Http;

Http::fake([
    'api.truelist.io/*' => Http::response([
        'state' => 'valid',
        'sub_state' => 'ok',
        'free_email' => false,
        'role' => false,
        'disposable' => false,
    ]),
]);
```

Or mock the client directly:

```php
use Truelist\TruelistClient;
use Truelist\ValidationResult;

$this->mock(TruelistClient::class, function ($mock) {
    $mock->shouldReceive('validate')
        ->andReturn(new ValidationResult(
            email: 'user@example.com',
            state: 'valid',
        ));
});
```

## Requirements

- PHP >= 8.1
- Laravel 10, 11, or 12

## Development

```bash
git clone https://github.com/Truelist-io-Email-Validation/truelist-laravel.git
cd truelist-laravel
composer install
vendor/bin/phpunit
```

## License

Released under the [MIT License](LICENSE).

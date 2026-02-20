<?php

namespace Truelist\Facades;

use Illuminate\Support\Facades\Facade;
use Truelist\TruelistClient;

/**
 * @method static \Truelist\ValidationResult validate(string $email)
 *
 * @see \Truelist\TruelistClient
 */
class Truelist extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TruelistClient::class;
    }
}

<?php

namespace Truelist;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Truelist\Rules\Deliverable;

class TruelistServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/truelist.php', 'truelist');

        $this->app->singleton(TruelistClient::class, function () {
            return new TruelistClient;
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/truelist.php' => config_path('truelist.php'),
            ], 'truelist-config');
        }

        Validator::extend('deliverable', function ($attribute, $value, $parameters, $validator) {
            $rule = new Deliverable;

            $failed = false;
            $rule->validate($attribute, $value, function ($message) use (&$failed) {
                $failed = true;
            });

            return ! $failed;
        });

        Validator::replacer('deliverable', function ($message, $attribute) {
            return str_replace(':attribute', $attribute, 'The :attribute is not a deliverable email address.');
        });
    }
}

<?php

namespace App\Helpers;

class AppEnvironment
{
    public static function isProduction(): bool
    {
        return app()->environment('production');
    }

    public static function isLocal(): bool
    {
        return app()->environment('local');
    }

    public static function current(): string
    {
        return app()->environment();
    }
}

<?php

namespace App\Support;

final class DemoMode
{
    public static function prototype(): bool
    {
        return (app()->environment('local') && config('medvida.teste_local'))
            || (app()->environment('academic') && config('medvida.academic_mode'));
    }

    public static function enabled(): bool
    {
        return self::prototype();
    }
}

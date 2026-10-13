<?php

namespace App\Http\Middleware;

use App\Support\DemoMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifiedAccountEmail
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! DemoMode::enabled()) {
            abort_unless($request->user()?->hasVerifiedEmail(), 403, 'Confirme seu e-mail antes de usar o MedVida.');
        }

        return $next($request);
    }
}

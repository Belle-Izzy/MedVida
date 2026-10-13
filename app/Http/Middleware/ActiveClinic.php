<?php

namespace App\Http\Middleware;

use App\Models\Clinica;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ActiveClinic
{
    public function handle(Request $request, Closure $next): Response
    {
        $clinica = $request->user();

        if ($clinica instanceof Clinica) {
            abort_unless($clinica->hasActiveAccess(), 403, 'O plano anual da clínica está pendente ou vencido.');
        }

        return $next($request);
    }
}

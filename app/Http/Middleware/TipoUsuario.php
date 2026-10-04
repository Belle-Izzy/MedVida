<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TipoUsuario
{
    public function handle(
        Request $request,
        Closure $next,
        string $tipo
    ): Response {
        $usuario = $request->user();

        if (! $usuario) {
            return response()->json([
                'message' => 'Não autenticado.',
            ], 401);
        }

        $tipoAtual = match (true) {
            $usuario instanceof \App\Models\Paciente => 'paciente',
            $usuario instanceof \App\Models\Clinica => 'clinica',
            default => null,
        };

        if ($tipoAtual !== $tipo) {
            return response()->json([
                'message' => 'Você não possui permissão para realizar esta ação.',
            ], 403);
        }

        return $next($request);
    }
}
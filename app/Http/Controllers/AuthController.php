<?php

namespace App\Http\Controllers;

use App\Models\Clinica;
use App\Models\Paciente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $dados = $request->validate([
            'tipo' => ['required', 'in:paciente,clinica'],
            'email' => ['required', 'email'],
            'senha' => ['required', 'string'],
        ]);

        $modelo = $dados['tipo'] === 'paciente'
            ? Paciente::class
            : Clinica::class;

        $usuario = $modelo::where('email', $dados['email'])->first();

        if (! $usuario || ! Hash::check($dados['senha'], $usuario->senha)) {
            return response()->json([
                'message' => 'E-mail ou senha inválidos.',
            ], 401);
        }

        $token = $usuario->createToken('medvida', [$dados['tipo']])->plainTextToken;

        return response()->json([
            'token' => $token,
            'tipo' => $dados['tipo'],
            'usuario' => $usuario,
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'usuario' => $request->user(),
            'tipo' => $request->user() instanceof Paciente ? 'paciente' : 'clinica',
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout realizado com sucesso.',
        ]);
    }
}

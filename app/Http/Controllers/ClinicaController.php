<?php

namespace App\Http\Controllers;

use App\Models\Clinica;
use App\Services\RegistrationEmail;
use App\Support\DemoMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ClinicaController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Clinica::query()->orderBy('nome')->paginate());
    }

    public function store(Request $request, RegistrationEmail $mail): JsonResponse
    {
        if (app()->environment('production') && ! $mail->canDeliver()) {
            return response()->json(['message' => 'O envio de e-mails ainda não está configurado.'], 503);
        }

        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'plano_atendido' => ['nullable', 'string', 'max:255'],
            'telefone' => ['required', 'string', 'max:20'],
            'endereco' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:clinicas,email'],
            'senha' => ['required', 'string', 'min:8'],
        ]);

        $data['senha'] = Hash::make($data['senha']);
        $data['status_cadastro'] = 'aguardando_pagamento';
        $clinica = Clinica::create($data);
        $prototype = DemoMode::prototype();
        $emailSent = $prototype ? false : $mail->send($clinica);

        return response()->json([
            'clinica' => $clinica,
            'email_enviado' => $emailSent,
            'activation_token' => $prototype ? $clinica->createToken('medvida-prototype-activation', ['clinica'])->plainTextToken : null,
            'message' => $prototype
                ? 'Conta criada. Conclua a ativação gratuita para entrar.'
                : ($emailSent
                ? 'Conta criada. Confirme seu e-mail e depois conclua o pagamento.'
                : 'Conta criada, mas o e-mail não foi entregue. Configure o envio e solicite um novo link.'),
        ], 201);
    }

    public function show(Clinica $clinica): JsonResponse
    {
        return response()->json($clinica);
    }

    public function update(Request $request, Clinica $clinica): JsonResponse
    {
        abort_unless($request->user()->is($clinica), 403, 'Você só pode alterar a própria clínica.');

        $data = $request->validate([
            'nome' => ['sometimes', 'string', 'max:255'],
            'plano_atendido' => ['nullable', 'string', 'max:255'],
            'telefone' => ['sometimes', 'string', 'max:20'],
            'endereco' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('clinicas', 'email')->ignore($clinica)],
            'senha' => ['sometimes', 'string', 'min:8'],
        ]);

        if (isset($data['senha'])) {
            $data['senha'] = Hash::make($data['senha']);
        }
        $emailChanged = isset($data['email']) && $data['email'] !== $clinica->email;
        if ($emailChanged) $data['email_verified_at'] = null;
        $clinica->update($data);
        if ($emailChanged && ! DemoMode::enabled()) {
            $clinica->tokens()->delete();
            $clinica->sendEmailVerificationNotification();
        }

        return response()->json($clinica->fresh());
    }
}

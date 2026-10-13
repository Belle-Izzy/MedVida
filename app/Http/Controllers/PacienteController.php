<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Services\RegistrationEmail;
use App\Support\DemoMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class PacienteController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Paciente::query()->orderBy('nome')->paginate());
    }

    public function store(Request $request, RegistrationEmail $mail): JsonResponse
    {
        if (app()->environment('production') && ! $mail->canDeliver()) {
            return response()->json(['message' => 'O envio de e-mails ainda não está configurado.'], 503);
        }

        $data = $this->validated($request);
        $data['senha'] = Hash::make($data['senha']);
        $paciente = Paciente::create($data);
        $testMode = DemoMode::enabled();
        $emailSent = $testMode ? false : $mail->send($paciente);

        return response()->json([
            'paciente' => $paciente,
            'email_enviado' => $emailSent,
            'message' => $testMode ? 'Conta de teste criada. Você já pode entrar.' : ($emailSent
                ? 'Conta criada. Confirme seu e-mail antes de entrar.'
                : 'Conta criada, mas o e-mail não foi entregue. Configure o envio e solicite um novo link.'),
            'teste_local' => $testMode,
        ], 201);
    }

    public function show(Paciente $paciente): JsonResponse
    {
        return response()->json($paciente);
    }

    public function update(Request $request, Paciente $paciente): JsonResponse
    {
        abort_unless($request->user()->is($paciente), 403, 'Você só pode alterar o próprio cadastro.');

        $data = $this->validated($request, $paciente);
        if (isset($data['senha'])) {
            $data['senha'] = Hash::make($data['senha']);
        }
        $emailChanged = isset($data['email']) && $data['email'] !== $paciente->email;
        if ($emailChanged) $data['email_verified_at'] = null;
        $paciente->update($data);
        if ($emailChanged && ! DemoMode::enabled()) {
            $paciente->tokens()->delete();
            $paciente->sendEmailVerificationNotification();
        }
        return response()->json($paciente->fresh());
    }

    private function validated(Request $request, ?Paciente $paciente = null): array
    {
        $required = $paciente ? 'sometimes' : 'required';
        return $request->validate([
            'cpf' => [$required, 'string', 'max:14', Rule::unique('pacientes', 'cpf')->ignore($paciente)],
            'telefone' => [$required, 'string', 'max:20'],
            'plano' => ['nullable', 'string', 'max:255'],
            'nome' => [$required, 'string', 'max:255'],
            'data_nascimento' => ['nullable', 'date', 'before:today'],
            'sexo' => ['nullable', 'string', 'max:30'],
            'caracteristicas_saude' => ['nullable', 'string'],
            'email' => [$required, 'email', 'max:255', Rule::unique('pacientes', 'email')->ignore($paciente)],
            'senha' => [$required, 'string', 'min:8'],
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
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

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['senha'] = Hash::make($data['senha']);
        return response()->json(Paciente::create($data), 201);
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
        $paciente->update($data);
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

<?php

namespace App\Http\Controllers;

use App\Models\Clinica;
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

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'plano_atendido' => ['nullable', 'string', 'max:255'],
            'telefone' => ['required', 'string', 'max:20'],
            'endereco' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:clinicas,email'],
            'senha' => ['required', 'string', 'min:8'],
        ]);

        $data['senha'] = Hash::make($data['senha']);
        return response()->json(Clinica::create($data), 201);
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
        $clinica->update($data);
        return response()->json($clinica->fresh());
    }
}

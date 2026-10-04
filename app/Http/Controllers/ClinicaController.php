<?php

namespace App\Http\Controllers;

use App\Models\Clinica;
use App\Models\PagamentoClinica;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        if (! config('medvida.simular_pagamento_de_adesao')) {
            return response()->json([
                'message' => 'O cadastro de clínicas está aguardando a integração segura do pagamento.',
            ], 503);
        }

        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'plano_atendido' => ['nullable', 'string', 'max:255'],
            'telefone' => ['required', 'string', 'max:20'],
            'endereco' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:clinicas,email'],
            'senha' => ['required', 'string', 'min:8'],
            'forma_pagamento' => ['required', Rule::in(['pix', 'cartao_credito', 'boleto'])],
        ]);

        $formaPagamento = $data['forma_pagamento'];
        unset($data['forma_pagamento']);
        $data['senha'] = Hash::make($data['senha']);
        $data['status_cadastro'] = 'ativa';
        $data['ativada_em'] = now();

        $clinica = DB::transaction(function () use ($data, $formaPagamento) {
            $clinica = Clinica::create($data);

            PagamentoClinica::create([
                'clinica_id' => $clinica->id,
                'tipo' => 'taxa_adesao',
                'forma_pagamento' => $formaPagamento,
                'status' => 'simulado_aprovado',
                'simulado' => true,
                'pago_em' => now(),
            ]);

            return $clinica;
        });

        return response()->json([
            'clinica' => $clinica,
            'message' => 'Cadastro ativado por simulação de pagamento. Nenhuma cobrança foi realizada.',
            'pagamento_simulado' => true,
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
        $clinica->update($data);

        return response()->json($clinica->fresh());
    }
}

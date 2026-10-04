<?php

namespace App\Http\Controllers;

use App\Models\Medico;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MedicoController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Medico::query()->with('clinica')->orderBy('nome')->paginate());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['clinica_id'] = $request->user()->id;

        return response()->json(Medico::create($data), 201);
    }

    public function show(Medico $medico): JsonResponse
    {
        return response()->json($medico->load('clinica'));
    }

    public function update(Request $request, Medico $medico): JsonResponse
    {
        $this->authorizeClinica($request, $medico);
        $medico->update($this->validated($request, $medico));
        return response()->json($medico->fresh()->load('clinica'));
    }

    public function destroy(Request $request, Medico $medico): JsonResponse
    {
        $this->authorizeClinica($request, $medico);
        $medico->delete();

        return response()->json(['message' => 'Médico removido com sucesso.']);
    }

    private function validated(Request $request, ?Medico $medico = null): array
    {
        $required = $medico ? 'sometimes' : 'required';
        return $request->validate([
            'nome' => [$required, 'string', 'max:255'],
            'crm' => [$required, 'string', 'max:30', Rule::unique('medicos', 'crm')->ignore($medico)],
            'especialidade' => [$required, 'string', 'max:255'],
            'telefone' => [$required, 'string', 'max:20'],
        ]);
    }

    private function authorizeClinica(Request $request, Medico $medico): void
    {
        abort_unless($medico->clinica_id === $request->user()->id, 403, 'Este médico não pertence à sua clínica.');
    }
}

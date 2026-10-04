<?php

namespace App\Http\Controllers;

use App\Models\Disponibilidade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DisponibilidadeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Disponibilidade::with('medico.clinica')
            ->orderBy('data')
            ->orderBy('horario');

        if ($request->filled('medico_id')) {
            $query->where('medico_id', $request->integer('medico_id'));
        }

        if ($request->filled('data')) {
            $query->whereDate('data', $request->input('data'));
        }

        if ($request->filled('disponivel')) {
            $query->where(
                'disponivel',
                filter_var($request->input('disponivel'), FILTER_VALIDATE_BOOLEAN)
            );
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'medico_id' => ['required', 'integer', 'exists:medicos,id'],
            'data' => ['required', 'date', 'after_or_equal:today'],
            'horario' => [
                'required',
                'date_format:H:i',
                Rule::unique('disponibilidades', 'horario')
                    ->where(fn ($query) => $query
                        ->where('medico_id', $request->input('medico_id'))
                        ->where('data', $request->input('data'))
                    ),
            ],
        ], [
            'medico_id.required' => 'O médico é obrigatório.',
            'medico_id.exists' => 'O médico informado não existe.',
            'data.required' => 'A data é obrigatória.',
            'data.after_or_equal' => 'Não é possível criar disponibilidade para uma data passada.',
            'horario.required' => 'O horário é obrigatório.',
            'horario.date_format' => 'O horário deve estar no formato HH:MM.',
            'horario.unique' => 'Este médico já possui disponibilidade neste horário.',
        ]);

        $this->authorizeClinica($request, $validated['medico_id']);

        $disponibilidade = Disponibilidade::create([
            'medico_id' => $validated['medico_id'],
            'data' => $validated['data'],
            'horario' => $validated['horario'],
            'disponivel' => true,
        ]);

        return response()->json(
            $disponibilidade->load('medico.clinica'),
            201
        );
    }

    public function show(Disponibilidade $disponibilidade): JsonResponse
    {
        return response()->json(
            $disponibilidade->load('medico.clinica')
        );
    }

    public function update(
        Request $request,
        Disponibilidade $disponibilidade
    ): JsonResponse {
        $this->authorizeClinica($request, $disponibilidade->medico_id);

        $validated = $request->validate([
            'data' => ['sometimes', 'date', 'after_or_equal:today'],
            'horario' => [
                'sometimes',
                'date_format:H:i',
                Rule::unique('disponibilidades', 'horario')
                    ->where(fn ($query) => $query
                        ->where(
                            'medico_id',
                            $disponibilidade->medico_id
                        )
                        ->where(
                            'data',
                            $request->input(
                                'data',
                                $disponibilidade->data->format('Y-m-d')
                            )
                        )
                        ->where(
                            'id',
                            '!=',
                            $disponibilidade->id
                        )
                    ),
            ],
            'disponivel' => ['sometimes', 'boolean'],
        ]);

        $disponibilidade->update($validated);

        return response()->json(
            $disponibilidade->fresh()->load('medico.clinica')
        );
    }

    public function destroy(
        Disponibilidade $disponibilidade
    ): JsonResponse {
        $this->authorizeClinica($request, $disponibilidade->medico_id);

        if (!$disponibilidade->disponivel) {
            return response()->json([
                'message' => 'Não é possível excluir uma disponibilidade que já foi utilizada.',
            ], 422);
        }

        $disponibilidade->delete();

        return response()->json([
            'message' => 'Disponibilidade removida com sucesso.',
        ]);
    }

    private function authorizeClinica(Request $request, int $medicoId): void
    {
        $pertenceAClinica = $request->user()
            ->medicos()
            ->whereKey($medicoId)
            ->exists();

        abort_unless($pertenceAClinica, 403, 'Este médico não pertence à sua clínica.');
    }
}

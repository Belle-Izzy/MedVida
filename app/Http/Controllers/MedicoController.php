<?php

namespace App\Http\Controllers;

use App\Models\Medico;
use App\Support\AppointmentSlots;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MedicoController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Medico::query()->with(['clinica:id,nome,endereco', 'horarios'])->orderBy('nome')->paginate());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $horarios = $data['horarios'];
        unset($data['horarios']);
        $data['clinica_id'] = $request->user()->id;

        $medico = DB::transaction(function () use ($data, $horarios) {
            $medico = Medico::create($data);
            $medico->horarios()->createMany($horarios);

            return $medico;
        });

        return response()->json($medico->load(['clinica', 'horarios']), 201);
    }

    public function show(Medico $medico): JsonResponse
    {
        return response()->json($medico->load(['clinica:id,nome,endereco', 'horarios']));
    }

    public function update(Request $request, Medico $medico): JsonResponse
    {
        $this->authorizeClinica($request, $medico);
        $data = $this->validated($request, $medico);
        $horarios = $data['horarios'] ?? null;
        unset($data['horarios']);

        DB::transaction(function () use ($medico, $data, $horarios) {
            $medico->update($data);
            if ($horarios !== null) {
                $medico->horarios()->delete();
                $medico->horarios()->createMany($horarios);
            }
        });

        return response()->json($medico->fresh()->load(['clinica', 'horarios']));
    }

    public function horarios(Request $request, Medico $medico): JsonResponse
    {
        $data = $request->validate(['data' => ['required', 'date', 'after_or_equal:today']]);
        $dataConsulta = Carbon::parse($data['data']);
        $horario = $medico->horarios()->where('dia_semana', $dataConsulta->dayOfWeekIso)->first();

        if (! $horario) {
            return response()->json([]);
        }

        $ocupados = $medico->consultas()
            ->whereDate('data', $dataConsulta)
            ->whereIn('status', ['pendente', 'confirmada'])
            ->pluck('horario')
            ->map(fn ($item) => Carbon::parse($item)->format('H:i'));

        $inicio = Carbon::parse($horario->inicio);
        $horarios = [];
        while (AppointmentSlots::fitsSchedule($horario->inicio, $horario->fim, $inicio->format('H:i'))) {
            $hora = $inicio->format('H:i');
            if (! $ocupados->contains($hora)
                && AppointmentSlots::isFuture($data['data'], $hora)) {
                $horarios[] = $hora;
            }
            $inicio->addHour();
        }

        return response()->json($horarios);
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
            'horarios' => [$medico ? 'sometimes' : 'required', 'array', 'min:1'],
            'horarios.*.dia_semana' => ['required', 'integer', 'between:1,7', 'distinct'],
            'horarios.*.inicio' => ['required', 'date_format:H:i'],
            'horarios.*.fim' => ['required', 'date_format:H:i', 'after:horarios.*.inicio'],
        ]);
    }

    private function authorizeClinica(Request $request, Medico $medico): void
    {
        abort_unless($medico->clinica_id === $request->user()->id, 403, 'Este médico não pertence à sua clínica.');
    }
}

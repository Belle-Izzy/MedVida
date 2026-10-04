<?php

namespace App\Http\Controllers;

use App\Models\Consulta;
use App\Models\Disponibilidade;
use App\Models\Medico;
use App\Models\Paciente;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConsultaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $consultas = Consulta::with(['medico.clinica', 'paciente']);

        if ($request->user() instanceof Paciente) {
            $consultas->where('paciente_id', $request->user()->id);
        } else {
            $consultas->whereHas('medico', fn ($query) => $query->where('clinica_id', $request->user()->id));
        }

        return response()->json($consultas->orderBy('data')->orderBy('horario')->paginate(15));
    }

    public function store(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'medico_id' => ['required', 'exists:medicos,id'],
            'paciente_id' => ['required', 'exists:pacientes,id'],
            'lugar' => ['required', 'string', 'max:255'],
            'data' => ['required', 'date', 'after_or_equal:today'],
            'horario' => ['required', 'date_format:H:i'],
            'mensagem_paciente' => ['nullable', 'string'],
        ]);

        abort_unless($request->user()->id === (int) $dados['paciente_id'], 403, 'Você só pode agendar consultas para si mesmo.');

        $consulta = DB::transaction(function () use ($dados) {
            $medico = Medico::with('horarios')->findOrFail($dados['medico_id']);
            $data = Carbon::parse($dados['data']);
            $horario = Carbon::createFromFormat('H:i', $dados['horario']);
            $agenda = $medico->horarios->firstWhere('dia_semana', $data->dayOfWeekIso);

            abort_unless($agenda && $horario->gte(Carbon::parse($agenda->inicio)) && $horario->lt(Carbon::parse($agenda->fim)), 409, 'Este horário não está na agenda do médico.');

            $ocupada = Consulta::query()
                ->where('medico_id', $medico->id)
                ->whereDate('data', $data)
                ->whereTime('horario', $dados['horario'])
                ->whereIn('status', ['pendente', 'confirmada'])
                ->lockForUpdate()
                ->exists();

            abort_if($ocupada, 409, 'Este horário não está mais disponível.');

            $consulta = Consulta::create([
                ...$dados,
                'status' => 'pendente',
            ]);

            return $consulta;
        });

        return response()->json($consulta->load(['medico.clinica', 'paciente']), 201);
    }

    public function show(Request $request, Consulta $consulta): JsonResponse
    {
        $this->authorizeConsulta($request, $consulta);

        return response()->json($consulta->load(['medico.clinica', 'paciente']));
    }

    public function confirmar(Request $request, Consulta $consulta): JsonResponse
    {
        $this->authorizeClinica($request, $consulta);
        abort_if($consulta->status !== 'pendente', 422, 'Somente consultas pendentes podem ser confirmadas.');

        $consulta->update(['status' => 'confirmada']);

        return response()->json($consulta->fresh()->load(['medico.clinica', 'paciente']));
    }

    public function recusar(Request $request, Consulta $consulta): JsonResponse
    {
        $this->authorizeClinica($request, $consulta);

        $consultaAtualizada = DB::transaction(function () use ($consulta) {
            $consulta->refresh();
            abort_if($consulta->status !== 'pendente', 422, 'Somente consultas pendentes podem ser recusadas.');

            $consulta->update(['status' => 'recusada']);
            $this->liberarHorario($consulta);

            return $consulta;
        });

        return response()->json($consultaAtualizada->fresh()->load(['medico.clinica', 'paciente']));
    }

    public function update(Request $request, Consulta $consulta): JsonResponse
    {
        $this->authorizePaciente($request, $consulta);
        abort_if($consulta->status !== 'pendente', 422, 'Somente consultas pendentes podem ser alteradas.');

        $dados = $request->validate([
            'lugar' => ['sometimes', 'string', 'max:255'],
            'mensagem_paciente' => ['sometimes', 'nullable', 'string'],
        ]);

        $consulta->update($dados);

        return response()->json($consulta->fresh()->load(['medico.clinica', 'paciente']));
    }

    public function destroy(Request $request, Consulta $consulta): JsonResponse
    {
        $this->authorizePaciente($request, $consulta);
        abort_if($consulta->status !== 'pendente', 422, 'Somente consultas pendentes podem ser canceladas.');

        $dataLimiteCancelamento = now()->startOfDay()->addDays(2);
        abort_if($consulta->data->copy()->startOfDay()->lt($dataLimiteCancelamento), 422, 'Esta consulta só pode ser cancelada com pelo menos 2 dias de antecedência.');

        DB::transaction(function () use ($consulta) {
            $consulta->update(['status' => 'cancelada']);
            $this->liberarHorario($consulta);
        });

        return response()->json(['message' => 'Consulta cancelada com sucesso.']);
    }

    private function liberarHorario(Consulta $consulta): void
    {
        Disponibilidade::query()
            ->where('medico_id', $consulta->medico_id)
            ->whereDate('data', $consulta->data)
            ->whereTime('horario', $consulta->horario)
            ->update(['disponivel' => true]);
    }

    private function authorizeConsulta(Request $request, Consulta $consulta): void
    {
        if ($request->user() instanceof Paciente) {
            $this->authorizePaciente($request, $consulta);
            return;
        }

        $this->authorizeClinica($request, $consulta);
    }

    private function authorizePaciente(Request $request, Consulta $consulta): void
    {
        abort_unless($consulta->paciente_id === $request->user()->id, 403, 'Esta consulta não pertence a você.');
    }

    private function authorizeClinica(Request $request, Consulta $consulta): void
    {
        $pertenceAClinica = $request->user()->medicos()->whereKey($consulta->medico_id)->exists();
        abort_unless($pertenceAClinica, 403, 'Esta consulta não pertence à sua clínica.');
    }
}

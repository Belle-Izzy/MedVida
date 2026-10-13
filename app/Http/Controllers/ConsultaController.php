<?php

namespace App\Http\Controllers;

use App\Models\Consulta;
use App\Models\Clinica;
use App\Models\Disponibilidade;
use App\Models\Medico;
use App\Models\Paciente;
use App\Services\PushNotifier;
use App\Support\AppointmentSlots;
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

        $pendingCount = (clone $consultas)->where('status', 'pendente')->count();
        if ($request->query('status') === 'ativas') {
            $consultas->whereIn('status', ['pendente', 'confirmada']);
        } elseif ($request->query('status') === 'proximas') {
            $consultas->whereIn('status', ['pendente', 'confirmada'])->whereDate('data', '>=', today());
        } elseif ($request->query('status') === 'pendente') {
            $consultas->where('status', 'pendente');
        } elseif ($request->query('status') === 'visiveis' && $request->user() instanceof Paciente) {
            $consultas->where('status', '!=', 'cancelada')->whereDate('data', '>=', today());
        }
        $page = $consultas->orderBy('data')->orderBy('horario')->paginate(15)->toArray();

        return response()->json([...$page, 'pending_count' => $pendingCount]);
    }

    public function store(Request $request, PushNotifier $push): JsonResponse
    {
        $dados = $request->validate([
            'medico_id' => ['required', 'exists:medicos,id'],
            'paciente_id' => ['required', 'exists:pacientes,id'],
            'lugar' => ['sometimes', 'string', 'max:255'],
            'data' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'horario' => ['required', 'date_format:H:i'],
            'mensagem_paciente' => ['nullable', 'string', 'max:300'],
        ]);

        abort_unless($request->user()->id === (int) $dados['paciente_id'], 403, 'Você só pode agendar consultas para si mesmo.');

        $consulta = DB::transaction(function () use ($dados) {
            $medico = Medico::with(['horarios', 'clinica'])->findOrFail($dados['medico_id']);
            $data = Carbon::parse($dados['data']);
            $agenda = $medico->horarios->firstWhere('dia_semana', $data->dayOfWeekIso);

            abort_unless($agenda, 409, 'Este horário não está na agenda do médico.');
            abort_unless(AppointmentSlots::fitsSchedule($agenda->inicio, $agenda->fim, $dados['horario']), 409, 'Este horário não está na agenda do médico.');
            abort_unless(AppointmentSlots::isFuture($dados['data'], $dados['horario']), 409, 'Este horário já passou.');

            // O endereço da consulta vem da clínica, nunca do formulário enviado pelo paciente.
            $dados['lugar'] = $medico->clinica->endereco;

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

        $consulta->load(['medico.clinica', 'paciente']);
        $clinica = $consulta->medico?->clinica;
        if ($clinica) {
            $count = Consulta::whereHas('medico', fn ($query) => $query->where('clinica_id', $clinica->id))
                ->where('status', 'pendente')->count();
            $push->send($clinica, 'Nova solicitação de consulta',
                $count === 1 ? 'Há 1 consulta aguardando confirmação.' : "Há {$count} consultas aguardando confirmação.",
                'pending', 'clinic-pending');
        }

        return response()->json($consulta, 201);
    }

    public function show(Request $request, Consulta $consulta): JsonResponse
    {
        $this->authorizeConsulta($request, $consulta);

        return response()->json($consulta->load(['medico.clinica', 'paciente']));
    }

    public function avisoCancelamento(Request $request): JsonResponse
    {
        $consulta = Consulta::with('medico')
            ->where('paciente_id', $request->user()->id)
            ->where('status', 'cancelada')
            ->where('cancelada_por', 'clinica')
            ->whereNull('cancelamento_lido_em')
            ->latest('updated_at')
            ->first();

        return response()->json(['data' => $consulta]);
    }

    public function confirmar(Request $request, Consulta $consulta, PushNotifier $push): JsonResponse
    {
        $this->authorizeClinica($request, $consulta);
        DB::transaction(function () use ($consulta) {
            $locked = Consulta::whereKey($consulta->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->status !== 'pendente', 422, 'Somente consultas pendentes podem ser confirmadas.');
            $locked->update(['status' => 'confirmada']);
        });
        $push->send($consulta->paciente, 'Consulta confirmada',
            'Sua solicitação foi aprovada. Abra o MedVida para ver os detalhes.',
            'home', "consulta-{$consulta->id}");

        return response()->json($consulta->fresh()->load(['medico.clinica', 'paciente']));
    }

    public function recusar(Request $request, Consulta $consulta, PushNotifier $push): JsonResponse
    {
        $this->authorizeClinica($request, $consulta);

        $consultaAtualizada = DB::transaction(function () use ($consulta) {
            $consulta->refresh();
            abort_if($consulta->status !== 'pendente', 422, 'Somente consultas pendentes podem ser recusadas.');

            $consulta->update(['status' => 'recusada']);
            $this->liberarHorario($consulta);

            return $consulta;
        });

        $push->send($consultaAtualizada->paciente, 'Solicitação recusada',
            'A clínica não pôde confirmar essa consulta. Abra o MedVida para ver suas consultas.',
            'consultas', "consulta-{$consulta->id}");

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

    public function mensagem(Request $request, Consulta $consulta): JsonResponse
    {
        $this->authorizePaciente($request, $consulta);
        abort_unless($consulta->status === 'confirmada', 422, 'A mensagem só pode ser enviada após a confirmação da consulta.');

        $dados = $request->validate([
            'mensagem' => ['required', 'string', 'max:300'],
        ]);

        $consulta->update(['mensagem_pos_confirmacao' => $dados['mensagem']]);

        return response()->json($consulta->fresh());
    }

    public function destroy(Request $request, Consulta $consulta, PushNotifier $push): JsonResponse
    {
        $porPaciente = $request->user() instanceof Paciente;
        abort_unless($porPaciente || $request->user() instanceof Clinica, 403, 'Você não possui permissão para cancelar esta consulta.');
        $porPaciente ? $this->authorizePaciente($request, $consulta) : $this->authorizeClinica($request, $consulta);

        DB::transaction(function () use ($consulta, $porPaciente) {
            $consulta = Consulta::whereKey($consulta->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($consulta->status, ['pendente', 'confirmada'], true), 422, 'Esta consulta não pode mais ser cancelada.');

            $dataLimiteCancelamento = now()->startOfDay()->addDays(2);
            abort_if($consulta->data->copy()->startOfDay()->lt($dataLimiteCancelamento), 422, 'Esta consulta só pode ser cancelada com pelo menos 2 dias de antecedência.');

            $consulta->update([
                'status' => 'cancelada',
                'cancelada_por' => $porPaciente ? 'paciente' : 'clinica',
                'cancelamento_lido_em' => null,
            ]);
            $this->liberarHorario($consulta);
        });

        if (! $porPaciente) {
            $push->send($consulta->paciente, 'Consulta cancelada',
                'A clínica cancelou sua consulta. Abra o MedVida para ver o aviso.',
                'home', "consulta-{$consulta->id}");
        }

        return response()->json(['message' => 'Consulta cancelada com sucesso.']);
    }

    public function marcarCancelamentoLido(Request $request, Consulta $consulta): JsonResponse
    {
        $this->authorizePaciente($request, $consulta);
        abort_unless($consulta->status === 'cancelada' && $consulta->cancelada_por === 'clinica', 422, 'Não há aviso de cancelamento para esta consulta.');

        $consulta->update(['cancelamento_lido_em' => now()]);

        return response()->json(['message' => 'Aviso marcado como lido.']);
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

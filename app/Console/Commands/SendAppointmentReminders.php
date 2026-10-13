<?php

namespace App\Console\Commands;

use App\Models\Consulta;
use App\Services\PushNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendAppointmentReminders extends Command
{
    protected $signature = 'medvida:send-reminders';
    protected $description = 'Envia lembretes de consultas confirmadas 3 dias e 1 dia antes';

    public function handle(PushNotifier $push): int
    {
        foreach ([3, 1] as $days) {
            $date = now('America/Sao_Paulo')->addDays($days)->toDateString();
            Consulta::with('paciente')
                ->where('status', 'confirmada')
                ->whereDate('data', $date)
                ->whereNotIn('id', DB::table('consulta_lembretes_enviados')->select('consulta_id')->where('dias_antes', $days))
                ->chunkById(100, function ($consultas) use ($days, $push) {
                    foreach ($consultas as $consulta) {
                        if (! $consulta->paciente) continue;
                        $body = $days === 3
                            ? 'Sua consulta será em 3 dias. Ainda há tempo para verificar as regras de cancelamento no app.'
                            : 'Sua consulta será amanhã. Confira o horário e o endereço no MedVida.';
                        if ($push->send($consulta->paciente, 'Lembrete de consulta', $body,
                            'consultas', "lembrete-{$days}-{$consulta->id}")) {
                            DB::table('consulta_lembretes_enviados')->insertOrIgnore([
                                'consulta_id' => $consulta->id,
                                'dias_antes' => $days,
                                'created_at' => now(),
                            ]);
                        }
                    }
                });
        }

        return self::SUCCESS;
    }
}

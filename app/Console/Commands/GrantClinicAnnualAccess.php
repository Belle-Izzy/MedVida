<?php

namespace App\Console\Commands;

use App\Models\Clinica;
use App\Support\DemoMode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class GrantClinicAnnualAccess extends Command
{
    protected $signature = 'medvida:clinic-admin {email : E-mail da clínica} {--reason= : Justificativa obrigatória da isenção}';

    protected $description = 'Cria ou libera uma clínica por 12 meses sem cobrança, com registro de auditoria';

    public function handle(): int
    {
        $email = filter_var($this->argument('email'), FILTER_VALIDATE_EMAIL);
        $reason = trim((string) $this->option('reason'));
        if (! $email || mb_strlen($reason) < 8) {
            $this->error('Informe um e-mail válido e --reason com pelo menos 8 caracteres.');

            return self::FAILURE;
        }

        $clinic = Clinica::where('email', $email)->first();
        if (! $clinic) {
            $name = trim((string) $this->ask('Nome da clínica'));
            $phone = trim((string) $this->ask('Telefone'));
            $address = trim((string) $this->ask('Endereço'));
            $password = (string) $this->secret('Senha inicial (não aparece na tela)');
            if (! $name || ! $phone || ! $address || mb_strlen($password) < 8) {
                $this->error('Preencha todos os dados; a senha precisa de ao menos 8 caracteres.');

                return self::FAILURE;
            }
            $clinic = Clinica::create([
                'nome' => $name, 'telefone' => $phone, 'endereco' => $address,
                'email' => $email, 'senha' => Hash::make($password),
                'status_cadastro' => 'aguardando_pagamento',
            ]);
        }

        $operator = (string) (getenv('USERNAME') ?: getenv('USER') ?: 'console');
        DB::transaction(function () use ($clinic, $reason, $operator) {
            $locked = Clinica::whereKey($clinic->id)->lockForUpdate()->firstOrFail();
            $start = $locked->acesso_ate?->isFuture() ? $locked->acesso_ate->copy() : now();
            $end = $start->copy()->addYear();
            $locked->pagamentos()->create([
                'tipo' => 'isencao_admin', 'forma_pagamento' => 'isento', 'status' => 'aprovado',
                'simulado' => false, 'operador_admin' => $operator, 'motivo_admin' => $reason,
                'acesso_inicio' => $start, 'acesso_fim' => $end,
            ]);
            $locked->update([
                'status_cadastro' => 'ativa', 'ativada_em' => $locked->ativada_em ?? now(),
                'ativacao_origem' => 'admin', 'acesso_ate' => $end,
            ]);
        });

        $this->info("Clínica {$email} liberada por 12 meses. Isenção registrada.");
        if (! $clinic->fresh()->hasVerifiedEmail() && ! DemoMode::enabled()) {
            try {
                $clinic->sendEmailVerificationNotification();
                $this->warn('A clínica ainda precisa confirmar o e-mail antes de entrar.');
            } catch (\Throwable $exception) {
                report($exception);
                $this->warn('A isenção foi salva, mas o e-mail não pôde ser enviado. Verifique o serviço de e-mail.');
            }
        }

        return self::SUCCESS;
    }
}

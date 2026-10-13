<?php

namespace App\Console\Commands;

use App\Models\Clinica;
use App\Support\DemoMode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreateDemoClinic extends Command
{
    protected $signature = 'medvida:demo-clinic';

    protected $description = 'Cria uma clínica vazia para teste local, sem cobrança ou envio de e-mail';

    public function handle(): int
    {
        if (! DemoMode::prototype()) {
            $this->error('Disponível somente no protótipo acadêmico.');
            return self::FAILURE;
        }

        $email = 'clinica.teste@medvida.local';
        if (Clinica::where('email', $email)->exists()) {
            $this->warn("A clínica {$email} já existe; senha e dados preservados.");
            return self::SUCCESS;
        }

        $password = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
        DB::transaction(function () use ($email, $password): void {
            $start = now();
            $end = $start->copy()->addYear();
            $clinic = Clinica::create([
                'nome' => 'Clínica Teste MedVida',
                'telefone' => '(00) 00000-0000',
                'endereco' => 'Endereço fictício para testes',
                'email' => $email,
                'senha' => Hash::make($password),
                'status_cadastro' => 'ativa',
                'ativada_em' => $start,
                'acesso_ate' => $end,
                'ativacao_origem' => 'admin',
            ]);
            $clinic->pagamentos()->create([
                'tipo' => 'isencao_admin',
                'forma_pagamento' => 'isento',
                'status' => 'aprovado',
                'simulado' => false,
                'operador_admin' => 'demo-local',
                'motivo_admin' => 'Teste local autorizado pela proprietária',
                'acesso_inicio' => $start,
                'acesso_fim' => $end,
            ]);
        });

        $this->info("Clínica criada: {$email}");
        $this->line("Senha inicial: {$password}");
        $this->warn('Não use esta conta ou esta senha em produção.');
        return self::SUCCESS;
    }
}

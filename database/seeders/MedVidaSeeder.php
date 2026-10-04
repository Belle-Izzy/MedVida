<?php

namespace Database\Seeders;

use App\Models\Clinica;
use App\Models\Disponibilidade;
use App\Models\Medico;
use App\Models\Paciente;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MedVidaSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CLÍNICAS
        |--------------------------------------------------------------------------
        */

        $clinica01 = Clinica::create([
            'nome' => 'clinica01',
            'plano_atendido' => 'Unimed',
            'telefone' => '24999990001',
            'endereco' => 'Endereco clinica01',
            'email' => 'clinica01@medvida.test',
            'senha' => Hash::make('12345678'),
        ]);

        $clinica02 = Clinica::create([
            'nome' => 'clinica02',
            'plano_atendido' => 'Amil',
            'telefone' => '24999990002',
            'endereco' => 'Endereco clinica02',
            'email' => 'clinica02@medvida.test',
            'senha' => Hash::make('12345678'),
        ]);

        /*
        |--------------------------------------------------------------------------
        | MÉDICOS
        |--------------------------------------------------------------------------
        */

        $medico01 = Medico::create([
            'clinica_id' => $clinica01->id,
            'nome' => 'medico01',
            'crm' => 'CRM-TESTE-001',
            'especialidade' => 'Cardiologia',
            'telefone' => '24999991001',
        ]);

        $medico02 = Medico::create([
            'clinica_id' => $clinica01->id,
            'nome' => 'medico02',
            'crm' => 'CRM-TESTE-002',
            'especialidade' => 'Clinica Geral',
            'telefone' => '24999991002',
        ]);

        $medico03 = Medico::create([
            'clinica_id' => $clinica02->id,
            'nome' => 'medico03',
            'crm' => 'CRM-TESTE-003',
            'especialidade' => 'Dermatologia',
            'telefone' => '24999991003',
        ]);

        /*
        |--------------------------------------------------------------------------
        | PACIENTES
        |--------------------------------------------------------------------------
        */

        $paciente01 = Paciente::create([
            'cpf' => '11111111111',
            'telefone' => '24999992001',
            'plano' => 'Unimed',
            'nome' => 'paciente01',
            'data_nascimento' => '1995-05-10',
            'sexo' => 'Não informado',
            'caracteristicas_saude' => 'Dados de teste',
            'email' => 'paciente01@medvida.test',
            'senha' => Hash::make('12345678'),
        ]);

        $paciente02 = Paciente::create([
            'cpf' => '22222222222',
            'telefone' => '24999992002',
            'plano' => 'Amil',
            'nome' => 'paciente02',
            'data_nascimento' => '1990-08-20',
            'sexo' => 'Não informado',
            'caracteristicas_saude' => 'Dados de teste',
            'email' => 'paciente02@medvida.test',
            'senha' => Hash::make('12345678'),
        ]);

        /*
        |--------------------------------------------------------------------------
        | DISPONIBILIDADES - MÉDICO 01
        |--------------------------------------------------------------------------
        */

        $data = now()->addDays(3)->format('Y-m-d');

        $horariosMedico01 = [
            '08:00',
            '08:30',
            '09:00',
            '10:30',
            '14:00',
            '15:30',
        ];

        foreach ($horariosMedico01 as $horario) {
            Disponibilidade::create([
                'medico_id' => $medico01->id,
                'data' => $data,
                'horario' => $horario,
                'disponivel' => true,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | DISPONIBILIDADES - MÉDICO 02
        |--------------------------------------------------------------------------
        */

        $horariosMedico02 = [
            '09:00',
            '09:30',
            '10:00',
            '13:30',
            '14:00',
            '16:00',
        ];

        foreach ($horariosMedico02 as $horario) {
            Disponibilidade::create([
                'medico_id' => $medico02->id,
                'data' => $data,
                'horario' => $horario,
                'disponivel' => true,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | DISPONIBILIDADES - MÉDICO 03
        |--------------------------------------------------------------------------
        */

        $horariosMedico03 = [
            '08:30',
            '09:30',
            '11:00',
            '14:30',
            '15:00',
            '16:30',
        ];

        foreach ($horariosMedico03 as $horario) {
            Disponibilidade::create([
                'medico_id' => $medico03->id,
                'data' => $data,
                'horario' => $horario,
                'disponivel' => true,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | RESUMO
        |--------------------------------------------------------------------------
        */

        $this->command->info('Dados de teste do MedVida criados com sucesso.');
        $this->command->info("Data dos horários: {$data}");
        $this->command->info("Paciente01 ID: {$paciente01->id}");
        $this->command->info("Paciente02 ID: {$paciente02->id}");
        $this->command->info("Medico01 ID: {$medico01->id}");
        $this->command->info("Medico02 ID: {$medico02->id}");
        $this->command->info("Medico03 ID: {$medico03->id}");
    }
}
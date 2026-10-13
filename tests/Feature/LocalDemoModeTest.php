<?php

namespace Tests\Feature;

use App\Models\Clinica;
use App\Models\Paciente;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LocalDemoModeTest extends TestCase
{
    public function test_local_demo_patient_registration_reaches_validation_without_email_delivery(): void
    {
        $this->app->instance('env', 'local');
        config()->set('medvida.teste_local', true);

        $this->postJson('/api/pacientes', [])->assertUnprocessable();
    }

    public function test_local_prototype_allows_clinic_registration_validation(): void
    {
        $this->app->instance('env', 'local');
        config()->set('medvida.teste_local', true);
        $this->postJson('/api/clinicas', [])->assertUnprocessable();
    }

    public function test_academic_mode_allows_registration_without_the_demo_password(): void
    {
        $this->app->instance('env', 'academic');
        config()->set('medvida.academic_mode', true);

        $this->get('/')->assertOk()->assertSee('name="medvida-prototipo" content="1"', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->postJson('/api/clinicas', [])->assertUnprocessable();
    }

    public function test_prototype_clinic_access_and_activation_are_unavailable_in_production(): void
    {
        $this->app->instance('env', 'production');
        config()->set('medvida.teste_local', true);

        $clinic = new Clinica();
        $clinic->id = 42;
        $clinic->status_cadastro = 'ativa';
        $clinic->ativada_em = now();
        $clinic->acesso_ate = now()->addYear();
        $clinic->ativacao_origem = 'prototipo';
        Sanctum::actingAs($clinic, ['clinica']);
        $this->postJson('/api/clinicas/prototipo/ativar')->assertForbidden();
    }

    public function test_demo_flag_never_bypasses_email_check_in_production(): void
    {
        $this->app->instance('env', 'production');
        config()->set('medvida.teste_local', true);

        $patient = new Paciente();
        $patient->id = 42;
        Sanctum::actingAs($patient, ['paciente']);

        $this->getJson('/api/consultas')->assertForbidden();
    }

}

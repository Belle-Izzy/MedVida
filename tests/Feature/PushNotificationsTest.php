<?php

namespace Tests\Feature;

use App\Models\Paciente;
use App\Models\Clinica;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PushNotificationsTest extends TestCase
{
    public function test_push_configuration_requires_login_and_does_not_expose_private_key(): void
    {
        config()->set('services.medvida_push.subject', 'mailto:test@example.com');
        config()->set('services.medvida_push.public_key', 'public-test-key');
        config()->set('services.medvida_push.private_key', 'private-test-key');

        $this->getJson('/api/push/config')->assertUnauthorized();
        $this->postJson('/api/push/subscriptions', [])->assertUnauthorized();

        $paciente = new Paciente();
        $paciente->id = 1;
        $paciente->email_verified_at = now();
        Sanctum::actingAs($paciente, ['paciente']);
        $this->getJson('/api/push/config')
            ->assertOk()
            ->assertJson(['public_key' => 'public-test-key'])
            ->assertDontSee('private-test-key');

        $clinica = new Clinica();
        $clinica->id = 2;
        $clinica->status_cadastro = 'ativa';
        $clinica->ativacao_origem = 'admin';
        $clinica->acesso_ate = now()->addYear();
        $clinica->email_verified_at = now();
        Sanctum::actingAs($clinica, ['clinica']);
        $this->getJson('/api/push/config')->assertOk();
    }

    public function test_private_network_push_endpoint_is_rejected(): void
    {
        config()->set('services.medvida_push.subject', 'mailto:test@example.com');
        config()->set('services.medvida_push.public_key', 'public-test-key');
        config()->set('services.medvida_push.private_key', 'private-test-key');

        $paciente = new Paciente();
        $paciente->id = 1;
        $paciente->email_verified_at = now();
        Sanctum::actingAs($paciente, ['paciente']);
        $this->postJson('/api/push/subscriptions', [
            'endpoint' => 'https://127.0.0.1/internal',
            'keys' => ['p256dh' => str_repeat('A', 87), 'auth' => str_repeat('B', 22)],
        ])->assertUnprocessable()->assertJsonValidationErrors('endpoint');
    }
}

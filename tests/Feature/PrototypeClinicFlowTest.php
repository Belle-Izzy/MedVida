<?php

namespace Tests\Feature;

use App\Models\Clinica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

#[RequiresPhpExtension('pdo_sqlite')]
class PrototypeClinicFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_and_activation_do_not_send_email_or_charge(): void
    {
        $this->app->instance('env', 'local');
        config()->set('medvida.teste_local', true);
        Notification::fake();

        $registration = $this->postJson('/api/clinicas', [
            'nome' => 'Clínica Apresentação',
            'telefone' => '(11) 99999-9999',
            'endereco' => 'Rua de Teste, 10',
            'email' => 'clinica@exemplo.test',
            'senha' => 'senha-segura-123',
        ])->assertCreated()
            ->assertJsonPath('clinica.status_cadastro', 'aguardando_pagamento')
            ->assertJsonPath('email_enviado', false);

        $token = $registration->json('activation_token');
        $this->assertNotEmpty($token);
        Notification::assertNothingSent();

        $this->withToken($token)->getJson('/api/consultas')->assertForbidden();
        $this->withToken($token)->postJson('/api/clinicas/prototipo/ativar')->assertOk()
            ->assertJsonPath('clinica.status_cadastro', 'ativa')
            ->assertJsonPath('clinica.ativacao_origem', 'prototipo');

        $clinic = Clinica::where('email', 'clinica@exemplo.test')->firstOrFail();
        $this->assertTrue($clinic->hasActiveAccess());
        $this->assertSame(0, $clinic->pagamentos()->count());
        $this->assertSame(0, $clinic->tokens()->count());
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();

        $this->postJson('/api/auth/login', [
            'tipo' => 'clinica',
            'email' => 'clinica@exemplo.test',
            'senha' => 'senha-segura-123',
        ])->assertOk()->assertJsonPath('usuario.status_cadastro', 'ativa');
    }
}

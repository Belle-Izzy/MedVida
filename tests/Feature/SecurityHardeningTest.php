<?php

namespace Tests\Feature;

use App\Models\Clinica;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    public function test_login_attempts_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/auth/login', [
                'tipo' => 'paciente',
                'email' => 'test@example.com',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/auth/login', [
            'tipo' => 'paciente',
            'email' => 'test@example.com',
        ])->assertStatus(429);
    }

    public function test_public_clinic_signup_requires_registration_fields(): void
    {
        $this->postJson('/api/clinicas', [
            'forma_pagamento' => 'pix',
        ])->assertUnprocessable();
    }

    public function test_inactive_clinic_token_cannot_access_private_routes(): void
    {
        $clinica = new Clinica();
        $clinica->id = 1;
        $clinica->status_cadastro = 'aguardando_pagamento';
        Sanctum::actingAs($clinica, ['clinica']);

        $this->getJson('/api/me')->assertOk();
        $this->getJson('/api/consultas')->assertForbidden();
    }

    public function test_html_and_api_responses_have_security_headers(): void
    {
        $this->get('/')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Content-Security-Policy');

        $this->getJson('/api/health')->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
    }
}

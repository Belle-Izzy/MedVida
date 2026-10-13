<?php

namespace Tests\Feature;

use App\Models\Clinica;
use App\Models\Paciente;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClinicPaymentSecurityTest extends TestCase
{
    public function test_unsigned_webhook_cannot_activate_a_clinic(): void
    {
        $this->postJson('/api/pagamentos/mercado-pago/webhook?type=order&data.id=ORD01ABC', [
            'type' => 'order', 'data' => ['id' => 'ORD01ABC'],
        ])->assertUnauthorized();
    }

    public function test_only_clinic_can_open_checkout_and_pending_clinic_cannot_use_private_api(): void
    {
        $this->postJson('/api/clinicas/pagamento/checkout')->assertUnauthorized();

        $patient = new Paciente();
        $patient->id = 10;
        Sanctum::actingAs($patient, ['paciente']);
        $this->postJson('/api/clinicas/pagamento/checkout')->assertForbidden();

        $clinic = new Clinica();
        $clinic->id = 11;
        $clinic->status_cadastro = 'aguardando_pagamento';
        $clinic->email_verified_at = now();
        Sanctum::actingAs($clinic, ['clinica']);
        $this->getJson('/api/me')->assertOk();
        $this->getJson('/api/consultas')->assertForbidden();
        $this->postJson('/api/clinicas/pagamento/checkout')->assertStatus(503);
    }
}

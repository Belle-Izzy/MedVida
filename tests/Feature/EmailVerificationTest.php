<?php

namespace Tests\Feature;

use App\Models\Paciente;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    public function test_registration_event_sends_a_verification_notification(): void
    {
        Notification::fake();
        $patient = new Paciente();
        $patient->id = 42;
        $patient->email = 'new@example.com';

        event(new Registered($patient));

        Notification::assertSentTo($patient, VerifyEmail::class);
    }

    public function test_unsigned_verification_link_is_rejected(): void
    {
        $this->get('/verificar-email/paciente/42/invalid')->assertForbidden();
    }

    public function test_unverified_patient_cannot_use_private_api(): void
    {
        $patient = new Paciente();
        $patient->id = 42;
        Sanctum::actingAs($patient, ['paciente']);

        $this->getJson('/api/consultas')->assertForbidden();
    }

    public function test_production_does_not_register_accounts_with_log_only_mailer(): void
    {
        $this->app->instance('env', 'production');
        config()->set('mail.default', 'log');

        $this->postJson('/api/pacientes', [])->assertStatus(503);
        $this->postJson('/api/clinicas', [])->assertStatus(503);
    }
}

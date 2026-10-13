<?php

use App\Http\Controllers\ClinicaController;
use App\Http\Controllers\ConsultaController;
use App\Http\Controllers\MedicoController;
use App\Http\Controllers\PacienteController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\PagamentoClinicaController;
use App\Http\Controllers\EmailVerificationController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DisponibilidadeController;

Route::get('/health', fn () => response()->json(['status' => 'ok']));
Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:medvida-login');
Route::post('auth/email/resend', [EmailVerificationController::class, 'resend'])->middleware('throttle:medvida-login');
Route::post('clinicas', [ClinicaController::class, 'store'])->middleware('throttle:5,1');
Route::post('pagamentos/mercado-pago/webhook', [PagamentoClinicaController::class, 'webhook'])->middleware('throttle:120,1');
Route::post('pacientes', [PacienteController::class, 'store'])->middleware('throttle:5,1');

Route::get('medicos', [MedicoController::class, 'index']);
Route::get('medicos/{medico}', [MedicoController::class, 'show']);
Route::get('medicos/{medico}/horarios', [MedicoController::class, 'horarios']);
Route::get('disponibilidades', [DisponibilidadeController::class, 'index'])->middleware('throttle:30,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);
});

Route::middleware(['auth:sanctum', 'email.verificado', 'tipo:clinica'])->group(function () {
    Route::post('clinicas/pagamento/checkout', [PagamentoClinicaController::class, 'checkout'])->middleware('throttle:5,1');
    Route::get('clinicas/pagamento/status', [PagamentoClinicaController::class, 'status'])->middleware('throttle:10,1');
});

Route::post('clinicas/prototipo/ativar', [PagamentoClinicaController::class, 'activatePrototype'])
    ->middleware(['auth:sanctum', 'tipo:clinica', 'throttle:5,1']);

Route::middleware(['auth:sanctum', 'email.verificado', 'clinica.ativa'])->group(function () {
    Route::get('push/config', [PushSubscriptionController::class, 'config']);
    Route::post('push/subscriptions', [PushSubscriptionController::class, 'store']);
    Route::delete('push/subscriptions', [PushSubscriptionController::class, 'destroy']);
    Route::get('consultas', [ConsultaController::class, 'index']);
    Route::get('consultas/{consulta}', [ConsultaController::class, 'show']);
    Route::delete('consultas/{consulta}', [ConsultaController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'email.verificado', 'clinica.ativa', 'tipo:paciente'])->group(function () {
    Route::get('avisos/cancelamento', [ConsultaController::class, 'avisoCancelamento']);
    Route::patch('pacientes/{paciente}', [PacienteController::class, 'update']);
    Route::post('consultas', [ConsultaController::class, 'store']);
    Route::patch('consultas/{consulta}', [ConsultaController::class, 'update']);
    Route::patch('consultas/{consulta}/mensagem', [ConsultaController::class, 'mensagem']);
    Route::patch('consultas/{consulta}/cancelamento-lido', [ConsultaController::class, 'marcarCancelamentoLido']);
});

Route::middleware(['auth:sanctum', 'email.verificado', 'clinica.ativa', 'tipo:clinica'])->group(function () {
    Route::patch('clinicas/{clinica}', [ClinicaController::class, 'update']);
    Route::post('medicos', [MedicoController::class, 'store']);
    Route::patch('medicos/{medico}', [MedicoController::class, 'update']);
    Route::delete('medicos/{medico}', [MedicoController::class, 'destroy']);
    Route::post('disponibilidades', [DisponibilidadeController::class, 'store']);
    Route::patch('disponibilidades/{disponibilidade}', [DisponibilidadeController::class, 'update']);
    Route::delete('disponibilidades/{disponibilidade}', [DisponibilidadeController::class, 'destroy']);
    Route::patch('consultas/{consulta}/confirmar', [ConsultaController::class, 'confirmar']);
    Route::patch('consultas/{consulta}/recusar', [ConsultaController::class, 'recusar']);
});

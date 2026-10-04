<?php

use App\Http\Controllers\ClinicaController;
use App\Http\Controllers\ConsultaController;
use App\Http\Controllers\MedicoController;
use App\Http\Controllers\PacienteController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DisponibilidadeController;

Route::get('/health', fn () => response()->json(['status' => 'ok']));
Route::post('auth/login', [AuthController::class, 'login']);
Route::post('clinicas', [ClinicaController::class, 'store']);
Route::post('pacientes', [PacienteController::class, 'store']);

Route::get('medicos', [MedicoController::class, 'index']);
Route::get('medicos/{medico}', [MedicoController::class, 'show']);
Route::get('medicos/{medico}/horarios', [MedicoController::class, 'horarios']);
Route::get('disponibilidades', [DisponibilidadeController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('consultas', [ConsultaController::class, 'index']);
    Route::get('consultas/{consulta}', [ConsultaController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'tipo:paciente'])->group(function () {
    Route::patch('pacientes/{paciente}', [PacienteController::class, 'update']);
    Route::post('consultas', [ConsultaController::class, 'store']);
    Route::patch('consultas/{consulta}', [ConsultaController::class, 'update']);
    Route::delete('consultas/{consulta}', [ConsultaController::class, 'destroy']);
    Route::patch('consultas/{consulta}/mensagem', [ConsultaController::class, 'mensagem']);
});

Route::middleware(['auth:sanctum', 'tipo:clinica'])->group(function () {
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

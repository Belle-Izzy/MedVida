<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pacientes', function (Blueprint $table) {
            $table->id();
            $table->string('cpf', 14)->unique();
            $table->string('telefone', 20);
            $table->string('plano')->nullable();
            $table->string('nome');
            $table->unsignedTinyInteger('idade')->nullable();
            $table->string('sexo', 30)->nullable();
            $table->text('caracteristicas_saude')->nullable();
            $table->string('email')->unique();
            $table->string('senha');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pacientes');
    }
};

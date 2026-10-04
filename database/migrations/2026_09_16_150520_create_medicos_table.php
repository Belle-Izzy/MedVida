<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinica_id')->constrained('clinicas')->restrictOnDelete();
            $table->string('nome');
            $table->string('crm', 30)->unique();
            $table->string('especialidade');
            $table->string('telefone', 20);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicos');
    }
};

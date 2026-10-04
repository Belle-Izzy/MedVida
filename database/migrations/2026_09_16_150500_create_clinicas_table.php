<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinicas', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('plano_atendido')->nullable();
            $table->string('telefone', 20);
            $table->string('endereco');
            $table->string('email')->unique();
            $table->string('senha');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinicas');
    }
};

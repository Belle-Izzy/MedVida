<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagamentos_clinica', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinica_id')->constrained()->cascadeOnDelete();
            $table->string('tipo', 30)->default('taxa_adesao');
            $table->string('forma_pagamento', 30);
            $table->string('status', 30)->default('aguardando_pagamento')->index();
            $table->boolean('simulado')->default(false);
            $table->string('referencia_provedor')->nullable()->unique();
            $table->timestamp('pago_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagamentos_clinica');
    }
};

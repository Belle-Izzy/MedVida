<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinicas', function (Blueprint $table) {
            $table->string('status_cadastro', 30)->default('aguardando_pagamento')->index();
            $table->timestamp('ativada_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clinicas', function (Blueprint $table) {
            $table->dropIndex(['status_cadastro']);
            $table->dropColumn(['status_cadastro', 'ativada_em']);
        });
    }
};

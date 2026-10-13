<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinicas', function (Blueprint $table) {
            $table->timestamp('acesso_ate')->nullable();
            $table->string('ativacao_origem', 30)->nullable();
        });

        Schema::table('pagamentos_clinica', function (Blueprint $table) {
            $table->uuid('referencia_externa')->nullable()->unique();
            $table->uuid('chave_idempotencia')->nullable()->unique();
            $table->unsignedInteger('valor_centavos')->nullable();
            $table->string('moeda', 3)->nullable();
            $table->text('checkout_url')->nullable();
            $table->string('operador_admin')->nullable();
            $table->text('motivo_admin')->nullable();
            $table->timestamp('acesso_inicio')->nullable();
            $table->timestamp('acesso_fim')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pagamentos_clinica', function (Blueprint $table) {
            $table->dropUnique(['referencia_externa']);
            $table->dropUnique(['chave_idempotencia']);
            $table->dropColumn(['referencia_externa', 'chave_idempotencia', 'valor_centavos', 'moeda', 'checkout_url', 'operador_admin', 'motivo_admin', 'acesso_inicio', 'acesso_fim']);
        });

        Schema::table('clinicas', function (Blueprint $table) {
            $table->dropColumn(['acesso_ate', 'ativacao_origem']);
        });
    }
};

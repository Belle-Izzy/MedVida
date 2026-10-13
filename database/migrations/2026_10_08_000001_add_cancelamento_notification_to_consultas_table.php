<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultas', function (Blueprint $table) {
            $table->string('cancelada_por', 10)->nullable();
            $table->timestamp('cancelamento_lido_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('consultas', function (Blueprint $table) {
            $table->dropColumn(['cancelada_por', 'cancelamento_lido_em']);
        });
    }
};

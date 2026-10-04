<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disponibilidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medico_id')
                ->constrained('medicos')
                ->cascadeOnDelete();
            $table->date('data');
            $table->time('horario');
            $table->boolean('disponivel')->default(true);
            $table->timestamps();

            $table->unique(
                ['medico_id', 'data', 'horario'],
                'disponibilidades_medico_data_horario_unique'
            );

            $table->index(['data', 'horario']);
            $table->index(['medico_id', 'data']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disponibilidades');
    }
};

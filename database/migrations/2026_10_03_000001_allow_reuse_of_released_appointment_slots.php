<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE consultas DROP CONSTRAINT IF EXISTS consultas_medico_data_horario_unique');
        DB::statement("CREATE UNIQUE INDEX consultas_horario_ativo_unique ON consultas (medico_id, data, horario) WHERE status IN ('pendente', 'confirmada')");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS consultas_horario_ativo_unique');
        DB::statement('ALTER TABLE consultas ADD CONSTRAINT consultas_medico_data_horario_unique UNIQUE (medico_id, data, horario)');
    }
};

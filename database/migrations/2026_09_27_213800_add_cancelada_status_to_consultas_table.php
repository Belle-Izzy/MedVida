<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE consultas
            DROP CONSTRAINT IF EXISTS consultas_status_check
        ");

        DB::statement("
            ALTER TABLE consultas
            ADD CONSTRAINT consultas_status_check
            CHECK (
                status IN (
                    'pendente',
                    'confirmada',
                    'recusada',
                    'cancelada'
                )
            )
        ");
    }

    public function down(): void
    {
        DB::statement("
            UPDATE consultas
            SET status = 'recusada'
            WHERE status = 'cancelada'
        ");

        DB::statement("
            ALTER TABLE consultas
            DROP CONSTRAINT IF EXISTS consultas_status_check
        ");

        DB::statement("
            ALTER TABLE consultas
            ADD CONSTRAINT consultas_status_check
            CHECK (
                status IN (
                    'pendente',
                    'confirmada',
                    'recusada'
                )
            )
        ");
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pacientes', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable();
        });
        Schema::table('clinicas', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pacientes', fn (Blueprint $table) => $table->dropColumn('email_verified_at'));
        Schema::table('clinicas', fn (Blueprint $table) => $table->dropColumn('email_verified_at'));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint_hash', 64)->unique();
            $table->text('endpoint');
            $table->text('p256dh');
            $table->text('auth');
            $table->string('content_encoding', 20)->default('aes128gcm');
            $table->string('owner_type', 20);
            $table->unsignedBigInteger('owner_id');
            $table->unsignedBigInteger('access_token_id');
            $table->timestamps();
            $table->index(['owner_type', 'owner_id']);
            $table->index('access_token_id');
        });

        Schema::create('consulta_lembretes_enviados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consulta_id')->constrained('consultas')->cascadeOnDelete();
            $table->unsignedTinyInteger('dias_antes');
            $table->timestamp('created_at');
            $table->unique(['consulta_id', 'dias_antes']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consulta_lembretes_enviados');
        Schema::dropIfExists('push_subscriptions');
    }
};

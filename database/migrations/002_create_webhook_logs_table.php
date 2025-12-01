<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 20)->index();
            $table->string('event_id')->unique();
            $table->string('event_type', 100)->index();
            $table->json('payload');
            $table->boolean('processed')->default(false)->index();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            // Índices para consultas frequentes
            $table->index(['gateway', 'processed'], 'idx_gateway_processed');
            $table->index(['event_type', 'created_at'], 'idx_event_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};

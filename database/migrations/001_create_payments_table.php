<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created at: 24/11/25
*/

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('gateway', 20)->index();
            $table->string('gateway_payment_id')->nullable()->index();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3);
            $table->string('status', 20)->index();
            $table->string('payment_method', 50);
            $table->json('customer_data');
            $table->json('metadata')->nullable();
            $table->text('error_message')->nullable();
            $table->string('payment_url')->nullable();
            $table->text('qr_code')->nullable();
            $table->text('barcode')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // Índices compostos para otimização
            $table->index(['gateway', 'gateway_payment_id'], 'idx_gateway_payment');
            $table->index(['status', 'created_at'], 'idx_status_created');
            $table->index('created_at', 'idx_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

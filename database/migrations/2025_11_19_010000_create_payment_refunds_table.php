<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 01:00:00
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')
                ->constrained('transactions')
                ->onDelete('cascade')
                ->comment('ID da transação associada');
            $table->decimal('amount', 10, 2)->comment('Valor do reembolso');
            $table->text('reason')->nullable()->comment('Motivo do reembolso');
            $table->string('status')->default('pending')->comment('Status do reembolso: pending, completed, failed');
            $table->string('gateway_refund_id')->nullable()->index()->comment('ID do reembolso no gateway de pagamento');
            $table->json('gateway_response')->nullable()->comment('Resposta completa do gateway');
            $table->timestamp('processed_at')->nullable()->comment('Data/hora do processamento');
            $table->timestamps();

            // Indexes para performance
            $table->index(['transaction_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
    }
};

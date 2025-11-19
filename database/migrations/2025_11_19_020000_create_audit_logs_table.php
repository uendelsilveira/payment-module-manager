<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 03:30:00
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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')
                ->constrained('transactions')
                ->onDelete('cascade')
                ->comment('ID da transação associada');
            $table->string('operation_type')->comment('Tipo de operação: refund, cancellation, etc.');
            $table->string('user_id')->nullable()->comment('ID do usuário/admin que iniciou a operação');
            $table->string('user_type')->nullable()->comment('Tipo do usuário (admin, system, api)');
            $table->decimal('amount', 10, 2)->nullable()->comment('Valor da operação');
            $table->text('reason')->nullable()->comment('Motivo da operação');
            $table->string('previous_status')->nullable()->comment('Status anterior da transação');
            $table->string('new_status')->nullable()->comment('Novo status da transação');
            $table->json('gateway_response')->nullable()->comment('Resposta do gateway');
            $table->string('ip_address', 45)->nullable()->comment('Endereço IP de origem');
            $table->string('correlation_id')->nullable()->index()->comment('ID de correlação para rastreamento');
            $table->json('metadata')->nullable()->comment('Metadados adicionais');
            $table->timestamp('created_at')->useCurrent()->comment('Data/hora da operação');

            // Indexes para performance
            $table->index(['transaction_id', 'operation_type']);
            $table->index(['operation_type', 'created_at']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

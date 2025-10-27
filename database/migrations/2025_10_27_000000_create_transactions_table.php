<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Us\PaymentModuleManager\Enums\PaymentGateway;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->nullable()->comment('ID da transação no gateway de pagamento');
            $table->string('gateway'); // Usar o enum PaymentGateway
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('BRL');
            $table->string('status')->default('pending'); // pending, approved, rejected, refunded, etc.
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Para armazenar dados adicionais do gateway
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};

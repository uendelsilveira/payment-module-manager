<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:21
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use UendelSilveira\PaymentModuleManager\Enums\PaymentStatus;

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
            $table->enum('status', array_map(static fn (PaymentStatus $s) => $s->value, PaymentStatus::cases()))
                ->default(PaymentStatus::PENDING->value);
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
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

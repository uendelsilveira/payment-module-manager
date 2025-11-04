<?php

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
        Schema::table('transactions', function (Blueprint $table) {
            $table->index('external_id');
            $table->index('status');
            $table->index('gateway');
            $table->index('created_at');
            $table->index(['gateway', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['external_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['gateway']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['gateway', 'status']);
            $table->dropIndex(['status', 'created_at']);
        });
    }
};

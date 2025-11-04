<?php

namespace UendelSilveira\PaymentModuleManager\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use UendelSilveira\PaymentModuleManager\Database\Factories\TransactionFactory;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'gateway',
        'amount',
        'currency',
        'status',
        'description',
        'external_id',
        'metadata',
        'retries_count',
        'last_attempt_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'metadata' => 'array',
        'last_attempt_at' => 'datetime',
    ];

    /**
     * Cria uma nova instância da factory para o modelo.
     */
    protected static function newFactory(): Factory
    {
        return TransactionFactory::new();
    }
}

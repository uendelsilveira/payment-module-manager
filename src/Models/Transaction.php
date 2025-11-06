<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 04/11/2025 16:09:38
*/

namespace UendelSilveira\PaymentModuleManager\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use UendelSilveira\PaymentModuleManager\Database\Factories\TransactionFactory;

/**
 * @property int $id
 * @property string $gateway
 * @property float $amount
 * @property string $currency
 * @property string $status
 * @property string|null $description
 * @property string|null $external_id
 * @property string|null $idempotency_key
 * @property array<string, mixed>|null $metadata
 * @property int $retries_count
 * @property \Illuminate\Support\Carbon|null $last_attempt_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<Transaction> where(string|array<int|string, mixed> $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static Transaction create(array<string, mixed> $attributes = [])
 * @method static \Illuminate\Database\Eloquent\Builder<Transaction> newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<Transaction> newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<Transaction> query()
 * @method static Transaction|null first()
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<Transaction>
 */
class Transaction extends Model
{
    /** @use HasFactory<\UendelSilveira\PaymentModuleManager\Database\Factories\TransactionFactory> */
    use HasFactory;
    use SoftDeletes;
    /** @var array<int, string> */
    protected $fillable = [
        'gateway',
        'amount',
        'currency',
        'status',
        'description',
        'external_id',
        'idempotency_key',
        'metadata',
        'retries_count',
        'last_attempt_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'amount' => 'float',
        'metadata' => 'array',
        'last_attempt_at' => 'datetime',
    ];

    /**
     * Cria uma nova instância da factory para o modelo.
     *
     * @return \UendelSilveira\PaymentModuleManager\Database\Factories\TransactionFactory
     */
    protected static function newFactory(): Factory
    {
        return TransactionFactory::new();
    }
}

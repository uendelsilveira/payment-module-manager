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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use UendelSilveira\PaymentModuleManager\Database\Factories\TransactionFactory;

/**
 * @property int $id
 * @property string $correlation_id
 * @property string $gateway
 * @property string $payment_method
 * @property int $installments
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
 * @property-read PaymentGateway $paymentGateway
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Refund> $refunds
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AuditLog> $auditLogs
 *
 * @method static \Illuminate\Database\Eloquent\Builder<Transaction> where(string|array<int|string, mixed> $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static Transaction create(array<string, mixed> $attributes = [])
 * @method static \Illuminate\Database\Eloquent\Builder<Transaction> newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<Transaction> newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<Transaction> query()
 * @method static Transaction|null first()
 * @method static \Illuminate\Database\Eloquent\Builder<Transaction> onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<Transaction> withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<Transaction> withoutTrashed()
 * @method static bool|null restore()
 * @method static bool|null forceDelete()
 * @method static int|null delete()
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<Transaction>
 */
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'correlation_id',
        'gateway',
        'payment_method',
        'installments',
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
     * Get the refunds for the transaction.
     *
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * Get the audit logs for the transaction.
     *
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Cria uma nova instância da factory para o modelo.
     */
    protected static function newFactory(): TransactionFactory
    {
        return TransactionFactory::new();
    }
}

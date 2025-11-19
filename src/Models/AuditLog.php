<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 03:30:00
*/

namespace UendelSilveira\PaymentModuleManager\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $transaction_id
 * @property string $operation_type
 * @property string|null $user_id
 * @property string|null $user_type
 * @property float|null $amount
 * @property string|null $reason
 * @property string|null $previous_status
 * @property string|null $new_status
 * @property array<string, mixed>|null $gateway_response
 * @property string|null $ip_address
 * @property string|null $correlation_id
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read Transaction $transaction
 *
 * @method static \Illuminate\Database\Eloquent\Builder<AuditLog> where(string|array<int|string, mixed> $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static AuditLog create(array<string, mixed> $attributes = [])
 * @method static \Illuminate\Database\Eloquent\Builder<AuditLog> newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<AuditLog> newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<AuditLog> query()
 * @method static AuditLog|null first()
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<AuditLog>
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null; // Audit logs are immutable, no updates

    protected $table = 'audit_logs';

    /** @var array<int, string> */
    protected $fillable = [
        'transaction_id',
        'operation_type',
        'user_id',
        'user_type',
        'amount',
        'reason',
        'previous_status',
        'new_status',
        'gateway_response',
        'ip_address',
        'correlation_id',
        'metadata',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'amount' => 'float',
        'gateway_response' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the transaction that owns the audit log.
     *
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Scope a query to only include refund operations.
     *
     * @param Builder<AuditLog> $builder
     *
     * @return Builder<AuditLog>
     */
    public function scopeRefunds(Builder $builder): Builder
    {
        return $builder->where('operation_type', 'refund');
    }

    /**
     * Scope a query to only include cancellation operations.
     *
     * @param Builder<AuditLog> $builder
     *
     * @return Builder<AuditLog>
     */
    public function scopeCancellations(Builder $builder): Builder
    {
        return $builder->where('operation_type', 'cancellation');
    }

    /**
     * Scope a query to filter by transaction ID.
     *
     * @param Builder<AuditLog> $builder
     *
     * @return Builder<AuditLog>
     */
    public function scopeForTransaction(Builder $builder, int $transactionId): Builder
    {
        return $builder->where('transaction_id', $transactionId);
    }

    /**
     * Scope a query to filter by correlation ID.
     *
     * @param Builder<AuditLog> $builder
     *
     * @return Builder<AuditLog>
     */
    public function scopeByCorrelationId(Builder $builder, string $correlationId): Builder
    {
        return $builder->where('correlation_id', $correlationId);
    }
}

<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 01:00:00
*/

namespace UendelSilveira\PaymentModuleManager\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use UendelSilveira\PaymentModuleManager\Database\Factories\RefundFactory;

/**
 * @property int $id
 * @property int $transaction_id
 * @property float $amount
 * @property string|null $reason
 * @property string $status
 * @property string|null $gateway_refund_id
 * @property array<string, mixed>|null $gateway_response
 * @property \Illuminate\Support\Carbon|null $processed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Transaction $transaction
 *
 * @method static \Illuminate\Database\Eloquent\Builder<Refund> where(string|array<int|string, mixed> $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static Refund create(array<string, mixed> $attributes = [])
 * @method static \Illuminate\Database\Eloquent\Builder<Refund> newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<Refund> newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<Refund> query()
 * @method static Refund|null first()
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<Refund>
 */
class Refund extends Model
{
    /** @use HasFactory<Factory<Refund>> */
    use HasFactory;

    protected $table = 'payment_refunds';

    /** @var array<int, string> */
    protected $fillable = [
        'transaction_id',
        'amount',
        'reason',
        'status',
        'gateway_refund_id',
        'gateway_response',
        'processed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'amount' => 'float',
        'gateway_response' => 'array',
        'processed_at' => 'datetime',
    ];

    /**
     * Get the transaction that owns the refund.
     *
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Scope a query to only include completed refunds.
     *
     * @param Builder<Refund> $builder
     *
     * @return Builder<Refund>
     */
    public function scopeCompleted(Builder $builder): Builder
    {
        return $builder->where('status', 'completed');
    }

    /**
     * Scope a query to only include pending refunds.
     *
     * @param Builder<Refund> $builder
     *
     * @return Builder<Refund>
     */
    public function scopePending(Builder $builder): Builder
    {
        return $builder->where('status', 'pending');
    }

    /**
     * Scope a query to only include failed refunds.
     *
     * @param Builder<Refund> $builder
     *
     * @return Builder<Refund>
     */
    public function scopeFailed(Builder $builder): Builder
    {
        return $builder->where('status', 'failed');
    }

    /**
     * Cria uma nova instância da factory para o modelo.
     *
     * @return Factory<Refund>
     */
    protected static function newFactory(): Factory
    {
        return RefundFactory::new();
    }
}

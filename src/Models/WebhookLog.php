<?php

namespace UendelSilveira\PaymentModuleManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use UendelSilveira\PaymentModuleManager\Database\Factories\WebhookLogFactory;

/**
 * @mixin \Illuminate\Database\Eloquent\Builder<WebhookLog>
 * @mixin \Illuminate\Database\Eloquent\Model
 *
 * @property array<string, mixed> $payload
 * @property array<string, mixed> $response_body
 * @property \Illuminate\Support\Carbon|null $processed_at
 * @property \Illuminate\Support\Carbon|null $last_retry_at
 *
 * @method static WebhookLogFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<WebhookLog>|static query()
 * @method static \Illuminate\Database\Eloquent\Builder<WebhookLog>|static newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<WebhookLog>|static newQuery()
 * @method static WebhookLog create(array<string, mixed> $attributes = [])
 * @method static WebhookLog|null find(int $id)
 * @method static \Illuminate\Database\Eloquent\Builder<WebhookLog>|static where(string $column, mixed $value)
 * @method static \Illuminate\Database\Eloquent\Collection<int, WebhookLog> all($columns = ['*'])
 */
class WebhookLog extends Model
{
    /** @use HasFactory<WebhookLogFactory> */
    use HasFactory;

    protected $table = 'webhook_logs';

    protected $fillable = [
        'gateway',
        'event_type',
        'payload',
        'status',
        'response_code',
        'response_body',
        'processed_at',
        'retries',
        'last_retry_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'payload' => 'array',
        'response_body' => 'array',
        'processed_at' => 'datetime',
        'last_retry_at' => 'datetime',
    ];
}

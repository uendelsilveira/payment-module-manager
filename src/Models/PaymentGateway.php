<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 14:24:12
*/

namespace UendelSilveira\PaymentModuleManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use UendelSilveira\PaymentModuleManager\Database\Factories\PaymentGatewayFactory;

/**
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 * @mixin \Illuminate\Database\Eloquent\Model
 *
 * @property array<string, mixed> $config
 * @property bool $is_active
 *
 * @method static PaymentGatewayFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @method static \Illuminate\Database\Eloquent\Builder<static> newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static> newQuery()
 * @method static static create(array<string, mixed> $attributes = [])
 * @method static static|null find(mixed $id, array<string> $columns = ['*'])
 * @method static static|null findOrFail(mixed $id, array<string> $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Builder<static> where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Collection<int, static> all(array<string> $columns = ['*'])
 */
class PaymentGateway extends Model
{
    /** @use HasFactory<PaymentGatewayFactory> */
    use HasFactory;

    protected $table = 'payment_gateways';

    protected $fillable = [
        'name',
        'code',
        'config',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'config' => 'array',
        'is_active' => 'boolean',
    ];
}

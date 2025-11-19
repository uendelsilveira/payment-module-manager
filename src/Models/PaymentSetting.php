<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:21
*/

namespace UendelSilveira\PaymentModuleManager\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property array<string, mixed>|null $value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<PaymentSetting> where(string|array<int|string, mixed> $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static PaymentSetting create(array<string, mixed> $attributes = [])
 * @method static PaymentSetting updateOrCreate(array<string, mixed> $attributes, array<string, mixed> $values = [])
 * @method static \Illuminate\Database\Eloquent\Builder<PaymentSetting> newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<PaymentSetting> newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<PaymentSetting> query()
 * @method static PaymentSetting|null first()
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<PaymentSetting>
 */
class PaymentSetting extends Model
{
    protected $table = 'payment_settings';

    /** @var array<int, string> */
    protected $fillable = [
        'key',
        'value',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'value' => 'array', // Assumindo que 'value' armazenará dados JSON
    ];
}

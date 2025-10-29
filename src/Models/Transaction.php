<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:21
*/

namespace UendelSilveira\PaymentModuleManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

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
}

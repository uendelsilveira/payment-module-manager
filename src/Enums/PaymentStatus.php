<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 14:24:12
*/

namespace UendelSilveira\PaymentModuleManager\Enums;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case PROCESSING = 'processing';
    case REJECTED = 'rejected';
    case REFUNDED = 'refunded';
    case CANCELLED = 'cancelled';
    case CHARGEBACK = 'chargeback';
    case FAILED = 'failed';
    case UNKNOWN = 'unknown';
}

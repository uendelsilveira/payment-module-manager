<?php

namespace UendelSilveira\PaymentModuleManager\Enums;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case REFUNDED = 'refunded';
    case CANCELLED = 'cancelled';
    case CHARGEBACK = 'chargeback';
    case FAILED = 'failed';
    case UNKNOWN = 'unknown';
}

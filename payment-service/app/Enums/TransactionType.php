<?php

namespace App\Enums;

enum TransactionType: string
{
    case Payment = 'payment';
    case Refund = 'refund';
}

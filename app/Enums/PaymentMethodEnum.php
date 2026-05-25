<?php

namespace App\Enums;

enum PaymentMethodEnum: string
{
    case BKASH = 'bkash';

    public function label(): string
    {
        return match ($this) {
            self::BKASH => 'Bkash',
        };
    }
}

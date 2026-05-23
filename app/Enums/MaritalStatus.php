<?php

namespace App\Enums;

enum MaritalStatus: string
{
    case SINGLE = 'Single';
    case MARRIED = 'Married';
    case DIVORCED = 'Divorced';
    case WIDOWED = 'Widowed';

    public function label(): string
    {
        return match ($this) {
            self::SINGLE => 'Single',
            self::MARRIED => 'Married',
            self::DIVORCED => 'Divorced',
            self::WIDOWED => 'Widowed',
        };
    }
}

<?php

namespace App\Enums;

enum AddressTypeEnum: string
{
    case PRESENT = 'present';
    case PERMANENT = 'permanent';

    public function label(): string
    {
        return match ($this) {
            self::PRESENT => 'Present',
            self::PERMANENT => 'Permanent',
        };
    }
}

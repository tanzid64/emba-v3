<?php

namespace App\Enums;

enum ReligionEnum: string
{
    case ISLAM = 'Islam';
    case HINDU = 'Hindu';
    case CHRISTIANITY = 'Christianity';
    case BUDDHISM = 'Buddhism';
    case OTHER = 'Other';

    public function label(): string
    {
        return match ($this) {
            self::ISLAM => 'Islam',
            self::HINDU => 'Hindu',
            self::CHRISTIANITY => 'Christianity',
            self::BUDDHISM => 'Buddhism',
            self::OTHER => 'Other',
        };
    }
}

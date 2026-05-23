<?php

namespace App\Enums;

enum BloodGroup: string
{
    case A_POSITIVE = 'A+';
    case A_NEGATIVE = 'A-';
    case B_POSITIVE = 'B+';
    case B_NEGATIVE = 'B-';
    case AB_POSITIVE = 'AB+';
    case AB_NEGATIVE = 'AB-';
    case O_POSITIVE = 'O+';
    case O_NEGATIVE = 'O-';
    case UNKNOWN = 'Unknown';

    public function label(): string
    {
        return match ($this) {
            self::A_POSITIVE => 'A+',
            self::A_NEGATIVE => 'A-',
            self::B_POSITIVE => 'B+',
            self::B_NEGATIVE => 'B-',
            self::AB_POSITIVE => 'AB+',
            self::AB_NEGATIVE => 'AB-',
            self::O_POSITIVE => 'O+',
            self::O_NEGATIVE => 'O-',
            self::UNKNOWN => 'Unknown',
        };
    }
}

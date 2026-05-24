<?php

namespace App\Enums;

enum DegreeType: string
{
    case SSC = 'SSC';
    case HSC = 'HSC';
    case UNDERGRADUATE = 'Undergraduate';
    case GRADUATE = 'Graduate';
    case OTHER = 'Other';

    public function label(): string
    {
        return match ($this) {
            self::SSC => 'SSC',
            self::HSC => 'HSC',
            self::UNDERGRADUATE => 'Undergraduate',
            self::GRADUATE => 'Graduate',
            self::OTHER => 'Other',
        };
    }
}

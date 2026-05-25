<?php

namespace App\Enums;

enum PaymentActorEnum: string
{
    case APPLICATION = 'Application';
    case ENROLLMENT = 'Enrollment';
    case ADMISSION = 'Admission';

    public function label(): string
    {
        return match ($this) {
            self::APPLICATION => 'Application',
            self::ENROLLMENT => 'Enrollment',
            self::ADMISSION => 'Admission',
        };
    }
}

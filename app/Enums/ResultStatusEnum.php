<?php

namespace App\Enums;

enum ResultStatusEnum: string
{
    case PASSED = 'Passed';
    case FAILED = 'Failed';

    public function label(): string
    {
        return match ($this) {
            self::PASSED => 'Passed',
            self::FAILED => 'Failed',
        };
    }
}

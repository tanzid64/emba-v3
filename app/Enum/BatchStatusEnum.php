<?php

namespace App\Enum;

enum BatchStatusEnum: string
{
    case DRAFT = 'draft';
    case OPEN = 'open';
    case CLOSED = 'closed';
}

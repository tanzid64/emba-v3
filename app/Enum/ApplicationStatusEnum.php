<?php

namespace App\Enum;

enum ApplicationStatusEnum: string
{
    case Pending = 'pending';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
}

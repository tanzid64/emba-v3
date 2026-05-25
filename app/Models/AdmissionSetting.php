<?php

namespace App\Models;

use App\Casts\DateFormatCast;
use App\Enum\BatchStatusEnum;
use Illuminate\Database\Eloquent\Model;

class AdmissionSetting extends Model
{
    protected $guarded = [];
    protected $casts = [
        'intake_started_at' => DateFormatCast::class,
        'intake_ended_at' => DateFormatCast::class,
        'application_payment_ended_at' => DateFormatCast::class,
        'admit_card_published_at' => DateFormatCast::class,
        'exam_date' => DateFormatCast::class,
        'viva_date' => DateFormatCast::class,
        'result_published_at' => DateFormatCast::class,
    ];

    protected $appends = [
        'is_application_open',
        'is_application_payment_open',
        'is_admit_card_published',
        'is_result_published',
    ];

    public function getIsApplicationOpenAttribute()
    {
        $rawStartDate = $this->getRawOriginal('intake_started_at');
        $rawEndDate = $this->getRawOriginal('intake_ended_at');
        $today = now()->toDateString();
        if (!is_null($rawStartDate)) {
            // If both start and end dates are set, check if today is between them
            if (!is_null($rawEndDate)) {
                return $today >= $rawStartDate && $today <= $rawEndDate;
            }
            // If only the start date is set, check if today is after the start date
            return $today >= $rawStartDate;
        }
        return false;
    }

    public function getIsApplicationPaymentOpenAttribute()
    {
        $rawApplicationStartDate = $this->getRawOriginal('intake_started_at');
        $rawPaymentEndDate = $this->getRawOriginal('application_payment_ended_at');
        $today = now()->toDateString();
        if (!is_null($rawApplicationStartDate)) {
            if (is_null($rawPaymentEndDate)) {
                return $today >= $rawApplicationStartDate;
            }
            return $today >= $rawApplicationStartDate && $today <= $rawPaymentEndDate;
        }
        return false;
    }

    public function getIsAdmitCardPublishedAttribute()
    {
        $rawAdmitCardPublishedAt = $this->getRawOriginal('admit_card_published_at');
        return !is_null($rawAdmitCardPublishedAt);
    }

    public function getIsResultPublishedAttribute()
    {
        $rawResultPublishedAt = $this->getRawOriginal('result_published_at');
        return !is_null($rawResultPublishedAt);
    }

    // Relationship with Batch
    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }
}

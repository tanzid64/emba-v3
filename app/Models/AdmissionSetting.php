<?php

namespace App\Models;

use App\Casts\DateFormatCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class AdmissionSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'application_number_start_from' => 'integer',
        'roll_number_start_from' => 'integer',
        'intake_started_at' => DateFormatCast::class,
        'intake_ended_at' => DateFormatCast::class,
        'application_payment_ended_at' => DateFormatCast::class,
        'admit_card_published_at' => DateFormatCast::class,
        'exam_center_uploaded_at' => DateFormatCast::class,
        'exam_date' => DateFormatCast::class,
        'viva_date' => DateFormatCast::class,
        'result_published_at' => DateFormatCast::class,
    ];

    protected $appends = [
        'notice_url',
        'is_application_open',
        'is_application_payment_open',
        'is_admit_card_published',
        'is_result_published',
        'is_exam_center_uploaded',
    ];

    public function getNoticeUrlAttribute()
    {
        return $this->notice ? Storage::url($this->notice) : null;
    }

    public function getIsApplicationOpenAttribute()
    {
        $rawStartDate = $this->getRawOriginal('intake_started_at');
        $rawEndDate = $this->getRawOriginal('intake_ended_at');
        $today = now()->toDateString();
        if (! is_null($rawStartDate)) {
            // If both start and end dates are set, check if today is between them
            if (! is_null($rawEndDate)) {
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
        if (! is_null($rawApplicationStartDate)) {
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

        return ! is_null($rawAdmitCardPublishedAt);
    }

    public function getIsExamCenterUploadedAttribute()
    {
        $rawExamCenterUploadedAt = $this->getRawOriginal('exam_center_uploaded_at');

        return ! is_null($rawExamCenterUploadedAt);
    }

    public function getIsResultPublishedAttribute()
    {
        $rawResultPublishedAt = $this->getRawOriginal('result_published_at');

        return ! is_null($rawResultPublishedAt);
    }

    // Relationship with Batch
    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }
}

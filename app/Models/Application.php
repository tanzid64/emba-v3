<?php

namespace App\Models;

use App\Casts\DateFormatCast;
use App\Enums\ApplicationStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentStatusEnum;
use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Application extends Model
{
    /** @use HasFactory<ApplicationFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'status' => ApplicationStatusEnum::class,
        'payment_status' => PaymentStatusEnum::class,
        'payment_method' => PaymentMethodEnum::class,
        'applied_at' => DateFormatCast::class,
    ];

    protected $appends = ['is_applied'];

    public function getIsAppliedAttribute(): bool
    {
        $raw = $this->getRawOriginal('applied_at');

        return ! is_null($raw);
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /**
     * Generate a unique application number for the given batch.
     * Format: {BATCH_CODE}-{6-digit zero-padded random}.
     */
    public static function generateApplicationNumber(Batch $batch): string
    {
        do {
            $candidate = sprintf('%s-%06d', $batch->code, random_int(1, 999_999));
        } while (static::where('application_number', $candidate)->exists());

        return $candidate;
    }

    /**
     * Find or create a draft application for the given applicant + current batch.
     */
    public static function draftFor(Applicant $applicant): self
    {
        $applicant->loadMissing('batch');

        return static::firstOrCreate(
            [
                'applicant_id' => $applicant->id,
                'batch_id' => $applicant->batch_id,
            ],
            [
                'application_number' => static::generateApplicationNumber($applicant->batch),
                'status' => ApplicationStatusEnum::PENDING,
                'payment_status' => PaymentStatusEnum::UNPAID,
            ],
        );
    }

    /**
     * Submit this application — sets applied_at and moves status to awaiting payment.
     */
    public function submit(): self
    {
        if ($this->is_applied) {
            return $this;
        }

        $this->fill([
            'applied_at' => now(),
            'status' => ApplicationStatusEnum::AWAITING_PAYMENT,
        ])->save();

        return $this;
    }
}

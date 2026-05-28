<?php

namespace App\Models;

use App\Casts\DateFormatCast;
use App\Enums\ApplicationStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentStatusEnum;
use App\Services\AdmissionNumberingService;
use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

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
        'paid_at' => DateFormatCast::class,
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

    public function examCenter(): BelongsTo
    {
        return $this->belongsTo(ExamCenter::class);
    }

    public function vivaBoard(): BelongsTo
    {
        return $this->belongsTo(VivaBoard::class);
    }

    /**
     * Find or create a draft application for the given applicant + current batch.
     * Drafts have no application_number — one is assigned on submit().
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
                'status' => ApplicationStatusEnum::PENDING,
                'payment_status' => PaymentStatusEnum::UNPAID,
            ],
        );
    }

    /**
     * Submit this application — assigns the sequential application_number,
     * sets applied_at, and moves status to AWAITING_PAYMENT.
     */
    public function submit(): self
    {
        if ($this->is_applied) {
            return $this;
        }

        DB::transaction(function (): void {
            $locked = self::where('id', $this->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Another request finalised this application while we were waiting on the lock.
            // Resync this in-memory instance from the locked row and bail out.
            if ($locked->getRawOriginal('applied_at') !== null) {
                $this->setRawAttributes($locked->getAttributes(), sync: true);

                return;
            }

            $this->loadMissing('batch');

            if ($this->application_number === null) {
                $this->application_number = app(AdmissionNumberingService::class)
                    ->nextApplicationNumber($this->batch);
            }

            $this->fill([
                'applied_at' => now(),
                'status' => ApplicationStatusEnum::AWAITING_PAYMENT,
            ])->save();
        });

        return $this;
    }
}

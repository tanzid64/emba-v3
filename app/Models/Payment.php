<?php

namespace App\Models;

use App\Casts\DateFormatCast;
use App\Enums\PaymentActorEnum;
use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'actor_table' => PaymentActorEnum::class,
        'payment_method' => PaymentMethodEnum::class,
        'status' => PaymentStatusEnum::class,
        'amount' => 'decimal:2',
        'gateway_response' => 'array',
        'metadata' => 'array',
        'paid_at' => DateFormatCast::class,
        'created_at' => DateFormatCast::class,
        'updated_at' => DateFormatCast::class,
    ];

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /**
     * Resolve the model this payment is for (Application, Enrollment, etc.)
     * based on the polymorphic actor_table / actor_id pair.
     */
    public function actor(): ?Model
    {
        $class = match ($this->actor_table) {
            PaymentActorEnum::APPLICATION => Application::class,
            default => null,
        };

        if (! $class || ! $this->actor_id) {
            return null;
        }

        return $class::find($this->actor_id);
    }
}

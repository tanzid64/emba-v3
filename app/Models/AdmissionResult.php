<?php

namespace App\Models;

use App\Enums\ResultStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionResult extends Model
{
    protected $guarded = [];

    protected $casts = [
        'merit_position' => 'integer',
        'mcq_marks' => 'decimal:2',
        'written_marks' => 'decimal:2',
        'viva_marks' => 'decimal:2',
        'schooling_marks' => 'decimal:2',
        'experience_marks' => 'decimal:2',
        'total_marks' => 'decimal:2',
        'is_adjusted' => 'boolean',
        'status' => ResultStatusEnum::class,
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }
}

<?php

namespace App\Models;

use App\Casts\DateFormatCast;
use App\Enum\BatchStatusEnum;
use Database\Factories\BatchFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Batch extends Model
{
    /** @use HasFactory<BatchFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'status' => BatchStatusEnum::class,
        'admission_year' => 'integer',
        'created_at' => DateFormatCast::class,
        'updated_at' => DateFormatCast::class,
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', BatchStatusEnum::OPEN);
    }

    public function applicants(): HasMany
    {
        return $this->hasMany(Applicant::class);
    }

    public function profiles(): HasMany
    {
        return $this->hasMany(ApplicantProfile::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function admissionSetting(): HasOne
    {
        return $this->hasOne(AdmissionSetting::class);
    }

    public function vivaBoards(): HasMany
    {
        return $this->hasMany(VivaBoard::class);
    }
}

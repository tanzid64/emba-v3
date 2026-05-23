<?php

namespace App\Models;

use App\Casts\DateFormatCast;
use App\Enum\BatchStatusEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
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

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}

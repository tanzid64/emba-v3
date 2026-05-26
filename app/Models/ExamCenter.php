<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamCenter extends Model
{
    protected $guarded = [];

    protected $casts = [
        'capacity' => 'integer',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}

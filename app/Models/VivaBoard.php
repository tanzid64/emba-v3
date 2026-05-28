<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VivaBoard extends Model
{
    protected $guarded = [];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}

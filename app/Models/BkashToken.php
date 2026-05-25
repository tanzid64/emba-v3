<?php

namespace App\Models;

use App\Casts\DateFormatCast;
use Illuminate\Database\Eloquent\Model;

class BkashToken extends Model
{
    protected $guarded = [];

    protected $casts = [
        'sandbox_mode' => 'boolean',
        'created_at' => DateFormatCast::class,
        'updated_at' => DateFormatCast::class,
    ];
}

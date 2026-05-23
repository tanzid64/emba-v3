<?php

namespace App\Models;

use App\Casts\DateFormatCast;
use App\Enum\BatchStatusEnum;
use Illuminate\Database\Eloquent\Model;

class Batch extends Model
{
    protected $guarded = [];
    protected $casts = [
        'status' => BatchStatusEnum::class,
        'admission_year' => 'integer',
        'created_at' => DateFormatCast::class,
        'updated_at' => DateFormatCast::class,
    ];

    // --------------------------------
    // | Relationships |
    // --------------------------------
}

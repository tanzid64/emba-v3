<?php

namespace App\Models;

use App\Enums\DegreeType;
use Illuminate\Database\Eloquent\Model;

class EducationHistory extends Model
{
    protected $guarded = [];

    protected $casts = [
        'type' => DegreeType::class,
    ];

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }
}

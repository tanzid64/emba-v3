<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpHistory extends Model
{
    protected $guarded = [];

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class District extends Model
{
    protected $guarded = [];

    public function upazilas()
    {
        return $this->hasMany(Upazila::class);
    }
}

<?php

namespace App\Models;

use App\Enums\AddressTypeEnum;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    protected $guarded = [];
    protected $casts = [
        'type' => AddressTypeEnum::class,
    ];
    
    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }
}

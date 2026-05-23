<?php

namespace App\Models;

use App\Casts\DateFormatCast;
use App\Enums\BloodGroup;
use App\Enums\GenderEnum;
use App\Enums\MaritalStatus;
use App\Enums\ReligionEnum;
use Illuminate\Database\Eloquent\Model;

class ApplicantProfile extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date_of_birth' => 'date',
        'tot_year_of_schooling' => 'decimal:2',
        'tot_year_of_exp' => 'decimal:2',
        'gender' => GenderEnum::class,
        'blood_group' => BloodGroup::class,
        'religion' => ReligionEnum::class,
        'marital_status' => MaritalStatus::class,
        'created_at' => DateFormatCast::class,
        'updated_at' => DateFormatCast::class,
    ];

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }
}

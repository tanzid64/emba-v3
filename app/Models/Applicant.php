<?php

namespace App\Models;

use App\Casts\DateFormatCast;
use App\Notifications\ApplicantResetPassword;
use App\Notifications\ApplicantVerifyEmail;
use Database\Factories\ApplicantFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['batch_id', 'email', 'phone_number', 'password'])]
#[Hidden(['password', 'remember_token'])]
class Applicant extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<ApplicantFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => DateFormatCast::class,
            'password' => 'hashed',
            'created_at' => DateFormatCast::class,
            'updated_at' => DateFormatCast::class,
        ];
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new ApplicantVerifyEmail);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ApplicantResetPassword($token));
    }

    public function profile()
    {
        return $this->hasOne(ApplicantProfile::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function expHistories()
    {
        return $this->hasMany(ExpHistory::class);
    }

    public function educationHistories()
    {
        return $this->hasMany(EducationHistory::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}

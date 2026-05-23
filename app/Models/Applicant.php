<?php

namespace App\Models;

use App\Notifications\ApplicantResetPassword;
use App\Notifications\ApplicantVerifyEmail;
use Database\Factories\ApplicantFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['email', 'phone_number', 'password'])]
#[Hidden(['password', 'remember_token'])]
class Applicant extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<ApplicantFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new ApplicantVerifyEmail);
    }

    public function sendPasswordResetNotification(string $token): void
    {
        $this->notify(new ApplicantResetPassword($token));
    }
}

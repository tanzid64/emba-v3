<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;

class ApplicantResetPassword extends ResetPassword
{
    protected function resetUrl(mixed $notifiable): string
    {
        if (static::$createUrlCallback) {
            return call_user_func(static::$createUrlCallback, $notifiable, $this->token);
        }

        return url(route('applicant.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
    }
}

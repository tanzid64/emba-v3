<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class AdminResetPassword extends ResetPassword
{
    public function toMail(mixed $notifiable): MailMessage
    {
        $expire = (int) config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject(__('Reset your password · :app', ['app' => config('app.name')]))
            ->greeting(__('Password reset request'))
            ->line(__('We received a request to reset the password for your :app admin account.', ['app' => config('app.name')]))
            ->action(__('Reset password'), $this->resetUrl($notifiable))
            ->line(__('This password reset link expires in :count minutes.', ['count' => $expire]))
            ->line(__('If you did not request a password reset, no further action is required.'))
            ->salutation(__('Best wishes,')."  \n".config('app.name'));
    }
}

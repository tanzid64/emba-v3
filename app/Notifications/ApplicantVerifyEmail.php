<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class ApplicantVerifyEmail extends VerifyEmail
{
    public function toMail(mixed $notifiable): MailMessage
    {
        $expire = (int) Config::get('auth.verification.expire', 60);

        return (new MailMessage)
            ->subject(__('Verify your email · :app', ['app' => config('app.name')]))
            ->greeting(__('Welcome to :app', ['app' => config('app.name')]))
            ->line(__('Thanks for registering. Please confirm your email address to activate your applicant account and continue your admission application.'))
            ->action(__('Verify email address'), $this->verificationUrl($notifiable))
            ->line(__('This verification link expires in :count minutes.', ['count' => $expire]))
            ->line(__('If you did not create an account, no further action is required.'))
            ->salutation(__('Best wishes,')."  \n".config('app.name'));
    }

    protected function verificationUrl(mixed $notifiable): string
    {
        if (static::$createUrlCallback) {
            return call_user_func(static::$createUrlCallback, $notifiable);
        }

        return URL::temporarySignedRoute(
            'applicant.verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }
}

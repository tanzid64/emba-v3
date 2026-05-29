<?php

use App\Enum\BatchStatusEnum;
use App\Models\Applicant;
use App\Models\Batch;
use App\Models\User;
use App\Notifications\AdminResetPassword;
use App\Notifications\ApplicantResetPassword;
use App\Notifications\ApplicantVerifyEmail;
use Illuminate\Mail\Markdown;

function makeApplicantForEmail(): Applicant
{
    $batch = Batch::create([
        'name' => 'EMBA Mail '.uniqid(),
        'code' => 'EMBA-ML-'.strtoupper(substr(uniqid(), -4)),
        'admission_year' => 2026,
        'status' => BatchStatusEnum::OPEN,
    ]);

    return Applicant::factory()->create(['batch_id' => $batch->id]);
}

it('brands the applicant verification email and renders the logo + brand colour', function () {
    $applicant = makeApplicantForEmail();

    $mail = (new ApplicantVerifyEmail)->toMail($applicant);

    expect($mail->subject)->toContain('Verify your email')
        ->and($mail->actionUrl)->toContain('applicant/email/verify')
        ->and($mail->actionUrl)->toContain('signature=');

    // Render through the published, branded markdown layout.
    $html = (string) app(Markdown::class)->render('notifications::email', $mail->data());

    expect($html)->toContain('assets/logo/logo.jpg')  // branded header logo
        ->and($html)->toContain('#2f1b72');           // brand-colour action button from the theme
});

it('brands the applicant password reset email and links to the applicant reset route', function () {
    $applicant = makeApplicantForEmail();

    $mail = (new ApplicantResetPassword('applicant-token-123'))->toMail($applicant);

    expect($mail->subject)->toContain('Reset your password')
        ->and($mail->actionUrl)->toContain(
            route('applicant.password.reset', ['token' => 'applicant-token-123', 'email' => $applicant->email], absolute: false)
        );
});

it('brands the admin password reset email and links to the admin reset route', function () {
    $user = User::factory()->create();

    $mail = (new AdminResetPassword('admin-token-123'))->toMail($user);

    expect($mail->subject)->toContain('Reset your password')
        ->and($mail->actionUrl)->toContain(
            route('password.reset', ['token' => 'admin-token-123', 'email' => $user->email], absolute: false)
        );
});

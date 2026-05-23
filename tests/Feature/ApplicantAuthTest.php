<?php

use App\Models\Applicant;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

// Registration
test('applicant can view register page', function () {
    $this->get(route('applicant.register'))->assertOk();
});

test('applicant can register', function () {
    Notification::fake();

    $this->post(route('applicant.register.store'), [
        'email' => 'applicant@example.com',
        'phone_number' => '+1234567890',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ])->assertRedirect(route('applicant.verification.notice'));

    $this->assertAuthenticatedAs(Applicant::first(), 'applicant');
    Notification::assertSentTo(Applicant::first(), \App\Notifications\ApplicantVerifyEmail::class);
});

test('applicant cannot register with duplicate email', function () {
    Applicant::factory()->create(['email' => 'taken@example.com']);

    $this->post(route('applicant.register.store'), [
        'email' => 'taken@example.com',
        'phone_number' => '+1234567890',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ])->assertSessionHasErrors('email');
});

// Login
test('applicant can view login page', function () {
    $this->get(route('applicant.login'))->assertOk();
});

test('applicant can login with correct credentials', function () {
    $applicant = Applicant::factory()->create();

    $this->post(route('applicant.login.store'), [
        'email' => $applicant->email,
        'password' => 'password',
    ])->assertRedirect(route('applicant.dashboard'));

    $this->assertAuthenticatedAs($applicant, 'applicant');
});

test('applicant cannot login with wrong password', function () {
    $applicant = Applicant::factory()->create();

    $this->post(route('applicant.login.store'), [
        'email' => $applicant->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');
});

test('applicant can logout', function () {
    $applicant = Applicant::factory()->create();

    $this->actingAs($applicant, 'applicant')
        ->post(route('applicant.logout'))
        ->assertRedirect(route('applicant.login'));

    $this->assertGuest('applicant');
});

// Password reset
test('applicant can request password reset link', function () {
    Notification::fake();
    $applicant = Applicant::factory()->create();

    $this->post(route('applicant.password.email'), ['email' => $applicant->email])
        ->assertSessionHas('status');

    Notification::assertSentTo($applicant, \App\Notifications\ApplicantResetPassword::class);
});

test('applicant can reset password', function () {
    $applicant = Applicant::factory()->create();
    $token = Password::broker('applicants')->createToken($applicant);

    $this->post(route('applicant.password.update'), [
        'token' => $token,
        'email' => $applicant->email,
        'password' => 'NewPassword1!',
        'password_confirmation' => 'NewPassword1!',
    ])->assertRedirect(route('applicant.login'));
});

// Email verification
test('unauthenticated applicant is redirected to login from verification notice', function () {
    $this->get(route('applicant.verification.notice'))
        ->assertRedirect(route('applicant.login'));
});

test('verified applicant is redirected from verification notice', function () {
    $applicant = Applicant::factory()->create();

    $this->actingAs($applicant, 'applicant')
        ->get(route('applicant.verification.notice'))
        ->assertRedirect(route('applicant.dashboard'));
});

test('unverified applicant sees verification notice', function () {
    $applicant = Applicant::factory()->unverified()->create();

    $this->actingAs($applicant, 'applicant')
        ->get(route('applicant.verification.notice'))
        ->assertOk();
});

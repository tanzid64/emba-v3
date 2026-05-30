<?php

use App\Http\Controllers\Applicant\Auth\EmailVerificationController;
use App\Http\Controllers\Applicant\Auth\ForgotPasswordController;
use App\Http\Controllers\Applicant\Auth\LoginController;
use App\Http\Controllers\Applicant\Auth\RegisterController;
use App\Http\Controllers\Applicant\Auth\ResetPasswordController;
use App\Http\Controllers\Payment\BkashController;
use Illuminate\Support\Facades\Route;

Route::prefix('applicant')->name('applicant.')->group(function () {
    // Guest-only routes
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');

    Route::get('register', [RegisterController::class, 'show'])->name('register');
    Route::post('register', [RegisterController::class, 'store'])->name('register.store');

    Route::get('forgot-password', [ForgotPasswordController::class, 'show'])->name('password.request');
    Route::post('forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');

    Route::get('reset-password/{token}', [ResetPasswordController::class, 'show'])->name('password.reset');
    Route::post('reset-password', [ResetPasswordController::class, 'store'])->name('password.update');

    // Authenticated routes
    Route::middleware('auth:applicant')->group(function () {
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

        Route::get('email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
        Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware('signed')
            ->name('verification.verify');
        Route::post('email/verification-notification', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:6,1')
            ->name('verification.send');

        Route::middleware('verified:applicant.verification.notice')->group(function () {
            Route::livewire('dashboard', 'pages::applicant.dashboard')->name('dashboard');
            Route::livewire('profile', 'pages::applicant.profile')->name('profile');
            Route::livewire('application', 'pages::applicant.application')->name('application');
            Route::livewire('payments', 'pages::applicant.payments')->name('payments');
            Route::livewire('admit-card', 'pages::applicant.admit-card')->name('admit-card');
            Route::livewire('viva-admit-card', 'pages::applicant.viva-admit-card')->name('viva-admit-card');
            Route::livewire('result', 'pages::applicant.result')->name('result');

            Route::prefix('payment/bkash')->name('payment.bkash.')->group(function () {
                Route::post('initiate', [BkashController::class, 'initiate'])->name('initiate');
                Route::get('callback', [BkashController::class, 'callback'])->name('callback');
            });
        });
    });
});

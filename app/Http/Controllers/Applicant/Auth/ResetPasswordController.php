<?php

namespace App\Http\Controllers\Applicant\Auth;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class ResetPasswordController extends Controller
{
    public function show(Request $request, string $token): View
    {
        return view('pages::applicant.auth.reset-password', [
            'token' => $token,
            'email' => $request->email,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', PasswordRule::default(), 'confirmed'],
        ]);

        $status = Password::broker('applicants')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Applicant $applicant, string $password): void {
                $applicant->forceFill(['password' => $password])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('applicant.login')->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }
}

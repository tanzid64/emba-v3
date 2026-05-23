<?php

namespace App\Http\Controllers\Applicant\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (auth('applicant')->check()) {
            return redirect()->route('applicant.dashboard');
        }

        return view('pages::applicant.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! auth('applicant')->attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => __('These credentials do not match our records.')])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('applicant.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        auth('applicant')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('applicant.login');
    }
}

<?php

namespace App\Http\Controllers\Applicant\Auth;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function notice(): View|RedirectResponse
    {
        if (auth('applicant')->user()->hasVerifiedEmail()) {
            return redirect()->route('applicant.dashboard');
        }

        return view('pages::applicant.auth.verify-email');
    }

    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        /** @var Applicant $applicant */
        $applicant = Applicant::findOrFail($id);

        abort_unless(
            auth('applicant')->id() === $applicant->id
                && hash_equals($hash, sha1($applicant->getEmailForVerification()))
                && $request->hasValidSignature(),
            403
        );

        if (! $applicant->hasVerifiedEmail()) {
            $applicant->markEmailAsVerified();
        }

        return redirect()->route('applicant.dashboard')->with('status', 'email-verified');
    }

    public function resend(Request $request): RedirectResponse
    {
        if (auth('applicant')->user()->hasVerifiedEmail()) {
            return redirect()->route('applicant.dashboard');
        }

        auth('applicant')->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}

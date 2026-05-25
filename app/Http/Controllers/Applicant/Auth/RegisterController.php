<?php

namespace App\Http\Controllers\Applicant\Auth;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Batch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (auth('applicant')->check()) {
            return redirect()->route('applicant.dashboard');
        }

        $activeBatch = Batch::active()->first();

        return view('pages::applicant.auth.register', [
            'hasActiveBatch' => $activeBatch !== null,
            'activeBatch' => $activeBatch,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(Applicant::class)],
            'phone_number' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ]);

        $activeBatch = Batch::active()->first();

        $applicant = Applicant::create([
            ...$validated,
            'batch_id' => $activeBatch?->id,
        ]);

        if ($activeBatch) {
            Application::draftFor($applicant->setRelation('batch', $activeBatch));
        }

        auth('applicant')->login($applicant);

        $applicant->sendEmailVerificationNotification();

        return redirect()->route('applicant.verification.notice');
    }
}

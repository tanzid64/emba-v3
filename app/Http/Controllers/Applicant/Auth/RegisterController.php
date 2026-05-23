<?php

namespace App\Http\Controllers\Applicant\Auth;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
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

        $hasActiveBatch = Batch::active()->exists();

        return view('pages::applicant.auth.register', compact('hasActiveBatch'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(Applicant::class)],
            'phone_number' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ]);

        $applicant = Applicant::create($validated);

        $activeBatch = Batch::active()->first();
        if ($activeBatch) {
            $applicant->applications()->create(['batch_id' => $activeBatch->id]);
        }

        auth('applicant')->login($applicant);

        $applicant->sendEmailVerificationNotification();

        return redirect()->route('applicant.verification.notice');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Payment;

class VerificationController extends Controller
{
    /**
     * Render the public application verification page reached via the QR
     * code printed on the application form PDF. The route is `signed` so
     * only the URL embedded in a legitimate PDF will resolve — tampered
     * or hand-typed URLs return 403.
     */
    public function application(string $appNo)
    {
        $application = Application::with([
            'batch',
            'applicant.profile',
        ])->where('application_number', $appNo)->firstOrFail();

        return view('pages.verify.application', compact('application'));
    }

    /**
     * Render the public payment-receipt verification page reached via the
     * QR code printed on the receipt PDF. Same `signed` middleware story
     * as the application verifier.
     */
    public function payment(string $paymentNo)
    {
        $payment = Payment::with([
            'batch',
            'applicant.profile',
        ])->where('payment_number', $paymentNo)->firstOrFail();

        return view('pages.verify.payment', compact('payment'));
    }
}

<?php

namespace App\Http\Controllers\Payment;

use App\Enums\ApplicationStatusEnum;
use App\Enums\PaymentActorEnum;
use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentStatusEnum;
use App\Exceptions\BkashException;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Payment;
use App\Services\AdmissionNumberingService;
use App\Services\BkashService;
use App\Services\ResultGenerationService;
use App\Support\Toast;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BkashController extends Controller
{
    public function __construct(private readonly BkashService $bkash) {}

    /**
     * POST /applicant/payment/bkash/initiate
     *
     * Creates a PENDING Payment row, calls bKash Create Payment API, then
     * redirects the applicant's browser to the bKash-hosted checkout page.
     *
     * Only handles application-fee payments for now — extend the match in
     * the callback handler when other actors (enrollment, admission) are
     * wired up.
     */
    public function initiate(Request $request): RedirectResponse
    {
        $applicant = Auth::guard('applicant')->user();
        $applicant->loadMissing('batch.admissionSetting');

        $application = $applicant->applications()
            ->where('batch_id', $applicant->batch_id)
            ->first();

        if (! $application || ! $application->is_applied) {
            Toast::error(__('Submit your application before paying the fee.'));

            return back();
        }

        if (in_array($application->payment_status, [PaymentStatusEnum::PAID, PaymentStatusEnum::COMPLETED], true)) {
            Toast::info(__('Your application fee has already been paid.'));

            return back();
        }

        $admissionSetting = $applicant->batch?->admissionSetting;

        if (! $admissionSetting || ! $admissionSetting->application_fee) {
            Toast::error(__('Admission settings are not configured. Please contact support.'));

            return back();
        }

        if ($this->paymentDeadlinePassed($admissionSetting->getRawOriginal('application_payment_ended_at'))) {
            Toast::error(__('The payment deadline has passed. The admission process is closed.'));

            return back();
        }

        $amount = (float) $admissionSetting->application_fee;
        $invoiceNumber = 'APP-'.$application->application_number.'-'.time();
        $paymentNumber = 'PMT-'.strtoupper(Str::random(10)).'-'.time();

        Payment::where('applicant_id', $applicant->id)
            ->where('batch_id', $applicant->batch_id)
            ->where('actor_table', PaymentActorEnum::APPLICATION)
            ->where('actor_id', $application->id)
            ->where('status', PaymentStatusEnum::PENDING)
            ->update(['status' => PaymentStatusEnum::FAILED]);

        $payment = Payment::create([
            'payment_number' => $paymentNumber,
            'batch_id' => $applicant->batch_id,
            'applicant_id' => $applicant->id,
            'actor_table' => PaymentActorEnum::APPLICATION,
            'actor_id' => $application->id,
            'payment_method' => PaymentMethodEnum::BKASH,
            'amount' => $amount,
            'status' => PaymentStatusEnum::PENDING,
            'metadata' => [
                'invoice' => $invoiceNumber,
                'application_number' => $application->application_number,
            ],
        ]);

        try {
            $response = $this->bkash->createPayment(
                amount: number_format($amount, 2, '.', ''),
                invoiceNumber: $invoiceNumber,
                callbackUrl: config('bkash.callback_url') ?: route('applicant.payment.bkash.callback'),
                payerReference: $application->application_number,
            );
        } catch (BkashException $e) {
            Log::error('[bKash] Create payment failed', [
                'applicant_id' => $applicant->id,
                'application_id' => $application->id,
                'invoice' => $invoiceNumber,
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);

            $payment->update([
                'status' => PaymentStatusEnum::FAILED,
                'gateway_response' => $e->getContext(),
            ]);

            Toast::error(__('Payment initiation failed. Please try again.'));

            return back();
        }

        $payment->update([
            'gateway_payment_id' => $response['paymentID'] ?? null,
            'gateway_response' => $response,
        ]);

        return redirect()->away($response['bkashURL']);
    }

    /**
     * GET /applicant/payment/bkash/callback
     *
     * Browser redirect from bKash after the applicant completes (or abandons)
     * the payment on the bKash-hosted page.
     *
     * Security: never trust the `status` query param alone. We always call
     * Execute Payment and verify transactionStatus === "Completed".
     */
    public function callback(Request $request): RedirectResponse
    {
        $paymentId = $request->query('paymentID');
        $bkashStatus = $request->query('status');

        if (! $paymentId) {
            Toast::error(__('Invalid payment callback received.'));

            return redirect()->route('applicant.application');
        }

        $payment = Payment::where('gateway_payment_id', $paymentId)
            ->with('applicant')
            ->first();

        if (! $payment) {
            Log::warning('[bKash] Callback for unknown paymentID', [
                'paymentID' => $paymentId,
                'status' => $bkashStatus,
            ]);

            Toast::error(__('Payment record not found. Contact support if you were charged.'));

            return redirect()->route('applicant.application');
        }

        if ($payment->status === PaymentStatusEnum::COMPLETED) {
            Toast::info(__('Payment already completed. Transaction ID: :trx', ['trx' => $payment->gateway_trx_id]));

            return redirect()->route('applicant.application');
        }

        if (in_array($bkashStatus, ['failure', 'cancel'], true)) {
            $payment->update(['status' => PaymentStatusEnum::FAILED]);

            Toast::error($bkashStatus === 'cancel'
                ? __('Payment was cancelled.')
                : __('Payment failed. Please try again.'));

            return redirect()->route('applicant.application');
        }

        try {
            $executeResponse = $this->bkash->executePayment($paymentId);
        } catch (BkashException $e) {
            Log::error('[bKash] Execute failed, falling back to Query', [
                'paymentID' => $paymentId,
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);

            try {
                $executeResponse = $this->bkash->queryPayment($paymentId);
            } catch (\Throwable $queryError) {
                Log::critical('[bKash] Both Execute and Query failed', [
                    'paymentID' => $paymentId,
                    'query_error' => $queryError->getMessage(),
                ]);

                $payment->update(['status' => PaymentStatusEnum::FAILED]);

                Toast::error(__('Payment verification failed. Contact support with reference: :ref', ['ref' => $paymentId]));

                return redirect()->route('applicant.application');
            }
        }

        if (($executeResponse['transactionStatus'] ?? '') !== 'Completed') {
            $payment->update([
                'status' => PaymentStatusEnum::FAILED,
                'gateway_response' => $executeResponse,
            ]);

            Toast::error(__('Payment was not completed. Please try again or contact support.'));

            return redirect()->route('applicant.application');
        }

        DB::transaction(function () use ($payment, $executeResponse): void {
            $locked = Payment::where('id', $payment->id)
                ->where('status', PaymentStatusEnum::PENDING)
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                return;
            }

            $locked->update([
                'gateway_payment_id' => $executeResponse['paymentID'] ?? $locked->gateway_payment_id,
                'gateway_trx_id' => $executeResponse['trxID'] ?? null,
                'status' => PaymentStatusEnum::COMPLETED,
                'paid_at' => now(),
                'gateway_response' => $executeResponse,
            ]);

            match ($locked->actor_table) {
                PaymentActorEnum::APPLICATION => $this->completeApplicationPayment($locked, $executeResponse),
                default => null,
            };
        });

        Log::info('[bKash] Payment completed', [
            'paymentID' => $executeResponse['paymentID'] ?? null,
            'trxID' => $executeResponse['trxID'] ?? null,
            'amount' => $executeResponse['amount'] ?? null,
            'customer' => $executeResponse['customerMsisdn'] ?? null,
        ]);

        Toast::success(__('Payment successful! Transaction ID: :trx', ['trx' => $executeResponse['trxID'] ?? '']));

        return redirect()->route('applicant.application');
    }

    private function paymentDeadlinePassed(?string $rawDeadline): bool
    {
        if (! $rawDeadline) {
            return false;
        }

        return now()->greaterThan(Carbon::parse($rawDeadline)->endOfDay());
    }

    private function completeApplicationPayment(Payment $payment, array $response): void
    {
        // Caller (BkashController::callback) wraps this in DB::transaction and locks the
        // Payment PENDING row, so only one execution reaches here per payment — no
        // separate Application row lock is required.
        $application = Application::find($payment->actor_id);

        if (! $application) {
            return;
        }

        if ($application->roll_number === null) {
            $application->roll_number = app(AdmissionNumberingService::class)
                ->nextRollNumber($application);
        }

        $application->fill([
            'status' => ApplicationStatusEnum::COMPLETED,
            'payment_status' => PaymentStatusEnum::PAID,
            'payment_method' => PaymentMethodEnum::BKASH,
            'amount' => $payment->amount,
            'payment_id' => $response['paymentID'] ?? null,
            'trx_id' => $response['trxID'] ?? null,
            'paid_at' => now(),
        ])->save();

        // Snapshot profile-derived marks (schooling + experience) so that any
        // later profile edits don't retroactively change the awarded values.
        // Idempotent per (batch_id, applicant_id), so replays are safe.
        try {
            app(ResultGenerationService::class)->generateForApplication($application);
        } catch (\Throwable $e) {
            Log::warning('[Result] Initial snapshot failed for application', [
                'application_id' => $application->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

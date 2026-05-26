<?php

use App\Enum\BatchStatusEnum;
use App\Enums\ApplicationStatusEnum;
use App\Enums\PaymentActorEnum;
use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentStatusEnum;
use App\Http\Controllers\Payment\BkashController;
use App\Models\AdmissionSetting;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Batch;
use App\Models\Payment;
use App\Services\BkashService;

function makeBatchAndApp(array $settingOverrides = []): array
{
    $batch = Batch::create([
        'name' => 'EMBA Pay '.uniqid(),
        'code' => 'EMBA-PY-'.strtoupper(substr(uniqid(), -4)),
        'admission_year' => 2026,
        'status' => BatchStatusEnum::OPEN,
    ]);

    AdmissionSetting::create(array_merge([
        'batch_id' => $batch->id,
        'application_number_start_from' => 1000,
        'roll_number_start_from' => 1000,
    ], $settingOverrides));

    $applicant = Applicant::factory()->create(['batch_id' => $batch->id]);

    $application = Application::create([
        'applicant_id' => $applicant->id,
        'batch_id' => $batch->id,
        'application_number' => $batch->code.'-1000',
        'status' => ApplicationStatusEnum::AWAITING_PAYMENT,
        'payment_status' => PaymentStatusEnum::UNPAID,
        'applied_at' => now(),
    ]);

    $payment = Payment::create([
        'payment_number' => 'PMT-'.uniqid(),
        'batch_id' => $batch->id,
        'applicant_id' => $applicant->id,
        'actor_table' => PaymentActorEnum::APPLICATION,
        'actor_id' => $application->id,
        'payment_method' => PaymentMethodEnum::BKASH,
        'amount' => 2500,
        'status' => PaymentStatusEnum::COMPLETED,
    ]);

    return compact('batch', 'applicant', 'application', 'payment');
}

function callCompleteApplicationPayment(Payment $payment, array $response): void
{
    $controller = new BkashController(app(BkashService::class));
    $ref = new ReflectionMethod($controller, 'completeApplicationPayment');
    $ref->setAccessible(true);
    $ref->invoke($controller, $payment, $response);
}

it('assigns a roll_number and marks application COMPLETED on payment success', function () {
    ['application' => $application, 'payment' => $payment, 'batch' => $batch] = makeBatchAndApp(
        ['roll_number_start_from' => 5000]
    );

    callCompleteApplicationPayment($payment, ['paymentID' => 'PAYID-1', 'trxID' => 'TRX-1']);

    $application->refresh();
    expect($application->roll_number)->toBe('5000');
    expect($application->status)->toBe(ApplicationStatusEnum::COMPLETED);
    expect($application->payment_status)->toBe(PaymentStatusEnum::PAID);
    expect($application->trx_id)->toBe('TRX-1');
});

it('does not reassign roll_number on a replayed payment completion', function () {
    ['application' => $application, 'payment' => $payment] = makeBatchAndApp();

    callCompleteApplicationPayment($payment, ['paymentID' => 'PAYID-1', 'trxID' => 'TRX-1']);
    $firstRoll = $application->fresh()->roll_number;

    callCompleteApplicationPayment($payment, ['paymentID' => 'PAYID-1', 'trxID' => 'TRX-1']);

    expect($application->fresh()->roll_number)->toBe($firstRoll);
});

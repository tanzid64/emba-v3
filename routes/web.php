<?php

use App\Http\Controllers\ExcelExportController;
use App\Http\Controllers\PDFController;
use App\Http\Controllers\VerificationController;
use App\Support\CurrentBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::livewire('batches', 'pages::admin.batches')->name('batches.index');
        Route::livewire('batches/create', 'pages::admin.batches.create')->name('batches.create');
        Route::livewire('applicants', 'pages::admin.applicants')->name('applicants.index');
        Route::livewire('confirmed-applicants', 'pages::admin.confirmed-applicants')->name('confirmed-applicants.index');
        Route::livewire('exam-centers', 'pages::admin.exam-centers')->name('exam-centers.index');
        Route::livewire('admit-cards', 'pages::admin.admit-cards')->name('admit-cards.index');
        Route::livewire('exam-results', 'pages::admin.exam-results')->name('exam-results.index');
        Route::livewire('applicants/{application}', 'pages::admin.applicant-show')->name('applicants.show');
        Route::livewire('applicants/{application}/edit', 'pages::admin.applicant-edit')->name('applicants.edit');
        Route::livewire('docs', 'pages::admin.docs')->name('docs');

        Route::post('current-batch', function (Request $request) {
            $data = $request->validate([
                'batch_id' => ['required', 'integer', 'exists:batches,id'],
            ]);

            CurrentBatch::set((int) $data['batch_id']);

            return redirect($request->input('_return_to') ?: url()->previous());
        })->name('current-batch.set');
    });
});

Route::group([
    'prefix' => 'pdf',
    'as' => 'pdf.',
    'middleware' => 'auth:applicant,web',
], function () {
    Route::get('application-form/{appNo}', [PDFController::class, 'generateApplicationFormPDF'])
        ->name('application-form');
    Route::get('payment-receipt/{paymentNo}', [PDFController::class, 'generatePaymentReceiptPDF'])
        ->name('payment-receipt');
    Route::get('admit-card/{appNo}', [PDFController::class, 'generateAdmitCardPDF'])
        ->name('admit-card');
});

// Admin-only batch/room reporting PDFs (attendance sheets, seat labels).
// The controller methods enforce admin-only access via ensureAdmin().
Route::middleware('auth')->prefix('pdf')->name('pdf.')->group(function () {
    Route::get('attendance-sheet/{centerId}', [PDFController::class, 'generateAttendanceSheet'])
        ->name('attendance-sheet')
        ->whereNumber('centerId');
    Route::get('attendance-sheet-all/{batchId}', [PDFController::class, 'generateAllAttendanceSheets'])
        ->name('attendance-sheet.all')
        ->whereNumber('batchId');
    Route::get('seat-labels/{batchId}', [PDFController::class, 'generateSeatLabels'])
        ->name('seat-labels')
        ->whereNumber('batchId');
    Route::get('exam-results/{batch}', [PDFController::class, 'generateExamResultsPDF'])
        ->name('exam-results');
});

// Admin-only Excel exports.
Route::middleware('auth')->prefix('excel')->name('excel.')->group(function () {
    Route::get('exam-results/{batch}', [ExcelExportController::class, 'examResults'])
        ->name('exam-results');
});

// Public, signed verification pages reached via the QR codes on the
// printed application form / payment receipt. `signed` middleware
// rejects any URL whose signature doesn't match — typing the URL by
// hand returns 403.
Route::middleware('signed')->name('verify.')->group(function () {
    Route::get('verify/application/{appNo}', [VerificationController::class, 'application'])
        ->name('application');
    Route::get('verify/payment/{paymentNo}', [VerificationController::class, 'payment'])
        ->name('payment');
});

require __DIR__.'/settings.php';

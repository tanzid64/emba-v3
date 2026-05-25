<?php

use App\Support\CurrentBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::livewire('batches', 'pages::admin.batches')->name('batches.index');
        Route::livewire('batches/create', 'pages::admin.batches.create')->name('batches.create');

        Route::post('current-batch', function (Request $request) {
            $data = $request->validate([
                'batch_id' => ['required', 'integer', 'exists:batches,id'],
            ]);

            CurrentBatch::set((int) $data['batch_id']);

            return redirect($request->input('_return_to') ?: url()->previous());
        })->name('current-batch.set');
    });
});

require __DIR__.'/settings.php';

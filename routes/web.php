<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::livewire('batches', 'pages::admin.batches')->name('batches.index');
        Route::livewire('batches/create', 'pages::admin.batches.create')->name('batches.create');
    });
});

require __DIR__.'/settings.php';

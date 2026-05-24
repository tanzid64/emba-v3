<?php

namespace App\Providers;

use App\Support\Toast;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;

class ExceptionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerGlobalExceptionHandler();
    }

    private function registerGlobalExceptionHandler(): void
    {
        app('livewire')->listen('exception', function ($component, $exception) {
            if ($exception instanceof ValidationException) {
                Toast::error('Validation error. Please check your data and try again.');

                return;
            }

            Toast::error($exception->getMessage() ?: 'An error occurred. Please try again.');
        });
    }
}

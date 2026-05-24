<?php

namespace App\Support;

use Livewire\Component;

class Toast
{
    public static function success(string $message, ?string $title = null): void
    {
        self::dispatch('success', $message, $title);
    }

    public static function error(string $message, ?string $title = null): void
    {
        self::dispatch('error', $message, $title);
    }

    public static function warning(string $message, ?string $title = null): void
    {
        self::dispatch('warning', $message, $title);
    }

    public static function info(string $message, ?string $title = null): void
    {
        self::dispatch('info', $message, $title);
    }

    public static function dispatch(string $variant, string $message, ?string $title = null): void
    {
        $payload = ['variant' => $variant, 'message' => $message];

        if ($title !== null) {
            $payload['title'] = $title;
        }

        $component = self::currentLivewireComponent();

        if ($component instanceof Component) {
            $component->dispatch('toast', ...$payload);

            return;
        }

        session()->flash('toast', $payload);
    }

    private static function currentLivewireComponent(): ?Component
    {
        if (! app()->bound('livewire')) {
            return null;
        }

        try {
            $current = app('livewire')->current();
        } catch (\Throwable) {
            return null;
        }

        return $current instanceof Component ? $current : null;
    }
}

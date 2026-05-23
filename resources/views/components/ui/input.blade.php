@props([
    'icon' => null,
    'clearable' => false,
    'type' => 'text',
    'placeholder' => '',
])

@php
    $base = 'block w-full rounded-lg border border-zinc-200 bg-white text-sm text-zinc-800 shadow-xs transition
 placeholder-zinc-400 focus:outline-none focus:border-zinc-400
 :border-white/20
 disabled:opacity-50 disabled:cursor-not-allowed';

    $padding = $icon
        ? ($clearable
            ? 'py-2 ps-9 pe-8'
            : 'py-2 ps-9 pe-3')
        : ($clearable
            ? 'py-2 ps-3 pe-8'
            : 'py-2 px-3');

    $inputClasses = trim(($attributes->get('class') ?? '') . ' ' . $base . ' ' . $padding);
@endphp

<div class="relative" x-data="{ val: '' }">
    @if ($icon)
        <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-zinc-400">
            <x-icon name="{{ 'lucide.' . $icon }}" class="size-4" />
        </div>
    @endif

    <input {{ $attributes->except('class')->merge(['type' => $type, 'placeholder' => $placeholder]) }} x-ref="input"
        @input="val = $el.value" class="{{ $inputClasses }}" />

    @if ($clearable)
        <button type="button" x-show="val !== ''" x-cloak
            @click="val = ''; $refs.input.value = ''; $refs.input.dispatchEvent(new Event('input')); $refs.input.focus()"
            class="absolute inset-y-0 end-0 flex items-center pe-2.5 text-zinc-400 hover:text-zinc-600 :text-zinc-300">
            <x-icon name="lucide.x" class="size-3.5" />
        </button>
    @endif
</div>

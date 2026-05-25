@props([
    'text' => '',
    'position' => 'top',
])

@php
    $transforms = [
        'top' => 'translate(-50%, -100%)',
        'bottom' => 'translate(-50%, 0)',
        'left' => 'translate(-100%, -50%)',
        'right' => 'translate(0, -50%)',
    ];
    $transform = $transforms[$position] ?? $transforms['top'];
@endphp

<span
    x-data="{
        open: false,
        x: 0,
        y: 0,
        gap: 8,
        position: '{{ $position }}',
        label: @js($text),
        show(event) {
            const rect = event.currentTarget.getBoundingClientRect();
            if (this.position === 'top') {
                this.x = rect.left + rect.width / 2;
                this.y = rect.top - this.gap;
            } else if (this.position === 'bottom') {
                this.x = rect.left + rect.width / 2;
                this.y = rect.bottom + this.gap;
            } else if (this.position === 'left') {
                this.x = rect.left - this.gap;
                this.y = rect.top + rect.height / 2;
            } else {
                this.x = rect.right + this.gap;
                this.y = rect.top + rect.height / 2;
            }
            this.open = true;
        },
    }"
    @mouseenter="show($event)"
    @mouseleave="open = false"
    @focusin="show($event)"
    @focusout="open = false"
    class="inline-flex"
>
    {{ $slot }}

    {{-- Teleport into <body> so the bubble escapes any overflow-hidden /
         overflow-auto ancestor (tables, cards, modals). --}}
    <template x-teleport="body">
        <span
            x-show="open && label"
            x-cloak
            x-text="label"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            role="tooltip"
            :style="`position: fixed; top: ${y}px; left: ${x}px; transform: {{ $transform }};`"
            class="pointer-events-none z-[9999] whitespace-nowrap rounded-md bg-brand px-2.5 py-1 text-xs font-semibold text-white shadow-lg"
        ></span>
    </template>
</span>

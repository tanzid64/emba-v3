@props([
    'name' => 'modal',
    'title' => '',
    'maxWidth' => 'md',
])

@php
    $widths = [
        'sm' => 'max-w-sm',
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl',
    ];
    $maxWidthClass = $widths[$maxWidth] ?? $widths['md'];
@endphp

<div x-data="{ open: false }" @open-modal.window="$event.detail.name === '{{ $name }}' && (open = true)"
    @close-modal.window="$event.detail.name === '{{ $name }}' && (open = false)">
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="open = false">
            {{-- Backdrop --}}
            <div x-show="open" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-black/40 backdrop-blur-[2px]" @click="open = false"></div>

            {{-- Panel --}}
            <div class="flex min-h-full items-center justify-center p-4">
                <div x-show="open" x-transition:enter="ease-out duration-250"
                    x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                    x-transition:leave="ease-in duration-150"
                    x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                    x-transition:leave-end="opacity-0 scale-95 translate-y-2"
                    class="relative w-full {{ $maxWidthClass }} rounded-2xl bg-white shadow-2xl">
                    @if ($title)
                        <div class="flex items-center justify-between border-b border-zinc-100 px-6 py-4">
                            <h2 class="text-base font-semibold text-zinc-900">{{ $title }}</h2>
                            <button type="button" @click="open = false"
                                class="rounded-lg p-1 text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-600 :bg-zinc-700 :text-zinc-200">
                                <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    @endif

                    <div class="{{ $title ? 'px-6 py-5' : 'p-6' }}">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

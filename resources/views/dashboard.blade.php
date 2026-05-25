<x-layouts::app :title="__('Dashboard')">
    @php
        $currentBatch = \App\Support\CurrentBatch::get();
        $batchStatusColor = match ($currentBatch?->status) {
            \App\Enum\BatchStatusEnum::OPEN => 'green',
            \App\Enum\BatchStatusEnum::DRAFT => 'yellow',
            \App\Enum\BatchStatusEnum::CLOSED => 'zinc',
            default => 'zinc',
        };
    @endphp

    <div class="flex h-full w-full flex-1 flex-col gap-6 p-6 rounded-xl">

        {{-- Dashboard header --}}
        <div class="flex items-start justify-between flex-wrap gap-3 pb-4 border-b border-zinc-100">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900">{{ __('Dashboard') }}</h1>
                @if ($currentBatch)
                    <p class="text-sm text-zinc-500 mt-1">
                        {{ __('Showing data for') }}
                        <span class="font-semibold text-zinc-800">{{ $currentBatch->name }}</span>
                        <span class="text-zinc-400">·</span>
                        <span class="font-mono text-zinc-600">{{ $currentBatch->code }}</span>
                    </p>
                @else
                    <p class="text-sm text-zinc-500 mt-1">{{ __('No batch selected — pick one from the sidebar.') }}</p>
                @endif
            </div>

            @if ($currentBatch)
                <div class="flex items-center gap-2">
                    <span class="text-xs text-zinc-500">
                        <x-lucide-calendar class="size-3.5 inline -mt-0.5" />
                        {{ __('Admission Year :year', ['year' => $currentBatch->admission_year]) }}
                    </span>
                    <x-ui.badge size="sm" :color="$batchStatusColor">
                        {{ ucfirst($currentBatch->status->value) }}
                    </x-ui.badge>
                </div>
            @endif
        </div>

        <livewire:pages::admin.quick-settings />

        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
            </div>
            <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
            </div>
            <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
            </div>
        </div>
        <div class="relative h-full flex-1 overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
            <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
        </div>
    </div>
</x-layouts::app>

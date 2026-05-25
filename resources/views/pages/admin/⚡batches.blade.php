<?php

use App\Enum\BatchStatusEnum;
use App\Models\Batch;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Admission Batch')]
#[Layout('layouts.app')]
class extends Component {
    public Collection $batches;

    public function mount(): void
    {
        $this->batches = Batch::query()
            ->withCount(['applicants', 'applications'])
            ->orderByDesc('admission_year')
            ->orderByDesc('id')
            ->get();
    }

    public function statusColor(?BatchStatusEnum $status): string
    {
        return match ($status) {
            BatchStatusEnum::OPEN => 'green',
            BatchStatusEnum::CLOSED => 'red',
            BatchStatusEnum::DRAFT => 'yellow',
            default => 'zinc',
        };
    }

    public function statusLabel(?BatchStatusEnum $status): string
    {
        return $status ? ucfirst($status->value) : '—';
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 lg:gap-6 lg:p-6">

    <div class="flex items-start justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-zinc-900">{{ __('Admission Batch') }}</h1>
            <p class="text-sm text-zinc-500 mt-1">{{ __('All admission cycles managed in the system.') }}</p>
        </div>
        <div class="flex items-center gap-3">
            <x-ui.badge size="sm" color="zinc">
                {{ trans_choice(':count batch|:count batches', $batches->count(), ['count' => $batches->count()]) }}
            </x-ui.badge>
            <x-ui.button variant="primary" icon="plus" :href="route('admin.batches.create')" wire:navigate>
                {{ __('Add new batch') }}
            </x-ui.button>
        </div>
    </div>

    <x-ui.table>
        <x-slot:columns>
            <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Batch') }}</th>
            <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Code') }}</th>
            <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Admission Year') }}</th>
            <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Status') }}</th>
            <th class="text-right font-semibold text-zinc-700 px-4 py-3">{{ __('Applicants') }}</th>
            <th class="text-right font-semibold text-zinc-700 px-4 py-3">{{ __('Applications') }}</th>
        </x-slot:columns>

        @forelse ($batches as $batch)
            <tr class="hover:bg-zinc-50/60 transition-colors">
                <td class="px-4 py-3 font-medium text-zinc-900">{{ $batch->name }}</td>
                <td class="px-4 py-3 font-mono text-zinc-700">{{ $batch->code }}</td>
                <td class="px-4 py-3 text-zinc-700">{{ $batch->admission_year }}</td>
                <td class="px-4 py-3">
                    <x-ui.badge :color="$this->statusColor($batch->status)" size="sm">
                        {{ $this->statusLabel($batch->status) }}
                    </x-ui.badge>
                </td>
                <td class="px-4 py-3 text-right text-zinc-700 tabular-nums">{{ number_format($batch->applicants_count) }}</td>
                <td class="px-4 py-3 text-right text-zinc-700 tabular-nums">{{ number_format($batch->applications_count) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="px-4 py-10 text-center text-zinc-500">
                    {{ __('No batches have been created yet.') }}
                </td>
            </tr>
        @endforelse
    </x-ui.table>
</div>

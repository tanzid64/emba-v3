<?php

use App\Enums\PaymentStatusEnum;
use App\Models\Application;
use App\Models\Batch;
use App\Models\ExamCenter;
use App\Support\CurrentBatch;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Exam Centers')] #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'per', except: 20)]
    public int $perPage = 20;

    public ?Batch $batch = null;

    public function mount(): void
    {
        $this->batch = CurrentBatch::get();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    public function with(): array
    {
        if (! $this->batch) {
            return [
                'centers' => null,
                'totalCapacity' => 0,
                'confirmedCount' => 0,
                'centerCount' => 0,
            ];
        }

        $term = trim($this->search);

        $centers = ExamCenter::query()
            ->where('batch_id', $this->batch->id)
            ->when($term !== '', function ($query) use ($term) {
                $like = '%'.$term.'%';
                $query->where(function ($q) use ($like) {
                    $q->where('center_no', 'like', $like)
                        ->orWhere('center_name', 'like', $like)
                        ->orWhere('room_name', 'like', $like);
                });
            })
            ->orderBy('center_no')
            ->orderBy('room_name')
            ->paginate($this->perPage);

        $totalCapacity = (int) ExamCenter::where('batch_id', $this->batch->id)->sum('capacity');
        $confirmedCount = Application::where('batch_id', $this->batch->id)
            ->whereIn('payment_status', [PaymentStatusEnum::PAID->value, PaymentStatusEnum::COMPLETED->value])
            ->count();
        $centerCount = ExamCenter::where('batch_id', $this->batch->id)
            ->distinct('center_no')
            ->count('center_no');

        return [
            'centers' => $centers,
            'totalCapacity' => $totalCapacity,
            'confirmedCount' => $confirmedCount,
            'centerCount' => $centerCount,
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 lg:gap-6 lg:p-6">

    {{-- Header --}}
    <div class="flex items-start justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-zinc-900">{{ __('Exam Centers') }}</h1>
            <p class="text-sm text-zinc-500 mt-1">
                @if ($batch)
                    {{ __('Exam centers and rooms configured for') }}
                    <span class="font-semibold text-zinc-700">{{ $batch->name }}</span>
                    <span class="text-zinc-400">·</span>
                    <span class="font-mono text-zinc-600">{{ $batch->code }}</span>
                @else
                    {{ __('Select a batch from the sidebar to view its exam centers.') }}
                @endif
            </p>
        </div>
    </div>

    @if (! $batch)
        <div class="rounded-xl border border-dashed border-zinc-200 bg-white px-6 py-16 text-center">
            <p class="text-sm text-zinc-500">
                {{ __('No batch selected. Pick one from the sidebar to load its exam centers.') }}</p>
        </div>
    @else
        {{-- Capacity summary --}}
        @php
            $shortfall = $confirmedCount - $totalCapacity;
            $capacityColor = $shortfall > 0 ? 'red' : 'green';
        @endphp
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="rounded-xl border border-zinc-200 bg-white px-5 py-4">
                <p class="text-xs font-medium text-zinc-500">{{ __('Centers') }}</p>
                <p class="mt-1 text-2xl font-bold text-zinc-900 tabular-nums">
                    {{ number_format($centerCount) }}
                </p>
                <p class="text-xs text-zinc-500 mt-1">
                    {{ trans_choice(':count room|:count rooms', $centers?->total() ?? 0, ['count' => number_format($centers?->total() ?? 0)]) }}
                </p>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white px-5 py-4">
                <p class="text-xs font-medium text-zinc-500">{{ __('Total capacity') }}</p>
                <p class="mt-1 text-2xl font-bold text-zinc-900 tabular-nums">
                    {{ number_format($totalCapacity) }}
                </p>
                <p class="text-xs text-zinc-500 mt-1">{{ __('seats across all rooms') }}</p>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white px-5 py-4">
                <p class="text-xs font-medium text-zinc-500">{{ __('Confirmed applicants') }}</p>
                <p class="mt-1 text-2xl font-bold text-zinc-900 tabular-nums">
                    {{ number_format($confirmedCount) }}
                </p>
                <div class="mt-1">
                    @if ($shortfall > 0)
                        <x-ui.badge size="sm" :color="$capacityColor">
                            {{ __('Short :n seats', ['n' => number_format($shortfall)]) }}
                        </x-ui.badge>
                    @else
                        <x-ui.badge size="sm" :color="$capacityColor">
                            {{ __(':n seats free', ['n' => number_format(abs($shortfall))]) }}
                        </x-ui.badge>
                    @endif
                </div>
            </div>
        </div>

        <x-ui.table :paginate="$centers">
            <x-slot:toolbar>
                <div class="flex items-center gap-3 flex-wrap">
                    <div class="flex-1 min-w-[260px] max-w-md">
                        <x-ui.input icon="search" clearable type="search"
                            placeholder="{{ __('Search by center no, name, or room…') }}"
                            wire:model.live.debounce.400ms="search" />
                    </div>

                    <select wire:model.live="perPage"
                        class="h-9 rounded-lg border border-zinc-200 bg-white px-3 pe-8 text-sm text-zinc-700 shadow-xs focus:outline-none focus:border-zinc-400">
                        @foreach ([10, 20, 50, 100] as $size)
                            <option value="{{ $size }}">{{ $size }} / {{ __('page') }}</option>
                        @endforeach
                    </select>

                    <div class="flex items-center gap-2 text-xs text-zinc-400" wire:loading
                        wire:target="search,perPage">
                        <x-lucide-loader-2 class="size-3.5 animate-spin" />
                        {{ __('Loading…') }}
                    </div>
                </div>
            </x-slot:toolbar>

            <x-slot:columns>
                <th class="text-left font-semibold text-zinc-700 px-4 py-3 w-12">{{ __('SL') }}</th>
                <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Center No.') }}</th>
                <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Center Name') }}</th>
                <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Room') }}</th>
                <th class="text-right font-semibold text-zinc-700 px-4 py-3 w-32">{{ __('Capacity') }}</th>
            </x-slot:columns>

            @forelse ($centers as $center)
                @php $sl = ($centers->firstItem() ?? 0) + $loop->index; @endphp
                <tr class="hover:bg-zinc-50/60 transition-colors">
                    <td class="px-4 py-3 text-zinc-500 tabular-nums">{{ $sl }}</td>
                    <td class="px-4 py-3 font-mono font-semibold text-zinc-800 whitespace-nowrap">
                        {{ $center->center_no }}
                    </td>
                    <td class="px-4 py-3 text-zinc-700">{{ $center->center_name }}</td>
                    <td class="px-4 py-3 text-zinc-700 whitespace-nowrap">{{ $center->room_name }}</td>
                    <td class="px-4 py-3 text-right tabular-nums font-semibold text-zinc-900">
                        {{ number_format($center->capacity) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-10 text-center text-zinc-500">
                        @if ($search !== '')
                            {{ __('No exam centers match the current search.') }}
                        @else
                            {{ __('No exam centers configured for this batch yet.') }}
                        @endif
                    </td>
                </tr>
            @endforelse
        </x-ui.table>
    @endif
</div>

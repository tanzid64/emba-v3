<?php

use App\Enums\PaymentStatusEnum;
use App\Models\Application;
use App\Models\Batch;
use App\Support\CurrentBatch;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Admit Cards')] #[Layout('layouts.app')] class extends Component {
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
            return ['applications' => null];
        }

        $term = trim($this->search);

        $applications = Application::query()
            ->where('batch_id', $this->batch->id)
            ->whereIn('payment_status', [PaymentStatusEnum::PAID->value, PaymentStatusEnum::COMPLETED->value])
            ->with([
                'applicant:id,email,phone_number',
                'applicant.profile:id,applicant_id,full_name,photo',
                'examCenter:id,center_no,center_name,room_name',
            ])
            ->when($term !== '', function ($query) use ($term) {
                $like = '%'.$term.'%';

                $query->where(function ($q) use ($like) {
                    $q->where('application_number', 'like', $like)
                        ->orWhere('roll_number', 'like', $like)
                        ->orWhereHas('applicant', function ($a) use ($like) {
                            $a->where('email', 'like', $like)->orWhere('phone_number', 'like', $like);
                        })
                        ->orWhereHas('applicant.profile', fn ($p) => $p->where('full_name', 'like', $like));
                });
            })
            ->orderBy('roll_number')
            ->paginate($this->perPage);

        return [
            'applications' => $applications,
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 lg:gap-6 lg:p-6">

    {{-- Header --}}
    <div class="flex items-start justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-zinc-900">{{ __('Admit Cards') }}</h1>
            <p class="text-sm text-zinc-500 mt-1">
                @if ($batch)
                    {{ __('View or download admit cards for paid applicants under') }}
                    <span class="font-semibold text-zinc-700">{{ $batch->name }}</span>
                    <span class="text-zinc-400">·</span>
                    <span class="font-mono text-zinc-600">{{ $batch->code }}</span>
                @else
                    {{ __('Select a batch from the sidebar to view its admit cards.') }}
                @endif
            </p>
        </div>
        @if ($batch && $applications)
            <x-ui.badge size="sm" color="green">
                {{ trans_choice(':count applicant|:count applicants', $applications->total(), ['count' => number_format($applications->total())]) }}
            </x-ui.badge>
        @endif
    </div>

    @if (! $batch)
        <div class="rounded-xl border border-dashed border-zinc-200 bg-white px-6 py-16 text-center">
            <p class="text-sm text-zinc-500">
                {{ __('No batch selected. Pick one from the sidebar to load its admit cards.') }}</p>
        </div>
    @else
        <x-ui.table :paginate="$applications">
            <x-slot:toolbar>
                <div class="flex items-center gap-3 flex-wrap">
                    <div class="flex-1 min-w-[260px] max-w-md">
                        <x-ui.input icon="search" clearable type="search"
                            placeholder="{{ __('Search by name, roll, app. ID, mobile, email…') }}"
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
                <th class="text-left font-semibold text-zinc-700 px-4 py-3 w-16">{{ __('Photo') }}</th>
                <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Roll No.') }}</th>
                <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('App. ID') }}</th>
                <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Name') }}</th>
                <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Exam Center') }}</th>
                <th class="text-right font-semibold text-zinc-700 px-4 py-3 w-32">{{ __('Action') }}</th>
            </x-slot:columns>

            @forelse ($applications as $application)
                @php
                    $profile = $application->applicant?->profile;
                    $center = $application->examCenter;
                    $sl = ($applications->firstItem() ?? 0) + $loop->index;
                @endphp
                <tr class="hover:bg-zinc-50/60 transition-colors align-top">
                    <td class="px-4 py-3 text-zinc-500 tabular-nums">{{ $sl }}</td>

                    <td class="px-4 py-3">
                        <img src="{{ $profile?->photo_url ?? asset('assets/images/default-avatar.png') }}"
                            alt="" class="size-10 rounded-md object-cover bg-zinc-100" />
                    </td>

                    <td class="px-4 py-3 font-mono font-semibold text-zinc-900 whitespace-nowrap">
                        {{ $application->roll_number ?? '—' }}
                    </td>

                    <td class="px-4 py-3 font-mono whitespace-nowrap">
                        <x-ui.tooltip text="{{ __('View admit card') }}">
                            <a href="{{ route('pdf.admit-card', $application->application_number) }}"
                                target="_blank" rel="noopener"
                                aria-label="{{ __('View admit card') }}"
                                class="text-brand hover:underline transition-colors">
                                {{ $application->application_number }}
                            </a>
                        </x-ui.tooltip>
                    </td>

                    <td class="px-4 py-3 font-semibold text-zinc-900 uppercase">
                        {{ $profile?->full_name ?? __('—') }}
                    </td>

                    <td class="px-4 py-3 text-sm text-zinc-700">
                        @if ($center)
                            <p class="font-semibold">{{ $center->center_name }}</p>
                            <p class="text-xs text-zinc-500">
                                <span class="font-mono">{{ $center->center_no }}</span>
                                <span class="text-zinc-400">·</span>
                                {{ $center->room_name }}
                            </p>
                        @else
                            <span class="text-zinc-400 italic">{{ __('Not assigned') }}</span>
                        @endif
                    </td>

                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1.5">
                            <x-ui.tooltip text="{{ __('View admit card') }}">
                                <a href="{{ route('pdf.admit-card', $application->application_number) }}"
                                    target="_blank" rel="noopener"
                                    aria-label="{{ __('View admit card') }}"
                                    class="inline-flex items-center justify-center size-8 rounded-lg border border-zinc-200 bg-white text-zinc-600 hover:border-brand/40 hover:text-brand transition-colors">
                                    <x-lucide-eye class="size-4" />
                                </a>
                            </x-ui.tooltip>

                            <x-ui.tooltip text="{{ __('Download admit card') }}">
                                <a href="{{ route('pdf.admit-card', ['appNo' => $application->application_number, 'action' => 'download']) }}"
                                    aria-label="{{ __('Download admit card') }}"
                                    class="inline-flex items-center justify-center size-8 rounded-lg border border-zinc-200 bg-white text-zinc-600 hover:border-brand/40 hover:text-brand transition-colors">
                                    <x-lucide-download class="size-4" />
                                </a>
                            </x-ui.tooltip>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-4 py-10 text-center text-zinc-500">
                        @if ($search !== '')
                            {{ __('No paid applicants match the current search.') }}
                        @else
                            {{ __('No paid applicants yet for this batch.') }}
                        @endif
                    </td>
                </tr>
            @endforelse
        </x-ui.table>
    @endif
</div>

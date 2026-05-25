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

new #[Title('All Applicants')]
#[Layout('layouts.app')]
class extends Component {
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'payment', except: '')]
    public string $paymentStatus = '';

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

    public function updatedPaymentStatus(): void
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

    /** @return array<int, PaymentStatusEnum> */
    public function paymentStatuses(): array
    {
        return PaymentStatusEnum::cases();
    }

    public function paymentBadgeColor(?PaymentStatusEnum $status): string
    {
        return match ($status) {
            PaymentStatusEnum::PAID, PaymentStatusEnum::COMPLETED => 'green',
            PaymentStatusEnum::UNPAID => 'zinc',
            PaymentStatusEnum::PENDING => 'yellow',
            PaymentStatusEnum::FAILED => 'red',
            default => 'zinc',
        };
    }

    public function with(): array
    {
        if (! $this->batch) {
            return ['applications' => null];
        }

        $term = trim($this->search);

        $applications = Application::query()
            ->where('batch_id', $this->batch->id)
            ->with([
                'applicant:id,email,phone_number',
                'applicant.profile:id,applicant_id,full_name,father_name,mother_name,photo',
            ])
            ->when($this->paymentStatus !== '', fn ($q) => $q->where('payment_status', $this->paymentStatus))
            ->when($term !== '', function ($query) use ($term) {
                $like = '%'.$term.'%';

                $query->where(function ($q) use ($like) {
                    $q->where('application_number', 'like', $like)
                        ->orWhereHas('applicant', function ($a) use ($like) {
                            $a->where('email', 'like', $like)
                                ->orWhere('phone_number', 'like', $like);
                        })
                        ->orWhereHas('applicant.profile', fn ($p) => $p->where('full_name', 'like', $like));
                });
            })
            ->latest('id')
            ->paginate($this->perPage);

        return [
            'applications' => $applications,
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl p-6">

    {{-- Header --}}
    <div class="flex items-start justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-zinc-900">{{ __('All Applicants') }}</h1>
            <p class="text-sm text-zinc-500 mt-1">
                @if ($batch)
                    {{ __('Applications received under') }}
                    <span class="font-semibold text-zinc-700">{{ $batch->name }}</span>
                    <span class="text-zinc-400">·</span>
                    <span class="font-mono text-zinc-600">{{ $batch->code }}</span>
                @else
                    {{ __('Select a batch from the sidebar to view its applications.') }}
                @endif
            </p>
        </div>
        @if ($batch && $applications)
            <x-ui.badge size="sm" color="zinc">
                {{ trans_choice(':count application|:count applications', $applications->total(), ['count' => number_format($applications->total())]) }}
            </x-ui.badge>
        @endif
    </div>

    @if (! $batch)
        <div class="rounded-xl border border-dashed border-zinc-200 bg-white px-6 py-16 text-center">
            <p class="text-sm text-zinc-500">{{ __('No batch selected. Pick one from the sidebar to load its applications.') }}</p>
        </div>
    @else
        <x-ui.table :paginate="$applications">
            <x-slot:toolbar>
                <div class="flex items-center gap-3 flex-wrap">
                    <div class="flex-1 min-w-[260px] max-w-md">
                        <x-ui.input
                            icon="search"
                            clearable
                            type="search"
                            placeholder="{{ __('Search by name, app. ID, mobile, email…') }}"
                            wire:model.live.debounce.400ms="search"
                        />
                    </div>

                    <select
                        wire:model.live="paymentStatus"
                        class="h-9 rounded-lg border border-zinc-200 bg-white px-3 pe-8 text-sm text-zinc-700 shadow-xs focus:outline-none focus:border-zinc-400"
                    >
                        <option value="">{{ __('All payments') }}</option>
                        @foreach ($this->paymentStatuses() as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </select>

                    <select
                        wire:model.live="perPage"
                        class="h-9 rounded-lg border border-zinc-200 bg-white px-3 pe-8 text-sm text-zinc-700 shadow-xs focus:outline-none focus:border-zinc-400"
                    >
                        @foreach ([10, 20, 50, 100] as $size)
                            <option value="{{ $size }}">{{ $size }} / {{ __('page') }}</option>
                        @endforeach
                    </select>

                    <div class="flex items-center gap-2 text-xs text-zinc-400" wire:loading wire:target="search,paymentStatus,perPage">
                        <x-lucide-loader-2 class="size-3.5 animate-spin" />
                        {{ __('Loading…') }}
                    </div>
                </div>
            </x-slot:toolbar>

            <x-slot:columns>
                <th class="text-left font-semibold text-zinc-700 px-4 py-3 w-12">{{ __('SL') }}</th>
                <th class="text-left font-semibold text-zinc-700 px-4 py-3 w-16">{{ __('Photo') }}</th>
                <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('App. ID') }}</th>
                <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Name') }}</th>
                <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Parents') }}</th>
                <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Contact') }}</th>
                <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Apply Date') }}</th>
                <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Payment Status') }}</th>
                <th class="text-right font-semibold text-zinc-700 px-4 py-3 w-32">{{ __('Action') }}</th>
            </x-slot:columns>

            @forelse ($applications as $application)
                @php
                    $profile = $application->applicant?->profile;
                    $sl = ($applications->firstItem() ?? 0) + $loop->index;
                @endphp
                <tr class="hover:bg-zinc-50/60 transition-colors align-top">
                    <td class="px-4 py-3 text-zinc-500 tabular-nums">{{ $sl }}</td>

                    <td class="px-4 py-3">
                        <img
                            src="{{ $profile?->photo_url ?? asset('assets/images/default-avatar.png') }}"
                            alt=""
                            class="size-10 rounded-md object-cover bg-zinc-100"
                        />
                    </td>

                    <td class="px-4 py-3 font-mono text-zinc-800 whitespace-nowrap">{{ $application->application_number }}</td>

                    <td class="px-4 py-3 font-semibold text-zinc-900 uppercase">
                        {{ $profile?->full_name ?? __('—') }}
                    </td>

                    <td class="px-4 py-3 text-sm text-zinc-700">
                        @if ($profile)
                            <p><span class="font-semibold text-zinc-600">{{ __("Father's Name") }}:</span> {{ $profile->father_name }}</p>
                            <p><span class="font-semibold text-zinc-600">{{ __("Mother's Name") }}:</span> {{ $profile->mother_name }}</p>
                        @else
                            <span class="text-zinc-400 italic">{{ __('Profile not completed') }}</span>
                        @endif
                    </td>

                    <td class="px-4 py-3 text-sm text-zinc-700">
                        <p class="whitespace-nowrap">{{ $application->applicant?->phone_number ?? '—' }}</p>
                        <p class="text-zinc-500 break-all">{{ $application->applicant?->email ?? '—' }}</p>
                    </td>

                    <td class="px-4 py-3 text-sm text-zinc-700 whitespace-nowrap">
                        @if ($application->applied_at)
                            {{ \Carbon\Carbon::parse($application->applied_at['original'])->format('d M Y') }}
                        @else
                            <span class="text-zinc-400 italic">{{ __('Not submitted') }}</span>
                        @endif
                    </td>

                    <td class="px-4 py-3 text-sm">
                        <div class="flex flex-col gap-1">
                            <x-ui.badge :color="$this->paymentBadgeColor($application->payment_status)" size="sm" class="w-fit">
                                {{ __('Status') }}: {{ $application->payment_status?->label() ?? '—' }}
                            </x-ui.badge>

                            @if ($application->trx_id)
                                <p class="text-xs text-zinc-600"><span class="font-semibold">{{ __('Trx ID') }}:</span> {{ $application->trx_id }}</p>
                            @endif

                            @if ($application->paid_at)
                                <p class="text-xs text-zinc-500"><span class="font-semibold">{{ __('Time') }}:</span> {{ $application->paid_at['formatted'] ?? '—' }}</p>
                            @endif
                        </div>
                    </td>

                    <td class="px-4 py-3">
                        <div class="flex flex-col gap-1.5 items-end">
                            <x-ui.button size="sm" variant="outline" icon="eye" :href="route('admin.applicants.show', $application)" wire:navigate>
                                {{ __('View') }}
                            </x-ui.button>
                            <x-ui.button size="sm" variant="outline" icon="pencil" :href="route('admin.applicants.edit', $application)" wire:navigate>
                                {{ __('Edit') }}
                            </x-ui.button>
                            <x-ui.button size="sm" variant="outline" icon="download">
                                {{ __('Download') }}
                            </x-ui.button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="px-4 py-10 text-center text-zinc-500">
                        @if ($search !== '' || $paymentStatus !== '')
                            {{ __('No applications match the current filters.') }}
                        @else
                            {{ __('No applications have been submitted for this batch yet.') }}
                        @endif
                    </td>
                </tr>
            @endforelse
        </x-ui.table>
    @endif
</div>

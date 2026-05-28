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

new #[Title('Confirmed Applications')] #[Layout('layouts.app')] class extends Component {
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
            ->with(['applicant:id,email,phone_number', 'applicant.profile:id,applicant_id,full_name,father_name,mother_name,photo'])
            ->when($term !== '', function ($query) use ($term) {
                $like = '%'.$term.'%';

                $query->where(function ($q) use ($like) {
                    $q->where('application_number', 'like', $like)
                        ->orWhere('roll_number', 'like', $like)
                        ->orWhere('trx_id', 'like', $like)
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
            <h1 class="text-xl font-bold text-zinc-900">{{ __('Confirmed Applications') }}</h1>
            <p class="text-sm text-zinc-500 mt-1">
                @if ($batch)
                    {{ __('Paid applications with assigned roll numbers under') }}
                    <span class="font-semibold text-zinc-700">{{ $batch->name }}</span>
                    <span class="text-zinc-400">·</span>
                    <span class="font-mono text-zinc-600">{{ $batch->code }}</span>
                @else
                    {{ __('Select a batch from the sidebar to view its confirmed applications.') }}
                @endif
            </p>
        </div>
        @if ($batch && $applications)
            <div class="flex items-center gap-2 flex-wrap">
                <x-ui.badge size="sm" color="green">
                    {{ trans_choice(':count confirmed|:count confirmed', $applications->total(), ['count' => number_format($applications->total())]) }}
                </x-ui.badge>
                @if ($applications->total() > 0)
                    <x-ui.button variant="outline" icon="download"
                        x-on:click="$dispatch('open-modal', { name: 'export-confirmed-applicants' })">
                        {{ __('Export') }}
                    </x-ui.button>
                @endif
            </div>
        @endif
    </div>

    @if (! $batch)
        <div class="rounded-xl border border-dashed border-zinc-200 bg-white px-6 py-16 text-center">
            <p class="text-sm text-zinc-500">
                {{ __('No batch selected. Pick one from the sidebar to load its confirmed applications.') }}</p>
        </div>
    @else
        <x-ui.table :paginate="$applications">
            <x-slot:toolbar>
                <div class="flex items-center gap-3 flex-wrap">
                    <div class="flex-1 min-w-[260px] max-w-md">
                        <x-ui.input icon="search" clearable type="search"
                            placeholder="{{ __('Search by name, roll, app. ID, trx, mobile, email…') }}"
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
                <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Parents') }}</th>
                <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Contact') }}</th>
                <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Payment') }}</th>
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
                        <img src="{{ $profile?->photo_url ?? asset('assets/images/default-avatar.png') }}"
                            alt="" class="size-10 rounded-md object-cover bg-zinc-100" />
                    </td>

                    <td class="px-4 py-3 font-mono font-semibold text-zinc-900 whitespace-nowrap">
                        {{ $application->roll_number ?? '—' }}
                    </td>

                    <td class="px-4 py-3 font-mono whitespace-nowrap">
                        <x-ui.tooltip text="{{ __('View application form') }}">
                            <a href="{{ route('pdf.application-form', $application->application_number) }}"
                                target="_blank" rel="noopener"
                                aria-label="{{ __('View application form') }}"
                                class="text-brand hover:underline transition-colors">
                                {{ $application->application_number }}
                            </a>
                        </x-ui.tooltip>
                    </td>

                    <td class="px-4 py-3 font-semibold text-zinc-900 uppercase">
                        {{ $profile?->full_name ?? __('—') }}
                    </td>

                    <td class="px-4 py-3 text-sm text-zinc-700">
                        @if ($profile)
                            <p><span class="font-semibold text-zinc-600">{{ __("Father's Name") }}:</span>
                                {{ $profile->father_name }}</p>
                            <p><span class="font-semibold text-zinc-600">{{ __("Mother's Name") }}:</span>
                                {{ $profile->mother_name }}</p>
                        @else
                            <span class="text-zinc-400 italic">{{ __('Profile not completed') }}</span>
                        @endif
                    </td>

                    <td class="px-4 py-3 text-sm text-zinc-700">
                        <p class="whitespace-nowrap">{{ $application->applicant?->phone_number ?? '—' }}</p>
                        <p class="text-zinc-500 break-all">{{ $application->applicant?->email ?? '—' }}</p>
                    </td>

                    <td class="px-4 py-3 text-sm">
                        <div class="flex flex-col gap-1">
                            @if ($application->trx_id)
                                <p class="text-xs text-zinc-700"><span
                                        class="font-semibold">{{ __('Trx') }}:</span> {{ $application->trx_id }}</p>
                            @endif

                            @if ($application->paid_at)
                                <p class="text-xs text-zinc-500"><span
                                        class="font-semibold">{{ __('Paid') }}:</span>
                                    {{ $application->paid_at['formatted'] ?? '—' }}</p>
                            @endif
                        </div>
                    </td>

                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1.5">
                            <x-ui.tooltip text="{{ __('View applicant') }}">
                                <a href="{{ route('admin.applicants.show', $application) }}" wire:navigate
                                    aria-label="{{ __('View applicant') }}"
                                    class="inline-flex items-center justify-center size-8 rounded-lg border border-zinc-200 bg-white text-zinc-600 hover:border-brand/40 hover:text-brand transition-colors">
                                    <x-lucide-eye class="size-4" />
                                </a>
                            </x-ui.tooltip>

                            <x-ui.tooltip text="{{ __('Edit applicant') }}">
                                <a href="{{ route('admin.applicants.edit', $application) }}" wire:navigate
                                    aria-label="{{ __('Edit applicant') }}"
                                    class="inline-flex items-center justify-center size-8 rounded-lg border border-zinc-200 bg-white text-zinc-600 hover:border-brand/40 hover:text-brand transition-colors">
                                    <x-lucide-pencil class="size-4" />
                                </a>
                            </x-ui.tooltip>

                            <x-ui.tooltip text="{{ __('Download application form') }}">
                                <a href="{{ route('pdf.application-form', ['appNo' => $application->application_number, 'action' => 'download']) }}"
                                    aria-label="{{ __('Download application form') }}"
                                    class="inline-flex items-center justify-center size-8 rounded-lg border border-zinc-200 bg-white text-zinc-600 hover:border-brand/40 hover:text-brand transition-colors">
                                    <x-lucide-download class="size-4" />
                                </a>
                            </x-ui.tooltip>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="px-4 py-10 text-center text-zinc-500">
                        @if ($search !== '')
                            {{ __('No confirmed applications match the current search.') }}
                        @else
                            {{ __('No confirmed applications yet for this batch.') }}
                        @endif
                    </td>
                </tr>
            @endforelse
        </x-ui.table>
    @endif

    {{-- ===================== EXPORT MODAL ===================== --}}
    <x-ui.modal name="export-confirmed-applicants" :title="__('Export Confirmed Applicants')" maxWidth="lg">
        @if ($batch)
            <div class="space-y-4">
                <div class="rounded-lg border border-brand/15 bg-brand-soft px-4 py-3 text-xs text-zinc-700 flex items-start gap-2">
                    <x-lucide-info class="size-4 shrink-0 text-brand mt-0.5" />
                    <p class="leading-relaxed">
                        {{ __('Exports the full confirmed (paid) applicant list for this batch in roll-number order, with a professional header (batch, timestamp, totals).') }}
                    </p>
                </div>

                <div class="flex flex-col sm:flex-row gap-2">
                    <x-ui.button variant="outline" icon="file-text" class="flex-1"
                        :href="route('pdf.confirmed-applicants', $batch)">
                        {{ __('Download PDF') }}
                    </x-ui.button>
                    <x-ui.button variant="primary" icon="file-spreadsheet" class="flex-1"
                        :href="route('excel.confirmed-applicants', $batch)">
                        {{ __('Download Excel') }}
                    </x-ui.button>
                </div>
            </div>

            <div class="flex justify-end items-center gap-2 mt-6 pt-4 border-t border-zinc-100">
                <x-ui.button variant="ghost"
                    x-on:click="$dispatch('close-modal', { name: 'export-confirmed-applicants' })">
                    {{ __('Close') }}
                </x-ui.button>
            </div>
        @endif
    </x-ui.modal>
</div>

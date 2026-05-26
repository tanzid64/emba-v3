<?php

use App\Enums\PaymentStatusEnum;
use App\Models\Application;
use App\Models\Batch;
use App\Support\CurrentBatch;
use App\Support\Toast;
use Carbon\Carbon;
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
        $this->batch = CurrentBatch::get()?->loadMissing('admissionSetting');
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

    public function isAdmissionClosed(): bool
    {
        $raw = $this->batch?->admissionSetting?->getRawOriginal('intake_ended_at');

        return $raw !== null && now()->greaterThan(Carbon::parse($raw)->endOfDay());
    }

    public function isPaymentClosed(): bool
    {
        $raw = $this->batch?->admissionSetting?->getRawOriginal('application_payment_ended_at');

        return $raw !== null && now()->greaterThan(Carbon::parse($raw)->endOfDay());
    }

    public function isExamCenterUploaded(): bool
    {
        return (bool) $this->batch?->admissionSetting?->is_exam_center_uploaded;
    }

    public function isAdmitCardPublished(): bool
    {
        return (bool) $this->batch?->admissionSetting?->is_admit_card_published;
    }

    public function canPublish(): bool
    {
        return $this->isAdmissionClosed()
            && $this->isPaymentClosed()
            && $this->isExamCenterUploaded()
            && ! $this->isAdmitCardPublished();
    }

    public function openPublishModal(): void
    {
        $this->dispatch('open-modal', name: 'publish-admit-cards');
    }

    public function closePublishModal(): void
    {
        $this->dispatch('close-modal', name: 'publish-admit-cards');
    }

    public function publishAdmitCards(): void
    {
        if (! $this->batch?->admissionSetting || ! $this->canPublish()) {
            return;
        }

        $this->batch->admissionSetting->update(['admit_card_published_at' => now()]);
        $this->batch->loadMissing('admissionSetting');

        $this->dispatch('close-modal', name: 'publish-admit-cards');
        Toast::success(__('Admit cards published — applicants can now download them from their portal.'));
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
        @if ($batch)
            <div class="flex items-center gap-2 flex-wrap">
                @if ($applications)
                    <x-ui.badge size="sm" color="green">
                        {{ trans_choice(':count applicant|:count applicants', $applications->total(), ['count' => number_format($applications->total())]) }}
                    </x-ui.badge>
                @endif

                <x-ui.button variant="primary" icon="send" wire:click="openPublishModal">
                    {{ __('Publish Admit Cards') }}
                </x-ui.button>
            </div>
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

    {{-- ===================== PUBLISH MODAL ===================== --}}
    <x-ui.modal name="publish-admit-cards" :title="__('Publish Admit Cards')" maxWidth="lg">
        @if ($batch && $this->isAdmitCardPublished())
            @php $publishedAt = $batch->admissionSetting?->admit_card_published_at; @endphp
            <div class="flex items-start gap-3 rounded-lg border border-green-200 bg-green-50 px-4 py-3">
                <x-lucide-check-circle class="size-5 shrink-0 text-green-600 mt-0.5" />
                <div class="text-sm text-zinc-700 leading-relaxed">
                    <p class="font-semibold text-green-800">
                        {{ __('Admit cards are already published.') }}
                    </p>
                    @if (is_array($publishedAt) && isset($publishedAt['formatted']))
                        <p class="text-xs text-green-700/80 mt-0.5">
                            {{ __('Published on :date', ['date' => $publishedAt['formatted']]) }}
                        </p>
                    @endif
                    <p class="mt-2">
                        {{ __('Applicants can see and download their admit cards from their portal. Intake dates, payment deadline, exam date and exam centers are now locked.') }}
                    </p>
                </div>
            </div>

            <div class="flex justify-end items-center gap-2 mt-6 pt-4 border-t border-zinc-100">
                <x-ui.button variant="ghost" wire:click="closePublishModal">
                    {{ __('Close') }}
                </x-ui.button>
            </div>
        @elseif ($batch)
            <div class="space-y-4">
                {{-- Rules / checklist --}}
                <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4">
                    <p class="text-sm font-semibold text-zinc-700 mb-3">
                        {{ __('All conditions must be satisfied before publishing:') }}
                    </p>
                    <ul class="space-y-2 text-sm">
                        <li class="flex items-center gap-2">
                            @if ($this->isAdmissionClosed())
                                <x-lucide-check-circle class="size-5 text-green-600 shrink-0" />
                                <span class="text-zinc-700">{{ __('Admission is not open') }}</span>
                            @else
                                <x-lucide-x-circle class="size-5 text-red-600 shrink-0" />
                                <span class="text-red-700 font-medium">{{ __('Admission is still open') }}</span>
                            @endif
                        </li>
                        <li class="flex items-center gap-2">
                            @if ($this->isPaymentClosed())
                                <x-lucide-check-circle class="size-5 text-green-600 shrink-0" />
                                <span class="text-zinc-700">{{ __('Payment acceptance has closed') }}</span>
                            @else
                                <x-lucide-x-circle class="size-5 text-red-600 shrink-0" />
                                <span
                                    class="text-red-700 font-medium">{{ __('Payment acceptance is still open') }}</span>
                            @endif
                        </li>
                        <li class="flex items-center gap-2">
                            @if ($this->isExamCenterUploaded())
                                <x-lucide-check-circle class="size-5 text-green-600 shrink-0" />
                                <span class="text-zinc-700">{{ __('Exam centers are uploaded') }}</span>
                            @else
                                <x-lucide-x-circle class="size-5 text-red-600 shrink-0" />
                                <span
                                    class="text-red-700 font-medium">{{ __('Exam centers are not uploaded yet') }}</span>
                            @endif
                        </li>
                    </ul>
                </div>

                @if ($this->canPublish())
                    {{-- Warning description --}}
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 flex items-start gap-2">
                        <x-lucide-alert-triangle class="size-5 shrink-0 text-amber-600 mt-0.5" />
                        <div class="text-sm text-zinc-700 leading-relaxed space-y-1.5">
                            <p class="font-semibold text-amber-800">{{ __('This action cannot be undone.') }}</p>
                            <ul class="list-disc list-inside space-y-1">
                                <li>{{ __('Applicants will immediately see and download their admit cards from their portal.') }}
                                </li>
                                <li>{{ __('Exam date, exam centers and room assignments will be locked from further changes.') }}
                                </li>
                                <li>{{ __('Roll numbers and seat assignments become final and cannot be reseated.') }}
                                </li>
                            </ul>
                        </div>
                    </div>
                @else
                    <p class="text-sm text-zinc-500">
                        {{ __('Close this dialog and come back once the conditions above are met.') }}
                    </p>
                @endif
            </div>

            {{-- Footer --}}
            <div class="flex justify-end items-center gap-2 mt-6 pt-4 border-t border-zinc-100">
                <x-ui.button variant="ghost" wire:click="closePublishModal">
                    {{ __('Cancel') }}
                </x-ui.button>

                @if ($this->canPublish())
                    <x-ui.button variant="primary" wire:click="publishAdmitCards" wire:loading.attr="disabled"
                        wire:target="publishAdmitCards">
                        <x-lucide-loader-2 class="animate-spin" wire:loading wire:target="publishAdmitCards" />
                        <x-lucide-check wire:loading.remove wire:target="publishAdmitCards" />
                        <span
                            wire:loading.remove wire:target="publishAdmitCards">{{ __('Confirm & Publish') }}</span>
                        <span wire:loading wire:target="publishAdmitCards">{{ __('Publishing…') }}</span>
                    </x-ui.button>
                @endif
            </div>
        @endif
    </x-ui.modal>
</div>

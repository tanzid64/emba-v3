<?php

use App\Models\AdmissionResult;
use App\Models\Batch;
use App\Services\VivaBoardAssignmentService;
use App\Support\CurrentBatch;
use App\Support\Toast;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Viva Admit Cards')] #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'per', except: 20)]
    public int $perPage = 20;

    public ?Batch $batch = null;

    private ?array $previewCache = null;

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

    /**
     * MCQ mark at/above which a candidate is eligible for the viva.
     */
    public function vivaThreshold(): float
    {
        return (float) ($this->batch?->admissionSetting?->viva_mcq_threshold
            ?? config('result.viva_mcq_threshold'));
    }

    /**
     * Eligible / assigned / unassigned counts for the current batch.
     *
     * @return array{boards: int, eligible: int, unassigned: int}
     */
    public function preview(): array
    {
        if (! $this->batch) {
            return ['boards' => 0, 'eligible' => 0, 'unassigned' => 0];
        }

        return $this->previewCache ??= app(VivaBoardAssignmentService::class)->preview($this->batch);
    }

    public function isVivaDateSet(): bool
    {
        return $this->batch?->admissionSetting?->getRawOriginal('viva_date') !== null;
    }

    public function isAllAssigned(): bool
    {
        $preview = $this->preview();

        return $preview['eligible'] > 0 && $preview['unassigned'] === 0;
    }

    public function isVivaAdmitCardPublished(): bool
    {
        return (bool) $this->batch?->admissionSetting?->is_viva_admit_card_published;
    }

    public function canPublish(): bool
    {
        return $this->isVivaDateSet()
            && $this->isAllAssigned()
            && ! $this->isVivaAdmitCardPublished();
    }

    public function openPublishModal(): void
    {
        $this->dispatch('open-modal', name: 'publish-viva-admit-cards');
    }

    public function closePublishModal(): void
    {
        $this->dispatch('close-modal', name: 'publish-viva-admit-cards');
    }

    public function publishVivaAdmitCards(): void
    {
        if (! $this->batch?->admissionSetting || ! $this->canPublish()) {
            return;
        }

        $this->batch->admissionSetting->update(['viva_admit_card_published_at' => now()]);
        $this->batch->loadMissing('admissionSetting');

        $this->dispatch('close-modal', name: 'publish-viva-admit-cards');
        Toast::success(__('Viva admit cards published — eligible applicants can now download them from their portal.'));
    }

    public function with(): array
    {
        if (! $this->batch) {
            return ['results' => null];
        }

        $term = trim($this->search);
        $threshold = $this->vivaThreshold();

        $results = AdmissionResult::query()
            ->where('batch_id', $this->batch->id)
            ->where('mcq_marks', '>=', $threshold)
            ->with([
                'applicant.profile:id,applicant_id,full_name,photo',
                'application' => fn ($q) => $q
                    ->where('batch_id', $this->batch->id)
                    ->with('vivaBoard:id,board_name,center_no,center_name,room_name'),
            ])
            ->when($term !== '', function ($query) use ($term) {
                $like = '%'.$term.'%';

                $query->where(function ($q) use ($like) {
                    $q->where('application_number', 'like', $like)
                        ->orWhere('roll_number', 'like', $like)
                        ->orWhereHas('applicant.profile', fn ($p) => $p->where('full_name', 'like', $like));
                });
            })
            ->orderByDesc('mcq_marks')
            ->orderByDesc('total_marks')
            ->paginate($this->perPage);

        return ['results' => $results];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 lg:gap-6 lg:p-6">

    {{-- Header --}}
    <div class="flex items-start justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-zinc-900">{{ __('Viva Admit Cards') }}</h1>
            <p class="text-sm text-zinc-500 mt-1">
                @if ($batch)
                    {{ __('View or download viva admit cards for shortlisted candidates under') }}
                    <span class="font-semibold text-zinc-700">{{ $batch->name }}</span>
                    <span class="text-zinc-400">·</span>
                    <span class="font-mono text-zinc-600">{{ $batch->code }}</span>
                @else
                    {{ __('Select a batch from the sidebar to view its viva admit cards.') }}
                @endif
            </p>
        </div>
        @if ($batch)
            <div class="flex items-center gap-2 flex-wrap">
                @if ($results)
                    <x-ui.badge size="sm" color="green">
                        {{ trans_choice(':count candidate|:count candidates', $results->total(), ['count' => number_format($results->total())]) }}
                    </x-ui.badge>
                @endif

                <x-ui.button variant="primary" icon="send" wire:click="openPublishModal">
                    {{ __('Publish Viva Admit Cards') }}
                </x-ui.button>
            </div>
        @endif
    </div>

    @if (! $batch)
        <div class="rounded-xl border border-dashed border-zinc-200 bg-white px-6 py-16 text-center">
            <p class="text-sm text-zinc-500">
                {{ __('No batch selected. Pick one from the sidebar to load its viva admit cards.') }}</p>
        </div>
    @else
        <x-ui.table :paginate="$results">
            <x-slot:toolbar>
                <div class="flex items-center gap-3 flex-wrap">
                    <div class="flex-1 min-w-[260px] max-w-md">
                        <x-ui.input icon="search" clearable type="search"
                            placeholder="{{ __('Search by name, roll, app. ID…') }}"
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
                <th class="text-left font-semibold text-zinc-700 px-4 py-3">{{ __('Viva Board') }}</th>
                <th class="text-right font-semibold text-zinc-700 px-4 py-3 w-32">{{ __('Action') }}</th>
            </x-slot:columns>

            @forelse ($results as $result)
                @php
                    $profile = $result->applicant?->profile;
                    $board = $result->application?->vivaBoard;
                    $sl = ($results->firstItem() ?? 0) + $loop->index;
                @endphp
                <tr class="hover:bg-zinc-50/60 transition-colors align-top">
                    <td class="px-4 py-3 text-zinc-500 tabular-nums">{{ $sl }}</td>

                    <td class="px-4 py-3">
                        <img src="{{ $profile?->photo_url ?? asset('assets/images/default-avatar.png') }}"
                            alt="" class="size-10 rounded-md object-cover bg-zinc-100" />
                    </td>

                    <td class="px-4 py-3 font-mono font-semibold text-zinc-900 whitespace-nowrap">
                        {{ $result->roll_number ?? '—' }}
                    </td>

                    <td class="px-4 py-3 font-mono whitespace-nowrap">
                        @if ($board)
                            <x-ui.tooltip text="{{ __('View viva admit card') }}">
                                <a href="{{ route('pdf.viva-admit-card', $result->application_number) }}"
                                    target="_blank" rel="noopener"
                                    class="text-brand hover:underline transition-colors">
                                    {{ $result->application_number }}
                                </a>
                            </x-ui.tooltip>
                        @else
                            <span class="text-zinc-700">{{ $result->application_number }}</span>
                        @endif
                    </td>

                    <td class="px-4 py-3 font-semibold text-zinc-900 uppercase">
                        {{ $profile?->full_name ?? __('—') }}
                    </td>

                    <td class="px-4 py-3 text-sm text-zinc-700">
                        @if ($board)
                            <p class="font-semibold">{{ $board->board_name }}</p>
                            <p class="text-xs text-zinc-500">
                                {{ $board->center_name }}
                                @if ($board->room_name)
                                    <span class="text-zinc-400">·</span> {{ $board->room_name }}
                                @endif
                            </p>
                        @else
                            <span class="text-zinc-400 italic">{{ __('Not assigned') }}</span>
                        @endif
                    </td>

                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1.5">
                            @if ($board)
                                <x-ui.tooltip text="{{ __('View viva admit card') }}">
                                    <a href="{{ route('pdf.viva-admit-card', $result->application_number) }}"
                                        target="_blank" rel="noopener"
                                        aria-label="{{ __('View viva admit card') }}"
                                        class="inline-flex items-center justify-center size-8 rounded-lg border border-zinc-200 bg-white text-zinc-600 hover:border-brand/40 hover:text-brand transition-colors">
                                        <x-lucide-eye class="size-4" />
                                    </a>
                                </x-ui.tooltip>

                                <x-ui.tooltip text="{{ __('Download viva admit card') }}">
                                    <a href="{{ route('pdf.viva-admit-card', ['appNo' => $result->application_number, 'action' => 'download']) }}"
                                        aria-label="{{ __('Download viva admit card') }}"
                                        class="inline-flex items-center justify-center size-8 rounded-lg border border-zinc-200 bg-white text-zinc-600 hover:border-brand/40 hover:text-brand transition-colors">
                                        <x-lucide-download class="size-4" />
                                    </a>
                                </x-ui.tooltip>
                            @else
                                <span class="text-xs text-zinc-400 italic">{{ __('Awaiting board') }}</span>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-4 py-10 text-center text-zinc-500">
                        @if ($search !== '')
                            {{ __('No shortlisted candidates match the current search.') }}
                        @else
                            {{ __('No candidates have reached the MCQ cutoff yet. Upload MCQ marks on the Exam Results page first.') }}
                        @endif
                    </td>
                </tr>
            @endforelse
        </x-ui.table>
    @endif

    {{-- ===================== PUBLISH MODAL ===================== --}}
    <x-ui.modal name="publish-viva-admit-cards" :title="__('Publish Viva Admit Cards')" maxWidth="lg">
        @if ($batch && $this->isVivaAdmitCardPublished())
            @php $publishedAt = $batch->admissionSetting?->viva_admit_card_published_at; @endphp
            <div class="flex items-start gap-3 rounded-lg border border-green-200 bg-green-50 px-4 py-3">
                <x-lucide-check-circle class="size-5 shrink-0 text-green-600 mt-0.5" />
                <div class="text-sm text-zinc-700 leading-relaxed">
                    <p class="font-semibold text-green-800">
                        {{ __('Viva admit cards are already published.') }}
                    </p>
                    @if (is_array($publishedAt) && isset($publishedAt['formatted']))
                        <p class="text-xs text-green-700/80 mt-0.5">
                            {{ __('Published on :date', ['date' => $publishedAt['formatted']]) }}
                        </p>
                    @endif
                    <p class="mt-2">
                        {{ __('Shortlisted applicants with an assigned viva board can now download their viva admit cards from the applicant portal.') }}
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
                <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4">
                    <p class="text-sm font-semibold text-zinc-700 mb-3">
                        {{ __('All conditions must be satisfied before publishing:') }}
                    </p>
                    <ul class="space-y-2 text-sm">
                        <li class="flex items-center gap-2">
                            @if ($this->isVivaDateSet())
                                <x-lucide-check-circle class="size-5 text-green-600 shrink-0" />
                                <span class="text-zinc-700">{{ __('Viva date is set') }}</span>
                            @else
                                <x-lucide-x-circle class="size-5 text-red-600 shrink-0" />
                                <span class="text-red-700 font-medium">{{ __('Viva date is not set yet') }}</span>
                            @endif
                        </li>
                        <li class="flex items-center gap-2">
                            @if ($this->isAllAssigned())
                                <x-lucide-check-circle class="size-5 text-green-600 shrink-0" />
                                <span class="text-zinc-700">{{ __('Every eligible candidate has a viva board') }}</span>
                            @else
                                <x-lucide-x-circle class="size-5 text-red-600 shrink-0" />
                                <span class="text-red-700 font-medium">
                                    @php $preview = $this->preview(); @endphp
                                    {{ trans_choice(':count candidate still has no board|:count candidates still have no board', $preview['unassigned'], ['count' => number_format($preview['unassigned'])]) }}
                                </span>
                            @endif
                        </li>
                    </ul>
                </div>

                @if ($this->canPublish())
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 flex items-start gap-2">
                        <x-lucide-alert-triangle class="size-5 shrink-0 text-amber-600 mt-0.5" />
                        <div class="text-sm text-zinc-700 leading-relaxed space-y-1.5">
                            <p class="font-semibold text-amber-800">{{ __('This action cannot be undone.') }}</p>
                            <ul class="list-disc list-inside space-y-1">
                                <li>{{ __('Shortlisted applicants will immediately be able to download their viva admit cards from their portal.') }}</li>
                                <li>{{ __('Make sure the viva date and all board assignments are final before publishing.') }}</li>
                            </ul>
                        </div>
                    </div>
                @else
                    <p class="text-sm text-zinc-500">
                        {{ __('Close this dialog and come back once the conditions above are met. Assign boards from the Viva Board page.') }}
                    </p>
                @endif
            </div>

            <div class="flex justify-end items-center gap-2 mt-6 pt-4 border-t border-zinc-100">
                <x-ui.button variant="ghost" wire:click="closePublishModal">
                    {{ __('Cancel') }}
                </x-ui.button>

                @if ($this->canPublish())
                    <x-ui.button variant="primary" wire:click="publishVivaAdmitCards" wire:loading.attr="disabled"
                        wire:target="publishVivaAdmitCards">
                        <x-lucide-loader-2 class="animate-spin" wire:loading wire:target="publishVivaAdmitCards" />
                        <x-lucide-check wire:loading.remove wire:target="publishVivaAdmitCards" />
                        <span wire:loading.remove wire:target="publishVivaAdmitCards">{{ __('Confirm & Publish') }}</span>
                        <span wire:loading wire:target="publishVivaAdmitCards">{{ __('Publishing…') }}</span>
                    </x-ui.button>
                @endif
            </div>
        @endif
    </x-ui.modal>
</div>

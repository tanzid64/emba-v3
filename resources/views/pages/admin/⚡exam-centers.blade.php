<?php

use App\Enums\PaymentStatusEnum;
use App\Models\Application;
use App\Models\Batch;
use App\Models\ExamCenter;
use App\Services\ExamCenterUploadService;
use App\Support\CurrentBatch;
use App\Support\Toast;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Exam Centers')] #[Layout('layouts.app')] class extends Component {
    use WithFileUploads;

    public ?Batch $batch = null;

    public $csv = null;

    public function mount(): void
    {
        $this->batch = CurrentBatch::get();
    }

    public function openNotUploadedModal(): void
    {
        $this->dispatch('open-modal', name: 'exam-center-not-uploaded');
    }

    public function openUploadModal(): void
    {
        $this->resetErrorBag();
        $this->csv = null;
        $this->dispatch('open-modal', name: 'upload-exam-center');
    }

    public function closeUploadModal(): void
    {
        $this->resetErrorBag();
        $this->csv = null;
        $this->dispatch('close-modal', name: 'upload-exam-center');
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

    public function isAdmitCardPublished(): bool
    {
        return (bool) $this->batch?->admissionSetting?->is_admit_card_published;
    }

    public function canUpload(): bool
    {
        return $this->isAdmissionClosed()
            && $this->isPaymentClosed()
            && ! $this->isAdmitCardPublished();
    }

    public function performUpload(ExamCenterUploadService $service): void
    {
        if (! $this->batch || ! $this->canUpload()) {
            return;
        }

        $this->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:1024'],
        ], attributes: [
            'csv' => __('CSV file'),
        ]);

        if (! $this->csv instanceof TemporaryUploadedFile) {
            $this->addError('csv', __('Please choose a CSV file to upload.'));

            return;
        }

        try {
            $result = $service->import($this->batch, $this->csv->getRealPath());
        } catch (\Throwable $e) {
            $this->addError('csv', $e->getMessage());

            return;
        }

        $this->csv = null;
        $this->dispatch('close-modal', name: 'upload-exam-center');

        Toast::success(__('Imported :centers centers / :rooms rooms · seated :assigned applicants.', $result));
    }

    public function with(): array
    {
        if (! $this->batch) {
            return [
                'centersByNo' => collect(),
                'roomCount' => 0,
                'totalCapacity' => 0,
                'confirmedCount' => 0,
                'centerCount' => 0,
                'isExamCenterUploaded' => false,
            ];
        }

        $rooms = ExamCenter::query()
            ->where('batch_id', $this->batch->id)
            ->withCount([
                'applications as assigned_count' => fn ($q) => $q->whereIn(
                    'payment_status',
                    [PaymentStatusEnum::PAID->value, PaymentStatusEnum::COMPLETED->value]
                ),
            ])
            ->orderBy('center_no')
            ->orderBy('room_name')
            ->get();

        $centersByNo = $rooms->groupBy('center_no');

        $totalCapacity = (int) $rooms->sum('capacity');
        $confirmedCount = Application::where('batch_id', $this->batch->id)
            ->whereIn('payment_status', [PaymentStatusEnum::PAID->value, PaymentStatusEnum::COMPLETED->value])
            ->count();

        return [
            'centersByNo' => $centersByNo,
            'roomCount' => $rooms->count(),
            'totalCapacity' => $totalCapacity,
            'confirmedCount' => $confirmedCount,
            'centerCount' => $centersByNo->count(),
            'isExamCenterUploaded' => (bool) $this->batch->admissionSetting?->is_exam_center_uploaded,
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
        @if ($batch)
            <div class="flex items-center gap-2 flex-wrap">
                @if ($isExamCenterUploaded)
                    <x-ui.button variant="outline" icon="file-text"
                        href="{{ route('pdf.attendance-sheet.all', $batch->id) }}" target="_blank" rel="noopener">
                        {{ __('Full Attendance Sheet') }}
                    </x-ui.button>
                    <x-ui.button variant="outline" icon="tag" href="{{ route('pdf.seat-labels', $batch->id) }}"
                        target="_blank" rel="noopener">
                        {{ __('Seat Labels') }}
                    </x-ui.button>
                @else
                    <x-ui.button variant="outline" icon="file-text" wire:click="openNotUploadedModal">
                        {{ __('Full Attendance Sheet') }}
                    </x-ui.button>
                    <x-ui.button variant="outline" icon="tag" wire:click="openNotUploadedModal">
                        {{ __('Seat Labels') }}
                    </x-ui.button>
                @endif
                <x-ui.button variant="primary" icon="upload" wire:click="openUploadModal">
                    {{ __('Upload Exam Center') }}
                </x-ui.button>
            </div>
        @endif
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
                    {{ trans_choice(':count room|:count rooms', $roomCount, ['count' => number_format($roomCount)]) }}
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

        {{-- Centers & Rooms (grouped) --}}
        @forelse ($centersByNo as $centerNo => $rooms)
            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white">

                {{-- Center header --}}
                <div class="flex items-center justify-between bg-zinc-50 px-5 py-3">
                    <div class="flex items-center gap-3">
                        <span class="font-semibold text-zinc-900">{{ $rooms->first()->center_name }}</span>
                        <span class="rounded bg-zinc-200 px-1.5 py-0.5 text-xs font-medium text-zinc-600">
                            {{ __('Center :no', ['no' => $centerNo]) }}
                        </span>
                    </div>
                    <span class="text-xs text-zinc-400">
                        {{ trans_choice(':count room|:count rooms', $rooms->count(), ['count' => $rooms->count()]) }}
                    </span>
                </div>

                {{-- Rooms table --}}
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b border-zinc-200 text-left text-xs font-semibold uppercase tracking-wide text-zinc-400">
                            <th class="px-5 py-2.5">{{ __('Room') }}</th>
                            <th class="px-5 py-2.5 text-center">{{ __('Capacity') }}</th>
                            <th class="px-5 py-2.5 text-center">{{ __('Assigned') }}</th>
                            <th class="px-5 py-2.5 text-right">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach ($rooms as $room)
                            <tr>
                                <td class="px-5 py-3 font-medium text-zinc-900">{{ $room->room_name }}</td>
                                <td class="px-5 py-3 text-center text-zinc-500 tabular-nums">
                                    {{ number_format($room->capacity) }}
                                </td>
                                <td class="px-5 py-3 text-center tabular-nums">
                                    <span @class([
                                        'font-semibold',
                                        'text-emerald-600' => $room->assigned_count > 0,
                                        'text-zinc-400' => $room->assigned_count === 0,
                                    ])>
                                        {{ number_format($room->assigned_count) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    @if ($room->assigned_count > 0)
                                        @if ($isExamCenterUploaded)
                                            <x-ui.button size="sm" icon="file-text"
                                                href="{{ route('pdf.attendance-sheet', $room->id) }}" target="_blank"
                                                rel="noopener">
                                                {{ __('Attendance Sheet') }}
                                            </x-ui.button>
                                        @else
                                            <x-ui.button size="sm" icon="file-text"
                                                wire:click="openNotUploadedModal">
                                                {{ __('Attendance Sheet') }}
                                            </x-ui.button>
                                        @endif
                                    @else
                                        <span class="text-xs text-zinc-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-zinc-200 bg-white px-6 py-16 text-center">
                <p class="text-sm text-zinc-500">
                    {{ __('No exam centers uploaded yet for this batch.') }}
                </p>
            </div>
        @endforelse
    @endif

    {{-- ===================== UPLOAD MODAL ===================== --}}
    <x-ui.modal name="upload-exam-center" :title="__('Upload Exam Centers')" maxWidth="lg">
        @if ($batch)
            <div class="space-y-4">
                {{-- Rules / checklist --}}
                <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4">
                    <p class="text-sm font-semibold text-zinc-700 mb-3">
                        {{ __('All conditions must be satisfied before uploading:') }}
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
                                <span class="text-red-700 font-medium">{{ __('Payment acceptance is still open') }}</span>
                            @endif
                        </li>
                        <li class="flex items-center gap-2">
                            @if (! $this->isAdmitCardPublished())
                                <x-lucide-check-circle class="size-5 text-green-600 shrink-0" />
                                <span class="text-zinc-700">{{ __('Admit cards are not published yet') }}</span>
                            @else
                                <x-lucide-x-circle class="size-5 text-red-600 shrink-0" />
                                <span class="text-red-700 font-medium">{{ __('Admit cards are already published — exam centers cannot be re-uploaded') }}</span>
                            @endif
                        </li>
                    </ul>
                </div>

                @if ($this->canUpload())
                    {{-- Sample CSV info --}}
                    <div class="rounded-lg border border-brand/15 bg-brand-soft px-4 py-3 text-xs text-zinc-700 flex items-start gap-2">
                        <x-lucide-info class="size-4 shrink-0 text-brand mt-0.5" />
                        <p class="leading-relaxed">
                            {{ __('Expected columns:') }}
                            <code class="font-mono text-zinc-900">center_no, center_name, room_name, capacity</code>.
                            {{ __('Total capacity must be at least equal to the number of confirmed applicants. Uploading will replace existing centers and reseat all confirmed applicants in roll order.') }}
                            <a href="{{ asset('sample_csv/exam_centers_sample.csv') }}" download
                                class="block mt-1 font-semibold text-brand hover:text-brand-dark">
                                <x-lucide-download class="inline size-3.5 mr-1" />{{ __('Download sample CSV') }}
                            </a>
                        </p>
                    </div>

                    {{-- File picker --}}
                    <div>
                        <label for="csv-upload"
                            class="flex flex-col items-center justify-center gap-2 px-4 py-6 rounded-lg border border-dashed border-zinc-300 bg-zinc-50 hover:bg-zinc-100 cursor-pointer transition-colors"
                            wire:loading.class="opacity-60" wire:target="csv">
                            <x-lucide-upload class="size-5 text-zinc-500" wire:loading.remove wire:target="csv" />
                            <x-lucide-loader-2 class="size-5 text-zinc-500 animate-spin" wire:loading
                                wire:target="csv" />
                            <span class="text-sm font-semibold text-zinc-700">
                                <span wire:loading.remove wire:target="csv">
                                    {{ $csv ? __('Choose a different CSV file') : __('Click to select a CSV file') }}
                                </span>
                                <span wire:loading wire:target="csv">{{ __('Uploading…') }}</span>
                            </span>
                            <span class="text-xs text-zinc-500">{{ __('CSV up to 1 MB') }}</span>
                        </label>
                        <input id="csv-upload" type="file" class="sr-only" accept=".csv,text/csv" wire:model="csv" />

                        @if ($csv instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile)
                            <div class="mt-2 rounded-lg border border-brand/20 bg-brand-soft px-3 py-2 flex items-center gap-2">
                                <x-lucide-file-check-2 class="size-4 text-brand shrink-0" />
                                <span
                                    class="flex-1 text-xs font-medium text-zinc-800 truncate">{{ $csv->getClientOriginalName() }}</span>
                                <span class="text-xs text-zinc-500">{{ number_format($csv->getSize() / 1024, 1) }}
                                    KB</span>
                                <button type="button" wire:click="$set('csv', null)"
                                    class="text-xs font-semibold text-red-600 hover:text-red-700">
                                    {{ __('Remove') }}
                                </button>
                            </div>
                        @endif

                        @error('csv')
                            <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                @else
                    <p class="text-sm text-zinc-500">
                        {{ __('Close this dialog and come back once the conditions above are met.') }}
                    </p>
                @endif
            </div>

            {{-- Footer --}}
            <div class="flex justify-end items-center gap-2 mt-6 pt-4 border-t border-zinc-100">
                <x-ui.button variant="ghost" wire:click="closeUploadModal">
                    {{ __('Close') }}
                </x-ui.button>

                @if ($this->canUpload())
                    <x-ui.button variant="primary" wire:click="performUpload" wire:loading.attr="disabled"
                        wire:target="performUpload,csv">
                        <x-lucide-loader-2 class="animate-spin" wire:loading wire:target="performUpload" />
                        <x-lucide-check wire:loading.remove wire:target="performUpload" />
                        <span wire:loading.remove wire:target="performUpload">{{ __('Save & assign seats') }}</span>
                        <span wire:loading wire:target="performUpload">{{ __('Processing…') }}</span>
                    </x-ui.button>
                @endif
            </div>
        @endif
    </x-ui.modal>

    {{-- ===================== NOT-UPLOADED WARNING MODAL ===================== --}}
    <x-ui.modal name="exam-center-not-uploaded" :title="__('Exam Center Not Uploaded')" maxWidth="md">
        <div class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
            <x-lucide-alert-triangle class="size-5 shrink-0 text-amber-600 mt-0.5" />
            <p class="text-sm text-zinc-700 leading-relaxed">
                {{ __('Exam center is not uploaded yet. Upload the centers CSV first to generate attendance sheets and seat labels.') }}
            </p>
        </div>

        <div class="flex justify-end items-center gap-2 mt-6 pt-4 border-t border-zinc-100">
            <x-ui.button variant="ghost" x-on:click="$dispatch('close-modal', { name: 'exam-center-not-uploaded' })">
                {{ __('Close') }}
            </x-ui.button>
        </div>
    </x-ui.modal>
</div>

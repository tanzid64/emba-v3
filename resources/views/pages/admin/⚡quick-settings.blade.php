<?php

use App\Enum\BatchStatusEnum;
use App\Models\AdmissionSetting;
use App\Models\Batch;
use App\Support\CurrentBatch;
use App\Support\Toast;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public ?Batch $batch = null;

    public ?AdmissionSetting $settings = null;

    public bool $isLocked = false;

    public ?string $editingField = null;

    public mixed $fieldValue = null;

    public mixed $fieldValueEnd = null;

    public $notice = null;

    public function mount(): void
    {
        $this->loadState();
    }

    private function loadState(): void
    {
        $this->batch = CurrentBatch::get()?->loadMissing('admissionSetting');
        $this->settings = $this->batch?->admissionSetting;
        $this->isLocked = $this->batch?->status === BatchStatusEnum::CLOSED;
        $this->editingField = null;
        $this->fieldValue = null;
        $this->fieldValueEnd = null;
        $this->notice = null;
        $this->resetErrorBag();
    }

    public function startEdit(string $field): void
    {
        if ($this->isLockedByAdmitCard($field)) {
            $this->dispatch('open-modal', name: 'admit-card-lock-warning');

            return;
        }

        if (! $this->canEdit($field)) {
            return;
        }

        $this->resetErrorBag();
        $this->editingField = $field;
        $this->fieldValue = null;
        $this->fieldValueEnd = null;
        $this->notice = null;

        if ($field === 'application_period') {
            $this->fieldValue = $this->settings->getRawOriginal('intake_started_at') ?? '';
            $this->fieldValueEnd = $this->settings->getRawOriginal('intake_ended_at') ?? '';
        } elseif ($field !== 'notice') {
            $this->fieldValue = $this->rawValueFor($field);
        }

        $this->dispatch('open-modal', name: 'edit-quick-setting');
    }

    public function cancelEdit(): void
    {
        $this->resetErrorBag();
        $this->editingField = null;
        $this->fieldValue = null;
        $this->fieldValueEnd = null;
        $this->notice = null;
        $this->dispatch('close-modal', name: 'edit-quick-setting');
    }

    public function saveField(): void
    {
        if (! $this->editingField || ! $this->canEdit($this->editingField)) {
            return;
        }

        if ($this->editingField === 'application_period') {
            $this->saveApplicationPeriod();

            return;
        }

        if ($this->editingField === 'notice') {
            $this->saveNotice();

            return;
        }

        $field = $this->editingField;

        $validated = $this->validate([
            'fieldValue' => $this->rulesFor($field),
        ]);

        $value = $validated['fieldValue'];
        if ($value === '' || $value === null) {
            $value = null;
        }

        if (in_array($field, ['application_number_start_from', 'roll_number_start_from'], true)) {
            $applicationColumn = $field === 'application_number_start_from' ? 'application_number' : 'roll_number';
            $currentMax = $this->highestIssuedNumberFor($applicationColumn);

            if ($currentMax !== null && (int) $value <= $currentMax) {
                $this->addError('fieldValue', __(':label cannot be set to :value — current highest number in this batch is :max.', [
                    'label' => $this->labelFor($field),
                    'value' => $value,
                    'max' => $currentMax,
                ]));

                return;
            }
        }

        $this->settings->update([$field => $value]);

        Toast::success(__(':label updated.', ['label' => $this->labelFor($field)]));

        $this->loadState();
        $this->dispatch('close-modal', name: 'edit-quick-setting');
    }

    private function saveApplicationPeriod(): void
    {
        $validated = $this->validate([
            'fieldValue' => ['nullable', 'date'],
            'fieldValueEnd' => ['nullable', 'date', 'after_or_equal:fieldValue'],
        ], attributes: [
            'fieldValue' => __('intake start'),
            'fieldValueEnd' => __('intake end'),
        ]);

        $this->settings->update([
            'intake_started_at' => $validated['fieldValue'] ?: null,
            'intake_ended_at' => $validated['fieldValueEnd'] ?: null,
        ]);

        Toast::success(__('Application Period updated.'));

        $this->loadState();
        $this->dispatch('close-modal', name: 'edit-quick-setting');
    }

    public function updatedNotice(): void
    {
        // Just validate the staged file; persistence happens on Save.
        $this->resetErrorBag('notice');

        if (! $this->notice instanceof TemporaryUploadedFile) {
            return;
        }

        $this->validate([
            'notice' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], attributes: [
            'notice' => __('circular PDF'),
        ]);
    }

    private function saveNotice(): void
    {
        if (! $this->notice instanceof TemporaryUploadedFile) {
            $this->addError('notice', __('Please choose a file to upload.'));

            return;
        }

        $this->validate([
            'notice' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], attributes: [
            'notice' => __('circular PDF'),
        ]);

        $previous = $this->settings->notice;
        if ($previous && Storage::disk('public')->exists($previous)) {
            Storage::disk('public')->delete($previous);
        }

        $path = $this->notice->store('admission-notices', 'public');
        $this->settings->update(['notice' => $path]);

        Toast::success(__('Circular PDF updated.'));

        $this->loadState();
        $this->dispatch('close-modal', name: 'edit-quick-setting');
    }

    private function canEdit(string $field): bool
    {
        if (! $this->settings || $this->isLocked) {
            return false;
        }

        // Milestone fields are managed via a workflow elsewhere, not from this card.
        if (in_array($field, ['admit_card_published_at', 'exam_center_uploaded_at', 'result_published_at'], true)) {
            return false;
        }

        return true;
    }

    private function rulesFor(string $field): array
    {
        return match ($field) {
            'application_fee', 'enrollment_fee', 'admission_fee' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'application_payment_ended_at' => ['nullable', 'date'],
            'exam_date', 'viva_date' => ['nullable', 'date'],
            'application_number_start_from', 'roll_number_start_from' => ['required', 'integer', 'min:1', 'max:2147483647'],
            default => ['nullable', 'string'],
        };
    }

    private function highestIssuedNumberFor(string $column): ?int
    {
        if (! $this->batch) {
            return null;
        }

        $max = \App\Models\Application::where('batch_id', $this->batch->id)
            ->whereNotNull($column)
            ->when($column === 'roll_number', fn ($q) => $q->whereNotNull('application_number'))
            ->pluck($column)
            ->map(fn (string $raw): ?int => \App\Services\AdmissionNumberingService::extractSequenceInt($raw, $column))
            ->filter()
            ->max();

        return $max === null ? null : (int) $max;
    }

    private function rawValueFor(string $field): mixed
    {
        $raw = $this->settings->getRawOriginal($field);

        if ($raw === null) {
            return '';
        }

        if (in_array($field, ['exam_date', 'viva_date'], true)) {
            return str_replace(' ', 'T', substr((string) $raw, 0, 16));
        }

        return $raw;
    }

    public function inputTypeFor(string $field): string
    {
        return match ($field) {
            'application_fee', 'enrollment_fee', 'admission_fee' => 'number',
            'application_number_start_from', 'roll_number_start_from' => 'number',
            'exam_date', 'viva_date' => 'datetime-local',
            default => 'date',
        };
    }

    public function labelFor(string $field): string
    {
        return [
            'notice' => __('Circular PDF'),
            'application_period' => __('Application Period'),
            'application_payment_ended_at' => __('Payment Deadline'),
            'exam_date' => __('Exam Date'),
            'viva_date' => __('Viva Date'),
            'application_fee' => __('Application Fee'),
            'enrollment_fee' => __('Enrollment Fee'),
            'admission_fee' => __('Admission Fee'),
            'admit_card_published_at' => __('Admit Card Published'),
            'exam_center_uploaded_at' => __('Exam Center Uploaded'),
            'result_published_at' => __('Result Published At'),
            'application_number_start_from' => __('Application No. Starts From'),
            'roll_number_start_from' => __('Roll No. Starts From'),
        ][$field] ?? $field;
    }

    public function descriptionFor(string $field): string
    {
        return [
            'notice' => __('Replaces the current admission circular shown on the applicant portal and on every applicant\'s dashboard. The previous file is permanently removed from storage when the new one is saved.'),
            'application_period' => __('Defines when applicants can submit new applications. Outside this window the application form is closed and the dashboard shows the batch as not accepting applicants.'),
            'application_payment_ended_at' => __('Last day for applicants to pay the application fee. After this date, unpaid applications will be marked as expired and excluded from the exam roll.'),
            'application_fee' => __('Amount each applicant pays to submit their application. Changing this only affects payments made from now on — applicants who already paid are not re-billed.'),
            'enrollment_fee' => __('Amount paid after a candidate is shortlisted to confirm their seat. Does not retroactively change existing enrollment records.'),
            'admission_fee' => __('Final tuition / admission charge billed once enrollment is finalised. Used when generating admission invoices for selected candidates.'),
            'exam_date' => __('Scheduled written exam time. Shown on the admit card and on the applicant portal as soon as it is saved.'),
            'viva_date' => __('Scheduled viva voce / interview time. Visible to short-listed applicants after the written exam result is published.'),
            'application_number_start_from' => __('Starting integer for application numbers in this batch. Cannot be lowered below the highest number already issued.'),
            'roll_number_start_from' => __('Starting integer for roll numbers in this batch. Cannot be lowered below the highest roll number already issued.'),
        ][$field] ?? '';
    }

    public function displayValueFor(string $field): string
    {
        if (! $this->settings) {
            return '—';
        }

        // Notice is rendered separately in the template via $settings->notice_url.
        if ($field === 'notice') {
            return $this->settings->notice ? '1' : '';
        }

        // Composite: intake start — intake end.
        if ($field === 'application_period') {
            $start = $this->dateOnly($this->settings->intake_started_at);
            $end = $this->dateOnly($this->settings->intake_ended_at);

            if ($start === '' && $end === '') {
                return '';
            }

            return ($start ?: '—').' — '.($end ?: '—');
        }

        // Money fields use the raw decimal value.
        if (in_array($field, ['application_fee', 'enrollment_fee', 'admission_fee'], true)) {
            $raw = $this->settings->getRawOriginal($field);

            if ($raw === null) {
                return '';
            }

            return 'Tk '.number_format((float) $raw, ((float) $raw == (int) $raw) ? 0 : 2);
        }

        // Date-only fields — strip the time portion from the cast output.
        if (in_array($field, ['application_payment_ended_at', 'admit_card_published_at', 'exam_center_uploaded_at'], true)) {
            return $this->dateOnly($this->settings->{$field});
        }

        if (in_array($field, ['application_number_start_from', 'roll_number_start_from'], true)) {
            $raw = $this->settings->getRawOriginal($field);

            return $raw === null ? '' : (string) $raw;
        }

        // Datetime fields — use the cast's formatted output as-is.
        $value = $this->settings->{$field};

        return is_array($value) ? ($value['formatted'] ?? '') : '';
    }

    /**
     * Pulls just the date portion out of a DateFormatCast value.
     * Cast format is "d M, Y - h:i A" — keep everything before the dash.
     */
    private function dateOnly(mixed $value): string
    {
        if (! is_array($value) || ! isset($value['formatted'])) {
            return '';
        }

        return trim(explode(' - ', $value['formatted'])[0] ?? '');
    }

    public function isMilestone(string $field): bool
    {
        return in_array($field, ['admit_card_published_at', 'exam_center_uploaded_at', 'result_published_at'], true);
    }

    /**
     * Fields that become read-only once admit cards are published — the
     * dates printed on the admit card itself must not drift afterward.
     */
    private const LOCKED_AFTER_ADMIT_CARD_PUBLISH = [
        'application_period',
        'application_payment_ended_at',
        'exam_date',
    ];

    public function isAdmitCardPublished(): bool
    {
        return (bool) $this->settings?->is_admit_card_published;
    }

    public function isLockedByAdmitCard(string $field): bool
    {
        return $this->isAdmitCardPublished()
            && in_array($field, self::LOCKED_AFTER_ADMIT_CARD_PUBLISH, true);
    }

    /** @return array<int, array{key: string, kind: string}> */
    public function fields(): array
    {
        return [
            ['key' => 'notice', 'kind' => 'file'],
            ['key' => 'application_period', 'kind' => 'date_range'],
            ['key' => 'application_fee', 'kind' => 'money'],
            ['key' => 'application_payment_ended_at', 'kind' => 'date'],
            ['key' => 'enrollment_fee', 'kind' => 'money'],
            ['key' => 'admission_fee', 'kind' => 'money'],
            ['key' => 'application_number_start_from', 'kind' => 'integer'],
            ['key' => 'roll_number_start_from', 'kind' => 'integer'],
            ['key' => 'exam_date', 'kind' => 'datetime'],
            ['key' => 'viva_date', 'kind' => 'datetime'],
            ['key' => 'exam_center_uploaded_at', 'kind' => 'milestone'],
            ['key' => 'admit_card_published_at', 'kind' => 'milestone'],
            ['key' => 'result_published_at', 'kind' => 'milestone'],
        ];
    }
}; ?>

@php
    $inputClasses = 'block w-full rounded-lg border border-zinc-200 bg-white text-sm text-zinc-800 shadow-xs px-3 py-1.5 placeholder-zinc-400 focus:outline-none focus:border-brand';
    $errorClasses = 'mt-1 text-xs font-medium text-red-600';
    $labelTopClasses = 'text-xs text-zinc-500';
    $valueClasses = 'text-sm font-semibold text-zinc-900 mt-1';
@endphp

<div class="rounded-xl border border-zinc-200 bg-white">

    {{-- Card header --}}
    <div class="flex items-center justify-between gap-3 px-6 py-4 border-b border-zinc-100">
        <div class="flex items-center gap-2">
            <x-lucide-calendar-days class="size-4 text-zinc-500" />
            <h2 class="text-sm font-bold text-zinc-900">{{ __('Admission Settings') }}</h2>
        </div>
        @if ($batch)
            <div class="flex items-center gap-2">
                <span class="text-xs text-zinc-500">{{ $batch->name }} · {{ $batch->code }}</span>
                @if ($isLocked)
                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-zinc-500 bg-zinc-100 px-2 py-0.5 rounded-full">
                        <x-lucide-lock class="size-3" /> {{ __('Read-only') }}
                    </span>
                @endif
            </div>
        @endif
    </div>

    @if (! $batch)
        <p class="px-6 py-10 text-center text-sm text-zinc-500">{{ __('Pick a batch from the sidebar to view its settings.') }}</p>
    @elseif (! $settings)
        <div class="px-6 py-10 text-center">
            <p class="text-sm text-zinc-600 mb-3">{{ __('This batch has no admission settings yet.') }}</p>
            <x-ui.button variant="primary" icon="plus" :href="route('admin.batches.create')" wire:navigate>
                {{ __('Add settings') }}
            </x-ui.button>
        </div>
    @else
        {{-- Two-column grid of rows --}}
        <div class="grid grid-cols-1 md:grid-cols-2 divide-x-0 md:divide-x divide-zinc-100">
            @foreach ($this->fields() as $i => $f)
                @php
                    $field = $f['key'];
                    $kind = $f['kind'];
                    $label = $this->labelFor($field);
                    $display = $this->displayValueFor($field);
                    $hasValue = $display !== '' && $display !== '—';
                    $isEditing = $editingField === $field;
                    $rowSep = $i >= 2 ? 'border-t border-zinc-100' : '';
                @endphp

                <div class="flex items-center gap-3 px-6 py-4 {{ $rowSep }}">

                    {{-- Label + value --}}
                    <div class="flex-1 min-w-0">
                        <p class="{{ $labelTopClasses }}">{{ $label }}</p>

                        @if ($kind === 'file')
                            @if ($settings->notice)
                                <a href="{{ $settings->notice_url }}" target="_blank" class="mt-1 inline-flex items-center gap-1.5 text-sm font-semibold text-brand hover:text-brand-dark">
                                    <x-lucide-file-text class="size-4" />
                                    {{ __('View PDF') }}
                                </a>
                            @else
                                <p class="{{ $valueClasses }} text-zinc-400 italic">{{ __('Not uploaded') }}</p>
                            @endif
                        @elseif ($hasValue)
                            <p class="{{ $valueClasses }}">{{ $display }}</p>
                        @else
                            <p class="{{ $valueClasses }} text-zinc-400 italic">{{ __('Not set') }}</p>
                        @endif
                    </div>

                    {{-- Action --}}
                    <div class="shrink-0 flex items-center gap-1.5">
                        @if ($kind === 'file' && ! $isLocked)
                            <x-ui.button size="sm" variant="outline" wire:click="startEdit('notice')">
                                <x-lucide-upload />
                                {{ $settings->notice ? __('Replace') : __('Upload') }}
                            </x-ui.button>
                        @elseif ($kind === 'milestone' && $hasValue)
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-green-700 bg-green-50 px-2 py-1 rounded-full">
                                {{ __('Done') }}
                            </span>
                        @elseif ($this->canEdit($field))
                            <x-ui.button size="sm" variant="outline" wire:click="startEdit('{{ $field }}')">
                                <x-lucide-pencil />
                                {{ __('Edit') }}
                            </x-ui.button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @error('notice')
            <p class="px-6 pb-4 text-xs font-medium text-red-600">{{ $message }}</p>
        @enderror
    @endif

    {{-- ===================== EDIT MODAL ===================== --}}
    <x-ui.modal name="edit-quick-setting" :title="$editingField ? __('Edit :label', ['label' => $this->labelFor($editingField)]) : ''" maxWidth="lg">
        @if ($editingField)
            @php $description = $this->descriptionFor($editingField); @endphp

            @if ($description)
                <div class="mb-5 rounded-lg border border-brand/15 bg-brand-soft px-4 py-3 text-xs text-zinc-700 flex items-start gap-2">
                    <x-lucide-info class="size-4 shrink-0 text-brand mt-0.5" />
                    <p class="leading-relaxed">{{ $description }}</p>
                </div>
            @endif

            <div class="space-y-3">
                @if ($editingField === 'application_period')
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block mb-1.5 text-xs font-semibold text-zinc-700">{{ __('Intake start') }}</label>
                            <input
                                type="date"
                                wire:model="fieldValue"
                                class="{{ $inputClasses }}"
                            />
                            @error('fieldValue') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block mb-1.5 text-xs font-semibold text-zinc-700">{{ __('Intake end') }}</label>
                            <input
                                type="date"
                                wire:model="fieldValueEnd"
                                class="{{ $inputClasses }}"
                            />
                            @error('fieldValueEnd') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                        </div>
                    </div>
                @elseif ($editingField === 'notice')
                    @if ($settings->notice && ! $notice)
                        <div class="rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 flex items-center gap-2">
                            <x-lucide-file-text class="size-4 text-zinc-500 shrink-0" />
                            <span class="flex-1 text-xs text-zinc-700">{{ __('Current file is set.') }}</span>
                            <a href="{{ $settings->notice_url }}" target="_blank" class="text-xs font-semibold text-brand hover:text-brand-dark">
                                {{ __('View current') }}
                            </a>
                        </div>
                    @endif

                    <label
                        for="notice-modal-upload"
                        class="flex flex-col items-center justify-center gap-2 px-4 py-6 rounded-lg border border-dashed border-zinc-300 bg-zinc-50 hover:bg-zinc-100 cursor-pointer transition-colors"
                        wire:loading.class="opacity-60"
                        wire:target="notice"
                    >
                        <x-lucide-upload class="size-5 text-zinc-500" wire:loading.remove wire:target="notice" />
                        <x-lucide-loader-2 class="size-5 text-zinc-500 animate-spin" wire:loading wire:target="notice" />
                        <span class="text-sm font-semibold text-zinc-700">
                            <span wire:loading.remove wire:target="notice">
                                {{ $notice ? __('Choose a different file') : __('Click to select a file') }}
                            </span>
                            <span wire:loading wire:target="notice">{{ __('Uploading…') }}</span>
                        </span>
                        <span class="text-xs text-zinc-500">{{ __('PDF, JPG or PNG up to 5 MB') }}</span>
                    </label>
                    <input
                        id="notice-modal-upload"
                        type="file"
                        class="sr-only"
                        accept="application/pdf,image/jpeg,image/png"
                        wire:model="notice"
                    />

                    @if ($notice instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile)
                        <div class="rounded-lg border border-brand/20 bg-brand-soft px-3 py-2 flex items-center gap-2">
                            <x-lucide-file-check-2 class="size-4 text-brand shrink-0" />
                            <span class="flex-1 text-xs font-medium text-zinc-800 truncate">{{ $notice->getClientOriginalName() }}</span>
                            <span class="text-xs text-zinc-500">{{ number_format($notice->getSize() / 1024, 1) }} KB</span>
                            <button type="button" wire:click="$set('notice', null)" class="text-xs font-semibold text-red-600 hover:text-red-700">
                                {{ __('Remove') }}
                            </button>
                        </div>
                    @endif

                    @error('notice') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                @else
                    <div>
                        <label class="block mb-1.5 text-xs font-semibold text-zinc-700">{{ $this->labelFor($editingField) }}</label>
                        <input
                            type="{{ $this->inputTypeFor($editingField) }}"
                            wire:model="fieldValue"
                            wire:keydown.enter="saveField"
                            @if (in_array($editingField, ['application_fee', 'enrollment_fee', 'admission_fee'])) step="0.01" min="0" @endif
                            class="{{ $inputClasses }}"
                            autofocus
                        />
                        @error('fieldValue') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                    </div>
                @endif
            </div>

            <div class="flex justify-end items-center gap-2 mt-6 pt-4 border-t border-zinc-100">
                <x-ui.button variant="ghost" wire:click="cancelEdit">
                    {{ __('Cancel') }}
                </x-ui.button>
                <x-ui.button variant="primary" wire:click="saveField" wire:loading.attr="disabled" wire:target="saveField">
                    <x-lucide-loader-2 class="animate-spin" wire:loading wire:target="saveField" />
                    <x-lucide-check wire:loading.remove wire:target="saveField" />
                    <span wire:loading.remove wire:target="saveField">{{ __('Save changes') }}</span>
                    <span wire:loading wire:target="saveField">{{ __('Saving…') }}</span>
                </x-ui.button>
            </div>
        @endif
    </x-ui.modal>

    {{-- ===================== ADMIT-CARD LOCK WARNING ===================== --}}
    <x-ui.modal name="admit-card-lock-warning" :title="__('Field Locked')" maxWidth="md">
        <div class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
            <x-lucide-alert-triangle class="size-5 shrink-0 text-amber-600 mt-0.5" />
            <p class="text-sm text-zinc-700 leading-relaxed">
                {{ __('You cannot update this after publishing admit cards. Intake dates, payment deadline and exam date are locked once admit cards are published, since these dates are printed on the cards already issued to applicants.') }}
            </p>
        </div>

        <div class="flex justify-end items-center gap-2 mt-6 pt-4 border-t border-zinc-100">
            <x-ui.button variant="ghost" x-on:click="$dispatch('close-modal', { name: 'admit-card-lock-warning' })">
                {{ __('Close') }}
            </x-ui.button>
        </div>
    </x-ui.modal>
</div>

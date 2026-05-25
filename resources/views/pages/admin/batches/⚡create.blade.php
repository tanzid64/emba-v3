<?php

use App\Enum\BatchStatusEnum;
use App\Models\AdmissionSetting;
use App\Models\Batch;
use App\Support\Toast;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('New Admission Batch')]
#[Layout('layouts.app')]
class extends Component {
    use WithFileUploads;

    /** @var array<string, mixed> */
    public array $batch = [
        'name' => '',
        'code' => '',
        'admission_year' => '',
        'status' => BatchStatusEnum::DRAFT->value,
    ];

    /** @var array<string, mixed> */
    public array $settings = [
        'intake_started_at' => '',
        'intake_ended_at' => '',
        'application_payment_ended_at' => '',
        'exam_date' => '',
        'viva_date' => '',
        'application_fee' => 2500,
        'enrollment_fee' => 500,
        'admission_fee' => 12000,
    ];

    public $notice = null;

    public function mount(): void
    {
        $this->batch['admission_year'] = (int) now()->year;
    }

    public function updatedBatchCode(string $value): void
    {
        $this->batch['code'] = strtoupper(trim($value));
    }

    public function save()
    {
        $statusValues = array_column(BatchStatusEnum::cases(), 'value');

        $validated = $this->validate([
            'batch.name' => ['required', 'string', 'max:255', 'unique:batches,name'],
            'batch.code' => ['required', 'string', 'max:50', 'unique:batches,code'],
            'batch.admission_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'batch.status' => ['required', 'string', 'in:'.implode(',', $statusValues)],

            'settings.intake_started_at' => ['nullable', 'date'],
            'settings.intake_ended_at' => ['nullable', 'date', 'after_or_equal:settings.intake_started_at'],
            'settings.application_payment_ended_at' => ['nullable', 'date', 'after_or_equal:settings.intake_started_at'],
            'settings.exam_date' => ['nullable', 'date'],
            'settings.viva_date' => ['nullable', 'date', 'after_or_equal:settings.exam_date'],

            'settings.application_fee' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'settings.enrollment_fee' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'settings.admission_fee' => ['required', 'numeric', 'min:0', 'max:1000000'],

            'notice' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], attributes: [
            'batch.name' => __('batch name'),
            'batch.code' => __('batch code'),
            'batch.admission_year' => __('admission year'),
            'batch.status' => __('status'),
            'settings.intake_started_at' => __('intake start date'),
            'settings.intake_ended_at' => __('intake end date'),
            'settings.application_payment_ended_at' => __('payment deadline'),
            'settings.exam_date' => __('exam date'),
            'settings.viva_date' => __('viva date'),
            'settings.application_fee' => __('application fee'),
            'settings.enrollment_fee' => __('enrollment fee'),
            'settings.admission_fee' => __('admission fee'),
            'notice' => __('notice document'),
        ]);

        $batch = DB::transaction(function () use ($validated) {
            $batch = Batch::create($validated['batch']);

            $settingPayload = collect($validated['settings'])
                ->map(fn ($value) => $value === '' ? null : $value)
                ->all();

            if ($this->notice instanceof TemporaryUploadedFile) {
                $settingPayload['notice'] = $this->notice->store('admission-notices', 'public');
            }

            AdmissionSetting::create([
                'batch_id' => $batch->id,
                ...$settingPayload,
            ]);

            return $batch;
        });

        Toast::success(__('Batch ":name" created successfully.', ['name' => $batch->name]));

        return redirect()->route('admin.batches.index');
    }

    /** @return array<int, BatchStatusEnum> */
    public function statuses(): array
    {
        return BatchStatusEnum::cases();
    }
}; ?>

@php
    $inputClasses = 'block w-full rounded-lg border border-zinc-200 bg-white text-sm text-zinc-800 shadow-xs px-3 py-2 placeholder-zinc-400 focus:outline-none focus:border-zinc-400 disabled:opacity-50 disabled:cursor-not-allowed';
    $labelClasses = 'block mb-1.5 text-xs font-semibold text-zinc-700';
    $errorClasses = 'mt-1.5 text-xs font-medium text-red-600';
    $sectionCard = 'rounded-xl border border-zinc-200 bg-white p-6';
    $sectionLegend = 'text-sm font-semibold text-zinc-800';
    $sectionDescription = 'text-xs text-zinc-500 mb-5';
@endphp

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 lg:gap-6 lg:p-6">

    {{-- Header --}}
    <div class="flex items-start justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-zinc-900">{{ __('Add Admission Batch') }}</h1>
            <p class="text-sm text-zinc-500 mt-1">{{ __('Create a new admission cycle and configure its settings in one place.') }}</p>
        </div>
        <x-ui.button variant="ghost" icon="arrow-left" :href="route('admin.batches.index')" wire:navigate>
            {{ __('Back to batches') }}
        </x-ui.button>
    </div>

    <form wire:submit="save" class="flex flex-col gap-6" enctype="multipart/form-data">

        {{-- ===================== BATCH DETAILS ===================== --}}
        <fieldset class="{{ $sectionCard }}">
            <legend class="{{ $sectionLegend }}">{{ __('Batch details') }}</legend>
            <p class="{{ $sectionDescription }}">{{ __('Identify and label the batch.') }}</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="batch-name" class="{{ $labelClasses }}">{{ __('Batch name') }} <span class="text-red-500">*</span></label>
                    <x-ui.input id="batch-name" type="text" wire:model="batch.name" placeholder="e.g. 47th EMBA" autofocus />
                    @error('batch.name') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="batch-code" class="{{ $labelClasses }}">{{ __('Batch code') }} <span class="text-red-500">*</span></label>
                    <x-ui.input id="batch-code" type="text" wire:model.blur="batch.code" placeholder="e.g. EMBA47" class="uppercase placeholder:normal-case" />
                    <p class="mt-1.5 text-xs text-zinc-500">{{ __('Auto-uppercased on blur.') }}</p>
                    @error('batch.code') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="batch-year" class="{{ $labelClasses }}">{{ __('Admission year') }} <span class="text-red-500">*</span></label>
                    <x-ui.input id="batch-year" type="number" wire:model="batch.admission_year" placeholder="2026" min="2000" max="2100" />
                    @error('batch.admission_year') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="batch-status" class="{{ $labelClasses }}">{{ __('Status') }} <span class="text-red-500">*</span></label>
                    <select id="batch-status" wire:model="batch.status" class="{{ $inputClasses }}">
                        @foreach ($this->statuses() as $status)
                            <option value="{{ $status->value }}">{{ ucfirst($status->value) }}</option>
                        @endforeach
                    </select>
                    @error('batch.status') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                </div>
            </div>
        </fieldset>

        {{-- ===================== INTAKE WINDOW ===================== --}}
        <fieldset class="{{ $sectionCard }}">
            <legend class="{{ $sectionLegend }}">{{ __('Intake window') }}</legend>
            <p class="{{ $sectionDescription }}">{{ __('Application open/close dates and the payment cut-off.') }}</p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div>
                    <label class="{{ $labelClasses }}">{{ __('Intake start') }}</label>
                    <x-ui.input type="date" wire:model="settings.intake_started_at" />
                    @error('settings.intake_started_at') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="{{ $labelClasses }}">{{ __('Intake end') }}</label>
                    <x-ui.input type="date" wire:model="settings.intake_ended_at" />
                    @error('settings.intake_ended_at') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="{{ $labelClasses }}">{{ __('Payment deadline') }}</label>
                    <x-ui.input type="date" wire:model="settings.application_payment_ended_at" />
                    @error('settings.application_payment_ended_at') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                </div>
            </div>
        </fieldset>

        {{-- ===================== NOTICE DOCUMENT ===================== --}}
        <fieldset class="{{ $sectionCard }}">
            <legend class="{{ $sectionLegend }}">{{ __('Admission notice') }}</legend>
            <p class="{{ $sectionDescription }}">{{ __('Upload the official admission circular shown to applicants. PDF, JPG, or PNG up to 5 MB.') }}</p>

            <div class="flex items-start gap-4 flex-wrap">
                <label
                    for="notice-upload"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-dashed border-zinc-300 text-sm font-semibold text-zinc-700 bg-zinc-50 hover:bg-zinc-100 cursor-pointer transition-colors"
                    wire:loading.class="opacity-60"
                    wire:target="notice"
                >
                    <x-lucide-upload class="size-4" />
                    <span wire:loading.remove wire:target="notice">{{ __('Choose file') }}</span>
                    <span wire:loading wire:target="notice">{{ __('Uploading…') }}</span>
                </label>
                <input
                    id="notice-upload"
                    type="file"
                    class="sr-only"
                    accept="application/pdf,image/jpeg,image/png"
                    wire:model="notice"
                />

                @if ($notice)
                    <div class="flex items-center gap-2 text-sm text-zinc-700">
                        <x-lucide-file-text class="size-4 text-zinc-500" />
                        <span class="font-medium truncate max-w-xs">{{ $notice->getClientOriginalName() }}</span>
                        <span class="text-xs text-zinc-500">({{ number_format($notice->getSize() / 1024, 1) }} KB)</span>
                        <button type="button" wire:click="$set('notice', null)" class="text-red-600 hover:text-red-700 text-xs font-semibold ml-1">
                            {{ __('Remove') }}
                        </button>
                    </div>
                @endif
            </div>

            @error('notice') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
        </fieldset>

        {{-- ===================== FEES ===================== --}}
        <fieldset class="{{ $sectionCard }}">
            <legend class="{{ $sectionLegend }}">{{ __('Fees') }}</legend>
            <p class="{{ $sectionDescription }}">{{ __('Amount applicants must pay at each stage (BDT).') }}</p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div>
                    <label class="{{ $labelClasses }}">{{ __('Application fee') }} <span class="text-red-500">*</span></label>
                    <x-ui.input type="number" step="0.01" min="0" wire:model="settings.application_fee" />
                    @error('settings.application_fee') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="{{ $labelClasses }}">{{ __('Enrollment fee') }} <span class="text-red-500">*</span></label>
                    <x-ui.input type="number" step="0.01" min="0" wire:model="settings.enrollment_fee" />
                    @error('settings.enrollment_fee') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="{{ $labelClasses }}">{{ __('Admission fee') }} <span class="text-red-500">*</span></label>
                    <x-ui.input type="number" step="0.01" min="0" wire:model="settings.admission_fee" />
                    @error('settings.admission_fee') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                </div>
            </div>
        </fieldset>

        {{-- ===================== EXAM SCHEDULE ===================== --}}
        <fieldset class="{{ $sectionCard }}">
            <legend class="{{ $sectionLegend }}">{{ __('Exam schedule') }}</legend>
            <p class="{{ $sectionDescription }}">{{ __('Optional. Fill in once the schedule is confirmed.') }}</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="{{ $labelClasses }}">{{ __('Exam date') }}</label>
                    <x-ui.input type="datetime-local" wire:model="settings.exam_date" />
                    @error('settings.exam_date') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="{{ $labelClasses }}">{{ __('Viva date') }}</label>
                    <x-ui.input type="datetime-local" wire:model="settings.viva_date" />
                    @error('settings.viva_date') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                </div>
            </div>
        </fieldset>

        {{-- ===================== ACTIONS ===================== --}}
        <div class="flex items-center justify-end gap-3 pt-2">
            <x-ui.button variant="ghost" :href="route('admin.batches.index')" wire:navigate>
                {{ __('Cancel') }}
            </x-ui.button>
            <x-ui.button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="save">
                <x-lucide-loader-2 class="animate-spin" wire:loading wire:target="save" />
                <x-lucide-check wire:loading.remove wire:target="save" />
                <span wire:loading.remove wire:target="save">{{ __('Create batch') }}</span>
                <span wire:loading wire:target="save">{{ __('Creating…') }}</span>
            </x-ui.button>
        </div>
    </form>
</div>

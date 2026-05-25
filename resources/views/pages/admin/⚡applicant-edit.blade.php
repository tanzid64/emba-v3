<?php

use App\Enums\AddressTypeEnum;
use App\Enums\BloodGroup;
use App\Enums\GenderEnum;
use App\Enums\MaritalStatus;
use App\Enums\ReligionEnum;
use App\Models\Address;
use App\Models\ApplicantProfile;
use App\Models\Application;
use App\Models\District;
use App\Models\EducationHistory;
use App\Models\ExpHistory;
use App\Models\Upazila;
use App\Support\Toast;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Edit Applicant')]
#[Layout('layouts.app')]
class extends Component {
    use WithFileUploads;

    public Application $application;

    public string $email = '';

    public string $phone_number = '';

    public $photo = null;

    public string $existingPhotoUrl = '';

    public bool $hasExistingPhoto = false;

    /** @var array<string, mixed> */
    public array $profile = [
        'full_name' => '',
        'father_name' => '',
        'mother_name' => '',
        'date_of_birth' => '',
        'gender' => GenderEnum::OTHER->value,
        'blood_group' => BloodGroup::UNKNOWN->value,
        'religion' => ReligionEnum::ISLAM->value,
        'marital_status' => MaritalStatus::SINGLE->value,
        'nationality' => 'Bangladeshi',
    ];

    /** @var array<string, array<string, mixed>> */
    public array $addresses = [];

    public bool $sameAsPresent = false;

    /** @var array<int, array<string, mixed>> */
    public array $educations = [];

    public int $totalYearsOfSchooling = 0;

    public float $totalYearsOfExperience = 0.0;

    /** @var array<int, array<string, mixed>> */
    public array $experiences = [];

    public function mount(Application $application): void
    {
        $this->application = $application->load([
            'applicant.profile',
            'applicant.addresses',
            'applicant.educationHistories',
            'applicant.expHistories',
            'batch',
        ]);

        $applicant = $this->application->applicant;

        $this->email = $applicant->email;
        $this->phone_number = $applicant->phone_number;
        $this->existingPhotoUrl = $applicant->profile?->photo_url ?? asset('assets/images/default-avatar.png');
        $this->hasExistingPhoto = filled($applicant->profile?->photo);
        $this->totalYearsOfSchooling = (int) ($applicant->profile?->tot_year_of_schooling ?? 0);
        $this->totalYearsOfExperience = (float) ($applicant->profile?->tot_year_of_exp ?? 0);

        if ($applicant->profile) {
            $this->profile = [
                'full_name' => $applicant->profile->full_name,
                'father_name' => $applicant->profile->father_name,
                'mother_name' => $applicant->profile->mother_name,
                'date_of_birth' => optional($applicant->profile->date_of_birth)->format('Y-m-d') ?? '',
                'gender' => $applicant->profile->gender?->value ?? GenderEnum::OTHER->value,
                'blood_group' => $applicant->profile->blood_group?->value ?? BloodGroup::UNKNOWN->value,
                'religion' => $applicant->profile->religion?->value ?? ReligionEnum::ISLAM->value,
                'marital_status' => $applicant->profile->marital_status?->value ?? MaritalStatus::SINGLE->value,
                'nationality' => $applicant->profile->nationality ?: 'Bangladeshi',
            ];
        }

        foreach (AddressTypeEnum::cases() as $type) {
            $existing = $applicant->addresses->firstWhere('type', $type);
            $this->addresses[$type->value] = [
                'id' => $existing?->id,
                'care' => $existing?->care ?? '',
                'road' => $existing?->road ?? '',
                'district_id' => $existing?->district_id ?? null,
                'upazila_id' => $existing?->upazila_id ?? null,
                'post_office' => $existing?->post_office ?? '',
                'postal_code' => $existing?->postal_code ?? '',
            ];
        }

        $this->sameAsPresent = $this->addressesIdentical();

        $existingByType = $applicant->educationHistories->keyBy(fn (EducationHistory $row) => $row->type?->value);

        $this->educations = [];
        foreach (config('degree.degrees') as $degree) {
            $row = $existingByType->get($degree['type']);
            $this->educations[$degree['type']] = [
                'id' => $row?->id,
                'name' => $row?->name ?? '',
                'major' => $row?->major ?? '',
                'institute' => $row?->institute ?? '',
                'result' => $row?->result ?? '',
                'scale' => $row?->scale ?? '',
                'passing_year' => $row?->passing_year,
                'duration' => $row?->duration,
            ];
        }

        $this->experiences = $applicant->expHistories
            ->map(fn (ExpHistory $row) => [
                'id' => $row->id,
                'organization' => $row->organization,
                'designation' => $row->designation,
                'duration' => $row->duration,
                'total_experience' => (float) $row->total_experience,
            ])
            ->values()
            ->all();

        if (empty($this->experiences)) {
            $this->experiences[] = $this->blankExperience();
        }
    }

    #[Computed]
    public function genders(): array
    {
        return GenderEnum::cases();
    }

    #[Computed]
    public function bloodGroups(): array
    {
        return BloodGroup::cases();
    }

    #[Computed]
    public function religions(): array
    {
        return ReligionEnum::cases();
    }

    #[Computed]
    public function maritalStatuses(): array
    {
        return MaritalStatus::cases();
    }

    #[Computed]
    public function degrees(): array
    {
        return config('degree.degrees', []);
    }

    #[Computed]
    public function scales(): array
    {
        return config('degree.scales', []);
    }

    #[Computed]
    public function durations(): array
    {
        return config('degree.durations', []);
    }

    #[Computed]
    public function passingYears(): array
    {
        return range(date('Y') + 1, 1970);
    }

    #[Computed]
    public function districts()
    {
        return District::orderBy('name')->get(['id', 'name']);
    }

    public function upazilasFor(?int $districtId)
    {
        if (! $districtId) {
            return collect();
        }

        return Upazila::where('district_id', $districtId)->orderBy('name')->get(['id', 'name']);
    }

    public function addExperience(): void
    {
        $this->experiences[] = $this->blankExperience();
    }

    public function removeExperience(int $index): void
    {
        unset($this->experiences[$index]);
        $this->experiences = array_values($this->experiences);

        if (empty($this->experiences)) {
            $this->experiences[] = $this->blankExperience();
        }
    }

    public function removePhoto(): void
    {
        $this->photo = null;
    }

    public function saveProfile(): void
    {
        foreach (['full_name', 'father_name', 'mother_name'] as $field) {
            $this->profile[$field] = mb_strtoupper(trim((string) $this->profile[$field]));
        }

        $applicant = $this->application->applicant;
        $previousEmail = $applicant->email;

        $validated = $this->validate([
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('applicants', 'email')->ignore($applicant->id)],
            'phone_number' => ['required', 'string', 'max:20'],
            'profile.full_name' => ['required', 'string', 'max:255'],
            'profile.father_name' => ['required', 'string', 'max:255'],
            'profile.mother_name' => ['required', 'string', 'max:255'],
            'profile.date_of_birth' => ['required', 'date', 'before:today'],
            'profile.gender' => ['required', 'string', 'in:'.implode(',', array_column(GenderEnum::cases(), 'value'))],
            'profile.blood_group' => ['required', 'string', 'in:'.implode(',', array_column(BloodGroup::cases(), 'value'))],
            'profile.religion' => ['required', 'string', 'in:'.implode(',', array_column(ReligionEnum::cases(), 'value'))],
            'profile.marital_status' => ['required', 'string', 'in:'.implode(',', array_column(MaritalStatus::cases(), 'value'))],
            'profile.nationality' => ['required', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ]);

        $applicant->fill([
            'email' => $validated['email'],
            'phone_number' => $validated['phone_number'],
        ]);

        // Admin edits are trusted — auto-verify a changed email so the
        // applicant doesn't get bounced into the verification flow.
        if ($previousEmail !== $validated['email']) {
            $applicant->email_verified_at = now();
        }

        $applicant->save();

        $payload = [...$validated['profile'], 'batch_id' => $applicant->batch_id];

        if ($this->photo instanceof TemporaryUploadedFile) {
            $existing = $applicant->profile?->photo;
            if ($existing && Storage::disk('public')->exists($existing)) {
                Storage::disk('public')->delete($existing);
            }
            $payload['photo'] = $this->photo->store('applicant-photos', 'public');
        }

        ApplicantProfile::updateOrCreate(['applicant_id' => $applicant->id], $payload);

        $applicant->refresh()->load('profile');
        $this->existingPhotoUrl = $applicant->profile?->photo_url ?? asset('assets/images/default-avatar.png');
        $this->hasExistingPhoto = filled($applicant->profile?->photo);
        $this->photo = null;

        Toast::success(__('Profile saved.'));
        $this->dispatch('go-to-tab', tab: 'addresses');
    }

    public function updatedSameAsPresent(bool $value): void
    {
        if ($value) {
            $this->mirrorPermanentFromPresent();
        }
    }

    public function updatedAddresses($value, ?string $key = null): void
    {
        if ($this->sameAsPresent && is_string($key) && str_starts_with($key, 'present.')) {
            $this->mirrorPermanentFromPresent();
        }
    }

    private function mirrorPermanentFromPresent(): void
    {
        $present = $this->addresses[AddressTypeEnum::PRESENT->value] ?? [];
        $permanentId = $this->addresses[AddressTypeEnum::PERMANENT->value]['id'] ?? null;

        $this->addresses[AddressTypeEnum::PERMANENT->value] = [
            'id' => $permanentId,
            'care' => $present['care'] ?? '',
            'road' => $present['road'] ?? '',
            'district_id' => $present['district_id'] ?? null,
            'upazila_id' => $present['upazila_id'] ?? null,
            'post_office' => $present['post_office'] ?? '',
            'postal_code' => $present['postal_code'] ?? '',
        ];
    }

    private function addressesIdentical(): bool
    {
        $present = $this->addresses[AddressTypeEnum::PRESENT->value] ?? null;
        $permanent = $this->addresses[AddressTypeEnum::PERMANENT->value] ?? null;

        if (! $present || ! $permanent) {
            return false;
        }

        $keys = ['care', 'road', 'district_id', 'upazila_id', 'post_office', 'postal_code'];
        $hasAny = collect($keys)->some(fn ($k) => filled($present[$k] ?? null));

        if (! $hasAny) {
            return false;
        }

        foreach ($keys as $k) {
            if (($present[$k] ?? null) !== ($permanent[$k] ?? null)) {
                return false;
            }
        }

        return true;
    }

    public function saveAddresses(): void
    {
        if ($this->sameAsPresent) {
            $this->mirrorPermanentFromPresent();
        }

        $rules = [];
        foreach (AddressTypeEnum::cases() as $type) {
            $rules["addresses.{$type->value}.care"] = ['nullable', 'string', 'max:255'];
            $rules["addresses.{$type->value}.road"] = ['nullable', 'string', 'max:255'];
            $rules["addresses.{$type->value}.district_id"] = ['nullable', 'integer', 'exists:districts,id'];
            $rules["addresses.{$type->value}.upazila_id"] = ['nullable', 'integer', 'exists:upazilas,id'];
            $rules["addresses.{$type->value}.post_office"] = ['nullable', 'string', 'max:255'];
            $rules["addresses.{$type->value}.postal_code"] = ['nullable', 'string', 'max:10'];
        }
        $this->validate($rules);

        foreach (AddressTypeEnum::cases() as $type) {
            $row = $this->addresses[$type->value];

            Address::updateOrCreate(
                ['applicant_id' => $this->application->applicant_id, 'type' => $type->value],
                [
                    'care' => $row['care'] ?: null,
                    'road' => $row['road'] ?: null,
                    'district_id' => $row['district_id'] ?: null,
                    'upazila_id' => $row['upazila_id'] ?: null,
                    'post_office' => $row['post_office'] ?: null,
                    'postal_code' => $row['postal_code'] ?: null,
                ],
            );
        }

        Toast::success(__('Addresses saved.'));
        $this->dispatch('go-to-tab', tab: 'education');
    }

    public function saveEducations(): void
    {
        $degrees = collect(config('degree.degrees'))->keyBy('type');
        $optionalTypes = ['Graduate', 'Other'];
        $valueFields = ['name', 'major', 'institute', 'result', 'scale', 'duration', 'passing_year'];
        $rules = [];

        foreach ($degrees as $type => $degree) {
            $isOptional = in_array($type, $optionalTypes, true);
            $hasAny = collect($valueFields)->some(fn ($k) => filled($this->educations[$type][$k] ?? null));

            if ($isOptional && ! $hasAny) {
                continue;
            }

            if (! empty($degree['has_options'])) {
                $rules["educations.$type.name"] = ['required', 'string', 'in:'.implode(',', $degree['options'] ?? [])];
            }
            $rules["educations.$type.major"] = ['required', 'string', 'max:255'];
            $rules["educations.$type.institute"] = ['required', 'string', 'max:255'];
            $rules["educations.$type.result"] = ['required', 'string', 'max:50'];
            $rules["educations.$type.scale"] = ['required', 'string', 'max:50'];
            $rules["educations.$type.duration"] = ['required', 'integer', 'min:1', 'max:12'];
            $rules["educations.$type.passing_year"] = ['required', 'integer', 'min:1950', 'max:'.(date('Y') + 5)];
        }

        $this->validate($rules);

        $applicant = $this->application->applicant;
        $totalDuration = 0;

        foreach ($degrees as $type => $degree) {
            $isOptional = in_array($type, $optionalTypes, true);
            $row = $this->educations[$type];
            $hasAny = collect($valueFields)->some(fn ($k) => filled($row[$k] ?? null));

            if ($isOptional && ! $hasAny) {
                EducationHistory::where('applicant_id', $applicant->id)->where('type', $type)->delete();

                continue;
            }

            $name = ! empty($degree['has_options']) ? $row['name'] : $degree['label'];
            $totalDuration += (int) $row['duration'];

            EducationHistory::updateOrCreate(
                ['applicant_id' => $applicant->id, 'type' => $type],
                [
                    'name' => $name,
                    'major' => $row['major'],
                    'institute' => $row['institute'],
                    'result' => $row['result'],
                    'scale' => $row['scale'],
                    'passing_year' => $row['passing_year'],
                    'duration' => $row['duration'],
                ],
            );
        }

        ApplicantProfile::where('applicant_id', $applicant->id)->update(['tot_year_of_schooling' => $totalDuration]);
        $this->totalYearsOfSchooling = $totalDuration;

        Toast::success(__('Education history saved.'));
        $this->dispatch('go-to-tab', tab: 'experience');
    }

    public function saveExperiences(): void
    {
        $this->validate([
            'experiences' => ['required', 'array', 'min:1'],
            'experiences.*.organization' => ['required', 'string', 'max:255'],
            'experiences.*.designation' => ['required', 'string', 'max:255'],
            'experiences.*.duration' => ['required', 'string', 'max:50'],
            'experiences.*.total_experience' => ['nullable', 'numeric', 'min:0', 'max:60'],
        ]);

        $applicant = $this->application->applicant;
        $keepIds = [];
        $totalExperience = 0.0;

        foreach ($this->experiences as $row) {
            $totalExperience += (float) ($row['total_experience'] ?? 0);

            $record = ExpHistory::updateOrCreate(
                ['id' => $row['id'] ?? null, 'applicant_id' => $applicant->id],
                [
                    'organization' => $row['organization'],
                    'designation' => $row['designation'],
                    'duration' => $row['duration'],
                    'total_experience' => $row['total_experience'] ?: null,
                ],
            );

            $keepIds[] = $record->id;
        }

        $applicant->expHistories()->whereNotIn('id', $keepIds)->delete();

        ApplicantProfile::where('applicant_id', $applicant->id)->update(['tot_year_of_exp' => $totalExperience]);
        $this->totalYearsOfExperience = $totalExperience;

        Toast::success(__('Experience history saved.'));
    }

    private function blankExperience(): array
    {
        return [
            'id' => null,
            'organization' => '',
            'designation' => '',
            'duration' => '',
            'total_experience' => null,
        ];
    }
}; ?>

@php
    $inputClasses = 'block w-full rounded-lg border border-zinc-200 bg-white text-sm text-zinc-800 shadow-xs px-3 py-2 placeholder-zinc-400 focus:outline-none focus:border-zinc-400 disabled:opacity-50 disabled:cursor-not-allowed';
    $labelClasses = 'block mb-1.5 text-xs font-semibold text-zinc-700';
    $errorClasses = 'mt-1.5 text-xs font-medium text-red-600';
    $sectionCard = 'rounded-xl border border-zinc-200 bg-white p-5';
    $sectionTitle = 'text-sm font-bold text-zinc-900';
    $tabs = [
        ['key' => 'profile', 'label' => 'Profile', 'icon' => 'user-circle'],
        ['key' => 'addresses', 'label' => 'Addresses', 'icon' => 'map-pin'],
        ['key' => 'education', 'label' => 'Education', 'icon' => 'graduation-cap'],
        ['key' => 'experience', 'label' => 'Experience', 'icon' => 'briefcase'],
    ];
@endphp

<div class="flex h-full w-full flex-1 flex-col gap-4 p-3 sm:p-4 lg:gap-6 lg:p-6"
    x-data="{
        tab: 'profile',
        validTabs: ['profile', 'addresses', 'education', 'experience'],
        init() {
            const fromHash = window.location.hash.replace('#', '');
            if (this.validTabs.includes(fromHash)) this.tab = fromHash;
            this.$watch('tab', (v) => history.replaceState(null, '', '#' + v));
            if (!window._embaAdminTabBridge && window.Livewire) {
                window._embaAdminTabBridge = true;
                window.Livewire.on('go-to-tab', (payload) => {
                    const data = Array.isArray(payload) ? payload[0] : payload;
                    if (this.validTabs.includes(data?.tab)) {
                        this.tab = data.tab;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                });
            }
        }
    }"
>
    {{-- ===================== HEADER ===================== --}}
    <div class="flex items-start justify-between gap-3 flex-wrap">
        <div class="flex items-start gap-4">
            <img
                src="{{ $existingPhotoUrl }}"
                alt=""
                class="size-20 rounded-xl object-cover bg-zinc-100 border border-zinc-200"
            />
            <div>
                <p class="text-xs font-bold uppercase tracking-widest text-brand">
                    {{ $application->batch?->name ?? '—' }} · {{ $application->batch?->code }}
                </p>
                <h1 class="text-2xl font-bold text-zinc-900 uppercase">
                    {{ $profile['full_name'] ?: __('Profile not completed') }}
                </h1>
                <p class="font-mono text-sm text-zinc-600 mt-1">{{ $application->application_number }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <x-ui.button variant="ghost" icon="arrow-left" :href="route('admin.applicants.show', $application)" wire:navigate>
                {{ __('Back to details') }}
            </x-ui.button>
        </div>
    </div>

    {{-- ===================== TAB BAR ===================== --}}
    <div class="rounded-xl border border-zinc-200 bg-white overflow-hidden">
        <div class="border-b border-zinc-200 bg-zinc-50 overflow-x-auto">
            <nav class="flex min-w-max">
                @foreach ($tabs as $t)
                    <button
                        type="button"
                        @click="tab = '{{ $t['key'] }}'"
                        :class="tab === '{{ $t['key'] }}'
                            ? 'text-brand border-b-2 border-brand bg-white'
                            : 'text-zinc-500 hover:text-zinc-800 border-b-2 border-transparent'"
                        class="flex items-center gap-2 px-5 py-3 text-sm font-semibold transition-colors -mb-px"
                    >
                        <x-dynamic-component :component="'lucide-' . $t['icon']" class="size-4" />
                        {{ $t['label'] }}
                    </button>
                @endforeach
            </nav>
        </div>

        {{-- ===================== PROFILE TAB ===================== --}}
        <div x-show="tab === 'profile'" x-cloak class="p-6 space-y-6">
            <form wire:submit="saveProfile" class="space-y-6" enctype="multipart/form-data">

                {{-- Profile photo --}}
                <fieldset class="{{ $sectionCard }}">
                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <legend class="{{ $sectionTitle }}">{{ __('Profile photo') }}</legend>
                        @if ($hasExistingPhoto && ! $photo)
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full">
                                <x-lucide-check class="size-3" /> {{ __('Uploaded') }}
                            </span>
                        @elseif (! $hasExistingPhoto && ! $photo)
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-amber-700 bg-amber-50 px-2.5 py-1 rounded-full">
                                <x-lucide-alert-circle class="size-3" /> {{ __('Not uploaded') }}
                            </span>
                        @endif
                    </div>

                    <div class="mt-5 grid grid-cols-1 md:grid-cols-[auto_1fr] gap-7 items-start">
                        @php $previewUrl = $photo ? $photo->temporaryUrl() : $existingPhotoUrl; @endphp

                        {{-- Avatar with camera overlay --}}
                        <div class="flex flex-col items-center md:items-start gap-3">
                            <div class="relative w-36 h-36" wire:loading.class="opacity-60" wire:target="photo">
                                <img
                                    src="{{ $previewUrl }}"
                                    alt="{{ __('Applicant photo') }}"
                                    class="w-36 h-36 rounded-full object-cover border-4 border-white ring-1 ring-zinc-200 shadow-md bg-zinc-100"
                                />
                                <label
                                    for="admin-photo"
                                    title="{{ __('Upload photo') }}"
                                    class="absolute bottom-1 right-1 w-10 h-10 rounded-full flex items-center justify-center text-white bg-brand shadow-md cursor-pointer transition-transform hover:scale-105 active:scale-95"
                                >
                                    <x-lucide-camera class="size-4" wire:loading.remove wire:target="photo" />
                                    <x-lucide-loader-2 class="size-4 animate-spin" wire:loading wire:target="photo" />
                                </label>
                                <input id="admin-photo" type="file" class="sr-only" accept="image/*" wire:model="photo" />
                            </div>

                            @if ($photo)
                                <div class="text-center md:text-left">
                                    <p class="text-xs font-semibold text-zinc-700 truncate max-w-[160px]">{{ $photo->getClientOriginalName() }}</p>
                                    <button type="button" wire:click="removePhoto" class="text-xs font-semibold text-red-600 hover:text-red-700 mt-1 inline-flex items-center gap-1">
                                        <x-lucide-x class="size-3" /> {{ __('Remove') }}
                                    </button>
                                </div>
                            @endif

                            @error('photo') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                        </div>

                        {{-- Tips --}}
                        <div class="rounded-lg border border-zinc-200 bg-zinc-50/60 p-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-zinc-500 mb-2">{{ __('Tips') }}</p>
                            <ul class="space-y-1.5 text-sm text-zinc-600">
                                <li class="flex items-start gap-2"><x-lucide-check class="size-3.5 text-emerald-600 mt-1 shrink-0" /> {{ __('Clear front-facing photo with a plain background.') }}</li>
                                <li class="flex items-start gap-2"><x-lucide-check class="size-3.5 text-emerald-600 mt-1 shrink-0" /> {{ __('Recent — taken within the last 6 months.') }}</li>
                                <li class="flex items-start gap-2"><x-lucide-check class="size-3.5 text-emerald-600 mt-1 shrink-0" /> {{ __('Any standard image format (JPG/PNG/WebP), up to 2 MB.') }}</li>
                            </ul>
                            <p class="mt-3 text-xs text-zinc-400">{{ __('Admin overrides any applicant-side restrictions.') }}</p>
                        </div>
                    </div>
                </fieldset>

                {{-- Contact --}}
                <fieldset class="{{ $sectionCard }}">
                    <legend class="{{ $sectionTitle }}">{{ __('Contact') }}</legend>
                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label class="{{ $labelClasses }}">{{ __('Email address') }} <span class="text-red-500">*</span></label>
                            <x-ui.input type="email" wire:model="email" placeholder="email@example.com" autocomplete="email" />
                            @error('email') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $labelClasses }}">{{ __('Phone number') }} <span class="text-red-500">*</span></label>
                            <x-ui.input type="tel" wire:model="phone_number" placeholder="01XXX-XXXXXX" autocomplete="tel" />
                            @error('phone_number') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </fieldset>

                {{-- Identity --}}
                <fieldset class="{{ $sectionCard }}">
                    <legend class="{{ $sectionTitle }}">{{ __('Identity') }}</legend>
                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label class="{{ $labelClasses }}">{{ __('Full name') }} <span class="text-red-500">*</span></label>
                            <x-ui.input type="text" wire:model="profile.full_name" class="uppercase placeholder:normal-case tracking-wide" placeholder="e.g. MD. TANZID HAQUE" />
                            @error('profile.full_name') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $labelClasses }}">{{ __('Date of birth') }} <span class="text-red-500">*</span></label>
                            <x-ui.input type="date" wire:model="profile.date_of_birth" />
                            @error('profile.date_of_birth') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $labelClasses }}">{{ __("Father's name") }} <span class="text-red-500">*</span></label>
                            <x-ui.input type="text" wire:model="profile.father_name" class="uppercase placeholder:normal-case tracking-wide" placeholder="{{ __('As per NID / Birth certificate') }}" />
                            @error('profile.father_name') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $labelClasses }}">{{ __("Mother's name") }} <span class="text-red-500">*</span></label>
                            <x-ui.input type="text" wire:model="profile.mother_name" class="uppercase placeholder:normal-case tracking-wide" placeholder="{{ __('As per NID / Birth certificate') }}" />
                            @error('profile.mother_name') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </fieldset>

                {{-- Demographics --}}
                <fieldset class="{{ $sectionCard }}">
                    <legend class="{{ $sectionTitle }}">{{ __('Demographics') }}</legend>
                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                        <div>
                            <label class="{{ $labelClasses }}">{{ __('Gender') }} <span class="text-red-500">*</span></label>
                            <select wire:model="profile.gender" class="{{ $inputClasses }}">
                                @foreach ($this->genders as $g)
                                    <option value="{{ $g->value }}">{{ $g->label() }}</option>
                                @endforeach
                            </select>
                            @error('profile.gender') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $labelClasses }}">{{ __('Blood group') }} <span class="text-red-500">*</span></label>
                            <select wire:model="profile.blood_group" class="{{ $inputClasses }}">
                                @foreach ($this->bloodGroups as $b)
                                    <option value="{{ $b->value }}">{{ $b->label() }}</option>
                                @endforeach
                            </select>
                            @error('profile.blood_group') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $labelClasses }}">{{ __('Religion') }} <span class="text-red-500">*</span></label>
                            <select wire:model="profile.religion" class="{{ $inputClasses }}">
                                @foreach ($this->religions as $r)
                                    <option value="{{ $r->value }}">{{ $r->label() }}</option>
                                @endforeach
                            </select>
                            @error('profile.religion') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $labelClasses }}">{{ __('Marital status') }} <span class="text-red-500">*</span></label>
                            <select wire:model="profile.marital_status" class="{{ $inputClasses }}">
                                @foreach ($this->maritalStatuses as $m)
                                    <option value="{{ $m->value }}">{{ $m->label() }}</option>
                                @endforeach
                            </select>
                            @error('profile.marital_status') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </fieldset>

                {{-- Background --}}
                <fieldset class="{{ $sectionCard }}">
                    <legend class="{{ $sectionTitle }}">{{ __('Background') }}</legend>
                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-5">
                        <div>
                            <label class="{{ $labelClasses }}">{{ __('Nationality') }}</label>
                            <x-ui.input type="text" wire:model="profile.nationality" />
                            @error('profile.nationality') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </fieldset>

                <div class="flex items-center justify-end gap-3">
                    <x-ui.button variant="primary" type="submit" icon="save" wire:loading.attr="disabled" wire:target="saveProfile">
                        <span wire:loading.remove wire:target="saveProfile">{{ __('Save profile') }}</span>
                        <span wire:loading wire:target="saveProfile">{{ __('Saving…') }}</span>
                    </x-ui.button>
                </div>
            </form>
        </div>

        {{-- ===================== ADDRESSES TAB ===================== --}}
        <div x-show="tab === 'addresses'" x-cloak class="p-6 space-y-6">
            <form wire:submit="saveAddresses" class="space-y-6">
                @foreach (AddressTypeEnum::cases() as $type)
                    @php
                        $isPermanent = $type === AddressTypeEnum::PERMANENT;
                        $locked = $isPermanent && $sameAsPresent;
                    @endphp
                    <fieldset class="{{ $sectionCard }}">
                        <legend class="sr-only">{{ $type->label() }} {{ __('Address') }}</legend>

                        {{-- Header row: icon + title + subtitle, with the
                             "same as present" toggle on the permanent card. --}}
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-4 mb-5 border-b border-zinc-100">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex items-center justify-center size-9 rounded-lg bg-brand text-white shrink-0">
                                    <x-lucide-map-pin class="size-4" />
                                </span>
                                <div>
                                    <h3 class="text-sm font-bold text-zinc-900">{{ $type->label() }} {{ __('Address') }}</h3>
                                    <p class="text-xs text-zinc-500 mt-0.5">
                                        {{ $isPermanent ? __('Long-term / home address on record.') : __('Where the applicant can be reached now.') }}
                                    </p>
                                </div>
                            </div>

                            @if ($isPermanent)
                                <label class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-zinc-50 border border-zinc-200 cursor-pointer select-none hover:border-zinc-300 transition">
                                    <input
                                        type="checkbox"
                                        wire:model.live="sameAsPresent"
                                        class="size-4 rounded border-zinc-300 text-brand focus:ring-brand/30"
                                    />
                                    <span class="text-xs font-semibold text-zinc-700">{{ __('Same as present address') }}</span>
                                </label>
                            @endif
                        </div>

                        {{-- Six input fields per address. --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 {{ $locked ? 'opacity-60' : '' }}">
                            <div>
                                <label class="{{ $labelClasses }}">{{ __('Care of') }}</label>
                                <x-ui.input type="text" wire:model="addresses.{{ $type->value }}.care" placeholder="{{ __('e.g. C/O Md. Karim') }}" :disabled="$locked" />
                                @error("addresses.{$type->value}.care") <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="{{ $labelClasses }}">{{ __('Road / Street') }}</label>
                                <x-ui.input type="text" wire:model="addresses.{{ $type->value }}.road" placeholder="{{ __('House, road, area') }}" :disabled="$locked" />
                                @error("addresses.{$type->value}.road") <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="{{ $labelClasses }}">{{ __('District') }}</label>
                                <select wire:model.live="addresses.{{ $type->value }}.district_id" class="{{ $inputClasses }}" @disabled($locked)>
                                    <option value="">— {{ __('Select district') }} —</option>
                                    @foreach ($this->districts as $d)
                                        <option value="{{ $d->id }}">{{ $d->name }}</option>
                                    @endforeach
                                </select>
                                @error("addresses.{$type->value}.district_id") <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="{{ $labelClasses }}">{{ __('Upazila') }}</label>
                                <select wire:model="addresses.{{ $type->value }}.upazila_id" class="{{ $inputClasses }}" @disabled($locked)>
                                    <option value="">— {{ __('Select upazila') }} —</option>
                                    @foreach ($this->upazilasFor($addresses[$type->value]['district_id'] ?? null) as $u)
                                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                                    @endforeach
                                </select>
                                @error("addresses.{$type->value}.upazila_id") <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="{{ $labelClasses }}">{{ __('Post office') }}</label>
                                <x-ui.input type="text" wire:model="addresses.{{ $type->value }}.post_office" placeholder="{{ __('e.g. Mirpur') }}" :disabled="$locked" />
                                @error("addresses.{$type->value}.post_office") <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="{{ $labelClasses }}">{{ __('Postal code') }}</label>
                                <x-ui.input type="text" wire:model="addresses.{{ $type->value }}.postal_code" placeholder="{{ __('e.g. 1216') }}" :disabled="$locked" />
                                @error("addresses.{$type->value}.postal_code") <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        @if ($locked)
                            <p class="mt-4 text-xs text-zinc-500 flex items-center gap-1.5">
                                <x-lucide-lock class="size-3 shrink-0" />
                                {{ __('Mirroring the present address. Uncheck the toggle above to edit separately.') }}
                            </p>
                        @endif
                    </fieldset>
                @endforeach

                <div class="flex items-center justify-end gap-3">
                    <x-ui.button variant="primary" type="submit" icon="save" wire:loading.attr="disabled" wire:target="saveAddresses">
                        <span wire:loading.remove wire:target="saveAddresses">{{ __('Save addresses') }}</span>
                        <span wire:loading wire:target="saveAddresses">{{ __('Saving…') }}</span>
                    </x-ui.button>
                </div>
            </form>
        </div>

        {{-- ===================== EDUCATION TAB ===================== --}}
        <div x-show="tab === 'education'" x-cloak class="p-6 space-y-6">
            <form wire:submit="saveEducations" class="space-y-6">
                @foreach ($this->degrees as $degree)
                    @php
                        $t = $degree['type'];
                        $isOptional = in_array($t, ['Graduate', 'Other'], true);
                    @endphp
                    <fieldset class="{{ $sectionCard }}">
                        <div class="flex items-center justify-between gap-3 flex-wrap">
                            <legend class="{{ $sectionTitle }}">{{ $degree['label'] }}</legend>
                            @if ($isOptional)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide bg-zinc-100 text-zinc-500">{{ __('Optional') }}</span>
                            @endif
                        </div>
                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                            @if (! empty($degree['has_options']))
                                <div class="sm:col-span-2 lg:col-span-3">
                                    <label class="{{ $labelClasses }}">{{ __('Degree') }}</label>
                                    <select wire:model="educations.{{ $t }}.name" class="{{ $inputClasses }}">
                                        <option value="">— {{ __('Select') }} —</option>
                                        @foreach ($degree['options'] as $opt)
                                            <option value="{{ $opt }}">{{ $opt }}</option>
                                        @endforeach
                                    </select>
                                    @error("educations.$t.name") <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                                </div>
                            @endif
                            <div>
                                <label class="{{ $labelClasses }}">{{ __('Group / Subject') }}</label>
                                @if ($degree['sub_type'] === 'select')
                                    <select wire:model="educations.{{ $t }}.major" class="{{ $inputClasses }}">
                                        <option value="">— {{ __('Select') }} —</option>
                                        @foreach ($degree['subjects'] ?? [] as $s)
                                            <option value="{{ $s }}">{{ $s }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <x-ui.input type="text" wire:model="educations.{{ $t }}.major" />
                                @endif
                                @error("educations.$t.major") <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="{{ $labelClasses }}">{{ __('Board / University') }}</label>
                                @if ($degree['board_type'] === 'select')
                                    <select wire:model="educations.{{ $t }}.institute" class="{{ $inputClasses }}">
                                        <option value="">— {{ __('Select') }} —</option>
                                        @foreach ($degree['boards'] ?? [] as $b)
                                            <option value="{{ $b }}">{{ $b }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <x-ui.input type="text" wire:model="educations.{{ $t }}.institute" />
                                @endif
                                @error("educations.$t.institute") <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="{{ $labelClasses }}">{{ __('Result / CGPA') }}</label>
                                <x-ui.input type="text" wire:model="educations.{{ $t }}.result" placeholder="e.g. 4.80" />
                                @error("educations.$t.result") <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="{{ $labelClasses }}">{{ __('Out of') }}</label>
                                <select wire:model="educations.{{ $t }}.scale" class="{{ $inputClasses }}">
                                    <option value="">— {{ __('Select') }} —</option>
                                    @foreach ($this->scales as $s)
                                        <option value="{{ $s['value'] }}">{{ $s['label'] }}</option>
                                    @endforeach
                                </select>
                                @error("educations.$t.scale") <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="{{ $labelClasses }}">{{ __('Duration') }}</label>
                                <select wire:model="educations.{{ $t }}.duration" class="{{ $inputClasses }}">
                                    <option value="">— {{ __('Select') }} —</option>
                                    @foreach ($this->durations as $d)
                                        <option value="{{ $d }}">{{ $d }} {{ $d === 1 ? __('year') : __('years') }}</option>
                                    @endforeach
                                </select>
                                @error("educations.$t.duration") <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="{{ $labelClasses }}">{{ __('Passing year') }}</label>
                                <select wire:model="educations.{{ $t }}.passing_year" class="{{ $inputClasses }}">
                                    <option value="">— {{ __('Select') }} —</option>
                                    @foreach ($this->passingYears as $y)
                                        <option value="{{ $y }}">{{ $y }}</option>
                                    @endforeach
                                </select>
                                @error("educations.$t.passing_year") <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </fieldset>
                @endforeach

                <div class="flex items-center justify-end gap-3">
                    <span class="text-xs text-zinc-500">
                        {{ __('Total schooling:') }}
                        <span class="font-bold text-zinc-800">{{ $totalYearsOfSchooling }} {{ $totalYearsOfSchooling === 1 ? __('year') : __('years') }}</span>
                    </span>
                    <x-ui.button variant="primary" type="submit" icon="save" wire:loading.attr="disabled" wire:target="saveEducations">
                        <span wire:loading.remove wire:target="saveEducations">{{ __('Save education') }}</span>
                        <span wire:loading wire:target="saveEducations">{{ __('Saving…') }}</span>
                    </x-ui.button>
                </div>
            </form>
        </div>

        {{-- ===================== EXPERIENCE TAB ===================== --}}
        <div x-show="tab === 'experience'" x-cloak class="p-6 space-y-6">
            <form wire:submit="saveExperiences" class="space-y-6">
                @foreach ($experiences as $index => $exp)
                    <fieldset class="{{ $sectionCard }}" wire:key="exp-{{ $index }}-{{ $exp['id'] ?? 'new' }}">
                        <div class="flex items-center justify-between gap-3 flex-wrap">
                            <legend class="{{ $sectionTitle }}">{{ __('Experience') }} #{{ $index + 1 }}</legend>
                            <button type="button" wire:click="removeExperience({{ $index }})"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold text-red-600 hover:bg-red-50 transition">
                                <x-lucide-trash-2 class="size-3.5" /> {{ __('Remove') }}
                            </button>
                        </div>
                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="{{ $labelClasses }}">{{ __('Organization') }}</label>
                                <x-ui.input type="text" wire:model="experiences.{{ $index }}.organization" />
                                @error("experiences.$index.organization") <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="{{ $labelClasses }}">{{ __('Designation') }}</label>
                                <x-ui.input type="text" wire:model="experiences.{{ $index }}.designation" />
                                @error("experiences.$index.designation") <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="{{ $labelClasses }}">{{ __('Duration') }}</label>
                                <x-ui.input type="text" wire:model="experiences.{{ $index }}.duration" placeholder="e.g. 2 years 6 months" />
                                @error("experiences.$index.duration") <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="{{ $labelClasses }}">{{ __('Total experience (years)') }}</label>
                                <x-ui.input type="number" step="0.1" min="0" wire:model="experiences.{{ $index }}.total_experience" />
                                @error("experiences.$index.total_experience") <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </fieldset>
                @endforeach

                <button type="button" wire:click="addExperience"
                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold border-2 border-dashed border-zinc-200 text-zinc-500 hover:border-zinc-300 hover:bg-zinc-50 hover:text-zinc-700 transition">
                    <x-lucide-plus class="size-4" /> {{ __('Add another experience') }}
                </button>

                <div class="flex items-center justify-end gap-3">
                    <span class="text-xs text-zinc-500">
                        {{ __('Total experience:') }}
                        <span class="font-bold text-zinc-800">{{ rtrim(rtrim(number_format($totalYearsOfExperience, 2, '.', ''), '0'), '.') }} {{ $totalYearsOfExperience === 1.0 ? __('year') : __('years') }}</span>
                    </span>
                    <x-ui.button variant="primary" type="submit" icon="save" wire:loading.attr="disabled" wire:target="saveExperiences">
                        <span wire:loading.remove wire:target="saveExperiences">{{ __('Save experience') }}</span>
                        <span wire:loading wire:target="saveExperiences">{{ __('Saving…') }}</span>
                    </x-ui.button>
                </div>
            </form>
        </div>
    </div>
</div>

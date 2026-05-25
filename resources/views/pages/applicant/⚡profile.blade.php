<?php

use App\Enums\AddressTypeEnum;
use App\Enums\BloodGroup;
use App\Enums\GenderEnum;
use App\Enums\MaritalStatus;
use App\Enums\ReligionEnum;
use App\Models\Address;
use App\Models\ApplicantProfile;
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

new #[Title('Profile')]
#[Layout('layouts.applicant.app')]
class extends Component {
    use WithFileUploads;

    public const PHOTO_MAX_KB = 200;

    public const PHOTO_WIDTH = 300;

    public const PHOTO_HEIGHT = 300;

    public string $email = '';

    public string $phone_number = '';

    public $photo = null;

    public string $existingPhotoUrl = '';

    public bool $hasExistingPhoto = false;

    /** @var array<string, ?bool> */
    public array $photoRules = [
        'format' => null,
        'size' => null,
        'dimensions' => null,
    ];

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

    public function mount(): void
    {
        $applicant = auth('applicant')->user()->load([
            'profile',
            'addresses',
            'educationHistories',
            'expHistories',
        ]);

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

        $existingByType = $applicant->educationHistories
            ->keyBy(fn (EducationHistory $row) => $row->type?->value);

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

        return Upazila::where('district_id', $districtId)
            ->orderBy('name')
            ->get(['id', 'name']);
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

    public function updatedPhoto(): void
    {
        $this->resetPhotoRules();

        if (! $this->photo instanceof TemporaryUploadedFile) {
            return;
        }

        try {
            $mime = $this->photo->getMimeType();
            $this->photoRules['format'] = is_string($mime) && str_starts_with($mime, 'image/');
        } catch (\Throwable) {
            $this->photoRules['format'] = false;
        }

        try {
            $this->photoRules['size'] = $this->photo->getSize() <= self::PHOTO_MAX_KB * 1024;
        } catch (\Throwable) {
            $this->photoRules['size'] = false;
        }

        try {
            $info = @getimagesize($this->photo->getRealPath());
            $this->photoRules['dimensions'] = $info && $info[0] === self::PHOTO_WIDTH && $info[1] === self::PHOTO_HEIGHT;
        } catch (\Throwable) {
            $this->photoRules['dimensions'] = false;
        }
    }

    public function removePhoto(): void
    {
        $this->photo = null;
        $this->resetPhotoRules();
    }

    private function resetPhotoRules(): void
    {
        $this->photoRules = ['format' => null, 'size' => null, 'dimensions' => null];
    }

    private function photoValid(): bool
    {
        return $this->photo instanceof TemporaryUploadedFile
            && $this->photoRules['format'] === true
            && $this->photoRules['size'] === true
            && $this->photoRules['dimensions'] === true;
    }

    public function saveProfile()
    {
        $this->profile['nationality'] = 'Bangladeshi';

        foreach (['full_name', 'father_name', 'mother_name'] as $field) {
            $this->profile[$field] = mb_strtoupper(trim((string) $this->profile[$field]));
        }

        $applicant = auth('applicant')->user();

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
        ]);

        if (! $this->hasExistingPhoto && ! $this->photo instanceof TemporaryUploadedFile) {
            $this->addError('photo', __('A profile photo is required.'));

            return;
        }

        if ($this->photo instanceof TemporaryUploadedFile && ! $this->photoValid()) {
            $this->addError('photo', __('The uploaded photo does not meet the requirements.'));

            return;
        }

        $emailChanged = $applicant->email !== $validated['email'];

        $applicant->fill([
            'email' => $validated['email'],
            'phone_number' => $validated['phone_number'],
        ]);

        if ($emailChanged) {
            $applicant->email_verified_at = null;
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

        ApplicantProfile::updateOrCreate(
            ['applicant_id' => $applicant->id],
            $payload,
        );

        $applicant->refresh()->load('profile');
        $this->existingPhotoUrl = $applicant->profile?->photo_url ?? asset('assets/images/default-avatar.png');
        $this->hasExistingPhoto = filled($applicant->profile?->photo);
        $this->photo = null;
        $this->resetPhotoRules();

        if ($emailChanged) {
            $applicant->sendEmailVerificationNotification();

            session()->flash('toast', [
                'variant' => 'success',
                'message' => __('Profile saved. Please verify your new email address.'),
            ]);

            return redirect()->route('applicant.verification.notice');
        }

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

        $applicant = auth('applicant')->user();

        foreach (AddressTypeEnum::cases() as $type) {
            $row = $this->addresses[$type->value];

            Address::updateOrCreate(
                ['applicant_id' => $applicant->id, 'type' => $type->value],
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

        $this->validate($rules, attributes: [
            'educations.SSC.major' => 'S.S.C group/subject',
            'educations.HSC.major' => 'H.S.C group/subject',
            'educations.Undergraduate.major' => 'Honours subject/major',
            'educations.Graduate.major' => 'Masters subject/major',
            'educations.Other.name' => 'other degree',
            'educations.Other.major' => 'other degree subject/major',
        ]);

        $applicant = auth('applicant')->user();
        $totalDuration = 0;

        foreach ($degrees as $type => $degree) {
            $isOptional = in_array($type, $optionalTypes, true);
            $row = $this->educations[$type];
            $hasAny = collect($valueFields)->some(fn ($k) => filled($row[$k] ?? null));

            if ($isOptional && ! $hasAny) {
                EducationHistory::where('applicant_id', $applicant->id)
                    ->where('type', $type)
                    ->delete();

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

        ApplicantProfile::where('applicant_id', $applicant->id)
            ->update(['tot_year_of_schooling' => $totalDuration]);

        $this->totalYearsOfSchooling = $totalDuration;

        $this->refreshEducations($applicant->refresh());

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

        $applicant = auth('applicant')->user();
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

        $applicant->expHistories()
            ->whereNotIn('id', $keepIds)
            ->delete();

        ApplicantProfile::where('applicant_id', $applicant->id)
            ->update(['tot_year_of_exp' => $totalExperience]);

        $this->totalYearsOfExperience = $totalExperience;

        $this->refreshExperiences($applicant);

        Toast::success(__('Experience history saved.'));
    }

    private function refreshEducations($applicant): void
    {
        $existing = $applicant->educationHistories()->get()
            ->keyBy(fn (EducationHistory $row) => $row->type?->value);

        foreach (config('degree.degrees') as $degree) {
            $row = $existing->get($degree['type']);
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
    }

    private function refreshExperiences($applicant): void
    {
        $this->experiences = $applicant->expHistories()->get()
            ->map(fn (ExpHistory $row) => [
                'id' => $row->id,
                'organization' => $row->organization,
                'designation' => $row->designation,
                'duration' => $row->duration,
                'total_experience' => (float) $row->total_experience,
            ])
            ->values()
            ->all();
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
    $tabs = [
        ['key' => 'profile',     'label' => 'Applicant Profile', 'icon' => 'user-circle',    'description' => 'Personal details and demographics.'],
        ['key' => 'addresses',   'label' => 'Addresses',         'icon' => 'map-pin',        'description' => 'Present and permanent contact addresses.'],
        ['key' => 'education',   'label' => 'Education History', 'icon' => 'graduation-cap', 'description' => 'Academic qualifications and results.'],
        ['key' => 'experience',  'label' => 'Experience',        'icon' => 'briefcase',      'description' => 'Professional work history.'],
    ];

    $inputClasses = 'w-full px-4 py-2.5 rounded-lg border bg-white text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-0 transition border-gray-200 focus:ring-indigo-100 focus:border-indigo-400 disabled:bg-gray-100 disabled:text-gray-500 disabled:cursor-not-allowed';
    $labelClasses = 'block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5';
    $errorClasses = 'mt-1.5 text-xs font-medium text-red-600';
    $sectionCard = 'rounded-2xl border border-gray-100 bg-gray-50/40 p-6 sm:p-7 transition-shadow hover:shadow-[0_2px_8px_rgba(15,10,40,0.04)]';
    $required = '<span class="text-red-500 ml-0.5">*</span>';
@endphp

<div
    x-data="{
        tab: 'profile',
        validTabs: ['profile', 'addresses', 'education', 'experience'],
        goToTab(target) {
            if (!this.validTabs.includes(target)) return;
            this.tab = target;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
        nextTab() {
            const idx = this.validTabs.indexOf(this.tab);
            if (idx >= 0 && idx < this.validTabs.length - 1) {
                this.goToTab(this.validTabs[idx + 1]);
            }
        },
        init() {
            const fromHash = window.location.hash.replace('#', '');
            if (this.validTabs.includes(fromHash)) {
                this.tab = fromHash;
            }
            this.$watch('tab', (value) => {
                history.replaceState(null, '', '#' + value);
            });
            window.addEventListener('hashchange', () => {
                const h = window.location.hash.replace('#', '');
                if (this.validTabs.includes(h)) this.tab = h;
            });
            window.addEventListener('go-to-tab', (e) => {
                const target = e.detail?.tab;
                if (target === 'next') {
                    this.nextTab();
                } else if (target) {
                    this.goToTab(target);
                }
            });

            // Bridge Livewire 'go-to-tab' events to a window CustomEvent (once)
            if (!window._embaTabBridge && window.Livewire) {
                window._embaTabBridge = true;
                window.Livewire.on('go-to-tab', (payload) => {
                    const data = Array.isArray(payload) ? payload[0] : payload;
                    window.dispatchEvent(new CustomEvent('go-to-tab', { detail: data || {} }));
                });
            }
        },
    }"
>

    <div class="mb-8">
        <p class="text-xs font-bold uppercase tracking-widest mb-1.5" style="color:#8b072b;">Applicant Portal</p>
        <h1 class="font-inter font-bold text-2xl sm:text-3xl text-gray-900">My Profile</h1>
        <p class="text-gray-500 text-sm mt-2">Manage your personal information, contact addresses, and academic and work history.</p>
    </div>

    {{-- ===================== TAB BAR ===================== --}}
    <div class="bg-white rounded-3xl shadow-[0_4px_24px_-8px_rgba(15,10,40,0.08)] border border-gray-100 overflow-hidden">
        <div class="border-b border-gray-100 bg-gradient-to-b from-gray-50/60 to-white overflow-x-auto">
            <nav class="flex min-w-max px-2 sm:px-4">
                @foreach ($tabs as $t)
                    <button
                        type="button"
                        @click="tab = '{{ $t['key'] }}'"
                        :class="tab === '{{ $t['key'] }}'
                            ? 'text-gray-900 border-b-2'
                            : 'text-gray-500 hover:text-gray-800 border-b-2 border-transparent'"
                        :style="tab === '{{ $t['key'] }}' ? 'border-color:#8b072b;' : ''"
                        class="flex items-center gap-2 px-5 py-4 text-sm font-semibold transition-colors -mb-px"
                    >
                        <x-dynamic-component :component="'lucide-' . $t['icon']" class="size-4" />
                        {{ $t['label'] }}
                    </button>
                @endforeach
            </nav>
        </div>

        {{-- ===================== PROFILE TAB ===================== --}}
        <div x-show="tab === 'profile'" x-cloak class="p-6 sm:p-10">
            <div class="mb-7 flex items-start gap-3">
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl text-white shrink-0" style="background:#2F1B72;">
                    <x-lucide-user-circle class="size-5" />
                </span>
                <div>
                    <h2 class="font-inter font-bold text-gray-900">Applicant Profile</h2>
                    <p class="text-sm text-gray-500 mt-0.5">Personal details and demographics used across your application.</p>
                </div>
            </div>

            <form wire:submit="saveProfile" class="space-y-7" enctype="multipart/form-data">

                @php
                    $previewUrl = $photo ? $photo->temporaryUrl() : $existingPhotoUrl;
                    $rulesItems = [
                        ['key' => 'format',     'label' => 'Image file (JPG, PNG, GIF, WebP, etc.)'],
                        ['key' => 'dimensions', 'label' => 'Exactly 300 × 300 pixels'],
                        ['key' => 'size',       'label' => 'File size under 200 KB'],
                    ];
                @endphp

                <div class="{{ $sectionCard }}">
                    <div class="flex items-center justify-between mb-5">
                        <h3 class="font-inter font-semibold text-sm text-gray-800">Profile photo {!! ! $hasExistingPhoto ? $required : '' !!}</h3>
                        @if ($hasExistingPhoto && ! $photo)
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full">
                                <x-lucide-check class="size-3" /> Uploaded
                            </span>
                        @elseif (! $hasExistingPhoto && ! $photo)
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-amber-700 bg-amber-50 px-2.5 py-1 rounded-full">
                                <x-lucide-alert-circle class="size-3" /> Not uploaded
                            </span>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-[auto_1fr] gap-7 items-start">

                        {{-- Avatar with overlay button --}}
                        <div class="flex flex-col items-center md:items-start gap-3">
                            <div class="relative w-36 h-36 group" wire:loading.class="opacity-60" wire:target="photo">
                                <img
                                    src="{{ $previewUrl }}"
                                    alt="Profile photo"
                                    class="w-36 h-36 rounded-full object-cover border-4 border-white ring-1 ring-gray-200 shadow-md bg-gray-100"
                                />

                                <label
                                    for="photo-upload"
                                    class="absolute bottom-1 right-1 w-10 h-10 rounded-full flex items-center justify-center text-white shadow-md cursor-pointer transition-transform hover:scale-105 active:scale-95"
                                    style="background:#2F1B72;"
                                    title="Upload photo"
                                >
                                    <x-lucide-camera class="size-4" wire:loading.remove wire:target="photo" />
                                    <x-lucide-loader-2 class="size-4 animate-spin" wire:loading wire:target="photo" />
                                </label>

                                <input
                                    id="photo-upload"
                                    type="file"
                                    accept="image/*"
                                    wire:model.live="photo"
                                    class="sr-only"
                                />
                            </div>

                            @if ($photo)
                                <div class="text-center md:text-left">
                                    <p class="text-xs font-semibold text-gray-700 truncate max-w-[160px]">{{ $photo->getClientOriginalName() }}</p>
                                    <button type="button" wire:click="removePhoto" class="text-xs font-semibold text-red-500 hover:text-red-700 mt-1 inline-flex items-center gap-1">
                                        <x-lucide-x class="size-3" /> Remove
                                    </button>
                                </div>
                            @endif

                            @error('photo')
                                <p class="{{ $errorClasses }}">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Instructions --}}
                        <div class="rounded-xl border border-gray-200 bg-white p-5">
                            <p class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-3">Photo requirements</p>
                            <ul class="space-y-2.5">
                                @foreach ($rulesItems as $item)
                                    @php $state = $photoRules[$item['key']]; @endphp
                                    <li class="flex items-start gap-2.5 text-sm">
                                        @if ($state === true)
                                            <span class="shrink-0 w-5 h-5 rounded-full bg-emerald-100 text-emerald-600 inline-flex items-center justify-center mt-0.5">
                                                <x-lucide-check class="size-3" />
                                            </span>
                                            <span class="text-emerald-700 font-medium">{{ $item['label'] }}</span>
                                        @elseif ($state === false)
                                            <span class="shrink-0 w-5 h-5 rounded-full bg-red-100 text-red-600 inline-flex items-center justify-center mt-0.5">
                                                <x-lucide-x class="size-3" />
                                            </span>
                                            <span class="text-red-700 font-medium">{{ $item['label'] }}</span>
                                        @else
                                            <span class="shrink-0 w-5 h-5 rounded-full bg-gray-100 text-gray-400 inline-flex items-center justify-center mt-0.5">
                                                <x-lucide-circle class="size-2.5" />
                                            </span>
                                            <span class="text-gray-600">{{ $item['label'] }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>

                            <p class="mt-4 text-xs text-gray-400">
                                Tip: Use a recent, front-facing photo with a clear background. You can resize images at
                                <a href="https://www.iloveimg.com/resize-image" target="_blank" class="font-semibold text-gray-500 hover:text-gray-700 underline">iloveimg.com</a>.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="{{ $sectionCard }}">
                    <h3 class="font-inter font-semibold text-sm text-gray-800 mb-5">Contact</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label class="{{ $labelClasses }}">Email address {!! $required !!}</label>
                            <input type="email" wire:model="email" required class="{{ $inputClasses }}" placeholder="email@example.com" autocomplete="email" />
                            <p class="mt-1.5 text-xs text-amber-600 flex items-center gap-1">
                                <x-lucide-alert-triangle class="size-3.5 shrink-0" />
                                Changing your email will require re-verification.
                            </p>
                            @error('email') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $labelClasses }}">Phone number {!! $required !!}</label>
                            <input type="tel" wire:model="phone_number" required class="{{ $inputClasses }}" placeholder="01XXX-XXXXXX" autocomplete="tel" />
                            @error('phone_number') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <div class="{{ $sectionCard }}">
                    <h3 class="font-inter font-semibold text-sm text-gray-800 mb-5">Identity</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label class="{{ $labelClasses }}">Full name {!! $required !!}</label>
                            <input type="text" wire:model="profile.full_name" required class="{{ $inputClasses }} uppercase placeholder:normal-case tracking-wide" placeholder="e.g. MD. TANZID HAQUE" />
                            @error('profile.full_name') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $labelClasses }}">Date of birth {!! $required !!}</label>
                            <input type="date" wire:model="profile.date_of_birth" required class="{{ $inputClasses }}" placeholder="YYYY-MM-DD" />
                            @error('profile.date_of_birth') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $labelClasses }}">Father's name {!! $required !!}</label>
                            <input type="text" wire:model="profile.father_name" required class="{{ $inputClasses }} uppercase placeholder:normal-case tracking-wide" placeholder="As per NID / Birth certificate" />
                            @error('profile.father_name') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $labelClasses }}">Mother's name {!! $required !!}</label>
                            <input type="text" wire:model="profile.mother_name" required class="{{ $inputClasses }} uppercase placeholder:normal-case tracking-wide" placeholder="As per NID / Birth certificate" />
                            @error('profile.mother_name') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <div class="{{ $sectionCard }}">
                    <h3 class="font-inter font-semibold text-sm text-gray-800 mb-5">Demographics</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div>
                            <label class="{{ $labelClasses }}">Gender {!! $required !!}</label>
                            <select wire:model="profile.gender" required class="{{ $inputClasses }}">
                                <option value="" disabled>Select gender</option>
                                @foreach ($this->genders as $g)
                                    <option value="{{ $g->value }}">{{ $g->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="{{ $labelClasses }}">Blood group {!! $required !!}</label>
                            <select wire:model="profile.blood_group" required class="{{ $inputClasses }}">
                                <option value="" disabled>Select blood group</option>
                                @foreach ($this->bloodGroups as $b)
                                    <option value="{{ $b->value }}">{{ $b->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="{{ $labelClasses }}">Religion {!! $required !!}</label>
                            <select wire:model="profile.religion" required class="{{ $inputClasses }}">
                                <option value="" disabled>Select religion</option>
                                @foreach ($this->religions as $r)
                                    <option value="{{ $r->value }}">{{ $r->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="{{ $labelClasses }}">Marital status {!! $required !!}</label>
                            <select wire:model="profile.marital_status" required class="{{ $inputClasses }}">
                                <option value="" disabled>Select status</option>
                                @foreach ($this->maritalStatuses as $m)
                                    <option value="{{ $m->value }}">{{ $m->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="{{ $sectionCard }}">
                    <h3 class="font-inter font-semibold text-sm text-gray-800 mb-5">Background</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                        <div>
                            <label class="{{ $labelClasses }}">Nationality {!! $required !!}</label>
                            <input type="text" wire:model="profile.nationality" disabled readonly placeholder="Bangladeshi" class="{{ $inputClasses }}" />
                            <p class="mt-1.5 text-xs text-gray-400">Currently only Bangladeshi applicants are supported.</p>
                        </div>
                    </div>
                </div>

                <div class="pt-2 flex items-center justify-between gap-3 flex-wrap border-t border-gray-100 -mx-6 sm:-mx-10 px-6 sm:px-10 mt-8 pt-6">
                    <div class="flex items-center gap-2">
                        <button type="button"
                            @click="goToTab('addresses')"
                            class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-lg text-sm font-semibold text-gray-700 border border-gray-200 bg-white hover:bg-gray-50 hover:border-gray-300 transition"
                        >
                            Next <x-lucide-arrow-right class="size-4" />
                        </button>
                    </div>

                    <button type="submit"
                        class="inline-flex items-center gap-2 px-7 py-3 rounded-xl font-bold text-white text-sm shadow-md shadow-rose-900/10 transition-opacity hover:opacity-90 disabled:opacity-60"
                        style="background:#8b072b;"
                        wire:loading.attr="disabled"
                        wire:target="saveProfile"
                    >
                        <span wire:loading.remove wire:target="saveProfile">Save profile</span>
                        <span wire:loading wire:target="saveProfile">Saving...</span>
                        <x-lucide-arrow-right class="size-4" wire:loading.remove wire:target="saveProfile" />
                    </button>
                </div>
            </form>
        </div>

        {{-- ===================== ADDRESSES TAB ===================== --}}
        <div x-show="tab === 'addresses'" x-cloak class="p-6 sm:p-10">
            <div class="mb-7 flex items-start gap-3">
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl text-white shrink-0" style="background:#2F1B72;">
                    <x-lucide-map-pin class="size-5" />
                </span>
                <div>
                    <h2 class="font-inter font-bold text-gray-900">Addresses</h2>
                    <p class="text-sm text-gray-500 mt-0.5">Provide both your present and permanent address details.</p>
                </div>
            </div>

            <form wire:submit="saveAddresses" class="space-y-7">
                @foreach (\App\Enums\AddressTypeEnum::cases() as $type)
                    @php
                        $isPermanent = $type === \App\Enums\AddressTypeEnum::PERMANENT;
                        $locked = $isPermanent && $sameAsPresent;
                    @endphp

                    <div class="{{ $sectionCard }}">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6 pb-5 border-b border-gray-100">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-white" style="background:#8b072b;">
                                    <x-lucide-map-pin class="size-4" />
                                </span>
                                <div>
                                    <h3 class="font-inter font-bold text-gray-800 text-sm">{{ $type->label() }} Address</h3>
                                    <p class="text-xs text-gray-400 mt-0.5">Where you can be reached.</p>
                                </div>
                            </div>

                            @if ($isPermanent)
                                <label class="inline-flex items-center gap-2.5 px-3 py-2 rounded-lg bg-white border border-gray-200 cursor-pointer select-none hover:border-gray-300 transition">
                                    <input
                                        type="checkbox"
                                        wire:model.live="sameAsPresent"
                                        class="w-4 h-4 rounded border-gray-300 text-[#8b072b] focus:ring-[#8b072b]/30"
                                    />
                                    <span class="text-xs font-semibold text-gray-700">Same as present address</span>
                                </label>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 {{ $locked ? 'opacity-60' : '' }}">
                            <div>
                                <label class="{{ $labelClasses }}">Care of</label>
                                <input type="text" wire:model="addresses.{{ $type->value }}.care" class="{{ $inputClasses }}" placeholder="e.g. C/O Md. Karim" @disabled($locked) />
                            </div>
                            <div>
                                <label class="{{ $labelClasses }}">Road / Street</label>
                                <input type="text" wire:model="addresses.{{ $type->value }}.road" class="{{ $inputClasses }}" placeholder="House, road, area" @disabled($locked) />
                            </div>
                            <div>
                                <label class="{{ $labelClasses }}">District</label>
                                <select wire:model.live="addresses.{{ $type->value }}.district_id" class="{{ $inputClasses }}" @disabled($locked)>
                                    <option value="">— Select district —</option>
                                    @foreach ($this->districts as $d)
                                        <option value="{{ $d->id }}">{{ $d->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="{{ $labelClasses }}">Upazila</label>
                                <select wire:model="addresses.{{ $type->value }}.upazila_id" class="{{ $inputClasses }}" @disabled($locked)>
                                    <option value="">— Select upazila —</option>
                                    @foreach ($this->upazilasFor($addresses[$type->value]['district_id'] ?? null) as $u)
                                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="{{ $labelClasses }}">Post office</label>
                                <input type="text" wire:model="addresses.{{ $type->value }}.post_office" class="{{ $inputClasses }}" placeholder="e.g. Mirpur" @disabled($locked) />
                            </div>
                            <div>
                                <label class="{{ $labelClasses }}">Postal code</label>
                                <input type="text" wire:model="addresses.{{ $type->value }}.postal_code" class="{{ $inputClasses }}" placeholder="e.g. 1216" @disabled($locked) />
                            </div>
                        </div>

                        @if ($locked)
                            <p class="mt-4 text-xs text-gray-500 flex items-center gap-1.5">
                                <x-lucide-lock class="size-3 shrink-0" />
                                Mirroring your present address. Uncheck the box above to edit separately.
                            </p>
                        @endif
                    </div>
                @endforeach

                <div class="pt-2 flex items-center justify-between gap-3 flex-wrap border-t border-gray-100 -mx-6 sm:-mx-10 px-6 sm:px-10 mt-8 pt-6">
                    <div class="flex items-center gap-2">
                        <button type="button"
                            @click="goToTab('profile')"
                            class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-lg text-sm font-semibold text-gray-700 border border-gray-200 bg-white hover:bg-gray-50 hover:border-gray-300 transition"
                        >
                            <x-lucide-arrow-left class="size-4" /> Previous
                        </button>
                        <button type="button"
                            @click="goToTab('education')"
                            class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-lg text-sm font-semibold text-gray-700 border border-gray-200 bg-white hover:bg-gray-50 hover:border-gray-300 transition"
                        >
                            Next <x-lucide-arrow-right class="size-4" />
                        </button>
                    </div>

                    <button type="submit"
                        class="inline-flex items-center gap-2 px-7 py-3 rounded-xl font-bold text-white text-sm shadow-md shadow-rose-900/10 transition-opacity hover:opacity-90 disabled:opacity-60"
                        style="background:#8b072b;"
                        wire:loading.attr="disabled"
                        wire:target="saveAddresses"
                    >
                        <span wire:loading.remove wire:target="saveAddresses">Save addresses</span>
                        <span wire:loading wire:target="saveAddresses">Saving...</span>
                        <x-lucide-arrow-right class="size-4" wire:loading.remove wire:target="saveAddresses" />
                    </button>
                </div>
            </form>
        </div>

        {{-- ===================== EDUCATION TAB ===================== --}}
        <div x-show="tab === 'education'" x-cloak class="p-6 sm:p-10">
            <div class="mb-7 flex items-start justify-between gap-4">
                <div class="flex items-start gap-3">
                    <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl text-white shrink-0" style="background:#2F1B72;">
                        <x-lucide-graduation-cap class="size-5" />
                    </span>
                    <div>
                        <h2 class="font-inter font-bold text-gray-900">Education History</h2>
                        <p class="text-sm text-gray-500 mt-0.5">Add every degree you've completed, starting from S.S.C.</p>
                    </div>
                </div>

                <div class="shrink-0 inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-gray-100 bg-gray-50/70">
                    <x-lucide-clock class="size-4 text-gray-400" />
                    <div class="leading-tight">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Total Years of Schooling</p>
                        <p class="text-sm font-bold text-gray-800">{{ $totalYearsOfSchooling }} {{ $totalYearsOfSchooling === 1 ? 'Year' : 'Years' }}</p>
                    </div>
                </div>
            </div>

            <form wire:submit="saveEducations" class="space-y-6">

                @php
                    $tableInput = 'w-full px-3 py-2 rounded-lg border bg-white text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-400 transition border-gray-200';
                    $tableHeadCell = 'px-3 py-3 text-xs font-bold uppercase tracking-wide text-gray-500 text-left';
                @endphp

                {{-- Desktop table view --}}
                <div class="hidden lg:block rounded-2xl border border-gray-100 bg-white overflow-hidden">
                    <table class="w-full">
                        <thead class="bg-gray-50/80 border-b border-gray-100">
                            <tr>
                                <th class="{{ $tableHeadCell }} w-44">Degree</th>
                                <th class="{{ $tableHeadCell }}">Group / Subject {!! $required !!}</th>
                                <th class="{{ $tableHeadCell }}">Board / University {!! $required !!}</th>
                                <th class="{{ $tableHeadCell }} w-32">Result / CGPA {!! $required !!}</th>
                                <th class="{{ $tableHeadCell }} w-36">Out of {!! $required !!}</th>
                                <th class="{{ $tableHeadCell }} w-36">Course Duration {!! $required !!}</th>
                                <th class="{{ $tableHeadCell }} w-32">Passing Year {!! $required !!}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($this->degrees as $degree)
                                @php
                                    $t = $degree['type'];
                                    $isOptional = in_array($t, ['Graduate', 'Other'], true);
                                @endphp
                                <tr class="hover:bg-gray-50/40">
                                    <td class="px-3 py-3 align-top">
                                        @if (! empty($degree['has_options']))
                                            <select wire:model="educations.{{ $t }}.name" class="{{ $tableInput }}">
                                                <option value="">Other degree</option>
                                                @foreach ($degree['options'] as $opt)
                                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                                @endforeach
                                            </select>
                                            @error("educations.$t.name") <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                                        @else
                                            <p class="text-sm font-semibold text-gray-800 pt-2">{{ $degree['label'] }}</p>
                                        @endif
                                        @if ($isOptional)
                                            <span class="mt-1.5 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide bg-gray-100 text-gray-500">Optional</span>
                                        @endif
                                    </td>

                                    {{-- Group / Subject --}}
                                    <td class="px-3 py-3 align-top">
                                        @if ($degree['sub_type'] === 'select')
                                            <select wire:model="educations.{{ $t }}.major" class="{{ $tableInput }}">
                                                <option value="">Select one</option>
                                                @foreach ($degree['subjects'] ?? [] as $s)
                                                    <option value="{{ $s }}">{{ $s }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <input type="text" wire:model="educations.{{ $t }}.major" placeholder="Subject / Major" class="{{ $tableInput }}" />
                                        @endif
                                        @error("educations.$t.major") <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                                    </td>

                                    {{-- Board / University --}}
                                    <td class="px-3 py-3 align-top">
                                        @if ($degree['board_type'] === 'select')
                                            <select wire:model="educations.{{ $t }}.institute" class="{{ $tableInput }}">
                                                <option value="">Select one</option>
                                                @foreach ($degree['boards'] ?? [] as $b)
                                                    <option value="{{ $b }}">{{ $b }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <input type="text" wire:model="educations.{{ $t }}.institute" placeholder="University name" class="{{ $tableInput }}" />
                                        @endif
                                        @error("educations.$t.institute") <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                                    </td>

                                    {{-- Result / CGPA --}}
                                    <td class="px-3 py-3 align-top">
                                        <input type="text" wire:model="educations.{{ $t }}.result" placeholder="e.g. 4.80" class="{{ $tableInput }}" />
                                        @error("educations.$t.result") <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                                    </td>

                                    {{-- Out of (Scale) --}}
                                    <td class="px-3 py-3 align-top">
                                        <select wire:model="educations.{{ $t }}.scale" class="{{ $tableInput }}">
                                            <option value="">Select one</option>
                                            @foreach ($this->scales as $s)
                                                <option value="{{ $s['value'] }}">{{ $s['label'] }}</option>
                                            @endforeach
                                        </select>
                                        @error("educations.$t.scale") <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                                    </td>

                                    {{-- Course Duration --}}
                                    <td class="px-3 py-3 align-top">
                                        <select wire:model="educations.{{ $t }}.duration" class="{{ $tableInput }}">
                                            <option value="">Select one</option>
                                            @foreach ($this->durations as $d)
                                                <option value="{{ $d }}">{{ $d }} {{ $d === 1 ? 'year' : 'years' }}</option>
                                            @endforeach
                                        </select>
                                        @error("educations.$t.duration") <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                                    </td>

                                    {{-- Passing Year --}}
                                    <td class="px-3 py-3 align-top">
                                        <select wire:model="educations.{{ $t }}.passing_year" class="{{ $tableInput }}">
                                            <option value="">Select one</option>
                                            @foreach ($this->passingYears as $y)
                                                <option value="{{ $y }}">{{ $y }}</option>
                                            @endforeach
                                        </select>
                                        @error("educations.$t.passing_year") <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Mobile stacked view --}}
                <div class="lg:hidden space-y-5">
                    @foreach ($this->degrees as $degree)
                        @php
                            $t = $degree['type'];
                            $isOptional = in_array($t, ['Graduate', 'Other'], true);
                        @endphp
                        <div class="{{ $sectionCard }}">
                            <div class="flex items-center gap-3 mb-5 pb-4 border-b border-gray-100">
                                <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-white" style="background:#8b072b;">
                                    <x-lucide-graduation-cap class="size-4" />
                                </span>
                                <h3 class="font-inter font-bold text-gray-800 text-sm">{{ $degree['label'] }}</h3>
                                @if ($isOptional)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide bg-gray-100 text-gray-500">Optional</span>
                                @endif
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                @if (! empty($degree['has_options']))
                                    <div class="sm:col-span-2">
                                        <label class="{{ $labelClasses }}">Degree {!! $required !!}</label>
                                        <select wire:model="educations.{{ $t }}.name" class="{{ $inputClasses }}">
                                            <option value="">Select degree</option>
                                            @foreach ($degree['options'] as $opt)
                                                <option value="{{ $opt }}">{{ $opt }}</option>
                                            @endforeach
                                        </select>
                                        @error("educations.$t.name") <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                                    </div>
                                @endif

                                <div>
                                    <label class="{{ $labelClasses }}">Group / Subject {!! $required !!}</label>
                                    @if ($degree['sub_type'] === 'select')
                                        <select wire:model="educations.{{ $t }}.major" class="{{ $inputClasses }}">
                                            <option value="">Select one</option>
                                            @foreach ($degree['subjects'] ?? [] as $s)
                                                <option value="{{ $s }}">{{ $s }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input type="text" wire:model="educations.{{ $t }}.major" placeholder="Subject / Major" class="{{ $inputClasses }}" />
                                    @endif
                                    @error("educations.$t.major") <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="{{ $labelClasses }}">Board / University {!! $required !!}</label>
                                    @if ($degree['board_type'] === 'select')
                                        <select wire:model="educations.{{ $t }}.institute" class="{{ $inputClasses }}">
                                            <option value="">Select one</option>
                                            @foreach ($degree['boards'] ?? [] as $b)
                                                <option value="{{ $b }}">{{ $b }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input type="text" wire:model="educations.{{ $t }}.institute" placeholder="University name" class="{{ $inputClasses }}" />
                                    @endif
                                    @error("educations.$t.institute") <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="{{ $labelClasses }}">Result / CGPA {!! $required !!}</label>
                                    <input type="text" wire:model="educations.{{ $t }}.result" placeholder="e.g. 4.80" class="{{ $inputClasses }}" />
                                    @error("educations.$t.result") <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="{{ $labelClasses }}">Out of {!! $required !!}</label>
                                    <select wire:model="educations.{{ $t }}.scale" class="{{ $inputClasses }}">
                                        <option value="">Select one</option>
                                        @foreach ($this->scales as $s)
                                            <option value="{{ $s['value'] }}">{{ $s['label'] }}</option>
                                        @endforeach
                                    </select>
                                    @error("educations.$t.scale") <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="{{ $labelClasses }}">Course Duration {!! $required !!}</label>
                                    <select wire:model="educations.{{ $t }}.duration" class="{{ $inputClasses }}">
                                        <option value="">Select one</option>
                                        @foreach ($this->durations as $d)
                                            <option value="{{ $d }}">{{ $d }} {{ $d === 1 ? 'year' : 'years' }}</option>
                                        @endforeach
                                    </select>
                                    @error("educations.$t.duration") <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="{{ $labelClasses }}">Passing Year {!! $required !!}</label>
                                    <select wire:model="educations.{{ $t }}.passing_year" class="{{ $inputClasses }}">
                                        <option value="">Select one</option>
                                        @foreach ($this->passingYears as $y)
                                            <option value="{{ $y }}">{{ $y }}</option>
                                        @endforeach
                                    </select>
                                    @error("educations.$t.passing_year") <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="pt-2 flex items-center justify-between gap-3 flex-wrap border-t border-gray-100 -mx-6 sm:-mx-10 px-6 sm:px-10 mt-8 pt-6">
                    <div class="flex items-center gap-2">
                        <button type="button"
                            @click="goToTab('addresses')"
                            class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-lg text-sm font-semibold text-gray-700 border border-gray-200 bg-white hover:bg-gray-50 hover:border-gray-300 transition"
                        >
                            <x-lucide-arrow-left class="size-4" /> Previous
                        </button>
                        <button type="button"
                            @click="goToTab('experience')"
                            class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-lg text-sm font-semibold text-gray-700 border border-gray-200 bg-white hover:bg-gray-50 hover:border-gray-300 transition"
                        >
                            Next <x-lucide-arrow-right class="size-4" />
                        </button>
                    </div>

                    <button type="submit"
                        class="inline-flex items-center gap-2 px-7 py-3 rounded-xl font-bold text-white text-sm shadow-md shadow-rose-900/10 transition-opacity hover:opacity-90 disabled:opacity-60"
                        style="background:#8b072b;"
                        wire:loading.attr="disabled"
                        wire:target="saveEducations"
                    >
                        <span wire:loading.remove wire:target="saveEducations">Save education</span>
                        <span wire:loading wire:target="saveEducations">Saving...</span>
                        <x-lucide-arrow-right class="size-4" wire:loading.remove wire:target="saveEducations" />
                    </button>
                </div>
            </form>
        </div>

        {{-- ===================== EXPERIENCE TAB ===================== --}}
        <div x-show="tab === 'experience'" x-cloak class="p-6 sm:p-10">
            @php
                $formattedExperience = rtrim(rtrim(number_format($totalYearsOfExperience, 2, '.', ''), '0'), '.');
                $formattedExperience = $formattedExperience === '' ? '0' : $formattedExperience;
            @endphp

            <div class="mb-7 flex items-start justify-between gap-4">
                <div class="flex items-start gap-3">
                    <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl text-white shrink-0" style="background:#2F1B72;">
                        <x-lucide-briefcase class="size-5" />
                    </span>
                    <div>
                        <h2 class="font-inter font-bold text-gray-900">Experience History</h2>
                        <p class="text-sm text-gray-500 mt-0.5">List your professional work experience, most recent first.</p>
                    </div>
                </div>

                <div class="shrink-0 inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-gray-100 bg-gray-50/70">
                    <x-lucide-clock class="size-4 text-gray-400" />
                    <div class="leading-tight">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Total Years of Experience</p>
                        <p class="text-sm font-bold text-gray-800">{{ $formattedExperience }} {{ $formattedExperience === '1' ? 'Year' : 'Years' }}</p>
                    </div>
                </div>
            </div>

            <form wire:submit="saveExperiences" class="space-y-7">

                @foreach ($experiences as $index => $exp)
                    <div class="{{ $sectionCard }}" wire:key="exp-{{ $index }}-{{ $exp['id'] ?? 'new' }}">
                        <div class="flex items-center justify-between mb-6 pb-5 border-b border-gray-100">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-white" style="background:#8b072b;">
                                    <x-lucide-briefcase class="size-4" />
                                </span>
                                <div>
                                    <h3 class="font-inter font-bold text-gray-800 text-sm">Experience #{{ $index + 1 }}</h3>
                                    <p class="text-xs text-gray-400 mt-0.5">Employment details.</p>
                                </div>
                            </div>
                            <button type="button"
                                wire:click="removeExperience({{ $index }})"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-red-600 hover:bg-red-50 transition"
                            >
                                <x-lucide-trash-2 class="size-3.5" /> Remove
                            </button>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div>
                                <label class="{{ $labelClasses }}">Organization</label>
                                <input type="text" wire:model="experiences.{{ $index }}.organization" class="{{ $inputClasses }}" placeholder="e.g. Square Pharmaceuticals Ltd." />
                                @error("experiences.$index.organization") <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="{{ $labelClasses }}">Designation</label>
                                <input type="text" wire:model="experiences.{{ $index }}.designation" class="{{ $inputClasses }}" placeholder="e.g. Senior Software Engineer" />
                                @error("experiences.$index.designation") <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="{{ $labelClasses }}">Duration</label>
                                <input type="text" wire:model="experiences.{{ $index }}.duration" class="{{ $inputClasses }}" placeholder="e.g. 2 years 6 months" />
                                @error("experiences.$index.duration") <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="{{ $labelClasses }}">Total experience (years)</label>
                                <input type="number" step="0.1" min="0" wire:model="experiences.{{ $index }}.total_experience" class="{{ $inputClasses }}" placeholder="e.g. 2.5" />
                                @error("experiences.$index.total_experience") <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                @endforeach

                <button type="button"
                    wire:click="addExperience"
                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-sm font-semibold border-2 border-dashed border-gray-200 text-gray-500 hover:border-gray-300 hover:bg-gray-50 hover:text-gray-700 transition"
                >
                    <x-lucide-plus class="size-4" /> Add another experience
                </button>

                <div class="pt-2 flex items-center justify-between gap-3 flex-wrap border-t border-gray-100 -mx-6 sm:-mx-10 px-6 sm:px-10 mt-8 pt-6">
                    <div class="flex items-center gap-2">
                        <button type="button"
                            @click="goToTab('education')"
                            class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-lg text-sm font-semibold text-gray-700 border border-gray-200 bg-white hover:bg-gray-50 hover:border-gray-300 transition"
                        >
                            <x-lucide-arrow-left class="size-4" /> Previous
                        </button>
                    </div>

                    <button type="submit"
                        class="inline-flex items-center gap-2 px-7 py-3 rounded-xl font-bold text-white text-sm shadow-md shadow-rose-900/10 transition-opacity hover:opacity-90 disabled:opacity-60"
                        style="background:#8b072b;"
                        wire:loading.attr="disabled"
                        wire:target="saveExperiences"
                    >
                        <span wire:loading.remove wire:target="saveExperiences">Save experience</span>
                        <span wire:loading wire:target="saveExperiences">Saving...</span>
                        <x-lucide-arrow-right class="size-4" wire:loading.remove wire:target="saveExperiences" />
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>

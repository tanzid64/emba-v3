<?php

use App\Enum\BatchStatusEnum;
use App\Enums\ApplicationStatusEnum;
use App\Enums\GenderEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\Applicant;
use App\Models\ApplicantProfile;
use App\Models\Application;
use App\Models\Batch;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->batch = Batch::create([
        'name' => 'EMBA Edit Test '.uniqid(),
        'code' => 'EMBA-ED-'.uniqid(),
        'admission_year' => 2026,
        'status' => BatchStatusEnum::OPEN,
    ]);

    $this->applicant = Applicant::factory()->create([
        'batch_id' => $this->batch->id,
        'email' => 'edit@example.com',
        'phone_number' => '01711112233',
    ]);

    ApplicantProfile::create([
        'applicant_id' => $this->applicant->id,
        'batch_id' => $this->batch->id,
        'full_name' => 'Original Name',
        'father_name' => 'Original Father',
        'mother_name' => 'Original Mother',
        'date_of_birth' => '1990-01-01',
    ]);

    $this->application = Application::create([
        'applicant_id' => $this->applicant->id,
        'batch_id' => $this->batch->id,
        'application_number' => $this->batch->code.'-E0001',
        'status' => ApplicationStatusEnum::AWAITING_PAYMENT,
        'payment_status' => PaymentStatusEnum::UNPAID,
        'applied_at' => now(),
    ]);
});

it('guests cannot access the admin edit page', function () {
    auth()->logout();

    $this->get(route('admin.applicants.edit', $this->application))
        ->assertRedirect(route('login'));
});

it('renders the edit page with the applicant prefilled', function () {
    $this->get(route('admin.applicants.edit', $this->application))
        ->assertOk()
        ->assertSee('Original Name')
        ->assertSee('edit@example.com')
        ->assertSee('01711112233')
        ->assertSeeInOrder(['Profile', 'Addresses', 'Education', 'Experience']);
});

it('saves profile updates without any locked guard', function () {
    Livewire\Livewire::test('pages::admin.applicant-edit', ['application' => $this->application])
        ->set('profile.full_name', 'Updated Name')
        ->set('profile.father_name', 'Updated Father')
        ->set('profile.mother_name', 'Updated Mother')
        ->set('profile.date_of_birth', '1989-12-31')
        ->set('profile.gender', GenderEnum::MALE->value)
        ->set('email', 'updated@example.com')
        ->set('phone_number', '01900000000')
        ->call('saveProfile')
        ->assertHasNoErrors();

    $this->applicant->refresh()->load('profile');

    expect($this->applicant->email)->toBe('updated@example.com')
        ->and($this->applicant->phone_number)->toBe('01900000000')
        ->and($this->applicant->profile->full_name)->toBe('UPDATED NAME')
        ->and($this->applicant->profile->father_name)->toBe('UPDATED FATHER')
        ->and($this->applicant->profile->mother_name)->toBe('UPDATED MOTHER')
        ->and($this->applicant->profile->gender)->toBe(GenderEnum::MALE);
});

it('auto-verifies the email when admin changes it', function () {
    $this->applicant->email_verified_at = null;
    $this->applicant->save();
    expect($this->applicant->fresh()->email_verified_at)->toBeNull();

    Livewire\Livewire::test('pages::admin.applicant-edit', ['application' => $this->application])
        ->set('profile.full_name', 'Some Name')
        ->set('profile.father_name', 'Father')
        ->set('profile.mother_name', 'Mother')
        ->set('profile.date_of_birth', '1990-01-01')
        ->set('email', 'new-email@example.com')
        ->call('saveProfile')
        ->assertHasNoErrors();

    $fresh = $this->applicant->fresh();
    expect($fresh->email)->toBe('new-email@example.com')
        ->and($fresh->email_verified_at)->not->toBeNull();
});

it('allows admin to edit even when the application is already completed', function () {
    $this->application->update([
        'status' => ApplicationStatusEnum::COMPLETED,
        'payment_status' => PaymentStatusEnum::PAID,
        'paid_at' => now(),
    ]);

    Livewire\Livewire::test('pages::admin.applicant-edit', ['application' => $this->application])
        ->set('profile.full_name', 'Locked Name Edit')
        ->set('profile.father_name', 'Some Father')
        ->set('profile.mother_name', 'Some Mother')
        ->set('profile.date_of_birth', '1990-01-01')
        ->call('saveProfile')
        ->assertHasNoErrors();

    expect($this->applicant->profile->fresh()->full_name)->toBe('LOCKED NAME EDIT');
});

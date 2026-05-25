<?php

use App\Enum\BatchStatusEnum;
use App\Enums\ApplicationStatusEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\Applicant;
use App\Models\ApplicantProfile;
use App\Models\Application;
use App\Models\Batch;
use App\Models\User;
use App\Support\CurrentBatch;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function createBatch(BatchStatusEnum $status = BatchStatusEnum::DRAFT): Batch
{
    static $sequence = 0;
    $sequence++;

    return Batch::create([
        'name' => 'EMBA Batch '.$sequence.'-'.uniqid(),
        'code' => 'EMBA-T-'.$sequence.'-'.uniqid(),
        'admission_year' => 2026,
        'status' => $status,
    ]);
}

function createApplication(Batch $batch, array $profile = [], array $applicant = [], array $application = []): Application
{
    $applicantModel = Applicant::factory()->create(array_merge([
        'batch_id' => $batch->id,
    ], $applicant));

    ApplicantProfile::create(array_merge([
        'applicant_id' => $applicantModel->id,
        'batch_id' => $batch->id,
        'full_name' => 'Test Applicant',
        'father_name' => 'Test Father',
        'mother_name' => 'Test Mother',
        'date_of_birth' => '1990-01-01',
    ], $profile));

    return Application::create(array_merge([
        'applicant_id' => $applicantModel->id,
        'batch_id' => $batch->id,
        'application_number' => $batch->code.'-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
        'status' => ApplicationStatusEnum::PENDING,
        'payment_status' => PaymentStatusEnum::UNPAID,
    ], $application));
}

it('guests cannot view the applicants page', function () {
    auth()->logout();

    $this->get(route('admin.applicants.index'))->assertRedirect(route('login'));
});

it('shows only applications in the currently selected batch', function () {
    $selected = createBatch(BatchStatusEnum::OPEN);
    $other = createBatch();

    createApplication($selected, profile: ['full_name' => 'Alice In Batch']);
    createApplication($other, profile: ['full_name' => 'Bob Other Batch']);

    CurrentBatch::set($selected->id);

    $this->get(route('admin.applicants.index'))
        ->assertOk()
        ->assertSee('Alice In Batch')
        ->assertDontSee('Bob Other Batch');
});

it('renders the new column headers and parent names', function () {
    $batch = createBatch(BatchStatusEnum::OPEN);
    CurrentBatch::set($batch->id);

    createApplication($batch,
        profile: [
            'full_name' => 'Mukta Roy',
            'father_name' => 'Madan Lal Roy',
            'mother_name' => 'Maloti Roy Kakoli',
        ],
        applicant: ['email' => 'mukta@example.com', 'phone_number' => '01629513118'],
        application: ['application_number' => 'EMBA-25800320'],
    );

    $this->get(route('admin.applicants.index'))
        ->assertOk()
        ->assertSeeInOrder(['SL', 'Photo', 'App. ID', 'Name', 'Parents', 'Contact', 'Apply Date', 'Payment Status', 'Action'])
        ->assertSee('EMBA-25800320')
        ->assertSee('Madan Lal Roy')
        ->assertSee('Maloti Roy Kakoli')
        ->assertSee('mukta@example.com')
        ->assertSee('01629513118');
});

it('filters applications by search term across name, application number, email, and phone', function () {
    $batch = createBatch(BatchStatusEnum::OPEN);
    CurrentBatch::set($batch->id);

    createApplication($batch,
        profile: ['full_name' => 'Alice Wonderland'],
        applicant: ['email' => 'alice@example.com', 'phone_number' => '01710000001'],
        application: ['application_number' => 'EMBA-AAA-001'],
    );

    createApplication($batch,
        profile: ['full_name' => 'Bob Builder'],
        applicant: ['email' => 'bob@example.com', 'phone_number' => '01720000002'],
        application: ['application_number' => 'EMBA-BBB-002'],
    );

    Livewire\Livewire::test('pages::admin.applicants')
        ->set('search', 'Wonderland')
        ->assertSee('Alice Wonderland')
        ->assertDontSee('Bob Builder')
        ->set('search', 'EMBA-BBB-002')
        ->assertSee('Bob Builder')
        ->assertDontSee('Alice Wonderland')
        ->set('search', '01710000001')
        ->assertSee('Alice Wonderland')
        ->assertDontSee('Bob Builder')
        ->set('search', 'bob@example.com')
        ->assertSee('Bob Builder')
        ->assertDontSee('Alice Wonderland');
});

it('filters by payment status', function () {
    $batch = createBatch(BatchStatusEnum::OPEN);
    CurrentBatch::set($batch->id);

    createApplication($batch,
        profile: ['full_name' => 'Paid Person'],
        application: ['payment_status' => PaymentStatusEnum::PAID],
    );

    createApplication($batch,
        profile: ['full_name' => 'Unpaid Person'],
        application: ['payment_status' => PaymentStatusEnum::UNPAID],
    );

    Livewire\Livewire::test('pages::admin.applicants')
        ->set('paymentStatus', PaymentStatusEnum::PAID->value)
        ->assertSee('Paid Person')
        ->assertDontSee('Unpaid Person')
        ->set('paymentStatus', PaymentStatusEnum::UNPAID->value)
        ->assertSee('Unpaid Person')
        ->assertDontSee('Paid Person');
});

it('renders an empty state when no batch is selected', function () {
    Batch::query()->delete();

    $this->get(route('admin.applicants.index'))
        ->assertOk()
        ->assertSee('No batch selected');
});

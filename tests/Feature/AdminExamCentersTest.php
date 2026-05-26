<?php

use App\Enum\BatchStatusEnum;
use App\Enums\ApplicationStatusEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\AdmissionSetting;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Batch;
use App\Models\ExamCenter;
use App\Models\User;
use App\Support\CurrentBatch;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    $this->batch = Batch::create([
        'name' => 'EMBA EC '.uniqid(),
        'code' => 'EMBA-EC-'.strtoupper(substr(uniqid(), -4)),
        'admission_year' => 2026,
        'status' => BatchStatusEnum::OPEN,
    ]);

    AdmissionSetting::create([
        'batch_id' => $this->batch->id,
        'application_number_start_from' => 1000,
        'roll_number_start_from' => 1000,
    ]);

    CurrentBatch::set($this->batch->id);
});

it('lists exam centers for the current batch and excludes other batches', function () {
    ExamCenter::create([
        'batch_id' => $this->batch->id,
        'center_no' => 'C-01',
        'center_name' => 'Main Campus',
        'room_name' => 'Room 101',
        'capacity' => 50,
    ]);

    $otherBatch = Batch::create([
        'name' => 'EMBA OB '.uniqid(),
        'code' => 'EMBA-OB-'.strtoupper(substr(uniqid(), -4)),
        'admission_year' => 2025,
        'status' => BatchStatusEnum::CLOSED,
    ]);

    ExamCenter::create([
        'batch_id' => $otherBatch->id,
        'center_no' => 'OTHER-99',
        'center_name' => 'Other Batch Center',
        'room_name' => 'Room 999',
        'capacity' => 100,
    ]);

    Livewire::test('pages::admin.exam-centers')
        ->assertOk()
        ->assertSee('C-01')
        ->assertSee('Main Campus')
        ->assertDontSee('OTHER-99');
});

it('shows total capacity and confirmed applicant counts', function () {
    foreach (['Room 101', 'Room 102'] as $room) {
        ExamCenter::create([
            'batch_id' => $this->batch->id,
            'center_no' => 'C-01',
            'center_name' => 'Main Campus',
            'room_name' => $room,
            'capacity' => 50,
        ]);
    }

    $applicant = Applicant::factory()->create(['batch_id' => $this->batch->id]);
    Application::create([
        'applicant_id' => $applicant->id,
        'batch_id' => $this->batch->id,
        'application_number' => $this->batch->code.'-1000',
        'roll_number' => '1000',
        'status' => ApplicationStatusEnum::COMPLETED,
        'payment_status' => PaymentStatusEnum::PAID,
        'applied_at' => now(),
    ]);

    Livewire::test('pages::admin.exam-centers')
        ->assertSee('100') // total capacity = 2 rooms * 50
        ->assertSee('1');  // confirmed applicants
});

it('searches by center number and room name', function () {
    ExamCenter::create([
        'batch_id' => $this->batch->id,
        'center_no' => 'C-01',
        'center_name' => 'Main Campus',
        'room_name' => 'Room 101',
        'capacity' => 50,
    ]);

    ExamCenter::create([
        'batch_id' => $this->batch->id,
        'center_no' => 'C-99',
        'center_name' => 'Annex',
        'room_name' => 'Lab 7',
        'capacity' => 25,
    ]);

    Livewire::test('pages::admin.exam-centers')
        ->set('search', 'Annex')
        ->assertSee('Lab 7')
        ->assertDontSee('Room 101');
});

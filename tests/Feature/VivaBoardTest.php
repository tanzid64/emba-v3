<?php

use App\Enum\BatchStatusEnum;
use App\Enums\ApplicationStatusEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Batch;
use App\Models\User;
use App\Models\VivaBoard;
use App\Support\CurrentBatch;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(), 'web');

    $this->batch = Batch::create([
        'name' => 'EMBA VB '.uniqid(),
        'code' => 'EMBA-VB-'.strtoupper(substr(uniqid(), -4)),
        'admission_year' => 2026,
        'status' => BatchStatusEnum::OPEN,
    ]);

    CurrentBatch::set($this->batch->id);
});

it('creates a viva board scoped to the current batch with center/room details', function () {
    Livewire::test('pages::admin.viva-boards')
        ->set('boardName', 'Board A')
        ->set('centerNo', 'C-01')
        ->set('centerName', 'Main Campus')
        ->set('roomName', 'Room 101')
        ->call('save')
        ->assertHasNoErrors();

    $board = VivaBoard::where('batch_id', $this->batch->id)->first();
    expect($board)->not->toBeNull();
    expect($board->board_name)->toBe('Board A');
    expect($board->center_no)->toBe('C-01');
    expect($board->center_name)->toBe('Main Campus');
    expect($board->room_name)->toBe('Room 101');
});

it('rejects a duplicate board name within the same batch', function () {
    VivaBoard::create(['batch_id' => $this->batch->id, 'board_name' => 'Board A']);

    Livewire::test('pages::admin.viva-boards')
        ->set('boardName', 'Board A')
        ->call('save')
        ->assertHasErrors(['boardName']);

    expect(VivaBoard::where('batch_id', $this->batch->id)->count())->toBe(1);
});

it('allows the same board name in a different batch', function () {
    $otherBatch = Batch::create([
        'name' => 'Other '.uniqid(), 'code' => 'OTH-'.strtoupper(substr(uniqid(), -4)),
        'admission_year' => 2026, 'status' => BatchStatusEnum::OPEN,
    ]);
    VivaBoard::create(['batch_id' => $otherBatch->id, 'board_name' => 'Board A']);

    Livewire::test('pages::admin.viva-boards')
        ->set('boardName', 'Board A')
        ->call('save')
        ->assertHasNoErrors();

    expect(VivaBoard::where('batch_id', $this->batch->id)->where('board_name', 'Board A')->exists())->toBeTrue();
});

it('edits an existing board', function () {
    $board = VivaBoard::create(['batch_id' => $this->batch->id, 'board_name' => 'Board A']);

    Livewire::test('pages::admin.viva-boards')
        ->call('openEditModal', $board->id)
        ->assertSet('boardName', 'Board A')
        ->set('boardName', 'Board B')
        ->call('save')
        ->assertHasNoErrors();

    expect($board->fresh()->board_name)->toBe('Board B');
});

it('deletes a board and un-assigns its candidates', function () {
    $board = VivaBoard::create(['batch_id' => $this->batch->id, 'board_name' => 'Board A']);

    $applicant = Applicant::factory()->create(['batch_id' => $this->batch->id]);
    $application = Application::create([
        'applicant_id' => $applicant->id,
        'batch_id' => $this->batch->id,
        'application_number' => $this->batch->code.'-1000',
        'roll_number' => '1000',
        'status' => ApplicationStatusEnum::COMPLETED,
        'payment_status' => PaymentStatusEnum::PAID,
        'applied_at' => now(),
        'viva_board_id' => $board->id,
    ]);

    Livewire::test('pages::admin.viva-boards')
        ->call('confirmDelete', $board->id)
        ->call('delete')
        ->assertHasNoErrors();

    expect(VivaBoard::find($board->id))->toBeNull();
    expect($application->fresh()->viva_board_id)->toBeNull();
});

it('lists only boards belonging to the current batch', function () {
    VivaBoard::create(['batch_id' => $this->batch->id, 'board_name' => 'Mine Board']);

    $otherBatch = Batch::create([
        'name' => 'Other '.uniqid(), 'code' => 'OTH2-'.strtoupper(substr(uniqid(), -4)),
        'admission_year' => 2026, 'status' => BatchStatusEnum::OPEN,
    ]);
    VivaBoard::create(['batch_id' => $otherBatch->id, 'board_name' => 'Foreign Board']);

    Livewire::test('pages::admin.viva-boards')
        ->assertSee('Mine Board')
        ->assertDontSee('Foreign Board');
});

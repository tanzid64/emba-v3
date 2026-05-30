<?php

use App\Models\Batch;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('deletes a batch when the name and password are correct', function () {
    $batch = Batch::factory()->create(['name' => 'EMBA 2026']);

    Livewire::test('pages::admin.batches')
        ->call('confirmDelete', $batch->id)
        ->set('confirmName', 'EMBA 2026')
        ->set('password', 'password')
        ->call('delete')
        ->assertHasNoErrors();

    expect(Batch::find($batch->id))->toBeNull();
});

it('does not delete the batch when the name does not match', function () {
    $batch = Batch::factory()->create(['name' => 'EMBA 2026']);

    Livewire::test('pages::admin.batches')
        ->call('confirmDelete', $batch->id)
        ->set('confirmName', 'Wrong Name')
        ->set('password', 'password')
        ->call('delete')
        ->assertHasErrors('confirmName');

    expect(Batch::find($batch->id))->not->toBeNull();
});

it('does not delete the batch when the password is wrong', function () {
    $batch = Batch::factory()->create(['name' => 'EMBA 2026']);

    Livewire::test('pages::admin.batches')
        ->call('confirmDelete', $batch->id)
        ->set('confirmName', 'EMBA 2026')
        ->set('password', 'wrong-password')
        ->call('delete')
        ->assertHasErrors('password');

    expect(Batch::find($batch->id))->not->toBeNull();
});

it('does not delete an open batch even with the correct name and password', function () {
    $batch = Batch::factory()->open()->create(['name' => 'EMBA 2026']);

    Livewire::test('pages::admin.batches')
        ->set('deletingBatchId', $batch->id)
        ->set('deletingBatchName', $batch->name)
        ->set('confirmName', 'EMBA 2026')
        ->set('password', 'password')
        ->call('delete');

    expect(Batch::find($batch->id))->not->toBeNull();
});

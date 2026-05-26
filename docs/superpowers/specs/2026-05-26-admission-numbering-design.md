# Admission Numbering Design

**Date:** 2026-05-26
**Scope:** Sequential, per-batch generation of `application_number` and `roll_number`, plus the admin UI to configure the starting values.

## Goal

Replace the current random `application_number` generator with a deterministic per-batch sequence, and add roll-number assignment on payment success. Admin can set the starting integer per batch via two new columns already migrated onto `admission_settings`: `application_number_start_from` and `roll_number_start_from` (both default 1000).

## Non-goals

- No display/format changes to applicant-facing or admin list views (the columns already render where they should; only the values produced change).
- No backfill of existing applications. Migration is recent (2026-05-23) with no production data; if that changes before merge, a separate backfill task is required.
- No reset / re-number tooling for an existing batch.

## Architecture

### Service: `App\Services\AdmissionNumberingService`

A stateless, container-resolved service exposing two methods:

```php
public function nextApplicationNumber(Batch $batch): string;
public function nextRollNumber(Application $application): string;
```

Both methods follow the same shape:

1. Open `DB::transaction`.
2. `AdmissionSetting::where('batch_id', $batch->id)->lockForUpdate()->first()` — pessimistic row lock on the batch's settings row. Two parallel callers for the same batch queue on this row; different batches do not contend.
3. Compute `$next = max($setting->{$startColumn}, ($currentMaxInBatch ?? 0) + 1)`. `currentMaxInBatch` is read with `Application::where('batch_id', …)->max($column)` casting to int. Falling back to `max(column)+1` (rather than just `start_from + count`) means manual edits or future imports cannot collide, and an admin bumping `start_from` after applications exist works as expected.
4. Return formatted string:
   - Application number: `"{$batch->code}-{$next}"` → `EMBA-047-1000`
   - Roll number: `(string) $next` → `"1000"` (stored in the existing `string` column)
5. Caller assigns the value to the model and saves it inside the same transaction.

**Idempotency:** both methods short-circuit and return the existing value if the target column is already populated on the model. Callers may invoke either method during a retried webhook/callback without bumping the counter.

**Concurrency model:** the `lockForUpdate` on `admission_settings` is the only synchronization required. The new `unique(['batch_id', 'application_number'])` and `unique(['batch_id', 'roll_number'])` constraints (see Migration below) act as a database-level safety net behind the lock.

### Integration points

**Application number — assigned on submit:**

- `app/Models/Application.php` — remove the existing random `generateApplicationNumber()` method (currently `app/Models/Application.php:52-59`).
- `app/Models/Application.php` — `draftFor()` (currently lines 64-79) no longer sets `application_number`. Drafts persist with `application_number = null`.
- `app/Models/Application.php` — `submit()` wraps its existing logic in `DB::transaction`, resolves `AdmissionNumberingService` from the container, calls `nextApplicationNumber($this->batch)`, sets `$this->application_number`, sets `applied_at`, transitions `status` to `AWAITING_PAYMENT`, saves.

**Roll number — assigned on payment success:**

- `app/Http/Controllers/Payment/BkashController.php` — `completeApplicationPayment()` (currently lines 264-281). Inside the existing outer transaction (caller wraps at lines 219-241), after setting `status = COMPLETED` and `payment_status = PAID`, call `AdmissionNumberingService::nextRollNumber($application)` and persist on the same model save.

### Admin UI

**Batch create form** — `resources/views/pages/admin/batches/⚡create.blade.php`

- Add two `flux:input type="number"` fields to the `$settings` array: `application_number_start_from`, `roll_number_start_from`.
- Place in a new "Numbering" fieldset between fees and exam schedule.
- Validation: `integer|min:1`.
- Defaults: 1000 (matching the migration defaults), so admins rarely have to touch them.
- Include both keys in `$settingPayload` when creating the `AdmissionSetting`.

**Quick settings panel** — `resources/views/pages/admin/⚡quick-settings.blade.php`

- Add the same two fields to the editable list, wired through the existing `saveField()` flow (currently lines 79-114).
- **Guardrail:** `saveField()` rejects a write to `application_number_start_from` or `roll_number_start_from` when the new value is `<=` the highest existing value for that column in the same batch's applications. On rejection, show a Flux toast naming the current max. This prevents an admin from accidentally setting the starting value into the used range and triggering a unique-constraint violation on the next submit.

### Migration changes

Single in-place edit to `database/migrations/2026_05_23_104604_create_applications_table.php` (safe — recent migration, no production data yet):

- `application_number`: change to `->nullable()` (drafts now persist before a number is assigned).
- Drop `unique(['applicant_id', 'roll_number', 'batch_id'])` — this composite is semantically wrong (it allows two applicants in the same batch to share a roll number).
- Add `unique(['batch_id', 'application_number'])`.
- Add `unique(['batch_id', 'roll_number'])` — MySQL allows multiple NULLs in a unique index, so unassigned drafts remain valid.

If at PR time production data exists, swap this in-place edit for a new `_alter_` migration with the same operations and a one-shot data backfill.

## Why not observers

Considered and rejected:

- Model observers fire during seeders and factories, which would pollute test fixtures and force `withoutEvents` workarounds.
- Observers hide the side effect from readers of the submit / payment-completion code paths.
- The number generation needs to run inside an explicit transaction with `lockForUpdate`. Doing that from an observer is awkward and obscures the transaction boundary.
- An explicit service call from `Application::submit()` and `BkashController::completeApplicationPayment()` makes the side effect local to the code that decided it should happen, and makes testing the transition trivial.

## Testing

All tests are Pest feature tests using `RefreshDatabase`.

**`tests/Feature/AdmissionNumberingTest.php`** — service-level:
- starts from `application_number_start_from` for the first application in a batch
- increments by 1 for the next application in the same batch
- per-batch isolation: two batches each start from their own `start_from`
- raising `start_from` above the current max raises the next number
- setting `start_from` below the current max is overridden by `max(column)+1`
- `nextRollNumber` is idempotent: a second call on the same application returns the existing value, does not bump the counter
- concurrent same-batch generation produces distinct numbers (verified by issuing two `nextApplicationNumber` calls in nested transactions and asserting different values)

**`tests/Feature/ApplicationSubmitTest.php`** — integration:
- submitting a draft assigns `application_number` matching `^{batch_code}-\d+$` and transitions status to `AWAITING_PAYMENT`
- a draft that is never submitted persists with `application_number = null`

**`tests/Feature/PaymentCompletesApplicationTest.php`** — extends or sits alongside `tests/Feature/VerifyPaymentTest.php`:
- a successful bKash callback assigns `roll_number`, sets `status = COMPLETED`, `payment_status = PAID`
- a replayed callback does not change the previously assigned `roll_number`

**`tests/Feature/AdminQuickSettingsTest.php`** — Livewire:
- admin updates `application_number_start_from` to a value above the current max → persisted
- admin sets it below the current max → rejected with an error message naming the max

**`tests/Feature/AdminBatchCreateTest.php`** — extend the existing test (or add one if absent):
- creating a new batch persists both `*_start_from` values onto the new `AdmissionSetting`

## File plan

| File | Action |
|---|---|
| `app/Services/AdmissionNumberingService.php` | new |
| `app/Models/Application.php` | remove `generateApplicationNumber`, edit `draftFor`, edit `submit` |
| `app/Http/Controllers/Payment/BkashController.php` | edit `completeApplicationPayment` |
| `resources/views/pages/admin/batches/⚡create.blade.php` | add 2 inputs, validation, payload entries |
| `resources/views/pages/admin/⚡quick-settings.blade.php` | add 2 editable fields with guardrail |
| `database/migrations/2026_05_23_104604_create_applications_table.php` | `application_number` nullable, drop old composite unique, add 2 new uniques |
| `tests/Feature/AdmissionNumberingTest.php` | new |
| `tests/Feature/ApplicationSubmitTest.php` | new |
| `tests/Feature/PaymentCompletesApplicationTest.php` | new |
| `tests/Feature/AdminQuickSettingsTest.php` | new |
| `tests/Feature/AdminBatchCreateTest.php` | new or extended |

## Out of scope

- Backfill or re-numbering of existing applications.
- Re-number / reset tooling for an existing batch.
- Applicant-facing or admin-list display changes (existing views already render the columns).
- Payment gateways other than bKash. If additional gateways land later, each new completion handler must call `nextRollNumber` the same way `BkashController::completeApplicationPayment` does.

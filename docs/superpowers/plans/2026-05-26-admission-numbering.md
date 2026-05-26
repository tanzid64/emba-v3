# Admission Numbering Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace random `application_number` generation with a deterministic per-batch sequence, assign `roll_number` on successful payment, and add admin UI for the two `*_start_from` settings.

**Architecture:** A stateless service (`AdmissionNumberingService`) generates next numbers per-batch using a pessimistic `lockForUpdate` on the `admission_settings` row. `Application::submit()` calls it to assign `application_number`; `BkashController::completeApplicationPayment()` calls it to assign `roll_number`. Both calls run inside an enclosing `DB::transaction`. Admin batch-create form and quick-settings panel expose the two `*_start_from` integer columns. A guardrail prevents lowering `*_start_from` into the already-used range.

**Tech Stack:** Laravel 13, Livewire 4, Pest 4, MySQL, Flux UI.

**Spec:** `docs/superpowers/specs/2026-05-26-admission-numbering-design.md`

**Conventions to keep in mind:**
- `App\Enums\*` (plural) holds most enums, including `ApplicationStatusEnum`, `PaymentStatusEnum`, `PaymentMethodEnum`, `PaymentActorEnum`. `App\Enum\BatchStatusEnum` is the one singular-folder exception. Match imports to the file you are editing.
- Run `vendor/bin/pint --dirty --format agent` after PHP edits, before commits.
- Use `php artisan test --compact --filter <name>` to scope test runs.
- **Test DB is SQLite in-memory** (per `phpunit.xml`). Do not use MySQL-specific SQL (`SUBSTRING_INDEX`, `CAST(... AS UNSIGNED)`, `SELECT FOR UPDATE`) in service code. Compute integer extraction in PHP. `lockForUpdate` is a no-op on SQLite — that's fine for tests; production is MySQL where it locks the row.
- `App\Support\CurrentBatch::set($id)` puts a batch id into the session under key `admin.current_batch_id`. Without a setting, `CurrentBatch::get()` falls back to the first `OPEN`-status batch. For quick-settings tests, call `CurrentBatch::set($this->batch->id)` in `beforeEach` to be explicit.

---

## Task 1: Migration changes + schema reset

**Files:**
- Modify: `database/migrations/2026_05_23_104604_create_applications_table.php`

Safe to edit in place because the migration is recent and no production data exists. If you discover production data when checking, stop and convert this into a new `_alter_` migration plus a backfill task.

- [ ] **Step 1: Inspect current migration**

Run: `php artisan migrate:status | head -20`
Expected: see the `2026_05_23_104604_create_applications_table` row marked `Ran`.

- [ ] **Step 2: Edit the migration**

Open `database/migrations/2026_05_23_104604_create_applications_table.php`.

Change line 21 from:
```php
$table->string('application_number');
```
to:
```php
$table->string('application_number')->nullable();
```

Replace line 35:
```php
$table->unique(['applicant_id', 'roll_number', 'batch_id']);
```
with these three lines:
```php
$table->unique(['batch_id', 'application_number']);
$table->unique(['batch_id', 'roll_number']);
```

(The old composite is dropped entirely. MySQL allows multiple NULLs in a unique index by default, so unsubmitted drafts and pre-payment applications remain valid.)

- [ ] **Step 3: Reset the schema**

Run: `php artisan migrate:fresh --no-interaction`
Expected: all migrations re-run cleanly, no errors. (Re-seeds if you have a `db:seed` configured for fresh — that is fine.)

- [ ] **Step 4: Verify the columns**

Run: `php artisan db:table applications`
Expected: in the column list, `application_number` is shown as nullable. In the indexes section, both `applications_batch_id_application_number_unique` and `applications_batch_id_roll_number_unique` appear; the old `applications_applicant_id_roll_number_batch_id_unique` is gone.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_05_23_104604_create_applications_table.php
git commit -m "fix(db): nullable application_number, fix unique indexes on applications"
```

---

## Task 2: AdmissionNumberingService (TDD)

**Files:**
- Create: `app/Services/AdmissionNumberingService.php`
- Test: `tests/Feature/AdmissionNumberingTest.php`

The service has two methods that share the same lock-and-compute logic. Build it in steps so the tests drive the shape.

- [ ] **Step 1: Create the test file with the first failing test**

Run: `php artisan make:test --pest AdmissionNumberingTest`
Then replace `tests/Feature/AdmissionNumberingTest.php` with:

```php
<?php

use App\Enum\BatchStatusEnum;
use App\Enums\ApplicationStatusEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\AdmissionSetting;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Batch;
use App\Services\AdmissionNumberingService;

function makeBatchWithSettings(array $settingOverrides = []): Batch
{
    $batch = Batch::create([
        'name' => 'EMBA AN '.uniqid(),
        'code' => 'EMBA-AN-'.strtoupper(substr(uniqid(), -4)),
        'admission_year' => 2026,
        'status' => BatchStatusEnum::OPEN,
    ]);

    AdmissionSetting::create(array_merge([
        'batch_id' => $batch->id,
        'application_number_start_from' => 1000,
        'roll_number_start_from' => 1000,
    ], $settingOverrides));

    return $batch->refresh();
}

function makeApplication(Batch $batch, array $overrides = []): Application
{
    $applicant = Applicant::factory()->create(['batch_id' => $batch->id]);

    return Application::create(array_merge([
        'applicant_id' => $applicant->id,
        'batch_id' => $batch->id,
        'status' => ApplicationStatusEnum::PENDING,
        'payment_status' => PaymentStatusEnum::UNPAID,
    ], $overrides));
}

it('returns start_from as the first application number for a batch', function () {
    $batch = makeBatchWithSettings(['application_number_start_from' => 1500]);

    $service = app(AdmissionNumberingService::class);

    expect($service->nextApplicationNumber($batch))->toBe($batch->code.'-1500');
});
```

- [ ] **Step 2: Run the test, confirm it fails**

Run: `php artisan test --compact --filter 'returns start_from as the first application number'`
Expected: FAIL — `Class "App\Services\AdmissionNumberingService" not found`.

- [ ] **Step 3: Create the service skeleton**

Create `app/Services/AdmissionNumberingService.php`:

```php
<?php

namespace App\Services;

use App\Models\AdmissionSetting;
use App\Models\Application;
use App\Models\Batch;
use Illuminate\Support\Facades\DB;

class AdmissionNumberingService
{
    public function nextApplicationNumber(Batch $batch): string
    {
        $next = $this->nextSequence(
            batchId: $batch->id,
            startColumn: 'application_number_start_from',
            applicationColumn: 'application_number',
        );

        return $batch->code.'-'.$next;
    }

    public function nextRollNumber(Application $application): string
    {
        if ($application->roll_number !== null) {
            return $application->roll_number;
        }

        $next = $this->nextSequence(
            batchId: $application->batch_id,
            startColumn: 'roll_number_start_from',
            applicationColumn: 'roll_number',
        );

        return (string) $next;
    }

    private function nextSequence(int $batchId, string $startColumn, string $applicationColumn): int
    {
        return DB::transaction(function () use ($batchId, $startColumn, $applicationColumn) {
            $setting = AdmissionSetting::where('batch_id', $batchId)
                ->lockForUpdate()
                ->firstOrFail();

            $startFrom = (int) $setting->{$startColumn};

            $currentMax = Application::where('batch_id', $batchId)
                ->whereNotNull($applicationColumn)
                ->pluck($applicationColumn)
                ->map(fn (string $raw): ?int => self::extractSequenceInt($raw, $applicationColumn))
                ->filter()
                ->max() ?? 0;

            return max($startFrom, $currentMax + 1);
        });
    }

    public static function extractSequenceInt(string $value, string $column): ?int
    {
        if ($column === 'roll_number') {
            return ctype_digit($value) ? (int) $value : null;
        }

        // application_number is stored as "{BATCH_CODE}-{INT}". Take the substring after the last dash.
        $tail = substr($value, strrpos($value, '-') + 1);

        return ctype_digit($tail) ? (int) $tail : null;
    }
}
```

- [ ] **Step 4: Run the test, confirm it passes**

Run: `php artisan test --compact --filter 'returns start_from as the first application number'`
Expected: PASS.

- [ ] **Step 5: Add the remaining service tests**

Append to `tests/Feature/AdmissionNumberingTest.php`:

```php
it('increments the next application number by 1', function () {
    $batch = makeBatchWithSettings();
    $service = app(AdmissionNumberingService::class);

    $first = $service->nextApplicationNumber($batch);
    makeApplication($batch, ['application_number' => $first]);

    expect($service->nextApplicationNumber($batch))->toBe($batch->code.'-1001');
});

it('isolates the sequence per batch', function () {
    $batchA = makeBatchWithSettings(['application_number_start_from' => 100]);
    $batchB = makeBatchWithSettings(['application_number_start_from' => 500]);

    $service = app(AdmissionNumberingService::class);

    expect($service->nextApplicationNumber($batchA))->toBe($batchA->code.'-100');
    expect($service->nextApplicationNumber($batchB))->toBe($batchB->code.'-500');
});

it('raises the counter when start_from is bumped above current max', function () {
    $batch = makeBatchWithSettings();
    makeApplication($batch, ['application_number' => $batch->code.'-1000']);

    $batch->admissionSetting->update(['application_number_start_from' => 2000]);

    $service = app(AdmissionNumberingService::class);

    expect($service->nextApplicationNumber($batch))->toBe($batch->code.'-2000');
});

it('overrides a start_from that is below current max with max+1', function () {
    $batch = makeBatchWithSettings(['application_number_start_from' => 1000]);
    makeApplication($batch, ['application_number' => $batch->code.'-1050']);

    $batch->admissionSetting->update(['application_number_start_from' => 1010]);

    $service = app(AdmissionNumberingService::class);

    expect($service->nextApplicationNumber($batch))->toBe($batch->code.'-1051');
});

it('starts roll numbers from roll_number_start_from', function () {
    $batch = makeBatchWithSettings(['roll_number_start_from' => 2500]);
    $application = makeApplication($batch);

    $service = app(AdmissionNumberingService::class);

    expect($service->nextRollNumber($application))->toBe('2500');
});

it('is idempotent when the application already has a roll number', function () {
    $batch = makeBatchWithSettings();
    $application = makeApplication($batch, ['roll_number' => '9999']);

    $service = app(AdmissionNumberingService::class);

    expect($service->nextRollNumber($application))->toBe('9999');

    // Counter should not have advanced — a fresh application gets start_from, not 10000.
    $fresh = makeApplication($batch);
    expect($service->nextRollNumber($fresh))->toBe('1000');
});
```

- [ ] **Step 6: Run the full file, confirm all pass**

Run: `php artisan test --compact tests/Feature/AdmissionNumberingTest.php`
Expected: 6 passing tests.

- [ ] **Step 7: Lint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: changes formatted, no errors.

- [ ] **Step 8: Commit**

```bash
git add app/Services/AdmissionNumberingService.php tests/Feature/AdmissionNumberingTest.php
git commit -m "feat(admission): per-batch sequential numbering service"
```

---

## Task 3: Wire `nextApplicationNumber` into `Application::submit()` (TDD)

**Files:**
- Modify: `app/Models/Application.php` (lines 48-96 — remove `generateApplicationNumber`, edit `draftFor`, edit `submit`)
- Test: `tests/Feature/ApplicationSubmitTest.php`

- [ ] **Step 1: Create the failing test**

Run: `php artisan make:test --pest ApplicationSubmitTest`
Then replace `tests/Feature/ApplicationSubmitTest.php` with:

```php
<?php

use App\Enum\BatchStatusEnum;
use App\Enums\ApplicationStatusEnum;
use App\Models\AdmissionSetting;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Batch;

beforeEach(function () {
    $this->batch = Batch::create([
        'name' => 'EMBA Submit '.uniqid(),
        'code' => 'EMBA-SB-'.strtoupper(substr(uniqid(), -4)),
        'admission_year' => 2026,
        'status' => BatchStatusEnum::OPEN,
    ]);

    AdmissionSetting::create([
        'batch_id' => $this->batch->id,
        'application_number_start_from' => 1000,
        'roll_number_start_from' => 1000,
    ]);

    $this->applicant = Applicant::factory()->create(['batch_id' => $this->batch->id]);
});

it('creates a draft without an application_number', function () {
    $application = Application::draftFor($this->applicant);

    expect($application->application_number)->toBeNull();
    expect($application->status)->toBe(ApplicationStatusEnum::PENDING);
});

it('assigns a sequential application_number on submit', function () {
    $application = Application::draftFor($this->applicant);

    $application->submit();

    expect($application->fresh()->application_number)->toBe($this->batch->code.'-1000');
    expect($application->fresh()->status)->toBe(ApplicationStatusEnum::AWAITING_PAYMENT);
    expect($application->fresh()->is_applied)->toBeTrue();
});

it('does not re-assign application_number on a repeat submit', function () {
    $application = Application::draftFor($this->applicant);
    $application->submit();
    $first = $application->fresh()->application_number;

    $application->submit();

    expect($application->fresh()->application_number)->toBe($first);
});
```

- [ ] **Step 2: Run the tests, confirm they fail**

Run: `php artisan test --compact tests/Feature/ApplicationSubmitTest.php`
Expected: FAIL — at minimum, the first test fails because `application_number` is currently NOT NULL... wait, you just made it nullable in Task 1, but `draftFor` still assigns a random value. Expect: first test FAILs (number is set, not null), second test FAILs (no service call), third may incidentally pass.

- [ ] **Step 3: Edit `app/Models/Application.php`**

Replace lines 48-96 (everything from the `generateApplicationNumber` doc block down to the closing brace of `submit()`) with:

```php
    /**
     * Find or create a draft application for the given applicant + current batch.
     * Drafts have no application_number — one is assigned on submit().
     */
    public static function draftFor(Applicant $applicant): self
    {
        $applicant->loadMissing('batch');

        return static::firstOrCreate(
            [
                'applicant_id' => $applicant->id,
                'batch_id' => $applicant->batch_id,
            ],
            [
                'status' => ApplicationStatusEnum::PENDING,
                'payment_status' => PaymentStatusEnum::UNPAID,
            ],
        );
    }

    /**
     * Submit this application — assigns the sequential application_number,
     * sets applied_at, and moves status to AWAITING_PAYMENT.
     */
    public function submit(): self
    {
        if ($this->is_applied) {
            return $this;
        }

        DB::transaction(function (): void {
            $this->loadMissing('batch');

            if ($this->application_number === null) {
                $this->application_number = app(\App\Services\AdmissionNumberingService::class)
                    ->nextApplicationNumber($this->batch);
            }

            $this->fill([
                'applied_at' => now(),
                'status' => ApplicationStatusEnum::AWAITING_PAYMENT,
            ])->save();
        });

        return $this;
    }
```

Then add the `DB` facade to the imports at the top of the file (after the existing `use` lines):

```php
use Illuminate\Support\Facades\DB;
```

- [ ] **Step 4: Run the tests, confirm they pass**

Run: `php artisan test --compact tests/Feature/ApplicationSubmitTest.php`
Expected: 3 passing tests.

- [ ] **Step 5: Make sure nothing else broke**

Run: `php artisan test --compact`
Expected: full suite green. (If `VerifyApplicationTest` or anything else relied on the random-number contract, fix the test by removing the assumption that `draftFor` populates the number.)

- [ ] **Step 6: Lint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: clean.

- [ ] **Step 7: Commit**

```bash
git add app/Models/Application.php tests/Feature/ApplicationSubmitTest.php
git commit -m "feat(admission): assign sequential application_number on submit"
```

---

## Task 4: Wire `nextRollNumber` into `BkashController::completeApplicationPayment` (TDD)

**Files:**
- Modify: `app/Http/Controllers/Payment/BkashController.php` (lines 264-281)
- Test: `tests/Feature/PaymentCompletesApplicationTest.php`

We test `completeApplicationPayment` indirectly via its single caller pattern: build a `BkashController` instance, set up Payment+Application rows by hand, then invoke the private method via reflection. (The full HTTP callback flow is already covered by `VerifyPaymentTest`; here we want fast, focused coverage of the new behaviour.)

- [ ] **Step 1: Create the failing test**

Run: `php artisan make:test --pest PaymentCompletesApplicationTest`
Then replace `tests/Feature/PaymentCompletesApplicationTest.php` with:

```php
<?php

use App\Enum\BatchStatusEnum;
use App\Enums\ApplicationStatusEnum;
use App\Enums\PaymentActorEnum;
use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentStatusEnum;
use App\Http\Controllers\Payment\BkashController;
use App\Models\AdmissionSetting;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Batch;
use App\Models\Payment;
use App\Services\BkashService;

function makeBatchAndApp(array $settingOverrides = []): array
{
    $batch = Batch::create([
        'name' => 'EMBA Pay '.uniqid(),
        'code' => 'EMBA-PY-'.strtoupper(substr(uniqid(), -4)),
        'admission_year' => 2026,
        'status' => BatchStatusEnum::OPEN,
    ]);

    AdmissionSetting::create(array_merge([
        'batch_id' => $batch->id,
        'application_number_start_from' => 1000,
        'roll_number_start_from' => 1000,
    ], $settingOverrides));

    $applicant = Applicant::factory()->create(['batch_id' => $batch->id]);

    $application = Application::create([
        'applicant_id' => $applicant->id,
        'batch_id' => $batch->id,
        'application_number' => $batch->code.'-1000',
        'status' => ApplicationStatusEnum::AWAITING_PAYMENT,
        'payment_status' => PaymentStatusEnum::UNPAID,
        'applied_at' => now(),
    ]);

    $payment = Payment::create([
        'payment_number' => 'PMT-'.uniqid(),
        'batch_id' => $batch->id,
        'applicant_id' => $applicant->id,
        'actor_table' => PaymentActorEnum::APPLICATION,
        'actor_id' => $application->id,
        'payment_method' => PaymentMethodEnum::BKASH,
        'amount' => 2500,
        'status' => PaymentStatusEnum::COMPLETED,
    ]);

    return compact('batch', 'applicant', 'application', 'payment');
}

function callCompleteApplicationPayment(Payment $payment, array $response): void
{
    $controller = new BkashController(app(BkashService::class));
    $ref = new ReflectionMethod($controller, 'completeApplicationPayment');
    $ref->setAccessible(true);
    $ref->invoke($controller, $payment, $response);
}

it('assigns a roll_number and marks application COMPLETED on payment success', function () {
    ['application' => $application, 'payment' => $payment, 'batch' => $batch] = makeBatchAndApp(
        ['roll_number_start_from' => 5000]
    );

    callCompleteApplicationPayment($payment, ['paymentID' => 'PAYID-1', 'trxID' => 'TRX-1']);

    $application->refresh();
    expect($application->roll_number)->toBe('5000');
    expect($application->status)->toBe(ApplicationStatusEnum::COMPLETED);
    expect($application->payment_status)->toBe(PaymentStatusEnum::PAID);
    expect($application->trx_id)->toBe('TRX-1');
});

it('does not reassign roll_number on a replayed payment completion', function () {
    ['application' => $application, 'payment' => $payment] = makeBatchAndApp();

    callCompleteApplicationPayment($payment, ['paymentID' => 'PAYID-1', 'trxID' => 'TRX-1']);
    $firstRoll = $application->fresh()->roll_number;

    callCompleteApplicationPayment($payment, ['paymentID' => 'PAYID-1', 'trxID' => 'TRX-1']);

    expect($application->fresh()->roll_number)->toBe($firstRoll);
});
```

- [ ] **Step 2: Run the tests, confirm they fail**

Run: `php artisan test --compact tests/Feature/PaymentCompletesApplicationTest.php`
Expected: FAIL — `roll_number` is null because no service is called yet.

- [ ] **Step 3: Edit `completeApplicationPayment` in `app/Http/Controllers/Payment/BkashController.php`**

Replace the method body at lines 264-281 with:

```php
    private function completeApplicationPayment(Payment $payment, array $response): void
    {
        $application = Application::find($payment->actor_id);

        if (! $application) {
            return;
        }

        if ($application->roll_number === null) {
            $application->roll_number = app(\App\Services\AdmissionNumberingService::class)
                ->nextRollNumber($application);
        }

        $application->fill([
            'status' => ApplicationStatusEnum::COMPLETED,
            'payment_status' => PaymentStatusEnum::PAID,
            'payment_method' => PaymentMethodEnum::BKASH,
            'amount' => $payment->amount,
            'payment_id' => $response['paymentID'] ?? null,
            'trx_id' => $response['trxID'] ?? null,
            'paid_at' => now(),
        ])->save();
    }
```

(Same shape as before — the only additions are the `roll_number` block and switching from `update()` to `fill()->save()` so the roll-number assignment persists in one write.)

- [ ] **Step 4: Run the tests, confirm they pass**

Run: `php artisan test --compact tests/Feature/PaymentCompletesApplicationTest.php`
Expected: 2 passing tests.

- [ ] **Step 5: Run the whole suite**

Run: `php artisan test --compact`
Expected: all green.

- [ ] **Step 6: Lint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Payment/BkashController.php tests/Feature/PaymentCompletesApplicationTest.php
git commit -m "feat(admission): assign roll_number on bkash payment success"
```

---

## Task 5: Admin batch-create form fields (TDD)

**Files:**
- Modify: `resources/views/pages/admin/batches/⚡create.blade.php`
- Test: `tests/Feature/AdminBatchCreateTest.php`

The component is a single-file Livewire SFC. We add two integer inputs to `$settings`, two validation rules, and let the existing `$settingPayload` spread pick them up.

- [ ] **Step 1: Identify (or create) an admin user factory state**

Run: `grep -rn "actingAs.*Admin\|admin()->create\|->state(\"admin\"\|loginAsAdmin" tests/`
If the project uses a specific `loginAs` helper for the admin guard, mirror that in the test. If unsure, look at existing admin-area tests (e.g. `tests/Feature/Admin*`) — copy their setup. If none exist, fall back to:

```php
use App\Models\User;
$this->actingAs(User::factory()->create(), 'web');
```

(Note: the project may use a separate admin guard. Discover and use whatever the existing pages-admin tests use.)

- [ ] **Step 2: Create the failing test**

Run: `php artisan make:test --pest AdminBatchCreateTest`
Then replace `tests/Feature/AdminBatchCreateTest.php` with the following — patch the admin-auth line to match Step 1:

```php
<?php

use App\Enum\BatchStatusEnum;
use App\Models\AdmissionSetting;
use App\Models\Batch;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->actingAs(User::factory()->create(), 'web'); // adjust to project's admin auth
});

it('persists application_number_start_from and roll_number_start_from when creating a batch', function () {
    Volt::test('pages.admin.batches.create')
        ->set('batch.name', 'EMBA Test '.uniqid())
        ->set('batch.code', 'EMBA-T-'.strtoupper(substr(uniqid(), -4)))
        ->set('batch.admission_year', 2026)
        ->set('batch.status', BatchStatusEnum::DRAFT->value)
        ->set('settings.application_fee', 2500)
        ->set('settings.enrollment_fee', 500)
        ->set('settings.admission_fee', 12000)
        ->set('settings.application_number_start_from', 1700)
        ->set('settings.roll_number_start_from', 1800)
        ->call('save');

    $batch = Batch::latest('id')->first();
    expect($batch)->not->toBeNull();

    $setting = AdmissionSetting::where('batch_id', $batch->id)->first();
    expect($setting->application_number_start_from)->toBe(1700);
    expect($setting->roll_number_start_from)->toBe(1800);
});
```

- [ ] **Step 3: Run the test, confirm it fails**

Run: `php artisan test --compact tests/Feature/AdminBatchCreateTest.php`
Expected: FAIL — likely a validation error because the new fields are unknown and not validated, or the assertion fails because the values aren't persisted. (If you hit a Volt resolution error, the page-component path may need adjusting — `pages.admin.batches.create` matches the file at `resources/views/pages/admin/batches/⚡create.blade.php` for Livewire 4 page components.)

- [ ] **Step 4: Edit `resources/views/pages/admin/batches/⚡create.blade.php`**

In the `$settings` array (currently lines 28-37), add two keys at the end:
```php
        'application_fee' => 2500,
        'enrollment_fee' => 500,
        'admission_fee' => 12000,
        'application_number_start_from' => 1000,
        'roll_number_start_from' => 1000,
    ];
```

In the validation rules array inside `save()` (after the three fee rules), add:
```php
            'settings.application_fee' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'settings.enrollment_fee' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'settings.admission_fee' => ['required', 'numeric', 'min:0', 'max:1000000'],

            'settings.application_number_start_from' => ['required', 'integer', 'min:1'],
            'settings.roll_number_start_from' => ['required', 'integer', 'min:1'],
```

In the `attributes:` array of the validate call, add:
```php
            'settings.application_number_start_from' => __('application number start'),
            'settings.roll_number_start_from' => __('roll number start'),
```

Then add a new fieldset to the template between the Fees fieldset (ends around line 269) and the Exam Schedule fieldset (starts around line 271). Insert:

```blade
        {{-- ===================== NUMBERING ===================== --}}
        <fieldset class="{{ $sectionCard }}">
            <legend class="{{ $sectionLegend }}">{{ __('Numbering') }}</legend>
            <p class="{{ $sectionDescription }}">{{ __('Starting integers for sequential application and roll numbers in this batch.') }}</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="{{ $labelClasses }}">{{ __('Application number starts from') }} <span class="text-red-500">*</span></label>
                    <x-ui.input type="number" step="1" min="1" wire:model="settings.application_number_start_from" />
                    @error('settings.application_number_start_from') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="{{ $labelClasses }}">{{ __('Roll number starts from') }} <span class="text-red-500">*</span></label>
                    <x-ui.input type="number" step="1" min="1" wire:model="settings.roll_number_start_from" />
                    @error('settings.roll_number_start_from') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                </div>
            </div>
        </fieldset>
```

- [ ] **Step 5: Run the test, confirm it passes**

Run: `php artisan test --compact tests/Feature/AdminBatchCreateTest.php`
Expected: PASS.

- [ ] **Step 6: Manually verify in the browser**

Open the admin batch create page (the user runs lerd; site is `https://emba-admission-v3.test/admin/batches/create`). Confirm the new fieldset appears between Fees and Exam Schedule with default values 1000/1000. Submit to verify no UI regression.

- [ ] **Step 7: Lint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 8: Commit**

```bash
git add resources/views/pages/admin/batches/⚡create.blade.php tests/Feature/AdminBatchCreateTest.php
git commit -m "feat(admin): batch create form supports start_from numbering inputs"
```

---

## Task 6: Quick-settings panel — editable fields + guardrail (TDD)

**Files:**
- Modify: `resources/views/pages/admin/⚡quick-settings.blade.php`
- Test: `tests/Feature/AdminQuickSettingsTest.php`

We add the two fields to the `fields()` list, extend `rulesFor`, `labelFor`, `descriptionFor`, `displayValueFor`, `inputTypeFor`, and add a guardrail in `saveField()` that rejects values `<=` the current max for that batch's applications.

- [ ] **Step 1: Create the failing test**

Run: `php artisan make:test --pest AdminQuickSettingsTest`
Then replace `tests/Feature/AdminQuickSettingsTest.php` with the test below — adjust the admin auth line to match the project's convention from Task 5 Step 1:

```php
<?php

use App\Enum\BatchStatusEnum;
use App\Enums\ApplicationStatusEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\AdmissionSetting;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Batch;
use App\Models\User;
use App\Support\CurrentBatch;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->actingAs(User::factory()->create(), 'web'); // adjust to project's admin auth

    $this->batch = Batch::create([
        'name' => 'EMBA QS '.uniqid(),
        'code' => 'EMBA-QS-'.strtoupper(substr(uniqid(), -4)),
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

it('persists a new application_number_start_from when above current max', function () {
    Volt::test('pages.admin.quick-settings')
        ->call('startEdit', 'application_number_start_from')
        ->set('fieldValue', 2500)
        ->call('saveField');

    expect($this->batch->fresh()->admissionSetting->application_number_start_from)->toBe(2500);
});

it('rejects an application_number_start_from below the current max', function () {
    $applicant = Applicant::factory()->create(['batch_id' => $this->batch->id]);
    Application::create([
        'applicant_id' => $applicant->id,
        'batch_id' => $this->batch->id,
        'application_number' => $this->batch->code.'-1200',
        'status' => ApplicationStatusEnum::AWAITING_PAYMENT,
        'payment_status' => PaymentStatusEnum::UNPAID,
        'applied_at' => now(),
    ]);

    Volt::test('pages.admin.quick-settings')
        ->call('startEdit', 'application_number_start_from')
        ->set('fieldValue', 1100)
        ->call('saveField')
        ->assertHasErrors(['fieldValue']);

    expect($this->batch->fresh()->admissionSetting->application_number_start_from)->toBe(1000);
});
```

- [ ] **Step 2: Run the tests, confirm they fail**

Run: `php artisan test --compact tests/Feature/AdminQuickSettingsTest.php`
Expected: FAIL — first test fails because the field isn't editable / not in `fields()`. Second test also fails because no guardrail exists yet.

- [ ] **Step 3: Edit `resources/views/pages/admin/⚡quick-settings.blade.php`**

In the `fields()` method (around line 322-337), add two entries at the bottom (before `admit_card_published_at`):

```php
            ['key' => 'application_fee', 'kind' => 'money'],
            ['key' => 'application_payment_ended_at', 'kind' => 'date'],
            ['key' => 'enrollment_fee', 'kind' => 'money'],
            ['key' => 'admission_fee', 'kind' => 'money'],
            ['key' => 'application_number_start_from', 'kind' => 'integer'],
            ['key' => 'roll_number_start_from', 'kind' => 'integer'],
            ['key' => 'exam_date', 'kind' => 'datetime'],
            ['key' => 'viva_date', 'kind' => 'datetime'],
```

In `rulesFor()` (around line 195-203), add the integer case before the `default`:

```php
        return match ($field) {
            'application_fee', 'enrollment_fee', 'admission_fee' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'application_payment_ended_at' => ['nullable', 'date'],
            'exam_date', 'viva_date' => ['nullable', 'date'],
            'application_number_start_from', 'roll_number_start_from' => ['required', 'integer', 'min:1'],
            default => ['nullable', 'string'],
        };
```

In `inputTypeFor()` (around line 220-227), add an entry:

```php
        return match ($field) {
            'application_fee', 'enrollment_fee', 'admission_fee' => 'number',
            'application_number_start_from', 'roll_number_start_from' => 'number',
            'exam_date', 'viva_date' => 'datetime-local',
            default => 'date',
        };
```

In `labelFor()` (the array around line 229-243), add:

```php
            'application_number_start_from' => __('Application No. Starts From'),
            'roll_number_start_from' => __('Roll No. Starts From'),
```

In `descriptionFor()` (around line 245-257), add:

```php
            'application_number_start_from' => __('Starting integer for application numbers in this batch. Cannot be lowered below the highest number already issued.'),
            'roll_number_start_from' => __('Starting integer for roll numbers in this batch. Cannot be lowered below the highest roll number already issued.'),
```

In `displayValueFor()` (around line 259-302), add a clause before the final `return is_array($value) …`:

```php
        if (in_array($field, ['application_number_start_from', 'roll_number_start_from'], true)) {
            $raw = $this->settings->getRawOriginal($field);
            return $raw === null ? '' : (string) $raw;
        }
```

Add the guardrail in `saveField()`. Insert this block just after the `$value` assignment / null-coercion at lines 103-107, before `$this->settings->update(...)`:

```php
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
```

Add a helper method below `rulesFor()`:

```php
    private function highestIssuedNumberFor(string $column): ?int
    {
        if (! $this->batch) {
            return null;
        }

        $max = \App\Models\Application::where('batch_id', $this->batch->id)
            ->whereNotNull($column)
            ->pluck($column)
            ->map(fn (string $raw): ?int => \App\Services\AdmissionNumberingService::extractSequenceInt($raw, $column))
            ->filter()
            ->max();

        return $max === null ? null : (int) $max;
    }
```

- [ ] **Step 4: Run the tests, confirm they pass**

Run: `php artisan test --compact tests/Feature/AdminQuickSettingsTest.php`
Expected: 2 passing tests.

- [ ] **Step 5: Run the whole suite**

Run: `php artisan test --compact`
Expected: all green.

- [ ] **Step 6: Manually verify in the browser**

Visit `https://emba-admission-v3.test/admin` (or wherever the quick-settings card renders). Confirm two new rows appear in the settings grid. Try editing one to a value above any existing application number — success. Try editing it below — error message shows the current max.

- [ ] **Step 7: Lint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 8: Commit**

```bash
git add resources/views/pages/admin/⚡quick-settings.blade.php tests/Feature/AdminQuickSettingsTest.php
git commit -m "feat(admin): quick-settings supports start_from with downgrade guardrail"
```

---

## Task 7: Final pass

- [ ] **Step 1: Run the entire test suite**

Run: `php artisan test --compact`
Expected: all green, no skipped tests from our work.

- [ ] **Step 2: Re-read the spec, verify every section is covered**

Open `docs/superpowers/specs/2026-05-26-admission-numbering-design.md`. For each subsection (Service, Integration, Admin UI, Migration, Testing), confirm a task above implemented it.

- [ ] **Step 3: Final pint pass**

Run: `vendor/bin/pint --dirty --format agent`
If anything changes, commit with `style: pint cleanup`.

- [ ] **Step 4: Stop here**

No PR or push unless the user asks for it.

<?php

namespace Database\Seeders;

use App\Enum\BatchStatusEnum;
use App\Enums\AddressTypeEnum;
use App\Enums\ApplicationStatusEnum;
use App\Enums\BloodGroup;
use App\Enums\DegreeType;
use App\Enums\GenderEnum;
use App\Enums\MaritalStatus;
use App\Enums\PaymentActorEnum;
use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentStatusEnum;
use App\Enums\ReligionEnum;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Batch;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds 1500 applications per batch (750 paid + 750 unpaid), with full
 * Applicant + ApplicantProfile + Address + EducationHistory rows.
 *
 * The "active" (OPEN) batch reserves one of its 750 unpaid slots for the
 * pre-existing tanzid3@gmail.com applicant created by ApplicantSeeder.
 */
class ApplicationSeeder extends Seeder
{
    private const PER_BATCH = 1500;

    private const PAID_PER_BATCH = 750;

    private const UNPAID_PER_BATCH = 750;

    public function run(): void
    {
        $batches = Batch::with('admissionSetting')->orderBy('id')->get();
        $activeBatch = Batch::where('status', BatchStatusEnum::OPEN)->first();

        foreach ($batches as $batch) {
            $this->seedBatch($batch, $activeBatch?->id === $batch->id);
        }
    }

    private function seedBatch(Batch $batch, bool $isActive): void
    {
        $this->command->info("Seeding batch {$batch->code} (".self::PER_BATCH.' apps)...');

        // For the active batch, reserve one unpaid slot for tanzid3 and generate one fewer applicant.
        $tanzid = $isActive ? Applicant::where('email', 'tanzid3@gmail.com')->first() : null;
        $bulkCount = $tanzid ? self::PER_BATCH - 1 : self::PER_BATCH;

        // Step 1 — generate the bulk applicants via factory (one row per insert; ~ a few seconds).
        $bulkApplicants = Applicant::factory($bulkCount)->create(['batch_id' => $batch->id]);

        // Combine all applicants for this batch (tanzid first so he becomes the last unpaid below).
        $allApplicants = $tanzid
            ? collect([$tanzid])->concat($bulkApplicants->all())
            : $bulkApplicants->collect();

        // Step 2 — seed profile/address/education for the bulk applicants only (tanzid's are already seeded).
        $this->seedProfiles($bulkApplicants, $batch->id);
        $this->seedAddresses($bulkApplicants);
        $this->seedEducation($bulkApplicants);

        // Step 3 — assemble application rows.
        // Layout: first 750 paid (with roll numbers), next 750 unpaid.
        // For the active batch, tanzid is at index 0; we want him in the unpaid range, so we paid the
        // bulk applicants first and put tanzid + remaining bulk applicants in the unpaid range.
        if ($isActive) {
            $paidApplicants = $bulkApplicants->take(self::PAID_PER_BATCH);
            $unpaidApplicants = collect([$tanzid])->concat($bulkApplicants->slice(self::PAID_PER_BATCH)->values()->all());
        } else {
            $paidApplicants = $allApplicants->take(self::PAID_PER_BATCH);
            $unpaidApplicants = $allApplicants->slice(self::PAID_PER_BATCH)->take(self::UNPAID_PER_BATCH);
        }

        $startFrom = (int) $batch->admissionSetting->application_number_start_from;
        $rollStartFrom = (int) $batch->admissionSetting->roll_number_start_from;

        $appNumber = $startFrom;
        $rollNumber = $rollStartFrom;
        $applicationRows = [];

        foreach ($paidApplicants as $applicant) {
            $applicationRows[] = $this->paidApplicationRow($applicant, $batch, $appNumber++, $rollNumber++);
        }

        foreach ($unpaidApplicants as $applicant) {
            $applicationRows[] = $this->unpaidApplicationRow($applicant, $batch, $appNumber++);
        }

        // Step 4 — bulk insert applications in chunks.
        collect($applicationRows)
            ->chunk(500)
            ->each(fn ($chunk) => Application::insert($chunk->all()));

        // Step 5 — payment rows for paid applications (need the inserted application IDs).
        // Bypass the model so the DateFormatCast on paid_at doesn't turn the value into an array.
        $paidApps = DB::table('applications')
            ->where('batch_id', $batch->id)
            ->where('payment_status', PaymentStatusEnum::PAID->value)
            ->get(['id', 'applicant_id', 'amount', 'payment_id', 'trx_id', 'paid_at']);

        $paymentRows = $paidApps->map(fn ($app) => $this->paymentRow($app, $batch->id))->all();

        collect($paymentRows)
            ->chunk(500)
            ->each(fn ($chunk) => Payment::insert($chunk->all()));
    }

    private function paidApplicationRow(Applicant $applicant, Batch $batch, int $appNumber, int $rollNumber): array
    {
        $appliedAt = now()->subDays(random_int(10, 60));
        $paidAt = $appliedAt->copy()->addDays(random_int(1, 5));

        return [
            'applicant_id' => $applicant->id,
            'batch_id' => $batch->id,
            'application_number' => $batch->code.'-'.$appNumber,
            'roll_number' => (string) $rollNumber,
            'status' => ApplicationStatusEnum::COMPLETED->value,
            'payment_status' => PaymentStatusEnum::PAID->value,
            'payment_method' => PaymentMethodEnum::BKASH->value,
            'amount' => 2500,
            'payment_id' => 'PAYID-'.strtoupper(Str::random(12)),
            'trx_id' => 'TRX'.strtoupper(Str::random(8)),
            'applied_at' => $appliedAt,
            'paid_at' => $paidAt,
            'created_at' => $appliedAt,
            'updated_at' => $paidAt,
        ];
    }

    private function unpaidApplicationRow(Applicant $applicant, Batch $batch, int $appNumber): array
    {
        $appliedAt = now()->subDays(random_int(1, 30));

        return [
            'applicant_id' => $applicant->id,
            'batch_id' => $batch->id,
            'application_number' => $batch->code.'-'.$appNumber,
            'roll_number' => null,
            'status' => ApplicationStatusEnum::AWAITING_PAYMENT->value,
            'payment_status' => PaymentStatusEnum::UNPAID->value,
            'payment_method' => null,
            'amount' => 0,
            'payment_id' => null,
            'trx_id' => null,
            'applied_at' => $appliedAt,
            'paid_at' => null,
            'created_at' => $appliedAt,
            'updated_at' => $appliedAt,
        ];
    }

    private function paymentRow(object $app, int $batchId): array
    {
        return [
            'payment_number' => 'PMT-'.strtoupper(Str::random(10)).'-'.$app->id,
            'batch_id' => $batchId,
            'applicant_id' => $app->applicant_id,
            'actor_table' => PaymentActorEnum::APPLICATION->value,
            'actor_id' => $app->id,
            'payment_method' => PaymentMethodEnum::BKASH->value,
            'amount' => $app->amount,
            'status' => PaymentStatusEnum::COMPLETED->value,
            'gateway_payment_id' => $app->payment_id,
            'gateway_trx_id' => $app->trx_id,
            'paid_at' => $app->paid_at,
            'created_at' => $app->paid_at,
            'updated_at' => $app->paid_at,
        ];
    }

    /**
     * @param  Collection<int, Applicant>  $applicants
     */
    private function seedProfiles($applicants, int $batchId): void
    {
        $faker = fake();
        $rows = [];

        foreach ($applicants as $applicant) {
            $rows[] = [
                'applicant_id' => $applicant->id,
                'batch_id' => $batchId,
                'full_name' => mb_strtoupper($faker->name()),
                'father_name' => mb_strtoupper('Md '.$faker->firstNameMale().' '.$faker->lastName()),
                'mother_name' => mb_strtoupper('Mst '.$faker->firstNameFemale().' '.$faker->lastName()),
                'date_of_birth' => $faker->dateTimeBetween('-40 years', '-22 years')->format('Y-m-d'),
                'photo' => null,
                'gender' => $faker->randomElement([GenderEnum::MALE->value, GenderEnum::FEMALE->value]),
                'blood_group' => $faker->randomElement([
                    BloodGroup::A_POSITIVE->value,
                    BloodGroup::B_POSITIVE->value,
                    BloodGroup::O_POSITIVE->value,
                    BloodGroup::AB_POSITIVE->value,
                ]),
                'religion' => $faker->randomElement([ReligionEnum::ISLAM->value, ReligionEnum::HINDU->value]),
                'marital_status' => $faker->randomElement([MaritalStatus::SINGLE->value, MaritalStatus::MARRIED->value]),
                'nationality' => 'Bangladeshi',
                'tot_year_of_schooling' => $faker->randomFloat(2, 14, 18),
                'tot_year_of_exp' => $faker->randomFloat(2, 1, 10),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        collect($rows)
            ->chunk(500)
            ->each(fn ($chunk) => DB::table('applicant_profiles')->insert($chunk->all()));
    }

    /**
     * @param  Collection<int, Applicant>  $applicants
     */
    private function seedAddresses($applicants): void
    {
        $rows = [];

        foreach ($applicants as $applicant) {
            foreach ([AddressTypeEnum::PRESENT, AddressTypeEnum::PERMANENT] as $type) {
                $rows[] = [
                    'applicant_id' => $applicant->id,
                    'type' => $type->value,
                    'care' => 'C/O Guardian',
                    'road' => 'House '.random_int(1, 99).', Road '.random_int(1, 30),
                    'district_id' => 18, // Dhaka — keeps seeder portable without random district lookups
                    'upazila_id' => 517,
                    'post_office' => 'Mirpur',
                    'postal_code' => '1216',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        collect($rows)
            ->chunk(500)
            ->each(fn ($chunk) => DB::table('addresses')->insert($chunk->all()));
    }

    /**
     * @param  Collection<int, Applicant>  $applicants
     */
    private function seedEducation($applicants): void
    {
        $faker = fake();
        $rows = [];

        foreach ($applicants as $applicant) {
            $rows[] = [
                'applicant_id' => $applicant->id,
                'type' => DegreeType::SSC->value,
                'name' => 'S.S.C / Equivalent',
                'major' => 'Science',
                'institute' => 'Dhaka',
                'result' => (string) $faker->randomFloat(2, 3.5, 5.0),
                'scale' => '5.00',
                'passing_year' => $faker->numberBetween(2010, 2016),
                'duration' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $rows[] = [
                'applicant_id' => $applicant->id,
                'type' => DegreeType::HSC->value,
                'name' => 'H.S.C / Equivalent',
                'major' => 'Science',
                'institute' => 'Dhaka',
                'result' => (string) $faker->randomFloat(2, 3.0, 5.0),
                'scale' => '5.00',
                'passing_year' => $faker->numberBetween(2012, 2018),
                'duration' => 2,
            ];
            $rows[] = [
                'applicant_id' => $applicant->id,
                'type' => DegreeType::UNDERGRADUATE->value,
                'name' => 'Honours / Degree',
                'major' => $faker->randomElement(['CSE', 'EEE', 'BBA', 'Economics', 'Civil']),
                'institute' => $faker->randomElement(['Green University of Bangladesh', 'University of Dhaka', 'BUET', 'North South University']),
                'result' => (string) $faker->randomFloat(2, 2.8, 4.0),
                'scale' => '4.00',
                'passing_year' => $faker->numberBetween(2016, 2023),
                'duration' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // The two middle rows above (HSC) omit timestamps for brevity — backfill before insert.
        foreach ($rows as &$row) {
            $row['created_at'] = $row['created_at'] ?? now();
            $row['updated_at'] = $row['updated_at'] ?? now();
        }
        unset($row);

        collect($rows)
            ->chunk(500)
            ->each(fn ($chunk) => DB::table('education_histories')->insert($chunk->all()));
    }
}

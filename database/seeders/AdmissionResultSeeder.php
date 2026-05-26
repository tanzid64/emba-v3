<?php

namespace Database\Seeders;

use App\Enums\PaymentStatusEnum;
use App\Models\Application;
use App\Models\Batch;
use App\Services\ResultGenerationService;
use Illuminate\Database\Seeder;

class AdmissionResultSeeder extends Seeder
{
    /**
     * Snapshot the profile-derived marks (schooling + experience) for
     * every paid applicant by routing each one through the production
     * ResultGenerationService — same code path as the bKash callback,
     * so seeded results match what users would see after payment.
     *
     * Idempotent: the service uses updateOrCreate, so re-running this
     * seeder refreshes existing rows instead of duplicating them.
     */
    public function run(): void
    {
        $service = app(ResultGenerationService::class);

        foreach (Batch::orderBy('id')->get() as $batch) {
            $apps = Application::query()
                ->where('batch_id', $batch->id)
                ->whereIn('payment_status', [
                    PaymentStatusEnum::PAID->value,
                    PaymentStatusEnum::COMPLETED->value,
                ])
                ->with(['applicant.profile', 'applicant.educationHistories'])
                ->get();

            if ($apps->isEmpty()) {
                continue;
            }

            $this->command->info("Generating admission results for batch {$batch->code} ({$apps->count()} apps)...");

            foreach ($apps as $app) {
                $service->generateForApplication($app);
            }
        }
    }
}

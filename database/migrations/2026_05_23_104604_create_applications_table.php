<?php

use App\Enums\ApplicationStatusEnum;
use App\Enums\PaymentStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();

            $table->string('application_number')->nullable();
            $table->string('roll_number')->nullable();
            $table->timestamp('applied_at')->nullable();

            $table->string('payment_status')->default(PaymentStatusEnum::UNPAID->value)->comment('Enum Ref: PaymentStatusEnum');
            $table->string('payment_method')->nullable()->comment('Enum Ref: PaymentMethodEnum');
            $table->decimal('amount', 10, 2)->default(0.00);
            $table->string('payment_id')->nullable(); // Bkash Payment ID
            $table->string('trx_id')->nullable(); // Bkash Transaction ID
            $table->timestamp('paid_at')->nullable();

            $table->string('status')->default(ApplicationStatusEnum::PENDING->value)->comment('Enum Ref: ApplicationStatusEnum');
            $table->timestamps();

            $table->unique(['batch_id', 'application_number']);
            $table->unique(['batch_id', 'roll_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};

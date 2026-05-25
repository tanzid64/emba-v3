<?php

use App\Enums\PaymentActorEnum;
use App\Enums\PaymentMethodEnum;
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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number')->unique();
            $table->foreignId('batch_id')->constrained()->onDelete('cascade');
            $table->foreignId('applicant_id')->constrained()->onDelete('cascade');
            $table->string('actor_table')->default(PaymentActorEnum::APPLICATION->value);
            $table->unsignedBigInteger('actor_id');

            $table->string('gateway_payment_id')->nullable(); // Bkash payment ID
            $table->string('gateway_trx_id')->nullable(); // Bkash trx ID
            $table->string('payment_method')->default(PaymentMethodEnum::BKASH->value);
            $table->decimal('amount', 15, 2);
            $table->timestamp('paid_at')->nullable();

            $table->json('gateway_response')->nullable(); // Store the entire response from the payment gateway
            $table->json('metadata')->nullable(); // Store any additional metadata related to the payment
            $table->string('status')->default(PaymentStatusEnum::PENDING->value); // pending, completed, failed, etc.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

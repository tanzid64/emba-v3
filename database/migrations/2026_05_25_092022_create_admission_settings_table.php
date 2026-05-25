<?php

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
        Schema::create('admission_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->onDelete('cascade');
            $table->date('intake_started_at')->nullable();
            $table->date('intake_ended_at')->nullable();
            $table->decimal('application_fee', 8, 2)->default(2500);
            $table->date('application_payment_ended_at')->nullable();
            $table->date('admit_card_published_at')->nullable();
            $table->timestamp('exam_date')->nullable();
            $table->timestamp('viva_date')->nullable();
            $table->timestamp('result_published_at')->nullable();
            $table->decimal('enrollment_fee', 8, 2)->default(500);
            $table->decimal('admission_fee', 8, 2)->default(12000);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admission_settings');
    }
};

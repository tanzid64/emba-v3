<?php

use App\Enums\ResultStatusEnum;
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
        Schema::create('admission_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->onDelete('cascade');
            $table->foreignId('applicant_id')->constrained()->onDelete('cascade');
            $table->string('application_number');
            $table->string('roll_number')->index();
            $table->unsignedInteger('merit_position')->nullable()->index();
            $table->decimal('mcq_marks', 8, 2)->default(0); // Max 55
            $table->decimal('written_marks', 8, 2)->default(0); // Max 25
            $table->decimal('viva_marks', 8, 2)->default(0); // Max 5
            $table->decimal('schooling_marks', 8, 2)->default(0); // Max 5
            $table->decimal('experience_marks', 8, 2)->default(0); // Max 10
            $table->decimal('total_marks', 8, 2); // Max 100
            $table->boolean('is_adjusted')->default(false); // If Schooling or Experience marks are adjusted in viva board.
            $table->string('status')->default(ResultStatusEnum::FAILED->value); // Passed or Failed
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admission_results');
    }
};

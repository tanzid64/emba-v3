<?php

use App\Enums\DegreeType;
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
        Schema::create('education_histories', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default(DegreeType::OTHER->value)->comment('SSC, HSC, BSc, MSc, etc.');
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->string('name')->comment('SsC, HsC, BSc, MSc, etc.');
            $table->string('major')->comment('Commerce, CSE, etc.');
            $table->string('institute')->comment('Board or University name');
            $table->string('result')->comment('GPA or Division');
            $table->string('scale')->comment('Scale of the result, e.g., 5.00');
            $table->year('passing_year')->nullable();
            $table->unsignedTinyInteger('duration')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('education_histories');
    }
};

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
        Schema::create('exp_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->string('organization')->comment('Name of the organization');
            $table->string('designation')->comment('Job title or position');
            $table->string('duration')->comment('2 years, 6 months, etc.');
            $table->decimal('total_experience', 10, 2)->comment('Total experience in years, e.g., 5.5')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exp_histories');
    }
};

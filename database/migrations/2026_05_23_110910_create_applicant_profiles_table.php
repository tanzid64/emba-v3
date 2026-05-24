<?php

use App\Enums\BloodGroup;
use App\Enums\GenderEnum;
use App\Enums\MaritalStatus;
use App\Enums\ReligionEnum;
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
        Schema::create('applicant_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->onDelete('cascade');
            $table->foreignId('batch_id')->constrained()->onDelete('cascade');

            $table->string('full_name');
            $table->string('father_name');
            $table->string('mother_name');
            $table->date('date_of_birth');
            $table->string('photo')->nullable();

            $table->string('gender', 1)->default(GenderEnum::OTHER->value);
            $table->string('blood_group')->default(BloodGroup::UNKNOWN->value);
            $table->string('religion')->default(ReligionEnum::ISLAM->value);
            $table->string('marital_status')->default(MaritalStatus::SINGLE->value);

            $table->string('nationality')->default('Bangladeshi');

            $table->decimal('tot_year_of_schooling', 4, 2)->default(0)->comment('Total years of schooling');
            $table->decimal('tot_year_of_exp', 4, 2)->default(0)->comment('Total years of work experience');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applicant_profiles');
    }
};

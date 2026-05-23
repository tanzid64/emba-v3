<?php

use App\Enum\BatchStatusEnum;
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
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('Admission batch name');
            $table->string('code')->unique()->comment('Admission batch code');
            $table->year('admission_year')->comment('Admission year');
            $table->string('status')->default(BatchStatusEnum::DRAFT->value)->comment('Enum Ref: BatchStatusEnum');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};

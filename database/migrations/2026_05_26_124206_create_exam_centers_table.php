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
        Schema::create('exam_centers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->onDelete('cascade');
            $table->string('center_no');
            $table->string('center_name');
            $table->string('room_name');
            $table->unsignedInteger('capacity')->default(0);
            $table->timestamps();

            $table->unique(['batch_id', 'center_no', 'room_name']);
            $table->index(['batch_id', 'center_no']);
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->foreignId('exam_center_id')->nullable()->constrained('exam_centers')->nullOnDelete()->after('roll_number');
            $table->index(['batch_id', 'exam_center_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_centers');
    }
};

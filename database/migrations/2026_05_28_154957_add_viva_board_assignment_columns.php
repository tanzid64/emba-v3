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
        // A confirmed applicant is assigned to a viva board, alongside their exam center.
        Schema::table('applications', function (Blueprint $table) {
            $table->foreignId('viva_board_id')->nullable()->after('exam_center_id')
                ->constrained('viva_boards')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('viva_board_id');
        });
    }
};

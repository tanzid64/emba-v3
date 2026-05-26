<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->foreignId('exam_center_id')
                ->nullable()
                ->after('roll_number')
                ->constrained('exam_centers')
                ->nullOnDelete();
            $table->string('seat_no')->nullable()->after('exam_center_id');

            $table->index(['batch_id', 'exam_center_id']);
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex(['batch_id', 'exam_center_id']);
            $table->dropConstrainedForeignId('exam_center_id');
            $table->dropColumn('seat_no');
        });
    }
};

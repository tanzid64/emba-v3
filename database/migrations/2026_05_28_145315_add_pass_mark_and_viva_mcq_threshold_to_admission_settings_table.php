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
        Schema::table('admission_settings', function (Blueprint $table) {
            // Total mark at/above which a candidate passes the admission test (out of 100).
            $table->decimal('pass_mark', 8, 2)->default(40)->after('admission_fee');
            // MCQ mark at/above which a candidate is eligible to sit for the viva (out of 55).
            $table->decimal('viva_mcq_threshold', 8, 2)->default(25)->after('pass_mark');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admission_settings', function (Blueprint $table) {
            $table->dropColumn(['pass_mark', 'viva_mcq_threshold']);
        });
    }
};

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
            $table->timestamp('viva_admit_card_published_at')->nullable()->after('admit_card_published_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admission_settings', function (Blueprint $table) {
            $table->dropColumn('viva_admit_card_published_at');
        });
    }
};

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
        Schema::create('bkash_tokens', function (Blueprint $table) {
            $table->id();
            $table->boolean('sandbox_mode')->default(true);
            $table->text('id_token');
            $table->bigInteger('id_expiry')->comment('unix timestamp');
            $table->text('refresh_token');
            $table->bigInteger('refresh_expiry');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bkash_tokens');
    }
};

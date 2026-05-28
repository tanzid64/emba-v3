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
        Schema::create('viva_boards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();
            $table->string('board_name');
            $table->string('center_no')->nullable();
            $table->string('center_name')->nullable();
            $table->string('room_name')->nullable();
            $table->timestamps();

            // Board names are unique within a batch, reusable across batches.
            $table->unique(['batch_id', 'board_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('viva_boards');
    }
};

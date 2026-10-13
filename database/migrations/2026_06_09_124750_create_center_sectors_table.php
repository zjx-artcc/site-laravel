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
        Schema::create('center_sectors', function (Blueprint $table) {
            $table->id();
            $table->string('split');
            $table->integer('sector_id');
            $table->integer('active_sector_id')->nullable();

            $table->unique(['split', 'sector_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('center_sectors');
    }
};

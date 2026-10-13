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
        Schema::table('event_positions', function (Blueprint $table) {
            $table->timestamp('assigned_start')->nullable()->change();
            $table->timestamp('assigned_end')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_positions', function (Blueprint $table) {
            $table->timestamp('assigned_start')->nullable(false)->change();
            $table->timestamp('assigned_end')->nullable(false)->change();
        });
    }
};

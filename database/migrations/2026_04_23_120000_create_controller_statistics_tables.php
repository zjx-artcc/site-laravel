<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('controller_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('callsign');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('facility_level');
            $table->dateTime('start');
            $table->dateTime('end');
            $table->index(['user_id', 'start']);
            $table->index('start');
        });

        Schema::create('controller_monthly_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('delivery_hours', 8, 4)->default(0);
            $table->decimal('ground_hours', 8, 4)->default(0);
            $table->decimal('tower_hours', 8, 4)->default(0);
            $table->decimal('approach_hours', 8, 4)->default(0);
            $table->decimal('center_hours', 8, 4)->default(0);
            $table->unique(['user_id', 'year', 'month']);
            $table->index(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('controller_monthly_stats');
        Schema::dropIfExists('controller_sessions');
    }
};

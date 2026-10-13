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
        Schema::table('training_tickets', function (Blueprint $table) {
            $table->longText('notes')->change();
            $table->longText('instructor_notes')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('training_tickets', function (Blueprint $table) {
            $table->mediumText('notes')->change();
            $table->mediumText('instructor_notes')->nullable()->change();
        });
    }
};

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
            $table->foreignId('issued_certification_level_id')
                ->nullable()
                ->after('position')
                ->constrained('certification_levels')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('training_tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('issued_certification_level_id');
        });
    }
};

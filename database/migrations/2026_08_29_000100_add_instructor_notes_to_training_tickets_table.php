<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Staff-only notes on a training ticket. Never shown to the student and
     * never sent to VATUSA — see App\Jobs\SyncTrainingTickets, which only
     * forwards the student-facing `notes` column.
     */
    public function up(): void
    {
        Schema::table('training_tickets', function (Blueprint $table) {
            $table->mediumText('instructor_notes')->nullable()->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('training_tickets', function (Blueprint $table) {
            $table->dropColumn('instructor_notes');
        });
    }
};

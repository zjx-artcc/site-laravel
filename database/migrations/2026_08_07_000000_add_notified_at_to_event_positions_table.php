<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_positions', function (Blueprint $table) {
            $table->timestamp('notified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_positions', function (Blueprint $table) {
            $table->dropColumn('notified_at');
        });
    }
};

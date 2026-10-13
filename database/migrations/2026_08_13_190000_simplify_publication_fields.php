<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('publications', function (Blueprint $table) {
            $table->dropColumn('version');
            $table->text('description')->nullable()->change();
        });

        Schema::table('publication_categories', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restoring NOT NULL fails outright if any description was cleared while
        // the column allowed nulls, so backfill those rows before changing it.
        DB::table('publication_categories')->whereNull('description')->update(['description' => '']);
        DB::table('publications')->whereNull('description')->update(['description' => '']);

        Schema::table('publication_categories', function (Blueprint $table) {
            $table->text('description')->nullable(false)->change();
        });

        // The dropped version strings cannot be recovered; existing rows get an
        // empty string so the NOT NULL column can be added back.
        Schema::table('publications', function (Blueprint $table) {
            $table->string('version')->default('');
            $table->text('description')->nullable(false)->change();
        });
    }
};

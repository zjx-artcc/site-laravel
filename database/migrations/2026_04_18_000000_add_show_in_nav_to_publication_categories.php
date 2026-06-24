<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publication_categories', function (Blueprint $table) {
            $table->boolean('show_in_nav')->default(true)->after('display_order');
        });
    }

    public function down(): void
    {
        Schema::table('publication_categories', function (Blueprint $table) {
            $table->dropColumn('show_in_nav');
        });
    }
};

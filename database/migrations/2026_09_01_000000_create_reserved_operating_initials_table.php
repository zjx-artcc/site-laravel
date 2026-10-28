<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Operating initials (OIs) that must never be assigned to a controller,
     * e.g. retired in memory of a former member.
     */
    public function up(): void
    {
        Schema::create('reserved_operating_initials', function (Blueprint $table) {
            $table->string('initials', 2)->primary();
            $table->string('reason')->nullable();
            $table->foreignId('reserved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reserved_operating_initials');
    }
};

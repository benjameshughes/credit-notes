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
        Schema::table('pdf_generation_jobs', function (Blueprint $table) {
            $table->dropUnique(['batch_id']);
            $table->index('batch_id'); // Add regular index for performance
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pdf_generation_jobs', function (Blueprint $table) {
            $table->dropIndex(['batch_id']);
            $table->unique('batch_id');
        });
    }
};

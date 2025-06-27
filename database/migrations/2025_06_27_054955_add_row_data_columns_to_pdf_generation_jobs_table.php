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
            $table->json('row_data')->nullable();
            $table->integer('row_index')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pdf_generation_jobs', function (Blueprint $table) {
            $table->dropColumn(['row_data', 'row_index']);
        });
    }
};

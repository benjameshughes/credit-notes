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
            $table->string('download_status')->default('pending')->after('status');
            // Values: 'pending', 'creating_download', 'ready'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pdf_generation_jobs', function (Blueprint $table) {
            $table->dropColumn('download_status');
        });
    }
};

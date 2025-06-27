<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdf_generation_jobs', function (Blueprint $table) {
            $table->string('single_pdf_path')->nullable()->after('zip_path');
        });
    }

    public function down(): void
    {
        Schema::table('pdf_generation_jobs', function (Blueprint $table) {
            $table->dropColumn('single_pdf_path');
        });
    }
};
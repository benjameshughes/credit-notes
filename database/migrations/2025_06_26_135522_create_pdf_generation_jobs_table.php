<?php

// database/migrations/create_pdf_generation_jobs_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('pdf_generation_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id')->unique();
            $table->string('original_filename');
            $table->integer('total_rows');
            $table->integer('processed_rows')->default(0);
            $table->integer('failed_rows')->default(0);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed']);
            $table->json('file_paths')->nullable(); // Store S3/DO paths
            $table->string('zip_path')->nullable(); // Final zip file path
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('pdf_generation_jobs');
    }
};

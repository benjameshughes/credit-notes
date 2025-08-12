<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = Schema::connection(null)->getConnection()->getDriverName();
        
        if ($driver === 'mysql') {
            // For MySQL, we modify the ENUM
            DB::statement("ALTER TABLE pdf_generation_jobs MODIFY COLUMN status ENUM('pending', 'processing', 'completed', 'failed', 'paused') NOT NULL");
        } else {
            // For SQLite, just change to varchar to allow 'paused'
            Schema::table('pdf_generation_jobs', function (Blueprint $table) {
                $table->string('status', 20)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First set any paused jobs to failed
        DB::statement("UPDATE pdf_generation_jobs SET status = 'failed' WHERE status = 'paused'");
        
        $driver = Schema::connection(null)->getConnection()->getDriverName();
        
        if ($driver === 'mysql') {
            // Remove 'paused' from the ENUM
            DB::statement("ALTER TABLE pdf_generation_jobs MODIFY COLUMN status ENUM('pending', 'processing', 'completed', 'failed') NOT NULL");
        } else {
            // For SQLite, change back to enum (though it will still allow other values)
            Schema::table('pdf_generation_jobs', function (Blueprint $table) {
                $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending')->change();
            });
        }
    }
};

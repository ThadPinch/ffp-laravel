<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePdfJobsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pdf_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('design_id')->constrained()->onDelete('cascade');
            $table->uuid('job_id')->unique();
            $table->string('type')->default('standard'); // standard or print_ready
            $table->string('status'); // pending, processing, completed, failed
            $table->string('file_path')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            // Index for faster queries
            $table->index(['job_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pdf_jobs');
    }
}
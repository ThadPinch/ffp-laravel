<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('designs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->json('elements'); // Store design elements as JSON
            $table->string('thumbnail')->nullable(); // Path to thumbnail image
            $table->string('name')->default('Untitled Design');
            $table->boolean('is_template')->default(false);
            $table->timestamps();
        });

        Schema::create('design_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('design_id')->constrained()->onDelete('cascade');
            $table->json('elements'); // Store design elements as JSON
            $table->string('thumbnail')->nullable(); // Path to thumbnail image
            $table->string('comment')->nullable(); // Optional comment about this version
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('design_versions');
        Schema::dropIfExists('designs');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('finished_width', 8, 2); // In inches
            $table->decimal('finished_length', 8, 2); // In inches
            $table->decimal('per_piece_weight', 8, 2)->nullable(); // In ounces/grams
            $table->text('description')->nullable();
            $table->string('production_product_id')->nullable(); // For integration with production system
            $table->string('product_image')->nullable(); // Image filename
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('products');
    }
};
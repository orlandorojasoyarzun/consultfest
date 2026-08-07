<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscriber_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('synopsis')->nullable();
            $table->unsignedSmallInteger('runtime_minutes')->nullable();
            $table->string('format')->nullable(); // 'digital', 'film', 'any'
            $table->string('country')->nullable();
            $table->unsignedSmallInteger('production_year')->nullable();
            $table->string('category')->nullable(); // same vocabulary as Festival.category
            $table->json('genres')->nullable();    // array of strings, mirrors Festival.details->genres
            $table->string('status')->default('draft'); // 'draft', 'active', 'archived'
            $table->timestamps();

            $table->index('subscriber_id');
            $table->index('country');
            $table->index('category');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productions');
    }
};
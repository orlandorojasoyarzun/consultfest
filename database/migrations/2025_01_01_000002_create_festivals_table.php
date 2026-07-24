<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('festivals', function (Blueprint $table) {
            $table->id();
            $table->integer('api_id')->unique();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('country')->nullable();
            $table->date('deadline')->nullable();
            $table->date('opening_date')->nullable();
            $table->decimal('submission_fee', 8, 2)->nullable();
            $table->boolean('accepting_submissions')->default(true);
            $table->integer('festival_score')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index('deadline');
            $table->index('opening_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('festivals');
    }
};

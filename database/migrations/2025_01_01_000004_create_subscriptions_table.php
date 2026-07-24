<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscriber_id')->constrained()->onDelete('cascade');
            $table->foreignId('festival_id')->constrained()->onDelete('cascade');
            $table->string('notification_type'); // 'opening', 'deadline', 'both'
            $table->boolean('notified_opening')->default(false);
            $table->boolean('notified_deadline')->default(false);
            $table->timestamps();

            $table->unique(['subscriber_id', 'festival_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};

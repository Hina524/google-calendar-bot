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
        Schema::create('watch_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_user_id')->constrained()->onDelete('cascade');
            $table->string('channel_id')->unique();
            $table->string('resource_id');
            $table->timestamp('expiration');
            $table->string('sync_token')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('watch_channels');
    }
};

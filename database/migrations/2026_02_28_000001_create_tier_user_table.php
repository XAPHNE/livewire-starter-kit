<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tier_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tier_id')->constrained('tiers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['tier_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tier_user');
    }
};

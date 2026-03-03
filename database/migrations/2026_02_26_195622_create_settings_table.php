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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Insert default values for settings
        DB::table('settings')->insert([
            ['key' => 'tfa_max_resend_attempts', 'value' => '3', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'tfa_lockdown_hours', 'value' => '24', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'max_login_attempts', 'value' => '3', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'login_lockdown_hours', 'value' => '24', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};

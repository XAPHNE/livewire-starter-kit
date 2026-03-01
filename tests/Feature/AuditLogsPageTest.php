<?php

use App\Models\User;

beforeEach(function () {
    // ensure both permissions exist
    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'View Audit Trail']);
    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'View Authentication Log']);

    // create a minimal audits table so the Livewire component can query it during tests
    \Illuminate\Support\Facades\Schema::create('audits', function (\Illuminate\Database\Schema\Blueprint $table) {
        $table->bigIncrements('id');
        $table->string('user_type')->nullable();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->string('event');
        $table->string('auditable_type');
        $table->string('auditable_id')->nullable();
        $table->json('old_values')->nullable();
        $table->json('new_values')->nullable();
        $table->string('url')->nullable();
        $table->string('ip_address')->nullable();
        $table->string('user_agent')->nullable();
        $table->timestamps();
    });

    // create the authentication_log table stub
    \Illuminate\Support\Facades\Schema::create('authentication_log', function (\Illuminate\Database\Schema\Blueprint $table) {
        $table->bigIncrements('id');
        $table->string('authenticatable_type');
        $table->unsignedBigInteger('authenticatable_id');
        $table->string('ip_address')->nullable();
        $table->text('user_agent')->nullable();
        $table->string('device_id')->nullable();
        $table->string('device_name')->nullable();
        $table->boolean('is_trusted')->default(false);
        $table->timestamp('login_at')->nullable();
        $table->boolean('login_successful')->default(false);
        $table->timestamp('logout_at')->nullable();
        $table->timestamp('last_activity_at')->nullable();
        $table->boolean('cleared_by_user')->default(false);
        $table->boolean('is_suspicious')->default(false);
        $table->string('suspicious_reason')->nullable();
    });
});

it('redirects guests to login', function () {
    $response = $this->get(route('audit-logs'));
    $response->assertRedirect(route('login'));
});

it('denies users without permission', function () {
    $user = User::factory()->create(['created_by' => 1, 'updated_by' => 1]);
    $this->actingAs($user);

    $response = $this->get(route('audit-logs'));
    $response->assertForbidden();
});

it('allows users with audit permission to see audits tab', function () {
    $user = User::factory()->create(['created_by' => 1, 'updated_by' => 1]);
    $user->givePermissionTo('View Audit Trail');
    $this->actingAs($user);

    $response = $this->get(route('audit-logs'));
    $response->assertOk();
    $response->assertSee('Model Audits');
    $response->assertDontSee('Authentication Logs');
});

it('allows users with authentication log permission to see auth tab only', function () {
    $user = User::factory()->create(['created_by' => 1, 'updated_by' => 1]);
    $user->givePermissionTo('View Authentication Log');
    $this->actingAs($user);

    $response = $this->get(route('audit-logs'));
    $response->assertOk();
    $response->assertSee('Authentication Logs');
    $response->assertDontSee('Model Audits');
});

it('allows users with both permissions to view both tabs', function () {
    $user = User::factory()->create(['created_by' => 1, 'updated_by' => 1]);
    $user->givePermissionTo(['View Audit Trail', 'View Authentication Log']);
    $this->actingAs($user);

    $response = $this->get(route('audit-logs'));
    $response->assertOk();
    $response->assertSee('Model Audits');
    $response->assertSee('Authentication Logs');
});

<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can access the dashboard', function () {
    // 1. Setup - Create a user with TFA disabled
    // Default factory state already sets email_verified_at
    $user = User::factory()->withoutTwoFactor()->create();

    // 2. Perform Request - Use actingAs to bypass the login form
    $response = $this->actingAs($user)->get(route('dashboard'));

    // 3. Verify Success
    $response->assertOk();
    $response->assertSee(__('Dashboard'));
});

test('authenticated users can navigate between protected pages', function () {
    // 1. Setup - Create a user with TFA disabled
    $user = User::factory()->withoutTwoFactor()->create();

    // 2. Perform Request - Start at dashboard
    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('Dashboard'));

    // 3. Navigate to Profile Settings
    $this->get(route('profile.edit'))
        ->assertOk()
        ->assertSee(__('Profile Settings'));

    // 4. Verify still authenticated
    $this->assertAuthenticatedAs($user);
});

test('super admins can navigate to all administrative pages', function () {
    // 1. Setup - Create a user and assign Super Admin role
    $superAdminRole = \Spatie\Permission\Models\Role::create(['name' => 'Super Admin']);
    $user = User::factory()->withoutTwoFactor()->create();
    $user->assignRole($superAdminRole);

    // 2. Perform Request - Start at dashboard
    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();

    // 3. Navigate to System Settings
    $this->get(route('system-settings'))
        ->assertOk()
        ->assertSee(__('System Settings'));

    // 4. Navigate to User Management
    $this->get(route('users'))
        ->assertOk()
        ->assertSee(__('Users'));
});

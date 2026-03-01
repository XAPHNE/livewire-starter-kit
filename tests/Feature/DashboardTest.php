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

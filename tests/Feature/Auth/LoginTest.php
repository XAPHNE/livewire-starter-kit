<?php

use App\Models\User;
use App\Livewire\Auth\TwoFactorChallenge;
use Livewire\Livewire;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

test('users can login successfully with tfa flow', function () {
    // 1. Setup - Let the logic happen exactly as in production
    $this->withoutMiddleware(ValidateCsrfToken::class);

    // 2. Setup User - With TFA enabled (default is 'email')
    $user = User::factory()->create([
        'email' => 'tfa-login@example.com',
        'password' => Hash::make('password'),
        'two_factor_type' => 'email',
    ]);

    // 3. Initiate Login
    $response = $this->post(route('login'), [
        'email' => 'tfa-login@example.com',
        'password' => 'password',
    ]);


    // Should redirect to challenge
    $response->assertRedirect(route('two-factor.login'));
    $this->assertGuest();

    // 4. Retrieve generated code (from DB since mail is in array driver)
    $user->refresh();
    $this->assertNotNull($user->two_factor_code);

    // 5. Complete Challenge using Livewire
    Livewire::test(TwoFactorChallenge::class)
        ->set('code', (string)$user->two_factor_code)
        ->call('verify')
        ->assertRedirect(config('fortify.home'));

    // 6. Verify result
    $this->assertAuthenticatedAs($user);
});

test('users can login successfully when tfa is disabled', function () {
    // 1. Setup - Disable CSRF for login flow
    $this->withoutMiddleware(ValidateCsrfToken::class);

    // 2. Setup User - With TFA disabled
    $user = User::factory()->withoutTwoFactor()->create([
        'email' => 'no-tfa@example.com',
        'password' => Hash::make('password'),
    ]);

    // 3. Perform Login
    $response = $this->post(route('login'), [
        'email' => 'no-tfa@example.com',
        'password' => 'password',
    ]);

    // 4. Verify Success
    // Since TFA is disabled, it should redirect straight to the dashboard
    $response->assertRedirect(config('fortify.home'));
    $this->assertAuthenticatedAs($user);
});

<?php

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('remember me checkbox is visible when session_expire_on_close is disabled', function () {
    Setting::set('session_expire_on_close', 'false');

    $response = $this->get(route('login'));

    $response->assertStatus(200);
    $response->assertSee('Remember me');
});

test('remember me checkbox is hidden when session_expire_on_close is enabled', function () {
    Setting::set('session_expire_on_close', 'true');

    $response = $this->get(route('login'));

    $response->assertStatus(200);
    $response->assertDontSee('Remember me');
});

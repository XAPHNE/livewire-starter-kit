<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Http\Middleware\EnforceSessions;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

test('session cookie has a lifetime when session_expire_on_close is disabled', function () {
    Setting::set('session_expire_on_close', 'false');

    $response = $this->get('/');

    $response->assertStatus(200);

    $sessionCookie = collect($response->headers->getCookies())->first(function ($cookie) {
            return $cookie->getName() === config('session.cookie');
        }
        );

        expect($sessionCookie)->not->toBeNull();
        expect($sessionCookie->getExpiresTime())->toBeGreaterThan(time());
});

test('session cookie expires on close (lifetime 0) when session_expire_on_close is enabled', function () {
    Setting::set('session_expire_on_close', 'true');
    config(['session.expire_on_close' => true]);

    $response = $this->get('/');

    $response->assertStatus(200);

    $sessionCookie = collect($response->headers->getCookies())->first(function ($cookie) {
            return $cookie->getName() === config('session.cookie');
        }
        );

        expect($sessionCookie)->not->toBeNull();
        expect($sessionCookie->getExpiresTime())->toBe(0);
});

test('middleware logs out remember me sessions if logout on close is enabled', function () {
    Setting::set('session_expire_on_close', 'true');

    $user = User::factory()->create();

    // Partial mock Auth guard
    Auth::partialMock()
        ->shouldReceive('check')->andReturn(true)
        ->shouldReceive('viaRemember')->once()->andReturn(true)
        ->shouldReceive('logout')->once();

    $request = Request::create('/dashboard', 'GET');
    $request->setLaravelSession(session()->driver());

    $middleware = new EnforceSessions();
    $response = $middleware->handle($request, function () {
            return response('next');
        }
        );

        expect($response->isRedirect())->toBeTrue();
        expect($response->headers->get('Location'))->toContain('/login');
});

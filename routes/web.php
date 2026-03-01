<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {

    Route::livewire('/user', 'pages::admin.user')->name('users')->middleware('can:View Users');
    Route::livewire('/role', 'pages::admin.role')->name('roles')->middleware('can:View Roles');
    Route::livewire('/permission', 'pages::admin.permission')->name('permissions')->middleware('can:View Permissions');
    Route::livewire('/system-settings', 'pages::admin.settings')->name('system-settings')->middleware('can:View Settings');
    Route::livewire('/tiers', 'pages::admin.tiers')->name('tiers')->middleware('can:View Tiers');

    // Audit Hub (Audit & Authentication Logs)
    // route itself only requires authentication; the component enforces
    // either permission via Gate::authorize in its mount() method.
    Route::livewire('/audit-hub', 'pages::admin.audit-hub')
        ->name('audit-hub')
        ->middleware('auth');
});
Route::get('/two-factor-challenge', \App\Livewire\Auth\TwoFactorChallenge::class)
    ->middleware(['guest:'.config('fortify.guard')])
    ->name('two-factor.login');

Route::get('/locked-account', function () {
    return abort(429);
})->name('locked-account');

require __DIR__.'/settings.php';

<?php

use App\Models\User;

// --- Smoke Tests ---

test('public auth pages load without errors', function () {
    visit(['/', '/login', '/register', '/forgot-password'])
        ->assertNoSmoke();
});

test('verify email page loads without errors', function () {
    $this->actingAs(User::factory()->unverified()->create());

    visit('/email/verify')->assertNoSmoke();
});

// --- Dark Mode ---

test('login page renders correctly in dark mode', function () {
    visit('/login')->inDarkMode()->assertNoSmoke();
});

// --- Interactive Flows ---

test('user can register an account', function () {
    $page = visit('/register');

    $page->fill('#name', 'Test User')
        ->fill('#email', 'newuser@example.com')
        ->fill('#password', 'Password123!')
        ->fill('#password_confirmation', 'Password123!')
        ->check('#terms')
        ->click('@register-user-button')
        ->assertPathIs('/email/verify');

    $this->assertAuthenticated();
});

test('user can log in', function () {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => 'password',
    ]);

    $page = visit('/login');

    $page->fill('#email', 'test@example.com')
        ->fill('#password', 'password')
        ->click('@login-button')
        ->assertPathIs('/dashboard');

    $this->assertAuthenticated();
});

test('user can request a password reset link', function () {
    User::factory()->create(['email' => 'test@example.com']);

    $page = visit('/forgot-password');

    $page->fill('#email', 'test@example.com')
        ->click('@forgot-password-submit')
        ->assertSee('We have emailed your password reset link');
});

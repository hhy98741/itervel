<?php

use App\Models\User;

// --- Smoke Tests ---

test('password reset pages load without errors', function () {
    visit(['/forgot-password', '/reset-password/test-token?email=test@example.com'])
        ->assertNoSmoke();
});

// --- Dark Mode ---

test('forgot password page renders correctly in dark mode', function () {
    visit('/forgot-password')->inDarkMode()->assertNoSmoke();
});

// --- Interactive Flows ---

test('user can request a password reset link', function () {
    User::factory()->create(['email' => 'test@example.com']);

    $page = visit('/forgot-password');

    $page->fill('#email', 'test@example.com')
        ->click('@forgot-password-submit')
        ->assertSee('We have emailed your password reset link');
});

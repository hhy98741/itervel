<?php

// --- Smoke Tests ---

test('terms of service page loads without errors', function () {
    $page = visit('/terms-of-service');

    $page->assertNoSmoke()
        ->assertVisible('[data-test="tos-content"]');
});

// --- Dark Mode ---

test('terms of service page renders correctly in dark mode', function () {
    visit('/terms-of-service')->inDarkMode()->assertNoSmoke();
});

// --- Interactive Flows ---

test('user can register after accepting terms of service', function () {
    $page = visit('/register');

    $page->fill('#name', 'Test User')
        ->fill('#email', 'newuser@example.com')
        ->fill('#password', 'Password123!')
        ->fill('#password_confirmation', 'Password123!')
        ->check('@terms-checkbox')
        ->click('@register-submit')
        ->assertPathIs('/email/verify');

    $this->assertAuthenticated();
});

test('tos link on register page opens in a new tab', function () {
    $page = visit('/register');

    $page->assertAttribute('[data-test="tos-link"]', 'target', '_blank');
});

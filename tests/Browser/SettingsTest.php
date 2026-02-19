<?php

use App\Models\User;

// --- Smoke Tests ---

test('settings pages load without errors', function () {
    $this->actingAs(User::factory()->create());

    visit([
        '/settings/profile',
        '/settings/password',
        '/settings/two-factor',
        '/settings/appearance',
    ])->assertNoSmoke();
});

// --- Dark Mode ---

test('profile settings renders correctly in dark mode', function () {
    $this->actingAs(User::factory()->create());

    visit('/settings/profile')->inDarkMode()->assertNoSmoke();
});

// --- Interactive Flows ---

test('user can update their profile name', function () {
    $user = User::factory()->create(['name' => 'Original Name']);

    $this->actingAs($user);

    $page = visit('/settings/profile');

    $page->clear('#name')
        ->fill('#name', 'Updated Name')
        ->click('@update-profile-button')
        ->assertSee('Saved');
});

test('user can update their password', function () {
    $user = User::factory()->create(['password' => 'password']);

    $this->actingAs($user);

    $page = visit('/settings/password');

    $page->fill('#current_password', 'password')
        ->fill('#password', 'NewPassword123!')
        ->fill('#password_confirmation', 'NewPassword123!')
        ->click('@update-password-button')
        ->assertSee('Saved');
});

test('user can switch appearance theme', function () {
    $this->actingAs(User::factory()->create());

    $page = visit('/settings/appearance');

    $page->click('@theme-dark')
        ->assertNoSmoke();

    $page->click('@theme-light')
        ->assertNoSmoke();
});

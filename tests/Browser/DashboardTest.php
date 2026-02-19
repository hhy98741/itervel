<?php

use App\Models\User;

// --- Smoke Tests ---

test('dashboard loads without errors', function () {
    $this->actingAs(User::factory()->create());

    visit('/dashboard')->assertNoSmoke();
});

// --- Dark Mode ---

test('dashboard renders correctly in dark mode', function () {
    $this->actingAs(User::factory()->create());

    visit('/dashboard')->inDarkMode()->assertNoSmoke();
});

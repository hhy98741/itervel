<?php

use App\Models\User;

test('terms of service page can be rendered', function () {
    $response = $this->get('/terms-of-service');

    $response->assertOk();
});

test('terms of service page is accessible to guests', function () {
    $response = $this->get(route('terms.show'));

    $response->assertOk();
});

test('registration records terms accepted timestamp', function () {
    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => 'on',
    ]);

    $user = User::where('email', 'test@example.com')->first();

    expect($user->terms_accepted_at)->not->toBeNull();
    expect($user->terms_accepted_at)->toBeInstanceOf(\Carbon\CarbonImmutable::class);
    expect($user->terms_accepted_at->diffInSeconds(now()))->toBeLessThan(5);
});

test('registration fails without terms acceptance', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('terms');
    $this->assertGuest();
    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
});

test('registration fails when terms value is empty string', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => '',
    ]);

    $response->assertSessionHasErrors('terms');
    $this->assertGuest();
});

test('registration fails when terms value is off', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => 'off',
    ]);

    $response->assertSessionHasErrors('terms');
    $this->assertGuest();
});

test('registration fails when terms value is false string', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => 'false',
    ]);

    $response->assertSessionHasErrors('terms');
    $this->assertGuest();
});

test('registration fails when terms value is zero', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => '0',
    ]);

    $response->assertSessionHasErrors('terms');
    $this->assertGuest();
});

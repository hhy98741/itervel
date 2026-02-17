<?php

use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => 'on',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'test@example.com')->first();
    expect($user->video_credits)->toBe(1);
});

test('new users receive 1 free video credit', function () {
    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => 'on',
    ]);

    $user = User::where('email', 'test@example.com')->first();

    expect($user->video_credits)->toBe(1);
});

test('registration requires terms acceptance', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('terms');
    $this->assertGuest();
});

test('registration fails when terms are not accepted', function () {
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

test('registration requires name', function () {
    $response = $this->post(route('register.store'), [
        'name' => '',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => 'on',
    ]);

    $response->assertSessionHasErrors('name');
    $this->assertGuest();
});

test('registration requires email', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => '',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => 'on',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('registration requires password', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => '',
        'password_confirmation' => '',
        'terms' => 'on',
    ]);

    $response->assertSessionHasErrors('password');
    $this->assertGuest();
});

test('registration requires password confirmation', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'different-password',
        'terms' => 'on',
    ]);

    $response->assertSessionHasErrors('password');
    $this->assertGuest();
});

test('registration fails with duplicate email', function () {
    User::factory()->create(['email' => 'test@example.com']);

    $response = $this->post(route('register.store'), [
        'name' => 'Another User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => 'on',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('registered user is prompted to verify email', function () {
    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => 'on',
    ]);

    $this->assertAuthenticated();

    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('verification.notice'));
});

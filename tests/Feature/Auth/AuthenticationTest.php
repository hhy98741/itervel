<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->create();

    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $this->assertGuest();
    $response->assertRedirect(route('home'));
});

test('users are rate limited after 5 failed attempts', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
    }

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});

test('remember me creates persistent session', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'remember' => 'on',
    ]);

    $this->assertAuthenticated();
    $user->refresh();
    expect($user->remember_token)->not->toBeNull();
});

test('login without remember me does not persist remember token change', function () {
    $user = User::factory()->create(['remember_token' => null]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $user->refresh();
    expect($user->remember_token)->toBeNull();
});

test('session lifetime is configured to 24 hours', function () {
    expect(config('session.lifetime'))->toBe(1440);
});

test('remember me duration is configured to 30 days', function () {
    $guard = Auth::guard('web');
    $reflection = new ReflectionProperty($guard, 'rememberDuration');
    expect($reflection->getValue($guard))->toBe(43200);
});

test('authenticated users are redirected from login page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('login'));

    $response->assertRedirect(route('dashboard'));
});

test('login fails with nonexistent email', function () {
    $this->post(route('login.store'), [
        'email' => 'nonexistent@example.com',
        'password' => 'password',
    ]);

    $this->assertGuest();
});

test('login requires email', function () {
    $response = $this->post(route('login.store'), [
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('login requires password', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
    ]);

    $response->assertSessionHasErrors('password');
    $this->assertGuest();
});

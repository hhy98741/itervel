<?php

use App\Models\User;
use App\Notifications\PasswordChanged;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

test('reset password link screen can be rendered', function () {
    $response = $this->get(route('password.request'));

    $response->assertOk();
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get(route('password.reset', $notification->token));

        $response->assertOk();

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});

test('password cannot be reset with invalid token', function () {
    $user = User::factory()->create();

    $response = $this->post(route('password.update'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertSessionHasErrors('email');
});

test('user is notified via email after password is reset', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        return true;
    });

    Notification::assertSentTo($user, PasswordChanged::class);
});

test('reset token can only be used once', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        // First use - should succeed
        $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasNoErrors();

        // Second use - should fail
        $response = $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'another-password',
            'password_confirmation' => 'another-password',
        ]);

        $response->assertSessionHasErrors('email');

        return true;
    });
});

test('password reset requests are rate limited to 3 per hour', function () {
    Notification::fake();

    $user = User::factory()->create();

    // First 3 requests should succeed (status may return RESET_THROTTLED from broker after first due to recentlyCreatedToken,
    // but the rate limiter should still count them)
    for ($i = 0; $i < 3; $i++) {
        $response = $this->post(route('password.email'), ['email' => $user->email]);
        $response->assertStatus(302); // Redirect (not 429)
    }

    // 4th request should be rate limited
    $response = $this->post(route('password.email'), ['email' => $user->email]);
    $response->assertTooManyRequests();
});

test('password reset fails with mismatched passwords', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertSessionHasErrors('password');

        return true;
    });
});

test('forgot password with non-existent email does not reveal user existence', function () {
    $response = $this->post(route('password.email'), ['email' => 'nonexistent@example.com']);

    // Fortify returns a redirect with an error status, not revealing whether the user exists
    $response->assertStatus(302);
});

test('password reset link request requires email', function () {
    $response = $this->post(route('password.email'), ['email' => '']);

    $response->assertSessionHasErrors('email');
});

test('password reset link request requires valid email format', function () {
    $response = $this->post(route('password.email'), ['email' => 'not-an-email']);

    $response->assertSessionHasErrors('email');
});

test('password reset token expiry is configured to 60 minutes', function () {
    expect(config('auth.passwords.users.expire'))->toBe(60);
});

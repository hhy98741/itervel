<?php

use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Support\Facades\Notification;

test('sends queued verification notification', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect(route('home'));

    Notification::assertSentTo($user, QueuedVerifyEmail::class);
});

test('does not send verification notification if email is verified', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect(route('dashboard', absolute: false));

    Notification::assertNothingSent();
});

test('resend verification is rate limited to 3 requests per hour', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    // First 3 requests should succeed
    foreach (range(1, 3) as $attempt) {
        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect(route('home'));
    }

    // 4th request should be rate limited
    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertStatus(429);
});

test('user model sends queued verify email notification directly', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $user->sendEmailVerificationNotification();

    Notification::assertSentTo($user, QueuedVerifyEmail::class);
    Notification::assertCount(1);
});

test('resend rate limit resets after one hour', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    // Exhaust the rate limit
    foreach (range(1, 3) as $attempt) {
        $this->actingAs($user)
            ->post(route('verification.send'));
    }

    // Travel forward 1 hour so the rate limit window resets
    $this->travel(1)->hours();

    // Request after reset should succeed (not 429)
    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect(route('home'));
});

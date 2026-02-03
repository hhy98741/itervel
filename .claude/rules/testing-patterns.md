---
load: conditional
paths:
    - tests/**
keywords:
    - test
    - pest
    - testing
    - spec
    - tdd
    - assertion
    - factory
description: Pest testing patterns, factory states, and test helpers
---

# Testing Patterns

## Test Organization

```
tests/
├── Feature/           # Integration tests (HTTP, database)
│   ├── Auth/         # Authentication tests
│   ├── Settings/     # Settings feature tests
│   └── *.php         # Other feature tests
├── Unit/             # Isolated unit tests
├── Pest.php          # Pest configuration
└── TestCase.php      # Base test class
```

## Pest Configuration

`tests/Pest.php` configures global test behavior:

```php
uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

uses(Tests\TestCase::class)
    ->in('Unit');
```

All Feature tests automatically use `RefreshDatabase` trait.

## Writing Feature Tests

### Basic Structure

```php
<?php

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/settings/profile');

    $response->assertOk();
});
```

### Testing Form Submissions

```php
test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patch('/settings/profile', [
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect('/settings/profile');

    $user->refresh();
    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
});
```

### Testing Validation Errors

```php
test('name is required', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patch('/settings/profile', [
        'name' => '',
        'email' => 'test@example.com',
    ]);

    $response->assertSessionHasErrors('name');
});
```

### Testing Authentication

```php
test('guests cannot access profile', function () {
    $response = $this->get('/settings/profile');

    $response->assertRedirect('/login');
});

test('authenticated users can access profile', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/settings/profile');

    $response->assertOk();
});
```

## Factory States

### Using Factory States

```php
// Default verified user
$user = User::factory()->create();

// Unverified user
$user = User::factory()->unverified()->create();

// User with two-factor authentication
$user = User::factory()->withTwoFactor()->create();

// Combine states
$user = User::factory()
    ->unverified()
    ->withTwoFactor()
    ->create();
```

### Custom Attributes

```php
$user = User::factory()->create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);
```

## Pest Expectations

Use Pest's `expect()` for assertions:

```php
expect($user->name)->toBe('Test User');
expect($user->email)->toEndWith('@example.com');
expect($user->email_verified_at)->not->toBeNull();
expect($response->status())->toBe(200);
```

### Chained Expectations

```php
expect($user)
    ->name->toBe('Test User')
    ->email->toBe('test@example.com')
    ->email_verified_at->not->toBeNull();
```

## Testing Two-Factor Authentication

```php
use Laravel\Fortify\Features;

test('two factor authentication can be enabled', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two factor authentication is not enabled.');
    }

    $user = User::factory()->create();

    $this->actingAs($user)->post('/user/two-factor-authentication');

    expect($user->fresh()->two_factor_secret)->not->toBeNull();
    expect($user->fresh()->two_factor_recovery_codes)->not->toBeNull();
});

test('two factor authentication can be confirmed', function () {
    $user = User::factory()->withTwoFactor()->create();

    // Get valid OTP code
    $code = app(TwoFactorAuthenticationProvider::class)
        ->verify(decrypt($user->two_factor_secret));

    $response = $this->actingAs($user)
        ->post('/user/confirmed-two-factor-authentication', [
            'code' => $code,
        ]);

    expect($user->fresh()->two_factor_confirmed_at)->not->toBeNull();
});
```

## Running Tests

```bash
# All tests
php artisan test --compact

# Specific file
php artisan test tests/Feature/Settings/ProfileTest.php --compact

# Filter by name
php artisan test --compact --filter="profile can be updated"

# With coverage
vendor/bin/pest --coverage

# Stop on first failure
php artisan test --compact --stop-on-failure
```

## Test Helpers

### Acting As User

```php
$this->actingAs($user)->get('/dashboard');
```

### Session Data

```php
$this->withSession(['key' => 'value'])->get('/page');
```

### Check Authentication State

```php
$this->assertAuthenticated();
$this->assertGuest();
```

### Assert Inertia Response

```php
$response->assertInertia(fn ($page) => $page
    ->component('settings/profile')
    ->has('user')
    ->where('mustVerifyEmail', true)
);
```

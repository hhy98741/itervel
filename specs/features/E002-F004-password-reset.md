# Feature: Password Reset

**Epic**: E002-user-authentication.md
**Feature**: E002-F004
**Epic depends on**: None
**Feature depends on**: E002-F001, E002-F003

## Task Description

Password Reset allows users who have forgotten their password to reset it via email. This feature builds on the existing Fortify-powered password reset infrastructure, which already includes a fully functional forgot-password page, reset-password page, backend actions, and basic tests.

**What it does**: Allows users who have forgotten their password to reset it via email.

**Expected outcome**: The user requests a password reset, receives a single-use link (expires after 1 hour), sets a new password, and is notified of the change via email. Maximum 3 reset requests per email per hour.

The existing password reset infrastructure is already substantial:

- **Backend**: `App\Actions\Fortify\ResetUserPassword` handles password validation and reset via the `ResetsUserPasswords` contract. Fortify's `PasswordResetLinkController` handles sending the reset link, and `NewPasswordController` handles the actual reset. The `CompletePasswordReset` action dispatches a `PasswordReset` event and regenerates the remember token.
- **Frontend**: `resources/js/pages/auth/forgot-password.tsx` renders the forgot-password form using Inertia's `<Form>` component with Wayfinder route `email` from `@/routes/password`. `resources/js/pages/auth/reset-password.tsx` renders the reset-password form with token, email, password, and password_confirmation fields using the Wayfinder route `update` from `@/routes/password`.
- **Tests**: `tests/Feature/Auth/PasswordResetTest.php` has 5 tests covering: reset link screen rendering, reset link request, reset password screen rendering, password reset with valid token, and password reset with invalid token.
- **Token expiry**: `config/auth.php` already sets `'expire' => 60` (60 minutes = 1 hour) for the `users` password broker -- matching the requirement.
- **Single-use tokens**: Laravel's password broker already deletes the token after successful reset (`$this->tokens->delete($user)` in `PasswordBroker::reset()`).
- **Built-in throttle**: `config/auth.php` has `'throttle' => 60` which enforces a 1-request-per-60-seconds cooldown via the broker's `recentlyCreatedToken` check. However, the requirement is "Maximum 3 reset requests per email per hour" which requires a custom rate limiter.

**Dependency on E001-F001 (User Registration)**: That feature adds `MustVerifyEmail` to the User model, a `video_credits` column, and Terms of Service validation. Password reset uses the same User model but does not require email verification to function (password reset is for guests who cannot log in).

**Dependency on E001-F003 (User Login)**: That feature configures rate limiting patterns in `FortifyServiceProvider` and session management. The password reset feature follows similar rate limiting patterns but applies them to the `password.email` route.

This feature needs to:

1. Add a custom rate limiter for password reset requests (3 per email per hour)
2. Register the rate limiter as middleware on the `password.email` route
3. Create a `PasswordChanged` notification sent after successful password reset
4. Listen to the `PasswordReset` event to dispatch the notification
5. Expand the test suite to cover rate limiting, token expiry, notification sending, and edge cases

## Objective

Customize the existing Fortify password reset flow to enforce a maximum of 3 reset requests per email per hour, and send a password-changed notification email after a successful reset. Expand the test suite to comprehensively verify all password reset behaviors including rate limiting, single-use tokens, token expiry, and email notifications.

## Solution Approach

### 1. Rate Limiting: 3 reset requests per email per hour

The password reset routes in Fortify do not have any throttle middleware by default (unlike the login route which uses `throttle:login`). The broker has a built-in `recentlyCreatedToken` check that prevents rapid successive requests (1 per `throttle` seconds from `config/auth.php`), but the feature requires "Maximum 3 reset requests per email per hour."

Create a named rate limiter `password-reset` in `FortifyServiceProvider::configureRateLimiting()` that allows 3 requests per 60 minutes keyed by email address:

```php
RateLimiter::for('password-reset', function (Request $request) {
    return Limit::perHour(3)->by($request->input('email'));
});
```

Then register this rate limiter on the `password.email` route. Since Fortify registers its own routes and does not provide a configuration key for password reset throttling (unlike `config('fortify.limiters.login')` for login), we need to add custom middleware to the route after Fortify registers it. The cleanest approach is to use route middleware in `FortifyServiceProvider::boot()` after the Fortify service provider runs, by overriding the route definition. However, since Fortify registers routes in its own service provider which runs before ours, we can append middleware to the route using `Route::getRoutes()`:

The simplest and cleanest approach: Add the throttle middleware to the `password.email` route in `bootstrap/app.php` or via a route group in `routes/web.php`. However, since Fortify registers the route itself, the best approach is to define the route middleware in `FortifyServiceProvider::boot()` using `Route::middleware()`:

```php
use Illuminate\Support\Facades\Route;

// In boot(), after configureRateLimiting():
Route::matched(function ($event) {
    if ($event->route->getName() === 'password.email') {
        $event->route->middleware('throttle:password-reset');
    }
});
```

Actually, the cleaner Laravel approach is to use the `withMiddleware` configuration in `bootstrap/app.php` to append middleware to specific routes, or to override the Fortify route. The most maintainable approach is:

**Option A (Recommended)**: Override the Fortify password.email route with our own that includes the throttle middleware. Since Fortify routes are registered when the service provider boots, and our `FortifyServiceProvider` boots after, we can register a matching route that takes precedence. However, this is fragile.

**Option B (Recommended)**: Use `bootstrap/app.php` to add middleware to the `password.email` named route. In Laravel 12, you can use `$middleware->appendToRoute('password.email', 'throttle:password-reset')`.

**Option C (Simplest)**: Use a Fortify route override. Set `'views' => false` for just the password reset routes... No, this affects all views.

**Best approach**: Register the rate limiter in `FortifyServiceProvider::configureRateLimiting()`, then in the `boot()` method, after Fortify has registered its routes, use an `afterResolving` or `booted` callback to append middleware. The simplest reliable approach is to use `Route::aliasMiddleware` and then override the route in `routes/web.php`:

```php
// In routes/web.php, AFTER Fortify routes are loaded (they load via the FortifyServiceProvider):
Route::post('/forgot-password', [\Laravel\Fortify\Http\Controllers\PasswordResetLinkController::class, 'store'])
    ->middleware(['web', 'guest:web', 'throttle:password-reset'])
    ->name('password.email');
```

However, this duplicates the Fortify route definition and is brittle.

**Final recommended approach**: The cleanest way is to define the rate limiter and append middleware to the route using `$this->app->booted()` in `FortifyServiceProvider`:

```php
// In FortifyServiceProvider::boot():
$this->app->booted(function () {
    $route = Route::getRoutes()->getByName('password.email');
    if ($route) {
        $route->middleware('throttle:password-reset');
    }
});
```

This appends the `throttle:password-reset` middleware to the existing Fortify-registered `password.email` route without duplicating or overriding anything.

### 2. Password Changed Notification

The feature requires "notified of the change via email" after a successful password reset. Fortify's `CompletePasswordReset` action dispatches a `PasswordReset` event after the password is reset. We can listen to this event to send a notification.

Create a `PasswordChanged` notification class that informs the user their password was changed. Then create an event listener that listens for the `PasswordReset` event and sends the notification.

```php
// app/Notifications/PasswordChanged.php
class PasswordChanged extends Notification implements ShouldQueue
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Password Has Been Reset')
            ->line('Your account password has been successfully reset.')
            ->line('If you did not make this change, please contact support immediately.')
            ->action('Log In', url(config('fortify.home', '/dashboard')));
    }
}
```

```php
// app/Listeners/SendPasswordChangedNotification.php
class SendPasswordChangedNotification
{
    public function handle(PasswordReset $event): void
    {
        $event->user->notify(new PasswordChanged());
    }
}
```

Register the listener in `AppServiceProvider` or using the `Event` facade. In Laravel 12, event discovery is automatic if the listener class follows the convention, but we should explicitly register it for clarity in `AppServiceProvider::boot()`:

```php
use Illuminate\Auth\Events\PasswordReset;
use App\Listeners\SendPasswordChangedNotification;

Event::listen(PasswordReset::class, SendPasswordChangedNotification::class);
```

### 3. Token Expiry Configuration Verification

The `config/auth.php` passwords configuration already sets `'expire' => 60` (60 minutes = 1 hour). This matches the requirement exactly. No changes needed for token expiry.

The `'throttle' => 60` setting in `config/auth.php` is the broker's built-in cooldown (minimum seconds between token creation for the same email). This provides a 60-second cooldown on top of our rate limiter. We keep this as-is since it adds a reasonable minimum gap between requests.

### 4. Expand Test Suite

The existing tests cover basic functionality. We need to add tests for:

- Rate limiting (3 requests per hour, 4th is rejected)
- Password changed notification is sent after successful reset
- Password reset link can only be used once (single-use token)
- Password reset fails with expired token
- Password reset fails with mismatched passwords
- Password reset fails without required fields
- Non-existent email does not reveal user existence (security)
- Reset password form displays correctly with token and email

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Providers/FortifyServiceProvider.php` -- Contains rate limiting configuration in `configureRateLimiting()`. Must add the `password-reset` rate limiter and append throttle middleware to the `password.email` route.
- `/Users/young/Nextcloud/dev/Itervel/app/Providers/AppServiceProvider.php` -- Application service provider. Must register the `PasswordReset` event listener for the password changed notification.
- `/Users/young/Nextcloud/dev/Itervel/app/Actions/Fortify/ResetUserPassword.php` -- The Fortify action that validates and resets the user's password. No changes needed, but referenced for understanding the reset flow.
- `/Users/young/Nextcloud/dev/Itervel/app/Concerns/PasswordValidationRules.php` -- Trait providing password validation rules used by `ResetUserPassword`. No changes needed, but referenced for understanding password validation.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` -- User model with `Notifiable` trait (required for sending notifications). No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/config/auth.php` -- Auth configuration with password reset token expiry (`expire => 60`) and throttle (`throttle => 60`). No changes needed -- token expiry already matches the 1-hour requirement.
- `/Users/young/Nextcloud/dev/Itervel/config/fortify.php` -- Fortify configuration with `Features::resetPasswords()` enabled. No changes needed but referenced for understanding the feature flag.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/auth/forgot-password.tsx` -- The forgot-password form React component. No changes needed -- already fully functional with email input, submit button, and status message display.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/auth/reset-password.tsx` -- The reset-password form React component. No changes needed -- already fully functional with token, email, password, and password_confirmation fields.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/auth/login.tsx` -- The login page with the "Forgot password?" link. No changes needed but referenced for understanding the user flow.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/routes/password/index.ts` -- Wayfinder-generated routes for password reset. Contains `email` (POST /forgot-password), `update` (POST /reset-password), `request` (GET /forgot-password), and `reset` (GET /reset-password/{token}). No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Auth/PasswordResetTest.php` -- Existing password reset tests (5 tests). Must be significantly expanded with rate limiting, notification, single-use token, and edge case tests.
- `/Users/young/Nextcloud/dev/Itervel/database/migrations/0001_01_01_000000_create_users_table.php` -- Contains the `password_reset_tokens` table creation. Referenced for understanding the token storage schema. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` -- User factory for creating test users. Referenced for test setup. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/bootstrap/app.php` -- Application bootstrap with middleware and routing configuration. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/vendor/laravel/fortify/src/Http/Controllers/PasswordResetLinkController.php` -- Fortify's controller for sending reset links. Referenced for understanding the flow. No changes needed (vendor file).
- `/Users/young/Nextcloud/dev/Itervel/vendor/laravel/fortify/src/Http/Controllers/NewPasswordController.php` -- Fortify's controller for resetting the password. Referenced for understanding the flow. No changes needed (vendor file).
- `/Users/young/Nextcloud/dev/Itervel/vendor/laravel/fortify/src/Actions/CompletePasswordReset.php` -- Dispatches the `PasswordReset` event after reset. Referenced for understanding where the notification listener hooks in. No changes needed (vendor file).
- `/Users/young/Nextcloud/dev/Itervel/vendor/laravel/framework/src/Illuminate/Auth/Passwords/PasswordBroker.php` -- Laravel's password broker. Handles token creation, throttling (`recentlyCreatedToken`), and token deletion after reset. Referenced for understanding single-use tokens and built-in throttling. No changes needed (vendor file).
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration. All Feature tests automatically use `RefreshDatabase`. No changes needed.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/app/Notifications/PasswordChanged.php` -- Mail notification sent to the user after a successful password reset. Informs them their password was changed and advises contacting support if they did not initiate the change. Created with `php artisan make:notification PasswordChanged --no-interaction`.
- `/Users/young/Nextcloud/dev/Itervel/app/Listeners/SendPasswordChangedNotification.php` -- Event listener that listens for `Illuminate\Auth\Events\PasswordReset` and sends the `PasswordChanged` notification to the user. Created with `php artisan make:listener SendPasswordChangedNotification --event=PasswordReset --no-interaction`.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: password-reset-backend-dev
    - Role: Creates the rate limiter, notification, event listener, and registers middleware on the password.email route. Handles all backend changes for the password reset feature.
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: password-reset-test-dev
    - Role: Expands the password reset test suite to cover rate limiting, notification sending, single-use tokens, token expiry, and validation edge cases
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: password-reset-reviewer
    - Role: Validates the complete password reset feature against acceptance criteria, runs all tests, checks types, runs linting and formatting
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Implement Rate Limiting, Notification, and Event Listener

- **Task ID**: backend-rate-limiting-and-notification
- **Depends On**: none
- **Assigned To**: password-reset-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- Read the following files to understand the existing patterns:
    - `/Users/young/Nextcloud/dev/Itervel/app/Providers/FortifyServiceProvider.php` (existing rate limiting patterns)
    - `/Users/young/Nextcloud/dev/Itervel/app/Providers/AppServiceProvider.php` (event registration location)
    - `/Users/young/Nextcloud/dev/Itervel/app/Actions/Fortify/ResetUserPassword.php` (reset action)
    - `/Users/young/Nextcloud/dev/Itervel/vendor/laravel/fortify/src/Actions/CompletePasswordReset.php` (dispatches PasswordReset event)
    - `/Users/young/Nextcloud/dev/Itervel/config/auth.php` (token expiry and throttle config)
- **Create the PasswordChanged notification**: Run `php artisan make:notification PasswordChanged --no-interaction` to create `/Users/young/Nextcloud/dev/Itervel/app/Notifications/PasswordChanged.php`. Then update it:
    - Implement the `ShouldQueue` interface (add `use Illuminate\Contracts\Queue\ShouldQueue;` import and `implements ShouldQueue` on the class)
    - Use the `Queueable` trait (add `use Illuminate\Bus\Queueable;` import and `use Queueable;` in the class)
    - Set `via()` to return `['mail']`
    - Implement `toMail()` to return a `MailMessage` with:
        - Subject: `'Your Password Has Been Reset'`
        - Line: `'Your account password has been successfully reset.'`
        - Line: `'If you did not make this change, please contact support immediately.'`
        - Action button: `'Log In'` pointing to `url(config('fortify.home', '/dashboard'))`
- **Create the event listener**: Run `php artisan make:listener SendPasswordChangedNotification --event=PasswordReset --no-interaction` to create `/Users/young/Nextcloud/dev/Itervel/app/Listeners/SendPasswordChangedNotification.php`. Then update it:
    - Import `Illuminate\Auth\Events\PasswordReset` (the event class)
    - Import `App\Notifications\PasswordChanged`
    - The `handle()` method should accept `PasswordReset $event` and call `$event->user->notify(new PasswordChanged())`
    - Add return type `void` to `handle()`
- **Register the event listener** in `/Users/young/Nextcloud/dev/Itervel/app/Providers/AppServiceProvider.php`:
    - Add `use Illuminate\Support\Facades\Event;` import
    - Add `use Illuminate\Auth\Events\PasswordReset;` import
    - Add `use App\Listeners\SendPasswordChangedNotification;` import
    - In the `boot()` method, after `$this->configureDefaults();`, add: `Event::listen(PasswordReset::class, SendPasswordChangedNotification::class);`
- **Add the rate limiter** in `/Users/young/Nextcloud/dev/Itervel/app/Providers/FortifyServiceProvider.php`:
    - In `configureRateLimiting()`, add a new rate limiter after the existing `login` rate limiter:
        ```php
        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perHour(3)->by($request->input('email'));
        });
        ```
    - Add `use Illuminate\Support\Facades\Route;` import if not already present
    - In `boot()`, after the existing method calls, add middleware to the `password.email` route:
        ```php
        $this->app->booted(function () {
            $route = Route::getRoutes()->getByName('password.email');
            if ($route) {
                $route->middleware('throttle:password-reset');
            }
        });
        ```
- Run `vendor/bin/pint --dirty` to fix any formatting issues
- Run the existing password reset tests to verify nothing is broken: `php artisan test tests/Feature/Auth/PasswordResetTest.php --compact`
- All existing tests should still pass

### 2. Write Comprehensive Password Reset Tests

- **Task ID**: write-password-reset-tests
- **Depends On**: backend-rate-limiting-and-notification
- **Assigned To**: password-reset-test-dev
- **Agent Type**: coder
- **Parallel**: false
- Read the existing `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Auth/PasswordResetTest.php` to understand current test patterns
- Read sibling test files (e.g., `AuthenticationTest.php`, `RegistrationTest.php`) to follow consistent Pest test patterns
- Read the new files created in the previous task:
    - `/Users/young/Nextcloud/dev/Itervel/app/Notifications/PasswordChanged.php`
    - `/Users/young/Nextcloud/dev/Itervel/app/Listeners/SendPasswordChangedNotification.php`
- Update `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Auth/PasswordResetTest.php` to include the following tests. Keep all 5 existing tests and add new ones:
- **Keep existing tests** (do not modify):
    - `test('reset password link screen can be rendered')` -- GET `route('password.request')` returns 200
    - `test('reset password link can be requested')` -- POST with valid email, assert `ResetPassword` notification sent
    - `test('reset password screen can be rendered')` -- Request link, then GET the reset URL with token, assert 200
    - `test('password can be reset with valid token')` -- Request link, then POST reset with valid token, assert redirect to login
    - `test('password cannot be reset with invalid token')` -- POST with invalid token, assert session has errors on `email`
- **Add new test** -- password changed notification is sent after successful reset:

    ```php
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
    ```

    Add `use App\Notifications\PasswordChanged;` to the test file imports.

- **Add new test** -- reset token is single-use (cannot be used twice):

    ```php
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
    ```

- **Add new test** -- rate limiting on password reset requests (3 per hour):

    ```php
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
    ```

- **Add new test** -- password reset fails with mismatched passwords:

    ```php
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
    ```

- **Add new test** -- forgot password with non-existent email does not reveal user existence:

    ```php
    test('forgot password with non-existent email does not reveal user existence', function () {
        $response = $this->post(route('password.email'), ['email' => 'nonexistent@example.com']);

        // Fortify returns a redirect with an error status, not revealing whether the user exists
        $response->assertStatus(302);
    });
    ```

- **Add new test** -- password reset requires email field:

    ```php
    test('password reset link request requires email', function () {
        $response = $this->post(route('password.email'), ['email' => '']);

        $response->assertSessionHasErrors('email');
    });
    ```

- **Add new test** -- password reset requires valid email format:

    ```php
    test('password reset link request requires valid email format', function () {
        $response = $this->post(route('password.email'), ['email' => 'not-an-email']);

        $response->assertSessionHasErrors('email');
    });
    ```

- **Add new test** -- token expiry is configured to 1 hour:
    ```php
    test('password reset token expiry is configured to 60 minutes', function () {
        expect(config('auth.passwords.users.expire'))->toBe(60);
    });
    ```
- Run all password reset tests: `php artisan test tests/Feature/Auth/PasswordResetTest.php --compact`
- Ensure all tests pass. If any test fails, debug and fix until all pass.
- Run `vendor/bin/pint --dirty` to fix any formatting issues in the test file.

### 3. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: backend-rate-limiting-and-notification, write-password-reset-tests
- **Assigned To**: password-reset-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed below
- Verify password reset tests pass: `php artisan test tests/Feature/Auth/PasswordResetTest.php --compact`
- Verify all auth tests pass: `php artisan test tests/Feature/Auth --compact`
- Run the full test suite to ensure no regressions: `php artisan test --compact`
- Run TypeScript type checking: `npm run types`
- Run ESLint: `npm run lint`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Verify the `PasswordChanged` notification exists at `/Users/young/Nextcloud/dev/Itervel/app/Notifications/PasswordChanged.php` and implements `ShouldQueue`
- Verify the `SendPasswordChangedNotification` listener exists at `/Users/young/Nextcloud/dev/Itervel/app/Listeners/SendPasswordChangedNotification.php` and handles `PasswordReset` event
- Verify the event listener is registered in `AppServiceProvider::boot()`
- Verify the `password-reset` rate limiter is defined in `FortifyServiceProvider::configureRateLimiting()` with `Limit::perHour(3)->by($request->input('email'))`
- Verify the `throttle:password-reset` middleware is appended to the `password.email` route via `$this->app->booted()` in `FortifyServiceProvider::boot()`
- Verify `config('auth.passwords.users.expire')` is `60` (1 hour)
- Verify the forgot-password page (`/forgot-password`) renders correctly
- Verify the reset-password page (`/reset-password/{token}`) renders correctly
- Verify the test file covers: rate limiting (3 per hour), notification sending, single-use tokens, password mismatch, missing email, invalid email format, token expiry configuration
- Confirm all acceptance criteria are met

## Acceptance Criteria

- The forgot-password page renders successfully at the `/forgot-password` route
- The reset-password page renders successfully at the `/reset-password/{token}` route with token and email
- A user can request a password reset link by submitting their email on the forgot-password page
- The password reset link email is sent using Laravel's built-in `ResetPassword` notification
- The reset link token expires after 1 hour (60 minutes) as configured in `config/auth.php`
- The reset token is single-use: once used to reset a password, it cannot be reused
- After successful password reset, the user receives a "password changed" email notification via the `PasswordChanged` notification
- Password reset requests are rate limited to a maximum of 3 per email per hour (4th request returns 429)
- Password reset fails with appropriate validation errors for: invalid token, mismatched passwords, missing email, invalid email format
- Non-existent email addresses do not reveal whether a user account exists
- The user is redirected to the login page after successful password reset
- All password reset tests pass
- All existing tests pass without regressions
- PHP code passes Pint formatting
- TypeScript types compile without errors
- ESLint checks pass

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run password reset tests
php artisan test tests/Feature/Auth/PasswordResetTest.php --compact

# Run all auth tests
php artisan test tests/Feature/Auth --compact

# Run full test suite for regression check
php artisan test --compact

# TypeScript type checking
npm run types

# ESLint linting
npm run lint

# PHP code formatting
vendor/bin/pint --dirty
```

## Notes

- The frontend pages (`forgot-password.tsx` and `reset-password.tsx`) do not need any changes. They are already fully functional with all required UI elements: email input, password fields, submit buttons, status messages, and error displays.
- The `config/auth.php` password reset configuration already has `'expire' => 60` (1 hour) and `'throttle' => 60` (60-second cooldown between token creations). The `throttle` setting is the broker's built-in cooldown and acts as a secondary protection on top of our custom rate limiter. We keep it as-is.
- The `PasswordChanged` notification should implement `ShouldQueue` so sending the email does not block the password reset response. If the queue is not configured (using sync driver), it will still execute synchronously, which is fine for development.
- The rate limiter uses `$request->input('email')` as the key, meaning rate limiting is per-email, not per-IP. This means if multiple IPs try to reset the same email, they share the 3-request-per-hour limit. This is the correct behavior for preventing abuse against a specific email address.
- The `recentlyCreatedToken` check in the password broker (60-second cooldown) will cause the broker to return `RESET_THROTTLED` for rapid successive requests even before the rate limiter kicks in. This means the first request succeeds, and any request within 60 seconds returns a "please wait" message from the broker. The rate limiter ensures that even with 60-second gaps, no more than 3 requests per hour are allowed.
- Laravel's `CompletePasswordReset` action calls `event(new PasswordReset($user))` which triggers our `SendPasswordChangedNotification` listener. The token is already deleted at this point (by the broker), and the user's password is already updated.
- All test users should be created with `User::factory()->create()` which creates verified users by default (with `email_verified_at` set). This is important after E001-F001 enables `MustVerifyEmail`.
- All commands should be run inside the Docker container. Use `make shell` to enter the container, or prefix with `docker compose exec app`.

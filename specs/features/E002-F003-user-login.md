# Feature: User Login

**Epic**: E002-user-authentication.md
**Feature**: E002-F003
**Dependencies**: E002-F001

## Task Description

User Login allows registered users to authenticate and access the Itervel platform. It builds on the existing Fortify-powered login infrastructure, which already includes a fully functional login page, backend authentication pipeline, rate limiting, "Remember me" checkbox, and basic tests. This feature customizes the existing infrastructure to meet platform-specific session and security requirements.

**What it does**: Allows registered users to log in to their account with their email and password.

**Expected outcome**: The user enters their credentials and gains access to the application. They can optionally choose "Remember me" for 30-day session persistence. After 5 failed login attempts, the account is locked for 15 minutes. Sessions expire after 24 hours of inactivity.

The existing login infrastructure is already substantial:

- **Backend**: Fortify's `AuthenticatedSessionController` handles the full login pipeline including `EnsureLoginIsNotThrottled`, `CanonicalizeUsername`, `RedirectIfTwoFactorAuthenticatable`, `AttemptToAuthenticate`, and `PrepareAuthenticatedSession`. The `FortifyServiceProvider` configures the login view, login rate limiter, and two-factor rate limiter.
- **Frontend**: `resources/js/pages/auth/login.tsx` renders a complete login form with email, password, "Remember me" checkbox, "Forgot password?" link, and a "Sign up" link. It uses Inertia's `<Form>` component and Wayfinder-generated routes (`@/routes/login`).
- **Tests**: `tests/Feature/Auth/AuthenticationTest.php` has 6 tests covering login screen rendering, successful authentication, 2FA redirect, invalid password rejection, logout, and rate limiting.
- **Rate limiting**: Currently configured as 5 attempts per minute per email+IP combination (`Limit::perMinute(5)`) with a 60-second decay.
- **Session**: Currently configured for 120-minute idle expiration via `SESSION_LIFETIME`.
- **Remember me**: Laravel's default remember cookie duration is 576000 minutes (400 days).

This feature needs to adjust three configuration values to meet the specified requirements:

1. Change the login rate limiter from 5 per minute (60s lockout) to 5 per 15 minutes (900s lockout)
2. Change the session lifetime from 120 minutes to 1440 minutes (24 hours)
3. Change the remember me cookie duration from 576000 minutes (400 days) to 43200 minutes (30 days)
4. Expand the test suite to cover all specified behaviors including lockout timing and remember me behavior

**Dependency on E001-F001 (User Registration)**: That feature enables `MustVerifyEmail` on the User model, adds a `video_credits` column, and adds Terms of Service validation. After E001-F001 is built, the User model will implement `MustVerifyEmail`, which means unverified users will be redirected to the verification page after login (via the `verified` middleware on the dashboard route). The login tests must account for this by using verified users (the factory default already sets `email_verified_at` to `now()`).

## Objective

Configure the existing Fortify login flow with platform-specific session and security settings: 15-minute lockout after 5 failed login attempts, 24-hour idle session expiration, and 30-day "Remember me" persistence. Expand the test suite to comprehensively verify all login behaviors.

## Solution Approach

### 1. Update Rate Limiting: 5 attempts, 15-minute lockout

The existing rate limiter in `FortifyServiceProvider::configureRateLimiting()` uses `Limit::perMinute(5)` for the `login` limiter. This allows 5 attempts per minute with a 60-second decay window.

The feature requires: after 5 failed attempts, lock for 15 minutes. Change to `Limit::perMinutes(15, 5)` which creates a rate limit of 5 attempts per 15-minute window. When the limit is exceeded, the user must wait until the window expires (up to 15 minutes).

```php
RateLimiter::for('login', function (Request $request) {
    $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

    return Limit::perMinutes(15, 5)->by($throttleKey);
});
```

Note: Because `config('fortify.limiters.login')` is set to `'login'`, Fortify uses this named rate limiter via Laravel's throttle middleware instead of its internal `EnsureLoginIsNotThrottled` pipeline action. This means the throttle key and decay behavior are fully controlled by our `RateLimiter::for('login', ...)` definition.

### 2. Update Session Lifetime: 24 hours

Change the `SESSION_LIFETIME` environment variable in `.env.example` from `120` to `1440` (24 hours = 1440 minutes). The `config/session.php` file already reads this value via `env('SESSION_LIFETIME', 120)`. We should also update the default fallback in `config/session.php` to `1440` so the application behaves correctly even without the `.env` variable.

```php
'lifetime' => (int) env('SESSION_LIFETIME', 1440),
```

### 3. Update Remember Me Duration: 30 days

Laravel's `SessionGuard` has a default `rememberDuration` of 576000 minutes (400 days). To change this to 30 days (43200 minutes), configure it in the `AppServiceProvider` or `FortifyServiceProvider` boot method using `Auth::guard('web')->setRememberDuration(43200)`.

The cleanest approach is to add this to `FortifyServiceProvider::boot()` since it is the authentication-focused provider:

```php
use Illuminate\Support\Facades\Auth;

// In boot() method, after existing configuration:
Auth::guard('web')->setRememberDuration(43200); // 30 days in minutes
```

### 4. Expand Test Suite

The existing `AuthenticationTest.php` already has 6 solid tests. We need to add tests that specifically validate:

- Rate limiting locks out after 5 failed attempts with proper error message
- Rate limiting lockout duration is 15 minutes (verify the retry-after timing)
- Remember me checkbox creates a persistent session (cookie with remember token)
- Login without remember me does not set the remember cookie
- Session expiration behavior (24-hour idle timeout)
- Login with valid credentials redirects to dashboard
- Login page displays status messages (e.g., after password reset)
- Authenticated users cannot access the login page (redirect to dashboard)

The existing rate limiting test uses `RateLimiter::increment()` with `md5()` hashing, but since we are using a named rate limiter via `config('fortify.limiters.login')`, the throttle key format follows Laravel's throttle middleware convention. The existing test already tests this correctly by pre-incrementing the limiter. We should update it to verify the 15-minute window and add additional test cases.

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Providers/FortifyServiceProvider.php` -- Contains the login rate limiter definition in `configureRateLimiting()`. Must change `Limit::perMinute(5)` to `Limit::perMinutes(15, 5)`. Also add the remember me duration configuration.
- `/Users/young/Nextcloud/dev/Itervel/config/session.php` -- Session configuration. Must update the default `SESSION_LIFETIME` fallback from `120` to `1440` to set 24-hour idle session expiration.
- `/Users/young/Nextcloud/dev/Itervel/.env.example` -- Environment variable template. Must update `SESSION_LIFETIME=120` to `SESSION_LIFETIME=1440`.
- `/Users/young/Nextcloud/dev/Itervel/config/fortify.php` -- Fortify configuration. Contains the `limiters.login` key that points to the named rate limiter. No changes needed, but referenced for understanding the rate limiting flow.
- `/Users/young/Nextcloud/dev/Itervel/config/auth.php` -- Auth configuration. Referenced for understanding guard setup. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` -- User model. After E001-F001, this will implement `MustVerifyEmail`. Referenced for understanding the auth flow. No changes needed for this feature.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/auth/login.tsx` -- The login page React component. Already fully functional with email, password, "Remember me" checkbox, "Forgot password?" link, and "Sign up" link. No changes needed, but referenced for understanding the frontend.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/routes/login/index.ts` -- Wayfinder-generated login route functions. Referenced for understanding the form action. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/auth-layout.tsx` -- Auth layout wrapper. Referenced for understanding page structure. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/auth/auth-simple-layout.tsx` -- Auth layout template. Referenced for context. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/checkbox.tsx` -- Radix checkbox component used for "Remember me". Referenced for context. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Auth/AuthenticationTest.php` -- Existing authentication tests. Must be expanded with additional tests for 15-minute lockout, remember me behavior, and session configuration.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` -- User factory with default state (verified user, default password 'password'). Referenced for test setup. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/bootstrap/app.php` -- Application bootstrap with middleware configuration. Referenced for context. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/app/Providers/AppServiceProvider.php` -- Application service provider. Referenced for understanding password defaults. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration. All Feature tests automatically use `RefreshDatabase`. Referenced for context. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/vendor/laravel/fortify/src/Http/Controllers/AuthenticatedSessionController.php` -- Fortify's login controller. Referenced for understanding the login pipeline. No changes needed (vendor file).
- `/Users/young/Nextcloud/dev/Itervel/vendor/laravel/fortify/src/Actions/AttemptToAuthenticate.php` -- Fortify's authentication action. Shows how `$request->boolean('remember')` is used with `$this->guard->attempt()`. Referenced for understanding remember me behavior. No changes needed (vendor file).
- `/Users/young/Nextcloud/dev/Itervel/vendor/laravel/fortify/src/LoginRateLimiter.php` -- Fortify's internal rate limiter (used when `config('fortify.limiters.login')` is NOT set). Not directly used since we define a named rate limiter, but referenced for understanding. No changes needed (vendor file).
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Web routes. The dashboard route has `['auth', 'verified']` middleware. Referenced for understanding post-login redirect. No changes needed.

### New Files

No new files need to be created. All changes are modifications to existing files.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: login-backend-dev
    - Role: Updates the rate limiter configuration to 15-minute lockout, configures 30-day remember me duration, updates session lifetime to 24 hours
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: login-test-dev
    - Role: Expands the authentication test suite to comprehensively cover rate limiting lockout behavior, remember me functionality, session configuration, and edge cases
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: login-reviewer
    - Role: Validates the complete login feature against acceptance criteria, runs all tests, checks types, runs linting and formatting
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Configure Login Rate Limiting, Session Lifetime, and Remember Me Duration

- **Task ID**: configure-login-backend
- **Depends On**: none
- **Assigned To**: login-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- Read the current `/Users/young/Nextcloud/dev/Itervel/app/Providers/FortifyServiceProvider.php` to understand the existing rate limiter and auth configuration
- In `FortifyServiceProvider::configureRateLimiting()`, change the login rate limiter from `Limit::perMinute(5)` to `Limit::perMinutes(15, 5)` to enforce a 5-attempt limit with a 15-minute lockout window. The full replacement:

    ```php
    RateLimiter::for('login', function (Request $request) {
        $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

        return Limit::perMinutes(15, 5)->by($throttleKey);
    });
    ```

- In `FortifyServiceProvider::boot()`, after the `$this->configureRateLimiting();` call, add the remember me duration configuration. Add `use Illuminate\Support\Facades\Auth;` to the imports, then add to the bottom of `boot()`:
    ```php
    /** @var \Illuminate\Auth\SessionGuard $guard */
    $guard = Auth::guard('web');
    $guard->setRememberDuration(43200); // 30 days in minutes
    ```
- Update `/Users/young/Nextcloud/dev/Itervel/config/session.php` -- change the `lifetime` default from `120` to `1440`:
    ```php
    'lifetime' => (int) env('SESSION_LIFETIME', 1440),
    ```
- Update `/Users/young/Nextcloud/dev/Itervel/.env.example` -- change `SESSION_LIFETIME=120` to `SESSION_LIFETIME=1440`
- Run `vendor/bin/pint --dirty` to fix any formatting issues
- Run the existing authentication tests to verify nothing is broken: `php artisan test tests/Feature/Auth/AuthenticationTest.php --compact`
- Note: The existing rate limiting test pre-increments the rate limiter by 5. After our change, the throttle key format used by the named rate limiter (via Laravel's `ThrottleRequests` middleware) may differ from the manual `md5()` key in the test. This test may need updating in the next task. If it fails, that is expected.

### 2. Write Comprehensive Login Tests

- **Task ID**: write-login-tests
- **Depends On**: configure-login-backend
- **Assigned To**: login-test-dev
- **Agent Type**: coder
- **Parallel**: false
- Read the existing `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Auth/AuthenticationTest.php` to understand current test patterns
- Read sibling test files (e.g., `RegistrationTest.php`, `PasswordResetTest.php`) to follow consistent test patterns
- Update `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Auth/AuthenticationTest.php` to include the following tests (keep all existing passing tests, update the rate limiting test, and add new ones):
- **Keep existing tests**:
    - `test('login screen can be rendered')` -- GET `route('login')` returns 200
    - `test('users can authenticate using the login screen')` -- POST valid credentials, assert authenticated, assert redirect to dashboard
    - `test('users with two factor enabled are redirected to two factor challenge')` -- POST valid credentials for 2FA user, assert redirect to `route('two-factor.login')`
    - `test('users can not authenticate with invalid password')` -- POST wrong password, assert guest
    - `test('users can logout')` -- POST to `route('logout')`, assert guest
- **Update existing rate limiting test** to verify the 15-minute lockout:
    - `test('users are rate limited after 5 failed attempts')` -- Make 5 failed login attempts with wrong passwords, then attempt a 6th. Assert the 6th returns a 429 Too Many Requests response. Verify the response includes a `Retry-After` header with a value up to 900 seconds (15 minutes).

    ```php
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
    ```

- **Add new test** -- remember me sets the remember cookie:
    - `test('remember me creates persistent session')` -- POST with `'remember' => 'on'`, assert authenticated, assert the response sets a cookie whose name matches the remember cookie pattern (`remember_web_*`).

    ```php
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
    ```

- **Add new test** -- login without remember me does not set remember token:
    - `test('login without remember me does not set remember token')` -- POST without `remember` field, assert authenticated, assert the user's `remember_token` in the database remains the factory default (which is a random string, but we can check it was not modified or check the cookie is not set).

    ```php
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
    ```

- **Add new test** -- session lifetime configuration:
    - `test('session lifetime is configured to 24 hours')` -- Assert that `config('session.lifetime')` equals 1440.
    ```php
    test('session lifetime is configured to 24 hours', function () {
        expect(config('session.lifetime'))->toBe(1440);
    });
    ```
- **Add new test** -- remember me duration:
    - `test('remember me duration is configured to 30 days')` -- Assert the guard's remember duration is 43200 minutes.
    ```php
    test('remember me duration is configured to 30 days', function () {
        $guard = Auth::guard('web');
        $reflection = new \ReflectionProperty($guard, 'rememberDuration');
        expect($reflection->getValue($guard))->toBe(43200);
    });
    ```
- **Add new test** -- authenticated users are redirected from login page:
    - `test('authenticated users are redirected from login page')` -- Acting as a user, GET the login page, assert redirect.

    ```php
    test('authenticated users are redirected from login page', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('login'));

        $response->assertRedirect(route('dashboard'));
    });
    ```

- **Add new test** -- login with nonexistent email:
    - `test('login fails with nonexistent email')` -- POST with an email that does not exist, assert guest, assert session has errors on email.

    ```php
    test('login fails with nonexistent email', function () {
        $this->post(route('login.store'), [
            'email' => 'nonexistent@example.com',
            'password' => 'password',
        ]);

        $this->assertGuest();
    });
    ```

- **Add new test** -- login requires email:
    - `test('login requires email')` -- POST without email, assert session has errors on email.

    ```php
    test('login requires email', function () {
        $response = $this->post(route('login.store'), [
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    });
    ```

- **Add new test** -- login requires password:
    - `test('login requires password')` -- POST without password, assert session has errors on password.

    ```php
    test('login requires password', function () {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    });
    ```

- Run all authentication tests: `php artisan test tests/Feature/Auth/AuthenticationTest.php --compact`
- Ensure all tests pass. If any test fails, debug and fix until all pass.
- Run `vendor/bin/pint --dirty` to fix any formatting issues in the test file.

### 3. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: configure-login-backend, write-login-tests
- **Assigned To**: login-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed below
- Verify authentication tests pass: `php artisan test tests/Feature/Auth/AuthenticationTest.php --compact`
- Verify all auth tests pass: `php artisan test tests/Feature/Auth --compact`
- Run the full test suite to ensure no regressions: `php artisan test --compact`
- Run TypeScript type checking: `npm run types`
- Run ESLint: `npm run lint`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Verify in `FortifyServiceProvider` that the login rate limiter uses `Limit::perMinutes(15, 5)` (not `Limit::perMinute(5)`)
- Verify in `FortifyServiceProvider` that the remember me duration is set to 43200 minutes via `Auth::guard('web')->setRememberDuration(43200)`
- Verify in `config/session.php` that the default lifetime fallback is `1440`
- Verify in `.env.example` that `SESSION_LIFETIME=1440`
- Verify the test file includes tests for: rate limiting (5 attempts, 15-minute lockout), remember me token, session lifetime config, remember duration config, authenticated user redirect, invalid credentials, missing email, missing password
- Confirm all acceptance criteria are met

## Acceptance Criteria

- Registered users can log in with valid email and password credentials
- Successful login redirects to the dashboard (`/dashboard`)
- The "Remember me" checkbox creates a persistent session with a 30-day remember cookie (43200 minutes)
- Login without "Remember me" does not set a remember token on the user
- After 5 failed login attempts, the user receives a 429 Too Many Requests response
- The rate limit lockout window is 15 minutes (attempts reset after the window)
- Sessions expire after 24 hours of inactivity (session lifetime is 1440 minutes)
- Login fails with appropriate validation errors for missing email, missing password, or invalid credentials
- Users with invalid credentials see an authentication failure error message
- Authenticated users are redirected away from the login page
- Users with two-factor authentication enabled are redirected to the 2FA challenge
- Users can log out successfully
- All authentication tests pass
- All existing tests pass without regressions
- PHP code passes Pint formatting

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run authentication tests
php artisan test tests/Feature/Auth/AuthenticationTest.php --compact

# Run all auth tests (includes 2FA, email verification, password reset, etc.)
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

- The login page frontend (`resources/js/pages/auth/login.tsx`) does not need any changes. It already has all the required UI elements: email field, password field, "Remember me" checkbox, "Forgot password?" link, and "Sign up" link.
- The rate limiter key format: When `config('fortify.limiters.login')` is set (which it is, to `'login'`), Fortify delegates rate limiting to Laravel's `ThrottleRequests` middleware using the named rate limiter. This means the rate limiting behavior is entirely controlled by the `RateLimiter::for('login', ...)` definition in `FortifyServiceProvider`. The `EnsureLoginIsNotThrottled` pipeline action is skipped (see `AuthenticatedSessionController::loginPipeline()` line 86).
- The `Limit::perMinutes(15, 5)` method creates a rate limit allowing 5 requests within a 15-minute sliding window. Once exceeded, subsequent requests are blocked until the window resets. This is the correct interpretation of "after 5 failed attempts, locked for 15 minutes."
- Laravel's `remember` functionality works as follows: when `$request->boolean('remember')` is `true`, Fortify's `AttemptToAuthenticate` passes `true` as the second argument to `$this->guard->attempt()`, which causes the `SessionGuard` to set a long-lived remember cookie. The cookie duration is controlled by `$guard->rememberDuration` (defaulting to 576000 minutes). We change this to 43200 minutes (30 days).
- The `SESSION_LIFETIME` controls idle session expiration. A session is considered "idle" when no requests are made within the lifetime window. Each request resets the timer. Setting it to 1440 minutes (24 hours) means the session expires after a full day of inactivity.
- All tests should use the `User::factory()->create()` method which creates verified users by default (with `email_verified_at` set). This is important after E001-F001 enables `MustVerifyEmail`, as unverified users would be redirected to the verification page instead of the dashboard.
- The existing test that uses `RateLimiter::increment()` with a manual `md5()` key may need adjustment since the named rate limiter (`'login'`) uses Laravel's internal throttle key format. The safest approach is to test rate limiting by actually making failed login attempts rather than manually incrementing the rate limiter counter.
- All commands should be run inside the Docker container. Use `make shell` to enter the container, or prefix with `docker compose exec app`.

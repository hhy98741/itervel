# Feature: Email Verification

**Epic**: E002-user-authentication.md
**Feature**: E002-F002
**Dependencies**: E002-F001

## Task Description

Email Verification ensures that users who register on the Itervel platform actually own the email address they provided. After a user registers, the system automatically sends a verification email containing a signed, time-limited link. Clicking the link verifies the user's account and grants them full access to the platform. Unverified users can log in and access basic settings, but they cannot create videos (routes protected by the `verified` middleware are blocked until verification is complete).

**What it does**: Sends a verification email after registration so users can confirm they own the email address.

**Expected outcome**: The user receives an email with a verification link within 30 seconds of registering. Clicking the link verifies their account. The link expires after 24 hours. Users can request up to 3 new verification emails per hour. Unverified users can log in but cannot create videos.

This feature depends on E001-F001 (User Registration), which enables `MustVerifyEmail` on the User model. With `MustVerifyEmail` active, the Laravel framework automatically fires the `Registered` event after user creation, which triggers the `SendEmailVerificationNotification` listener that sends the `VerifyEmail` notification. Fortify provides all the controllers and routes for the verification flow (`/email/verify`, `/email/verify/{id}/{hash}`, `/email/verification-notification`).

### Current State of the Codebase

The verification infrastructure is **largely already in place**:

1. **Fortify config**: `Features::emailVerification()` is already enabled in `/Users/young/Nextcloud/dev/Itervel/config/fortify.php` (line 149).
2. **Fortify routes**: Fortify automatically registers `verification.notice`, `verification.verify`, and `verification.send` routes when email verification is enabled.
3. **Frontend page**: `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/auth/verify-email.tsx` already exists with a "Resend verification email" button using the Wayfinder-generated `send.form()` action.
4. **Wayfinder routes**: `/Users/young/Nextcloud/dev/Itervel/resources/js/routes/verification/index.ts` is auto-generated with `notice`, `verify`, and `send` route functions.
5. **FortifyServiceProvider view**: `Fortify::verifyEmailView()` is already configured to render `auth/verify-email` via Inertia (line 65-67 of FortifyServiceProvider).
6. **Existing tests**: `tests/Feature/Auth/EmailVerificationTest.php` has 5 tests and `tests/Feature/Auth/VerificationNotificationTest.php` has 2 tests covering basic flows.
7. **Dashboard route**: Already uses `['auth', 'verified']` middleware in `routes/web.php` (line 15).
8. **VerifyEmail notification**: Laravel's built-in `Illuminate\Auth\Notifications\VerifyEmail` generates a `temporarySignedRoute` with configurable expiration via `Config::get('auth.verification.expire', 60)` (default 60 minutes).
9. **Fortify throttling**: The `verification.send` route uses `throttle:` middleware with a configurable limiter via `config('fortify.limiters.verification')`, defaulting to `6,1` (6 requests per minute).

### What This Feature Must Change

The epic specifies three key requirements that diverge from the defaults:

1. **24-hour link expiration** (default is 60 minutes): Requires adding `auth.verification.expire` config set to `1440` (24 hours in minutes).
2. **3 resend requests per hour** (default is 6 per minute): Requires configuring a custom rate limiter named `verification` and referencing it in `config/fortify.php`.
3. **Unverified users can log in but cannot create videos**: The `verified` middleware must be applied to video creation routes. Since no video creation routes exist yet (those are future features), this feature must establish the pattern and document it. The dashboard already uses `verified` middleware. The verify-email page must clearly communicate the limitation.

Additionally, E001-F001 will have already enabled `MustVerifyEmail` on the User model, so the automatic sending on registration is handled. This feature focuses on: configuring the verification link expiration, setting up proper rate limiting for resend requests, enhancing the verify-email page UX, and writing comprehensive tests for the full verification lifecycle.

## Objective

Configure the email verification system so that verification links expire after 24 hours, resend requests are limited to 3 per hour, the verify-email page provides clear UX feedback, and all verification flows are thoroughly tested. Establish the `verified` middleware pattern for protecting future video creation routes.

## Solution Approach

### 1. Configure 24-Hour Verification Link Expiration

The `VerifyEmail` notification (in `vendor/laravel/framework/src/Illuminate/Auth/Notifications/VerifyEmail.php`) reads `Config::get('auth.verification.expire', 60)` to determine link lifetime. Currently, there is no `verification` key in `config/auth.php`. Adding it will override the 60-minute default:

```php
// In config/auth.php, add after 'password_timeout':
'verification' => [
    'expire' => 1440, // 24 hours in minutes
],
```

This is the standard Laravel approach -- no custom notification class needed.

### 2. Configure Rate Limiting for Verification Resend (3 per hour)

Fortify reads `config('fortify.limiters.verification')` for throttling the `verification.send` and `verification.verify` routes. The default fallback is `'6,1'` (6 requests per minute). To implement 3 per hour:

**a) Add a `verification` limiter key to `config/fortify.php`:**

```php
'limiters' => [
    'login' => 'login',
    'two-factor' => 'two-factor',
    'verification' => 'verification',
],
```

**b) Define the `verification` rate limiter in `FortifyServiceProvider`:**

```php
RateLimiter::for('verification', function (Request $request) {
    return Limit::perHour(3)->by($request->user()?->id ?: $request->ip());
});
```

This limits each authenticated user to 3 verification email resend requests per hour. The `$request->user()?->id` ensures the limit is per-user (since the verification routes require auth), with a fallback to IP for edge cases.

### 3. Enhance the Verify-Email Page

The existing `verify-email.tsx` page is functional but minimal. Enhancements:

- Add an informational message explaining why verification is needed ("You need to verify your email to create videos")
- Display the user's email address so they can confirm it is correct
- Add a link to profile settings where they can change their email if it is wrong
- Show rate limit context ("You can request up to 3 new emails per hour")
- Improve the success message styling

The page already has the resend button via Wayfinder's `send.form()` and displays the `verification-link-sent` status. The User's email is available via shared Inertia data (`auth.user.email`).

### 4. Ensure Notification is Queued for 30-Second Delivery

The epic requires the email to arrive within 30 seconds. Laravel's `VerifyEmail` notification is not queued by default (it is sent synchronously). To ensure it is queued (for better UX during registration), the User model can implement `ShouldQueue` on its notification sending, or we can override the notification class. However, since the default queue connection is `database` and the test environment typically uses `sync`, and the 30-second requirement is more about the delivery pipeline than our code, the simplest approach is to ensure the notification is queued by implementing `Illuminate\Contracts\Queue\ShouldQueue` on a custom notification class that extends `VerifyEmail`.

Actually, the simplest and most Laravel-idiomatic approach is to make the `VerifyEmail` notification queueable by overriding `sendEmailVerificationNotification()` on the User model to use a queued version:

```php
public function sendEmailVerificationNotification(): void
{
    $this->notify(new \App\Notifications\QueuedVerifyEmail);
}
```

Where `QueuedVerifyEmail` extends `VerifyEmail` and implements `ShouldQueue`.

### 5. Expand Test Coverage

The existing tests cover basic rendering and link verification. Additional tests needed:

- Verification link expires after 24 hours (using `Carbon::setTestNow()`)
- Rate limiting on resend (hitting the limit and getting 429)
- Unverified user cannot access `verified` middleware routes
- Verified user can access `verified` middleware routes
- Resend notification is queued
- Verification email content (link presence)
- Already verified user resending does not send notification

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/config/auth.php` -- Must add `verification.expire` config key set to `1440` for 24-hour link expiration. Currently has no verification section.
- `/Users/young/Nextcloud/dev/Itervel/config/fortify.php` -- Must add `'verification' => 'verification'` to the `limiters` array so Fortify uses the custom rate limiter for verification routes.
- `/Users/young/Nextcloud/dev/Itervel/app/Providers/FortifyServiceProvider.php` -- Must add a `RateLimiter::for('verification', ...)` definition in the `configureRateLimiting()` method to limit resend requests to 3 per hour.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` -- Must override `sendEmailVerificationNotification()` to use the queued notification class. After E001-F001, this model already implements `MustVerifyEmail`.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/auth/verify-email.tsx` -- Must enhance with user email display, explanation of verification requirement, profile settings link, and rate limit messaging.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/auth.ts` -- Referenced for the `User` type which includes `email` and `email_verified_at`. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/auth-layout.tsx` -- The auth layout used by verify-email page. No changes needed but referenced for understanding.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/text-link.tsx` -- Used for linking to profile settings from the verify-email page.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/button.tsx` -- Used in the verify-email page for the resend button. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/spinner.tsx` -- Used in the verify-email page for processing state. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/routes/verification/index.ts` -- Wayfinder-generated route functions for verification. Auto-generated, no manual changes.
- `/Users/young/Nextcloud/dev/Itervel/vendor/laravel/framework/src/Illuminate/Auth/Notifications/VerifyEmail.php` -- The base VerifyEmail notification. Referenced for understanding the URL generation and expiration logic. Not modified.
- `/Users/young/Nextcloud/dev/Itervel/vendor/laravel/fortify/src/Http/Controllers/EmailVerificationNotificationController.php` -- Fortify's controller for resending verification emails. Not modified but referenced for understanding the flow.
- `/Users/young/Nextcloud/dev/Itervel/vendor/laravel/fortify/routes/routes.php` -- Fortify's route definitions showing the `throttle:$verificationLimiter` middleware on verification routes. Referenced for understanding.
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Contains the dashboard route with `['auth', 'verified']` middleware. Referenced as the pattern for protecting routes.
- `/Users/young/Nextcloud/dev/Itervel/routes/settings.php` -- Settings routes split between `auth` only and `['auth', 'verified']` groups. Referenced for the middleware pattern.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Auth/EmailVerificationTest.php` -- Existing tests for email verification (5 tests). Must be expanded with additional test cases.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Auth/VerificationNotificationTest.php` -- Existing tests for resend notification (2 tests). Must be expanded with rate limiting and queuing tests.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration. All Feature tests use `RefreshDatabase`. Referenced for conventions.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Auth/AuthenticationTest.php` -- Referenced for test pattern conventions (rate limiting tests, Pest style).
- `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` -- User factory with `unverified()` state. Used in tests. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/bootstrap/app.php` -- Application bootstrap with middleware configuration. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/app/Providers/AppServiceProvider.php` -- App service provider. No changes needed but referenced for understanding Password defaults.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/app/Notifications/QueuedVerifyEmail.php` -- A queued version of the `VerifyEmail` notification that extends `Illuminate\Auth\Notifications\VerifyEmail` and implements `Illuminate\Contracts\Queue\ShouldQueue`. This ensures verification emails are sent via the queue for faster response times during registration.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: verification-backend-dev
    - Role: Configures verification link expiration, rate limiting, queued notification, and all backend changes for the email verification feature
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: verification-frontend-dev
    - Role: Enhances the verify-email page with improved UX, user email display, rate limit messaging, and profile settings link
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: verification-test-dev
    - Role: Expands the email verification and notification test suites to cover link expiration, rate limiting, queuing, and access control
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: verification-reviewer
    - Role: Validates the complete email verification feature against all acceptance criteria, runs tests, checks types, linting, and formatting
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

### 1. Configure Backend: Link Expiration, Rate Limiting, and Queued Notification

- **Task ID**: backend-verification-config
- **Depends On**: none
- **Assigned To**: verification-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- Activate the `developing-with-fortify` skill before making changes
- **Create the queued notification class** by running: `php artisan make:notification QueuedVerifyEmail --no-interaction`
- Edit `/Users/young/Nextcloud/dev/Itervel/app/Notifications/QueuedVerifyEmail.php` to:
    - Extend `Illuminate\Auth\Notifications\VerifyEmail` instead of the default `Notification` base class
    - Implement `Illuminate\Contracts\Queue\ShouldQueue`
    - Use the `Illuminate\Bus\Queueable` trait
    - Remove all the default generated methods (`via`, `toMail`, `toArray`, `toDatabase`) since the parent class handles everything
    - The class body should be minimal:

        ```php
        <?php

        namespace App\Notifications;

        use Illuminate\Auth\Notifications\VerifyEmail;
        use Illuminate\Bus\Queueable;
        use Illuminate\Contracts\Queue\ShouldQueue;

        class QueuedVerifyEmail extends VerifyEmail implements ShouldQueue
        {
            use Queueable;
        }
        ```

- **Update the User model** at `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php`:
    - Add an import for `App\Notifications\QueuedVerifyEmail`
    - Override the `sendEmailVerificationNotification()` method to use the queued notification:
        ```php
        /**
         * Send the email verification notification.
         */
        public function sendEmailVerificationNotification(): void
        {
            $this->notify(new QueuedVerifyEmail);
        }
        ```
    - Note: After E001-F001, the model already implements `MustVerifyEmail` and has the import uncommented. If E001-F001 has not been built yet, also uncomment `use Illuminate\Contracts\Auth\MustVerifyEmail;` and add `implements MustVerifyEmail` to the class declaration.
- **Configure 24-hour link expiration** in `/Users/young/Nextcloud/dev/Itervel/config/auth.php`:
    - Add a `'verification'` section after the `'password_timeout'` key (before the closing array bracket):

        ```php
        /*
        |--------------------------------------------------------------------------
        | Email Verification
        |--------------------------------------------------------------------------
        |
        | Here you may define the number of minutes that the email verification
        | link will be considered valid. This security feature keeps links
        | short-lived so they have less time to be guessed.
        |
        */

        'verification' => [
            'expire' => 1440,
        ],
        ```

- **Configure verification rate limiter** in `/Users/young/Nextcloud/dev/Itervel/config/fortify.php`:
    - Add `'verification' => 'verification',` to the `'limiters'` array (after `'two-factor' => 'two-factor'`):
        ```php
        'limiters' => [
            'login' => 'login',
            'two-factor' => 'two-factor',
            'verification' => 'verification',
        ],
        ```
- **Define the verification rate limiter** in `/Users/young/Nextcloud/dev/Itervel/app/Providers/FortifyServiceProvider.php`:
    - In the `configureRateLimiting()` method, add a new `RateLimiter::for('verification', ...)` block:
        ```php
        RateLimiter::for('verification', function (Request $request) {
            return Limit::perHour(3)->by($request->user()?->id ?: $request->ip());
        });
        ```
- Run `vendor/bin/pint --dirty` to format any changed PHP files
- Verify the changes compile: `php artisan route:list --name=verification` to confirm verification routes are registered with the custom limiter

### 2. Enhance Verify-Email Frontend Page

- **Task ID**: frontend-verify-email-page
- **Depends On**: none
- **Assigned To**: verification-frontend-dev
- **Agent Type**: coder
- **Parallel**: true
- Activate the `inertia-react-development` and `tailwindcss-development` skills
- Read the existing verify-email page at `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/auth/verify-email.tsx` and the auth type definitions at `/Users/young/Nextcloud/dev/Itervel/resources/js/types/auth.ts`
- Enhance `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/auth/verify-email.tsx` with the following changes:
    - Import `usePage` from `@inertiajs/react` to access shared auth data
    - Import `type SharedData` from `@/types`
    - Import the profile edit route from Wayfinder: `import { edit } from '@/routes/profile'`
    - Access the user's email: `const { auth } = usePage<SharedData>().props;`
    - Update the page description to include the user's email: `"We've sent a verification email to {auth.user.email}. Please click the link in the email to verify your account."`
    - Add an informational paragraph below the description explaining the verification requirement: "You need to verify your email address before you can create videos. You can still browse the platform and update your settings while unverified."
    - Below the success status message, add a subtle note: "You can request up to 3 verification emails per hour."
    - Add a link to profile settings where the user can change their email if it is wrong: `<TextLink href={edit()}>Update your email address</TextLink>`
    - Keep the existing resend button and logout link functionality intact
    - Follow the existing code style: use `@inertiajs/react` Form component, Wayfinder routes, and the existing layout/component patterns
- Run `npm run types` to verify TypeScript types compile correctly
- Run `npm run lint` to verify no linting issues

### 3. Write Comprehensive Verification Tests

- **Task ID**: write-verification-tests
- **Depends On**: backend-verification-config, frontend-verify-email-page
- **Assigned To**: verification-test-dev
- **Agent Type**: coder
- **Parallel**: false
- Activate the `pest-testing` skill
- **Expand `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Auth/EmailVerificationTest.php`** with additional tests. Keep all existing tests and add:
    - `test('verification link expires after 24 hours')`: Create an unverified user, generate a `temporarySignedRoute` with `now()->addMinutes(1440)`, then use `$this->travel(25)->hours()` (or `Carbon::setTestNow(now()->addHours(25))`) and attempt to visit the link. Assert the link is rejected (403 or redirect without Verified event). Restore the clock after.
    - `test('verification link is valid within 24 hours')`: Create an unverified user, generate a link, travel 23 hours forward, and assert the link still works (Verified event is dispatched).
    - `test('unverified user is redirected from verified routes')`: Create an unverified user, attempt to access `route('dashboard')` (which has `verified` middleware), assert redirect to `route('verification.notice')`.
    - `test('verified user can access verified routes')`: Create a (default) verified user, access `route('dashboard')`, assert 200 OK.
- **Expand `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Auth/VerificationNotificationTest.php`** with additional tests. Keep all existing tests and add:
    - `test('resend verification notification is rate limited to 3 per hour')`: Create an unverified user, send 3 POST requests to `route('verification.send')` (all should succeed), then send a 4th and assert it returns 429 (Too Many Requests).
    - `test('verification notification is queued')`: Use `Notification::fake()`, create an unverified user, call `$user->sendEmailVerificationNotification()`, assert that a `QueuedVerifyEmail` notification was sent (or use `Queue::fake()` and assert a job was pushed).
    - `test('resend rate limit resets after one hour')`: Send 3 requests, travel 1 hour forward, send another request, assert it succeeds.
- Follow the existing Pest test patterns from sibling files
- Use `User::factory()->unverified()->create()` for unverified users and `User::factory()->create()` for verified users
- Use `expect()` assertions following existing patterns
- Run the tests: `php artisan test tests/Feature/Auth/EmailVerificationTest.php tests/Feature/Auth/VerificationNotificationTest.php --compact`
- Ensure all tests pass

### 4. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: backend-verification-config, frontend-verify-email-page, write-verification-tests
- **Assigned To**: verification-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed below
- Verify email verification tests pass: `php artisan test tests/Feature/Auth/EmailVerificationTest.php --compact`
- Verify notification tests pass: `php artisan test tests/Feature/Auth/VerificationNotificationTest.php --compact`
- Verify all auth tests pass: `php artisan test tests/Feature/Auth --compact`
- Run the full test suite to ensure no regressions: `php artisan test --compact`
- Run TypeScript type checking: `npm run types`
- Run ESLint: `npm run lint`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Verify `config/auth.php` contains `'verification' => ['expire' => 1440]`
- Verify `config/fortify.php` has `'verification' => 'verification'` in the `limiters` array
- Verify `FortifyServiceProvider` defines `RateLimiter::for('verification', ...)` with `Limit::perHour(3)`
- Verify `App\Notifications\QueuedVerifyEmail` exists, extends `VerifyEmail`, and implements `ShouldQueue`
- Verify User model overrides `sendEmailVerificationNotification()` to use `QueuedVerifyEmail`
- Verify the verify-email page displays the user's email address
- Verify the verify-email page has rate limit messaging
- Verify the verify-email page has a link to profile settings
- Confirm all acceptance criteria are met

## Acceptance Criteria

- The verification email is sent automatically when a user registers (via the `MustVerifyEmail` contract enabled in E001-F001)
- The verification link in the email expires after exactly 24 hours (configured via `auth.verification.expire = 1440`)
- Users can request up to 3 resend verification emails per hour; the 4th request within an hour returns HTTP 429
- The verification notification is queued (uses `ShouldQueue`) for responsive registration UX
- Unverified users can log in and access routes with only `auth` middleware (e.g., profile settings)
- Unverified users are redirected to the email verification notice page when attempting to access routes with the `verified` middleware (e.g., dashboard)
- Clicking a valid, non-expired verification link verifies the user's account (`email_verified_at` is set)
- Clicking an expired verification link (older than 24 hours) does not verify the account
- Clicking a verification link with an invalid hash does not verify the account
- Already-verified users visiting the verification notice page are redirected to the dashboard
- Already-verified users clicking a verification link are redirected without re-dispatching the Verified event
- The verify-email page displays the user's email address
- The verify-email page explains that verification is required to create videos
- The verify-email page shows the rate limit information (3 per hour)
- The verify-email page provides a link to update the email address via profile settings
- All existing email verification tests continue to pass
- All new tests pass
- No regressions in the full test suite
- TypeScript types compile without errors
- ESLint and PHP Pint formatting checks pass

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run email verification tests
php artisan test tests/Feature/Auth/EmailVerificationTest.php --compact

# Run verification notification tests
php artisan test tests/Feature/Auth/VerificationNotificationTest.php --compact

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

- **Dependency on E001-F001**: This feature assumes E001-F001 has already been built, which means the User model already implements `MustVerifyEmail`. If for any reason E001-F001 has not been completed, the backend task must also uncomment the `MustVerifyEmail` import and add `implements MustVerifyEmail` to the User model class declaration.
- **The `auth.verification.expire` config key**: This is a standard Laravel config key read by `Illuminate\Auth\Notifications\VerifyEmail::verificationUrl()` (line 85 of the notification class). It defaults to 60 minutes if not set. Setting it to 1440 gives us 24 hours.
- **Fortify's throttle mechanism**: Fortify reads `config('fortify.limiters.verification', '6,1')` as the default. When we provide a named limiter (`'verification'`), Fortify uses `throttle:verification` which maps to our `RateLimiter::for('verification', ...)` definition. The named limiter takes precedence over the default `6,1` string.
- **Queued notification**: The `QueuedVerifyEmail` class is intentionally minimal -- it only adds `ShouldQueue` and `Queueable` to the existing `VerifyEmail` notification. This means all URL generation, email content, and expiration logic is inherited from the parent class without duplication.
- **30-second delivery guarantee**: The 30-second requirement from the epic refers to the complete pipeline (queue processing + email delivery). With the notification queued via the database queue driver, actual delivery time depends on queue worker processing speed and SMTP relay time. The code ensures the notification is dispatched quickly by queuing it rather than sending synchronously during the HTTP request.
- **Rate limiter per-user binding**: The rate limiter uses `$request->user()?->id` as the primary key because the verification routes require authentication. This ensures each user has their own independent rate limit. The `?: $request->ip()` fallback handles edge cases where the user might not be resolved.
- **Future video creation routes**: The epic states "Unverified users can log in but cannot create videos." Since video creation routes do not exist yet, this feature establishes the pattern by ensuring the `verified` middleware is documented and tested. Future features (E001-F010 Create Project, E001-F017 Topic Input, etc.) must apply `['auth', 'verified']` middleware to their routes.
- **All commands should be run inside the Docker container**. Use `make shell` to enter the container, or prefix with `docker compose exec app`.
- **The `profile.edit` route** is available for linking from the verify-email page. This route is in the `auth` middleware group (not `verified`), so unverified users can access it to update their email address. See `/Users/young/Nextcloud/dev/Itervel/routes/settings.php` line 12.

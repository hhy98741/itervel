# Prompt: Add Browser Tests to Existing Features

Add browser tests to this project using **Pest 4 Browser Plugin** (Playwright). This project is **Laravel 12 / Inertia v2 / React 19 / TypeScript / Pest 4**. There are no browser tests yet — you're writing them from scratch.

## Prerequisites

Before writing any code, check these and fix any that are missing:

1. **Pest Browser Plugin**: Run `composer show pestphp/pest-plugin-browser`. If not installed, tell me and stop.
2. **Playwright**: Run `npx playwright --version`. If not installed, tell me to run `npm install playwright@latest && npx playwright install` and stop.
3. **`.gitignore`**: Check if `tests/Browser/Screenshots` is in `.gitignore`. If not, add it.
4. **`tests/Pest.php`**: Check if there's already a `->in('Browser')` binding. If not, add this block:

```php
pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Browser');
```

## Pages and Routes

Here is every page in the project. You will write smoke tests for ALL of them.

### Public Pages (no auth)

| Page            | Route              | URL                | File                                          |
| --------------- | ------------------ | ------------------ | --------------------------------------------- |
| Welcome/Home    | `home`             | `/`                | `resources/js/pages/welcome.tsx`              |
| Login           | `login`            | `/login`           | `resources/js/pages/auth/login.tsx`           |
| Register        | `register`         | `/register`        | `resources/js/pages/auth/register.tsx`        |
| Forgot Password | `password.request` | `/forgot-password` | `resources/js/pages/auth/forgot-password.tsx` |

### Auth Pages (require authentication)

| Page                 | Route                 | URL                      | File                                               |
| -------------------- | --------------------- | ------------------------ | -------------------------------------------------- |
| Dashboard            | `dashboard`           | `/dashboard`             | `resources/js/pages/dashboard.tsx`                 |
| Email Verification   | `verification.notice` | `/email/verify`          | `resources/js/pages/auth/verify-email.tsx`         |
| Confirm Password     | `password.confirm`    | `/user/confirm-password` | `resources/js/pages/auth/confirm-password.tsx`     |
| Two-Factor Challenge | —                     | `/two-factor-challenge`  | `resources/js/pages/auth/two-factor-challenge.tsx` |
| Profile Settings     | `profile.edit`        | `/settings/profile`      | `resources/js/pages/settings/profile.tsx`          |
| Password Settings    | `user-password.edit`  | `/settings/password`     | `resources/js/pages/settings/password.tsx`         |
| Two-Factor Settings  | `two-factor.show`     | `/settings/two-factor`   | `resources/js/pages/settings/two-factor.tsx`       |
| Appearance Settings  | `appearance.edit`     | `/settings/appearance`   | `resources/js/pages/settings/appearance.tsx`       |

### Key Components with Interactive Behavior

| Component              | File                                                 | Interaction                                                   |
| ---------------------- | ---------------------------------------------------- | ------------------------------------------------------------- |
| Delete User (modal)    | `resources/js/components/delete-user.tsx`            | Opens confirmation dialog, requires password, deletes account |
| Two-Factor Setup Modal | `resources/js/components/two-factor-setup-modal.tsx` | Multi-step modal: QR code → enter TOTP code → confirm         |
| Appearance Tabs        | `resources/js/components/appearance-tabs.tsx`        | Theme switcher (light/dark/system)                            |

## Existing `data-test` Attributes

These already exist — do NOT add duplicates:

```
login-button                    — resources/js/pages/auth/login.tsx
register-user-button            — resources/js/pages/auth/register.tsx
email-password-reset-link-button — resources/js/pages/auth/forgot-password.tsx
reset-password-button           — resources/js/pages/auth/reset-password.tsx
confirm-password-button         — resources/js/pages/auth/confirm-password.tsx
update-profile-button           — resources/js/pages/settings/profile.tsx
update-password-button          — resources/js/pages/settings/password.tsx
delete-user-button              — resources/js/components/delete-user.tsx
confirm-delete-user-button      — resources/js/components/delete-user.tsx
logout-button                   — resources/js/components/user-menu-content.tsx
sidebar-menu-button             — resources/js/components/nav-user.tsx
```

## Missing `data-test` Attributes — Add These

Add these `data-test` attributes to the following components. Do NOT touch files that already have all their needed attributes.

### `resources/js/pages/auth/verify-email.tsx`

- `data-test="resend-verification-button"` on the "Resend verification email" `<Button>`

### `resources/js/pages/settings/two-factor.tsx`

- `data-test="enable-2fa-button"` on the "Enable 2FA" `<Button>` (inside the `<Form>`)
- `data-test="disable-2fa-button"` on the "Disable 2FA" `<Button>`
- `data-test="continue-2fa-setup-button"` on the "Continue Setup" `<Button>`

### `resources/js/components/two-factor-setup-modal.tsx`

Read this file, then add:

- `data-test="2fa-confirm-code-input"` on the TOTP code input
- `data-test="2fa-confirm-button"` on the confirm/submit button
- `data-test="2fa-cancel-button"` on the cancel/close button

### `resources/js/pages/auth/two-factor-challenge.tsx`

- `data-test="2fa-challenge-submit-button"` on the "Continue" `<Button>`
- `data-test="2fa-toggle-recovery-button"` on the toggle recovery mode `<button>`

### `resources/js/components/appearance-tabs.tsx`

Read this file, then add:

- `data-test="theme-light"` on the light theme button/card
- `data-test="theme-dark"` on the dark theme button/card
- `data-test="theme-system"` on the system theme button/card

## Factory States Available

The `User` factory has these states — use them in tests:

```php
User::factory()->create()                    // default verified user
User::factory()->unverified()->create()      // email_verified_at = null
User::factory()->withTwoFactor()->create()   // has confirmed 2FA enabled
```

## Browser Tests to Write

Create **3 test files**. Use flat `test()` functions, no `describe()` blocks.

### File 1: `tests/Browser/AuthTest.php`

```php
<?php

use App\Models\User;

// --- Smoke Tests ---

test('public auth pages load without errors', function () {
    visit(['/', '/login', '/register', '/forgot-password'])
        ->assertNoSmoke();
});

test('verify email page loads without errors', function () {
    $this->actingAs(User::factory()->unverified()->create());

    visit('/email/verify')->assertNoSmoke();
});

// --- Dark Mode ---

test('login page renders correctly in dark mode', function () {
    visit('/login')->inDarkMode()->assertNoSmoke();
});

// --- Interactive Flows ---

test('user can register an account', function () {
    $page = visit('/register');

    $page->fill('#name', 'Test User')
         ->fill('#email', 'newuser@example.com')
         ->fill('#password', 'Password123!')
         ->fill('#password_confirmation', 'Password123!')
         ->click('@register-user-button')
         ->assertPathIs('/email/verify');

    $this->assertAuthenticated();
});

test('user can log in', function () {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => 'password',
    ]);

    $page = visit('/login');

    $page->fill('#email', 'test@example.com')
         ->fill('#password', 'password')
         ->click('@login-button')
         ->assertPathIs('/dashboard');

    $this->assertAuthenticated();
});

test('user can request a password reset link', function () {
    User::factory()->create(['email' => 'test@example.com']);

    $page = visit('/forgot-password');

    $page->fill('#email', 'test@example.com')
         ->click('@email-password-reset-link-button')
         ->assertSee('We have emailed your password reset link');
});
```

**Notes**:

- The register test uses `#name`, `#email`, `#password`, `#password_confirmation` — these are the `id` attributes on the inputs. Use `#id` selectors for form fields since they don't have `data-test` attributes and `id` is equally stable.
- The register form may have a ToS checkbox depending on what E002-F001 added. Read `resources/js/pages/auth/register.tsx` to check — if there's a ToS checkbox, add `.check('#tos')` or `.click('@tos-checkbox')` before submitting.
- The password reset test just checks the success message appears — it doesn't test the actual reset flow (that requires email which feature tests already cover).

### File 2: `tests/Browser/SettingsTest.php`

```php
<?php

use App\Models\User;

// --- Smoke Tests ---

test('settings pages load without errors', function () {
    $this->actingAs(User::factory()->create());

    visit([
        '/settings/profile',
        '/settings/password',
        '/settings/two-factor',
        '/settings/appearance',
    ])->assertNoSmoke();
});

// --- Dark Mode ---

test('profile settings renders correctly in dark mode', function () {
    $this->actingAs(User::factory()->create());

    visit('/settings/profile')->inDarkMode()->assertNoSmoke();
});

// --- Interactive Flows ---

test('user can update their profile name', function () {
    $user = User::factory()->create(['name' => 'Original Name']);

    $this->actingAs($user);

    $page = visit('/settings/profile');

    $page->clear('#name')
         ->fill('#name', 'Updated Name')
         ->click('@update-profile-button')
         ->assertSee('Saved');
});

test('user can update their password', function () {
    $user = User::factory()->create(['password' => 'password']);

    $this->actingAs($user);

    $page = visit('/settings/password');

    $page->fill('#current_password', 'password')
         ->fill('#password', 'NewPassword123!')
         ->fill('#password_confirmation', 'NewPassword123!')
         ->click('@update-password-button')
         ->assertSee('Saved');
});

test('user can switch appearance theme', function () {
    $this->actingAs(User::factory()->create());

    $page = visit('/settings/appearance');

    $page->click('@theme-dark')
         ->assertNoSmoke();

    $page->click('@theme-light')
         ->assertNoSmoke();
});
```

### File 3: `tests/Browser/DashboardTest.php`

```php
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
```

## Important Conventions

1. **`@selector`** maps to `data-test="selector"` — e.g., `->click('@login-button')` finds `data-test="login-button"`
2. **`#selector`** maps to `id="selector"` — use for form inputs that have `id` attributes
3. **`$this->actingAs()` BEFORE `visit()`** for authenticated pages
4. **Desktop Chrome only** — no `->on()->mobile()` or `->firefox()`
5. **`assertNoSmoke()`** = `assertNoJavaScriptErrors()` + `assertNoConsoleLogs()`
6. **Batch smoke tests** into single `visit([...urls])` calls to minimize browser launches
7. **Do NOT duplicate** what feature tests in `tests/Feature/` already cover (validation, authorization, redirects, rate limiting)
8. The 2FA flow is complex (enable → scan QR → enter TOTP code → confirm). Do NOT write a browser test for it — feature tests already cover the full flow. Just smoke test the page.
9. Run all commands inside the Docker container (`make shell` or `docker compose exec app`)

## Execution Order

1. Check prerequisites (Pest plugin, Playwright, `.gitignore`, `Pest.php`)
2. Add the missing `data-test` attributes listed above
3. Run `npm run build` to recompile after frontend changes
4. Create `tests/Browser/` directory if it doesn't exist
5. Write the 3 test files
6. Run browser tests: `php artisan test tests/Browser --compact`
7. Fix any failures
8. Run full suite to check regressions: `php artisan test --compact`
9. Run `vendor/bin/pint --dirty` on any PHP files you created
10. Report what was done

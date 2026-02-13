# Feature: Password Change

**Epic**: E003-user-profile-and-account-management.md
**Feature**: E003-F002
**Epic depends on**: E002-user-authentication.md
**Feature depends on**: E002-F003

## Task Description

Password Change allows logged-in users to change their current password from the settings area of the application. This is a standard account security feature that requires users to verify their identity by entering their current password before setting a new one.

**What it does**: Allows logged-in users to change their current password.

**Expected outcome**: The user enters their current password for verification, then sets a new password. The password is updated immediately.

The existing password change infrastructure is already substantial and fully functional:

- **Backend**: `PasswordController` at `app/Http/Controllers/Settings/PasswordController.php` handles both the settings page rendering (`edit()`) and password update (`update()`). The controller uses a `PasswordUpdateRequest` form request for validation.
- **Validation**: `PasswordUpdateRequest` at `app/Http/Requests/Settings/PasswordUpdateRequest.php` uses the `PasswordValidationRules` trait (from `app/Concerns/PasswordValidationRules.php`) which enforces: `current_password` must be correct (via Laravel's `current_password` validation rule), and `password` must meet `Password::default()` requirements and be confirmed.
- **Password defaults**: `AppServiceProvider` configures `Password::defaults()` -- in production: min 12 chars, mixed case, letters, numbers, symbols, uncompromised. In non-production: null (no extra requirements beyond the base).
- **Frontend**: `resources/js/pages/settings/password.tsx` renders a complete password change form with current password, new password, and confirm password fields. It uses Inertia's `<Form>` component with Wayfinder-generated actions (`PasswordController.update.form()`). The form shows inline validation errors, resets error fields on error, resets all fields on success, and shows a "Saved" confirmation message.
- **Routes**: `routes/settings.php` defines GET `/settings/password` (`user-password.edit`) and PUT `/settings/password` (`user-password.update`). Both require `auth` and `verified` middleware. The update route also has `throttle:6,1` middleware (6 requests per minute).
- **Settings navigation**: The settings sidebar (`resources/js/layouts/settings/layout.tsx`) already includes a "Password" link pointing to the password settings page.
- **Tests**: `tests/Feature/Settings/PasswordUpdateTest.php` has 3 tests covering page rendering, successful password update, and incorrect current password rejection.

**Dependency on E001-F003 (User Login)**: The login feature configures Fortify authentication, session management, and rate limiting. The password change feature requires users to be authenticated (which requires login to work). After E001-F003 is built, the session lifetime will be 24 hours and the login rate limiter will use a 15-minute lockout window. These changes do not affect the password change feature directly, but the password change tests must use authenticated users (via `actingAs()`), which depends on the auth system being properly configured.

Since the full password change infrastructure already exists, this feature focuses on:

1. Verifying the existing implementation is correct and complete
2. Expanding the test suite to comprehensively cover all password change scenarios and edge cases
3. Ensuring no code or formatting issues exist

## Objective

Ensure the password change feature is complete, well-tested, and production-ready. The existing implementation should be validated against the feature requirements, and the test suite should be expanded to comprehensively cover all scenarios including validation rules, edge cases, authentication guards, throttling, and success confirmation.

## Solution Approach

### 1. Validate Existing Backend Implementation

The existing `PasswordController::update()` method receives a `PasswordUpdateRequest`, then updates the user's password:

```php
public function update(PasswordUpdateRequest $request): RedirectResponse
{
    $request->user()->update([
        'password' => $request->password,
    ]);

    return back();
}
```

The `PasswordUpdateRequest` validates:

- `current_password`: required, string, must match the user's current password (via `current_password` rule)
- `password`: required, string, meets `Password::default()` requirements, confirmed (requires `password_confirmation` field)

The `password` attribute has the `hashed` cast on the User model, so it is automatically hashed when set via mass assignment. This is correct.

The route has `throttle:6,1` middleware which limits password update attempts to 6 per minute per user, providing protection against brute-force current password guessing.

No backend changes are needed. The implementation is complete.

### 2. Validate Existing Frontend Implementation

The password page at `resources/js/pages/settings/password.tsx` renders:

- A "Current password" field (`current_password`)
- A "New password" field (`password`)
- A "Confirm password" field (`password_confirmation`)
- A "Save password" button with disabled state during processing
- A "Saved" confirmation message shown via `<Transition>` after successful submission
- Error messages displayed inline via `<InputError>` components
- Form resets error fields on validation errors and all fields on success
- Uses `preserveScroll: true` to prevent page jumping

No frontend changes are needed. The implementation is complete.

### 3. Expand Test Suite

The existing 3 tests cover:

1. Password settings page renders (GET returns 200)
2. Password can be updated (PUT with correct current + valid new password)
3. Incorrect current password is rejected (PUT with wrong current password)

Additional tests needed to comprehensively cover the feature:

- Guests cannot access the password settings page (redirect to login)
- Guests cannot update their password (redirect to login)
- New password must be confirmed (missing `password_confirmation`)
- New password confirmation must match
- Current password field is required
- New password field is required
- Password update is throttled (6 attempts per minute)
- Password is actually hashed in the database (not stored as plaintext)
- User can log in with the new password after changing it
- Old password no longer works after changing it

### 4. Run Formatting and Validation

After expanding tests, run Pint to ensure PHP formatting compliance and run the full test suite to verify no regressions.

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/PasswordController.php` -- The password settings controller. Has `edit()` (renders the Inertia page) and `update()` (validates and updates the password). No changes needed, but must be read and understood for test coverage.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/Settings/PasswordUpdateRequest.php` -- Form request for password updates. Validates `current_password` and `password` using the `PasswordValidationRules` trait. No changes needed, but must be understood for writing validation tests.
- `/Users/young/Nextcloud/dev/Itervel/app/Concerns/PasswordValidationRules.php` -- Shared trait providing `passwordRules()` and `currentPasswordRules()`. Used by both `PasswordUpdateRequest` and `ResetUserPassword`. No changes needed, but referenced for understanding validation rules.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/password.tsx` -- The frontend password settings page with current password, new password, confirm password fields. No changes needed, but referenced for understanding the form fields and behavior.
- `/Users/young/Nextcloud/dev/Itervel/routes/settings.php` -- Routes for the password settings page. Defines GET and PUT `/settings/password` with `auth`, `verified`, and `throttle:6,1` middleware. No changes needed, but referenced for understanding route configuration and writing tests.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/PasswordUpdateTest.php` -- Existing password update tests (3 tests). Must be expanded with additional test cases covering all scenarios.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/ProfileUpdateTest.php` -- Sibling settings test file. Referenced for consistent test patterns and conventions.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` -- User model with `hashed` cast on `password`. Referenced for understanding how password hashing works. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/app/Providers/AppServiceProvider.php` -- Configures `Password::defaults()` for production vs non-production. Referenced for understanding password strength requirements. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` -- User factory with default password `'password'` and verified email. Referenced for test setup. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration. All Feature tests automatically use `RefreshDatabase`. Referenced for understanding test setup. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/actions/App/Http/Controllers/Settings/PasswordController.ts` -- Wayfinder-generated controller action functions. Auto-generated, no changes needed. Referenced for understanding frontend form actions.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/routes/user-password/index.ts` -- Wayfinder-generated named route functions. Auto-generated, no changes needed. Referenced for understanding route names used in tests.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/settings/layout.tsx` -- Settings layout with sidebar navigation including "Password" link. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/config/fortify.php` -- Fortify configuration. Referenced for understanding the auth features and guards. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/bootstrap/app.php` -- Application bootstrap with middleware configuration. Referenced for understanding middleware stack. No changes needed.

### New Files

No new files need to be created. All changes are expansions to the existing test file.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Test Developer
    - Name: password-test-dev
    - Role: Expands the password update test suite to comprehensively cover all password change scenarios including validation rules, authentication guards, throttling, edge cases, and success behavior
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: password-reviewer
    - Role: Validates the complete password change feature against acceptance criteria, runs all tests, checks types, runs linting and formatting, verifies existing implementation is correct
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Expand Password Change Test Suite

- **Task ID**: expand-password-tests
- **Depends On**: none
- **Assigned To**: password-test-dev
- **Agent Type**: coder
- **Parallel**: true
- Read the existing `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/PasswordUpdateTest.php` to understand current test patterns and what is already covered
- Read the sibling test file `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/ProfileUpdateTest.php` to follow consistent test conventions (e.g., use of `$this->actingAs()`, `route()` helper, `expect()` assertions)
- Read `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/Settings/PasswordUpdateRequest.php` and `/Users/young/Nextcloud/dev/Itervel/app/Concerns/PasswordValidationRules.php` to understand all validation rules that need testing
- Read `/Users/young/Nextcloud/dev/Itervel/routes/settings.php` to understand the route names (`user-password.edit`, `user-password.update`), HTTP methods (GET, PUT), and middleware (`auth`, `verified`, `throttle:6,1`)
- Read `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/PasswordController.php` to understand the controller behavior
- **Keep all 3 existing tests unchanged** -- they are correct and should remain:
    - `test('password update page is displayed')` -- verifies GET returns 200
    - `test('password can be updated')` -- verifies PUT with correct data updates password
    - `test('correct password must be provided to update password')` -- verifies wrong current password is rejected
- **Add the following new tests** to `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/PasswordUpdateTest.php`:

- `test('guests cannot access password settings page')` -- As a guest (not authenticated), GET the password settings page. Assert redirect to the login page.

    ```php
    test('guests cannot access password settings page', function () {
        $response = $this->get(route('user-password.edit'));

        $response->assertRedirect(route('login'));
    });
    ```

- `test('guests cannot update password')` -- As a guest, PUT to the password update route. Assert redirect to the login page.

    ```php
    test('guests cannot update password', function () {
        $response = $this->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertRedirect(route('login'));
    });
    ```

- `test('current password is required')` -- Authenticated user submits without `current_password` field. Assert session has errors on `current_password`.

    ```php
    test('current password is required', function () {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('user-password.edit'))
            ->put(route('user-password.update'), [
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response->assertSessionHasErrors('current_password');
    });
    ```

- `test('new password is required')` -- Authenticated user submits without `password` field. Assert session has errors on `password`.

    ```php
    test('new password is required', function () {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('user-password.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'password',
            ]);

        $response->assertSessionHasErrors('password');
    });
    ```

- `test('new password must be confirmed')` -- Authenticated user submits with new password but without `password_confirmation`. Assert session has errors on `password`.

    ```php
    test('new password must be confirmed', function () {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('user-password.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
            ]);

        $response->assertSessionHasErrors('password');
    });
    ```

- `test('new password confirmation must match')` -- Authenticated user submits with mismatched `password` and `password_confirmation`. Assert session has errors on `password`.

    ```php
    test('new password confirmation must match', function () {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('user-password.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'different-password',
            ]);

        $response->assertSessionHasErrors('password');
    });
    ```

- `test('password is hashed when stored')` -- After a successful password update, verify the stored password is hashed (not plaintext). Use `Hash::check()` to verify and also confirm the raw database value is not equal to the plaintext password.

    ```php
    test('password is hashed when stored', function () {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ]);

        $user->refresh();

        expect(Hash::check('new-secure-password', $user->password))->toBeTrue();
        expect($user->password)->not->toBe('new-secure-password');
    });
    ```

- `test('old password no longer works after password change')` -- After changing the password, attempt to change it again using the old password as `current_password`. Assert it fails with session errors on `current_password`.

    ```php
    test('old password no longer works after password change', function () {
        $user = User::factory()->create();

        // Change password
        $this
            ->actingAs($user)
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        // Try to change again with the old password
        $response = $this
            ->actingAs($user)
            ->from(route('user-password.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'another-password',
                'password_confirmation' => 'another-password',
            ]);

        $response->assertSessionHasErrors('current_password');
    });
    ```

- `test('password update redirects back to password settings')` -- After a successful password update, assert the redirect goes back to the password settings page with no errors.

    ```php
    test('password update redirects back to password settings', function () {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('user-password.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('user-password.edit'));
    });
    ```

- Ensure the `use` imports at the top of the test file include `Hash` from `Illuminate\Support\Facades\Hash` (it is already imported in the existing file)
- Run all password update tests: `php artisan test tests/Feature/Settings/PasswordUpdateTest.php --compact`
- Ensure all tests pass. If any test fails, debug and fix until all pass
- Run `vendor/bin/pint --dirty` to fix any formatting issues in the test file

### 2. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: expand-password-tests
- **Assigned To**: password-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed below
- Verify password update tests pass: `php artisan test tests/Feature/Settings/PasswordUpdateTest.php --compact`
- Verify all settings tests pass: `php artisan test tests/Feature/Settings --compact`
- Run the full test suite to ensure no regressions: `php artisan test --compact`
- Run TypeScript type checking: `npm run types`
- Run ESLint: `npm run lint`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Verify in `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/PasswordController.php` that:
    - The `edit()` method renders `settings/password` via Inertia
    - The `update()` method uses `PasswordUpdateRequest` for validation
    - The `update()` method updates the user's password and redirects back
- Verify in `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/Settings/PasswordUpdateRequest.php` that:
    - The `current_password` rule includes `current_password` validation
    - The `password` rule includes `Password::default()` and `confirmed`
- Verify in `/Users/young/Nextcloud/dev/Itervel/routes/settings.php` that:
    - GET `/settings/password` has `auth` and `verified` middleware
    - PUT `/settings/password` has `auth`, `verified`, and `throttle:6,1` middleware
- Verify in `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/password.tsx` that:
    - The form includes `current_password`, `password`, and `password_confirmation` fields
    - The form uses `PasswordController.update.form()` for the action
    - Error messages display for each field
    - A success confirmation message appears after save
    - The form resets fields on success and error fields on error
- Verify the test file covers: page rendering, successful update, wrong current password, guest access denial (both GET and PUT), required fields (current password, new password), password confirmation (missing and mismatched), password hashing, old password invalidation, redirect behavior
- Confirm all acceptance criteria are met

## Acceptance Criteria

- Authenticated users can access the password settings page at `/settings/password`
- Users must provide their correct current password to change their password
- The new password must be confirmed (password and password_confirmation must match)
- The password is updated immediately upon successful submission
- The password is securely hashed when stored (not stored as plaintext)
- The old password no longer works after a successful change
- Appropriate validation errors are displayed for: missing current password, incorrect current password, missing new password, missing password confirmation, mismatched password confirmation
- After a successful password update, the user is redirected back to the password settings page
- A "Saved" confirmation message appears briefly after successful update (frontend behavior, verified by existing implementation)
- Guests (unauthenticated users) are redirected to login when attempting to access password settings
- The password update route is throttled (6 requests per minute)
- All password update tests pass
- All existing tests pass without regressions
- PHP code passes Pint formatting
- TypeScript type checking passes
- ESLint linting passes

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run password update tests
php artisan test tests/Feature/Settings/PasswordUpdateTest.php --compact

# Run all settings tests
php artisan test tests/Feature/Settings --compact

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

- The password change feature is already fully implemented in the codebase. The backend controller, form request, validation rules, frontend page, routes, and settings navigation are all in place and functional. The primary work for this feature is expanding the test suite to provide comprehensive coverage.
- The `PasswordValidationRules` trait is shared between `PasswordUpdateRequest` (password change) and `ResetUserPassword` (password reset via email). Any changes to validation rules would affect both features. No changes are needed for this feature.
- The `Password::defaults()` configuration in `AppServiceProvider` means password strength requirements differ between environments. In production, passwords must be at least 12 characters with mixed case, letters, numbers, symbols, and must not be in a known breach database. In non-production (testing), there are no extra requirements beyond the base Laravel defaults. Tests run in non-production mode, so they can use simple passwords like `'new-password'`.
- The `hashed` cast on the User model's `password` attribute means the password is automatically hashed when assigned via mass assignment (`$user->update(['password' => ...])`) or property assignment. The controller does not need to manually hash the password.
- The route uses `throttle:6,1` middleware which limits the password update endpoint to 6 requests per minute per user/IP. This protects against brute-force attempts to guess the current password.
- The frontend form uses Inertia's `<Form>` component (not `useForm`), which provides built-in support for `processing` state, `errors`, `recentlySuccessful`, `resetOnError`, and `resetOnSuccess`. This is a pattern used throughout the settings pages.
- All commands should be run inside the Docker container. Use `make shell` to enter the container, or prefix with `docker compose exec app`.

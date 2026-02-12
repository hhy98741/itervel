# Feature: Terms of Service Acceptance

**Epic**: E001-ai-powered-faceless-video-creation-platform.md
**Feature**: E001-F073
**Dependencies**: E001-F001

## Task Description

Terms of Service Acceptance requires users to explicitly agree to the Terms of Service before creating an account. This is both a UX requirement (users must check a checkbox) and a compliance requirement (the system must record when the user accepted the terms).

**What it does**: Requires users to accept the Terms of Service before creating an account.

**Expected outcome**: A checkbox must be checked before the registration form can be submitted. If unchecked, the user sees an error message.

This feature depends on E001-F001 (User Registration), which will have already implemented:

- A `terms` checkbox on the registration form using the Radix `Checkbox` component with `name="terms"`
- Backend validation with `'terms' => ['accepted']` in `CreateNewUser`
- An `InputError` component displaying the `terms` validation error
- A placeholder `#` link for "Terms of Service" text

E001-F073 builds on that foundation by:

1. **Adding a `terms_accepted_at` timestamp** to the `users` table and recording the exact moment a user accepted the Terms of Service during registration. This is essential for legal compliance and audit trails.
2. **Creating a Terms of Service page** at `/terms-of-service` so the registration form's "Terms of Service" link points to a real page rather than a placeholder `#` anchor.
3. **Updating the registration form link** to navigate to the actual Terms of Service page.
4. **Writing focused tests** that verify the ToS acceptance behavior: the timestamp is recorded, the link works, and validation errors are properly surfaced.

## Objective

Complete the Terms of Service acceptance flow by recording acceptance timestamps for compliance, creating a viewable Terms of Service page, linking the registration form to it, and verifying the complete flow with dedicated tests.

## Solution Approach

### 1. Database Migration: Add `terms_accepted_at` column

Create a migration to add a nullable `timestamp` column `terms_accepted_at` to the `users` table. This records when the user accepted the Terms of Service, providing an audit trail for legal compliance. It is nullable because existing users (created before this feature) may not have explicitly accepted terms.

```php
Schema::table('users', function (Blueprint $table) {
    $table->timestamp('terms_accepted_at')->nullable()->after('email_verified_at');
});
```

### 2. Update User Model

Add `terms_accepted_at` to the `$fillable` array and the `casts()` method on the User model:

```php
protected $fillable = [
    'name',
    'email',
    'password',
    'video_credits',
    'terms_accepted_at',
];

protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'terms_accepted_at' => 'datetime',
        'password' => 'hashed',
        'two_factor_confirmed_at' => 'datetime',
    ];
}
```

Note: By the time this feature is built, E001-F001 will have already added `video_credits` to `$fillable`. The model update here only adds `terms_accepted_at`.

### 3. Update `CreateNewUser` Action

Modify `CreateNewUser::create()` to record the acceptance timestamp. E001-F001 will have already added the `'terms' => ['accepted']` validation rule. This feature adds the timestamp recording in the `User::create()` call:

```php
return User::create([
    'name' => $input['name'],
    'email' => $input['email'],
    'password' => $input['password'],
    'video_credits' => 1,
    'terms_accepted_at' => now(),
]);
```

### 4. Update UserFactory

Add `terms_accepted_at` to the factory's default state so tests have a consistent value. For the default factory state, set it to `now()` (since factory-created users represent "normal" registered users). Add a factory state `withoutTermsAcceptance()` for testing edge cases:

```php
public function definition(): array
{
    return [
        // ... existing fields ...
        'terms_accepted_at' => now(),
    ];
}

public function withoutTermsAcceptance(): static
{
    return $this->state(fn (array $attributes) => [
        'terms_accepted_at' => null,
    ]);
}
```

### 5. Create Terms of Service Page

Create a simple Inertia page at `resources/js/pages/terms-of-service.tsx` that displays the Terms of Service content. This is a public page (no authentication required) using the `AuthLayout` for consistent styling with other unauthenticated pages, or a minimal layout. The content will be placeholder text for now, to be replaced with actual legal text later.

Add a route in `routes/web.php`:

```php
Route::get('terms-of-service', function () {
    return Inertia::render('terms-of-service');
})->name('terms.show');
```

### 6. Update Registration Form Link

Update `resources/js/pages/auth/register.tsx` to change the ToS link from `#` to the actual Terms of Service page route. Import the Wayfinder-generated named route and use it:

```tsx
import { termsShow } from '@/routes';
// or use the route name pattern depending on what Wayfinder generates

<TextLink href={termsShow()}>Terms of Service</TextLink>;
```

Since `TextLink` wraps Inertia's `<Link>` component, this will be a client-side navigation. However, to allow the ToS page to open without losing form state, consider using a standard `<a>` tag with `target="_blank"` so the ToS page opens in a new tab. This prevents users from losing their partially filled registration form.

### 7. Update TypeScript Types

Add `terms_accepted_at` to the `User` type in `resources/js/types/auth.ts`:

```ts
export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    terms_accepted_at: string | null;
    video_credits: number;
    // ... rest
};
```

Note: E001-F001 will have already added `video_credits`. This feature adds `terms_accepted_at`.

### 8. Write Dedicated Tests

Create a focused test file `tests/Feature/Auth/TermsOfServiceAcceptanceTest.php` that tests:

- The Terms of Service page renders at `/terms-of-service`
- Registration records `terms_accepted_at` as a non-null timestamp
- Registration without accepting terms fails with a validation error on the `terms` field
- Registration with `terms` set to falsy values (`''`, `'off'`, `'false'`, `0`) fails validation
- The `terms_accepted_at` timestamp is close to the current time (within a few seconds)

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Actions/Fortify/CreateNewUser.php` -- The Fortify action that validates and creates users. Must be updated to set `terms_accepted_at => now()` in the `User::create()` call. By the time this feature runs, E001-F001 will have already added `'terms' => ['accepted']` validation.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` -- The User Eloquent model. Must add `terms_accepted_at` to `$fillable` and `casts()`.
- `/Users/young/Nextcloud/dev/Itervel/app/Concerns/PasswordValidationRules.php` -- Trait for password validation. No changes needed, referenced for context.
- `/Users/young/Nextcloud/dev/Itervel/app/Concerns/ProfileValidationRules.php` -- Trait for profile validation. No changes needed, referenced for context.
- `/Users/young/Nextcloud/dev/Itervel/app/Providers/FortifyServiceProvider.php` -- Configures Fortify views and actions. No changes needed, referenced for context.
- `/Users/young/Nextcloud/dev/Itervel/app/Providers/AppServiceProvider.php` -- Configures app defaults. No changes needed, referenced for context.
- `/Users/young/Nextcloud/dev/Itervel/config/fortify.php` -- Fortify feature flags. No changes needed, referenced for context.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/auth/register.tsx` -- The registration form React component. Must update the ToS link from `#` placeholder to the actual Terms of Service page route, opened in a new tab.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/auth/login.tsx` -- The login page. Referenced as a pattern for Checkbox and Link usage.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/checkbox.tsx` -- The Radix Checkbox component. No changes needed, referenced for understanding how the ToS checkbox works.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/input-error.tsx` -- Error display component. No changes needed, already used for `terms` error in the register form (from E001-F001).
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/text-link.tsx` -- Styled Inertia Link component. Referenced for understanding the ToS link pattern.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/auth.ts` -- TypeScript types for User and Auth. Must add `terms_accepted_at` to the `User` type.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/auth-layout.tsx` -- Auth layout wrapper. Referenced as the layout to use for the Terms of Service page, or a simpler guest layout.
- `/Users/young/Nextcloud/dev/Itervel/database/migrations/0001_01_01_000000_create_users_table.php` -- Original users migration. Referenced for understanding current schema.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` -- User factory. Must add `terms_accepted_at` to the default state and a `withoutTermsAcceptance()` state.
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Main web routes file. Must add a route for the Terms of Service page.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Auth/RegistrationTest.php` -- Existing registration tests. Referenced for patterns; the new ToS tests will be in a separate file.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration. All Feature tests use `RefreshDatabase` automatically.
- `/Users/young/Nextcloud/dev/Itervel/specs/features/E001-F001-user-registration.md` -- The E001-F001 plan. Referenced to understand what will already be built before this feature runs.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/database/migrations/YYYY_MM_DD_HHMMSS_add_terms_accepted_at_to_users_table.php` -- Migration to add the `terms_accepted_at` nullable timestamp column to the users table. The exact filename will be generated by `php artisan make:migration`.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/terms-of-service.tsx` -- A public page displaying the Terms of Service content. Uses a guest-accessible layout.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Auth/TermsOfServiceAcceptanceTest.php` -- Dedicated test file for Terms of Service acceptance behavior during registration.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: tos-backend-dev
    - Role: Creates the database migration for `terms_accepted_at`, updates the User model (fillable + casts), updates CreateNewUser to record the timestamp, updates UserFactory with the new field and factory state, adds the Terms of Service route, and runs PHP formatting
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: tos-frontend-dev
    - Role: Creates the Terms of Service page component, updates the registration form to link to the real ToS page (opening in a new tab), and updates TypeScript types
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: tos-test-dev
    - Role: Writes dedicated tests for Terms of Service acceptance including timestamp recording, validation error scenarios, and the ToS page rendering
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: tos-reviewer
    - Role: Validates the complete feature against acceptance criteria, runs all tests, checks types, runs linting and formatting, verifies the migration runs cleanly
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

### 1. Create Migration, Update Backend Models, and Add Route

- **Task ID**: backend-migration-models-route
- **Depends On**: none
- **Assigned To**: tos-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- Run `php artisan make:migration add_terms_accepted_at_to_users_table --table=users --no-interaction` to create the migration file
- In the migration's `up()` method, add: `$table->timestamp('terms_accepted_at')->nullable()->after('email_verified_at');`
- In the migration's `down()` method, add: `$table->dropColumn('terms_accepted_at');`
- Update `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php`:
    - Add `'terms_accepted_at'` to the `$fillable` array. Note: E001-F001 will have already added `video_credits`, so the fillable array should already contain `['name', 'email', 'password', 'video_credits']`. Add `'terms_accepted_at'` after `'video_credits'`.
    - Add `'terms_accepted_at' => 'datetime'` to the `casts()` method return array
- Update `/Users/young/Nextcloud/dev/Itervel/app/Actions/Fortify/CreateNewUser.php`:
    - In the `User::create()` call, add `'terms_accepted_at' => now(),` to the array. Note: E001-F001 will have already added `'video_credits' => 1` to this array, so add the new field after it.
- Update `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php`:
    - Add `'terms_accepted_at' => now(),` to the `definition()` return array
    - Add a new factory state method `withoutTermsAcceptance()` that sets `'terms_accepted_at' => null`
- Update `/Users/young/Nextcloud/dev/Itervel/routes/web.php`:
    - Add `use Inertia\Inertia;` import at the top if not already present (it is already imported)
    - Add a new route before the dashboard route: `Route::get('terms-of-service', fn () => Inertia::render('terms-of-service'))->name('terms.show');`
    - This route should be public (no auth middleware) so both guests and authenticated users can access it
- Run the migration: `php artisan migrate --no-interaction`
- Run `vendor/bin/pint --dirty` to fix any formatting issues

### 2. Create Terms of Service Page and Update Registration Form

- **Task ID**: frontend-tos-page-and-form
- **Depends On**: none
- **Assigned To**: tos-frontend-dev
- **Agent Type**: coder
- **Parallel**: true
- Update `/Users/young/Nextcloud/dev/Itervel/resources/js/types/auth.ts`:
    - Add `terms_accepted_at: string | null;` to the `User` type. Note: E001-F001 will have already added `video_credits: number;`. Place `terms_accepted_at` after `email_verified_at` and before `video_credits`.
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/terms-of-service.tsx`:
    - Create a new page component that renders the Terms of Service content
    - Use the `Head` component from `@inertiajs/react` with `<Head title="Terms of Service" />`
    - Use a simple centered layout similar to the auth pages. Import `AuthLayout` from `@/layouts/auth-layout` and use it with `title="Terms of Service"` and `description="Please read our terms carefully"`
    - The body should contain placeholder legal content wrapped in prose-styled HTML. Use a `<div className="prose dark:prose-invert max-w-none text-sm">` or similar Tailwind typography styling for readable text formatting
    - Include sections: Introduction, Account Terms, Acceptable Use, Intellectual Property, Termination, Limitation of Liability, and Changes to Terms
    - Each section should have a heading (`<h2>`) and paragraph text (`<p>`) with placeholder content that can be replaced with real legal text later
    - At the bottom, include a link back to registration: "Ready to create an account?" with a `TextLink` to the register route
- Update `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/auth/register.tsx`:
    - The ToS checkbox and error display will already exist from E001-F001. The only change needed is updating the link target.
    - Find the "Terms of Service" `TextLink` (which E001-F001 will have added with `href="#"`) and change it to use a standard `<a>` tag (not an Inertia Link) with `href="/terms-of-service"` and `target="_blank"` and `rel="noopener noreferrer"`. This opens the ToS in a new tab so users do not lose their registration form state.
    - Style the `<a>` tag to match the `TextLink` styling: `className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"`
    - Alternatively, since `TextLink` wraps Inertia's `Link` which does not support `target="_blank"`, replace just the ToS link with a native `<a>` element styled consistently.
- Run `npm run types` to verify TypeScript types compile correctly
- Run `npm run lint` to verify no linting issues

### 3. Write Terms of Service Acceptance Tests

- **Task ID**: write-tos-tests
- **Depends On**: backend-migration-models-route, frontend-tos-page-and-form
- **Assigned To**: tos-test-dev
- **Agent Type**: coder
- **Parallel**: false
- Create `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Auth/TermsOfServiceAcceptanceTest.php` using `php artisan make:test --pest Auth/TermsOfServiceAcceptanceTest --no-interaction`
- Add the following tests following the existing Pest test patterns (see `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Auth/RegistrationTest.php` for style):
    - `test('terms of service page can be rendered')` -- GET `/terms-of-service` returns 200
    - `test('terms of service page is accessible to guests')` -- GET `/terms-of-service` as unauthenticated user returns 200 (not redirected to login)
    - `test('registration records terms accepted timestamp')` -- POST registration with valid data including `'terms' => 'on'`, then query the created user and assert `terms_accepted_at` is not null and is a valid datetime close to `now()`
    - `test('registration fails without terms acceptance')` -- POST registration without the `terms` field, assert session has errors for `terms`, assert guest (not authenticated), assert no user was created
    - `test('registration fails when terms value is empty')` -- POST registration with `'terms' => ''`, assert session has errors for `terms`
    - `test('registration fails when terms value is off')` -- POST registration with `'terms' => 'off'`, assert session has errors for `terms`
    - `test('registration fails when terms value is false')` -- POST registration with `'terms' => 'false'`, assert session has errors for `terms`
    - `test('registration fails when terms value is zero')` -- POST registration with `'terms' => '0'`, assert session has errors for `terms`
- For all registration POST tests, use `$this->post(route('register.store'), [...])` with valid data for all other fields (`name`, `email`, `password`, `password_confirmation`) and only vary the `terms` field
- Use `expect()` assertions following existing Pest patterns
- Run the tests: `php artisan test tests/Feature/Auth/TermsOfServiceAcceptanceTest.php --compact`
- Also run existing registration tests to ensure no regressions: `php artisan test tests/Feature/Auth/RegistrationTest.php --compact`
- Ensure all tests pass

### 4. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: backend-migration-models-route, frontend-tos-page-and-form, write-tos-tests
- **Assigned To**: tos-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed below
- Verify ToS acceptance tests pass: `php artisan test tests/Feature/Auth/TermsOfServiceAcceptanceTest.php --compact`
- Verify registration tests still pass: `php artisan test tests/Feature/Auth/RegistrationTest.php --compact`
- Verify all auth tests pass: `php artisan test tests/Feature/Auth --compact`
- Run the full test suite to ensure no regressions: `php artisan test --compact`
- Run TypeScript type checking: `npm run types`
- Run ESLint: `npm run lint`
- Run Prettier formatting check: `npm run format:check`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Verify the migration file exists and adds the `terms_accepted_at` column
- Verify `User` model has `terms_accepted_at` in `$fillable` and `casts()`
- Verify `CreateNewUser` sets `terms_accepted_at => now()` in the `User::create()` call
- Verify `UserFactory` includes `terms_accepted_at` in its default state and has the `withoutTermsAcceptance()` factory state
- Verify the Terms of Service page renders at `/terms-of-service`
- Verify the registration form links to `/terms-of-service` with `target="_blank"` (opens in new tab)
- Verify the `User` TypeScript type includes `terms_accepted_at: string | null`
- Confirm all acceptance criteria are met

## Acceptance Criteria

- The Terms of Service page renders successfully at `/terms-of-service` and is accessible to unauthenticated users
- The registration form's "Terms of Service" link navigates to the `/terms-of-service` page in a new tab
- Submitting the registration form without checking the Terms of Service checkbox results in a validation error displayed on the form for the `terms` field
- Successful registration records a `terms_accepted_at` timestamp on the user record
- The `terms_accepted_at` column exists on the `users` table as a nullable timestamp
- The `User` model has `terms_accepted_at` in `$fillable` and casts it as `datetime`
- The `UserFactory` includes `terms_accepted_at` in its default state and provides a `withoutTermsAcceptance()` state
- The `User` TypeScript type includes `terms_accepted_at: string | null`
- All ToS acceptance tests pass
- All existing tests pass without regressions
- TypeScript types compile without errors
- ESLint and Prettier checks pass
- PHP code passes Pint formatting

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run ToS acceptance tests
php artisan test tests/Feature/Auth/TermsOfServiceAcceptanceTest.php --compact

# Run registration tests (regression check)
php artisan test tests/Feature/Auth/RegistrationTest.php --compact

# Run all auth tests
php artisan test tests/Feature/Auth --compact

# Run full test suite for regression check
php artisan test --compact

# TypeScript type checking
npm run types

# ESLint linting
npm run lint

# Prettier formatting check
npm run format:check

# PHP code formatting
vendor/bin/pint --dirty
```

## Notes

- This feature depends on E001-F001 (User Registration), which will have already implemented the basic `terms` checkbox on the registration form with `'terms' => ['accepted']` backend validation and the `InputError` display for the `terms` field. This feature builds on that by adding the compliance timestamp, the actual ToS page, and a proper link.
- The `terms_accepted_at` timestamp is crucial for legal compliance. Many jurisdictions require proof that a user accepted terms at a specific date and time. Storing this as a database column (rather than just relying on the existence of the user record) provides a clear audit trail.
- The `terms_accepted_at` column is nullable because users created before this feature (or via the factory in existing tests) may not have an explicit acceptance timestamp. The factory default sets it to `now()` for convenience in new tests.
- The ToS page content is placeholder text. Real legal content should be added later by a legal team. The page structure and route are the engineering deliverables.
- The Terms of Service link in the registration form opens in a new tab (`target="_blank"`) to prevent users from losing their partially filled registration form. This is a UX best practice for external/reference links in forms.
- The Radix `Checkbox` component renders a hidden `<input type="hidden" name="terms" value="on">` when checked. When unchecked, no value is sent. Laravel's `accepted` validation rule checks that the value is `"yes"`, `"on"`, `1`, `"1"`, `true`, or `"true"` -- so `"on"` from the Radix checkbox satisfies this rule.
- All commands should be run inside the Docker container. Use `make shell` to enter the container, or prefix with `docker compose exec app`.
- The `Password::defaults()` configuration in `AppServiceProvider` only enforces strict password requirements in production. In testing, the default password rules apply. Test data using `'password'` as the password value will work in tests.

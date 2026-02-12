# Feature: User Registration

**Epic**: E001-ai-powered-faceless-video-creation-platform.md
**Feature**: E001-F001
**Dependencies**: none

## Task Description

User Registration is the foundational authentication feature that allows new users to create an account on the Itervel platform. It extends the existing Fortify-powered registration flow (which already has a working page, backend action, and tests) with platform-specific requirements: a Terms of Service acceptance checkbox, a `video_credits` column on the users table initialized to 1 free credit, and email verification prompting via the `MustVerifyEmail` contract.

**What it does**: Allows new users to create an account using their email address and a password.

**Expected outcome**: A user fills in their email, creates a password (meeting strength requirements), confirms the password, accepts the Terms of Service, and submits the form. They receive a new account with 1 free video credit and are prompted to verify their email.

The existing registration infrastructure is already substantial:

- **Backend**: `App\Actions\Fortify\CreateNewUser` handles user creation with validation via `PasswordValidationRules` and `ProfileValidationRules` traits. Fortify routes are registered automatically. `FortifyServiceProvider` configures the register view to render `auth/register` via Inertia.
- **Frontend**: `resources/js/pages/auth/register.tsx` renders a registration form with name, email, password, and password confirmation fields using Inertia's `<Form>` component and Wayfinder routes.
- **Tests**: `tests/Feature/Auth/RegistrationTest.php` has two basic tests (render and submit).
- **Email verification**: Already enabled in `config/fortify.php` (`Features::emailVerification()`), and the verify-email page exists at `resources/js/pages/auth/verify-email.tsx`.

This feature needs to add: (1) a `video_credits` column to the users table, (2) a Terms of Service checkbox to the registration form, (3) backend validation for the ToS acceptance, (4) initialization of 1 free credit upon user creation, (5) enabling the `MustVerifyEmail` contract on the User model so users are prompted to verify, and (6) expanded tests covering all new behavior.

## Objective

Extend the existing Fortify registration flow to include Terms of Service acceptance, award 1 free video credit to every new user, and enable email verification prompting. The registration form will have a ToS checkbox, the backend will validate it and create users with `video_credits = 1`, and the User model will implement `MustVerifyEmail` so unverified users are redirected to the email verification page.

## Solution Approach

### 1. Database Migration: Add `video_credits` column

Create a new migration to add an unsigned integer `video_credits` column to the `users` table with a default of `0`. The default is `0` because the credit is explicitly granted during registration in the `CreateNewUser` action (not implicitly via a database default). This makes the credit assignment intentional and testable.

```php
Schema::table('users', function (Blueprint $table) {
    $table->unsignedInteger('video_credits')->default(0)->after('password');
});
```

### 2. User Model Changes

Two changes to `App\Models\User`:

**a) Implement `MustVerifyEmail`**: Uncomment the existing `use Illuminate\Contracts\Auth\MustVerifyEmail;` import and add `implements MustVerifyEmail` to the class declaration. This is already hinted at in the model (line 5 has the commented-out import). This will cause Fortify to redirect unverified users to the email verification page.

**b) Add `video_credits` to `$fillable`**: Add `'video_credits'` to the `$fillable` array so it can be set during mass assignment in `CreateNewUser`.

### 3. Update `CreateNewUser` Action

Modify `App\Actions\Fortify\CreateNewUser::create()` to:

- Add a validation rule for `terms` (the ToS checkbox): `'terms' => ['accepted']`
- Set `video_credits` to `1` in the `User::create()` call

```php
return User::create([
    'name' => $input['name'],
    'email' => $input['email'],
    'password' => $input['password'],
    'video_credits' => 1,
]);
```

The `'accepted'` validation rule ensures the field is present and truthy (checkbox checked).

### 4. Update UserFactory

Add `video_credits` to the factory's default state so existing tests that create users via the factory have a consistent value:

```php
'video_credits' => 0,
```

### 5. Update Registration Frontend

Modify `resources/js/pages/auth/register.tsx` to add a Terms of Service checkbox between the password confirmation field and the submit button. Use the existing `Checkbox` component from `@/components/ui/checkbox` (already used in the login page for "Remember me"). The checkbox must have `name="terms"` and `value="on"` to satisfy Laravel's `accepted` validation rule when submitted via the Inertia `<Form>` component.

Add a `TextLink` for "Terms of Service" that links to a placeholder route (or a `#` anchor for now, since the ToS page is not part of this feature).

### 6. Update TypeScript Types

Add `video_credits` to the `User` type in `resources/js/types/auth.ts` so it is available throughout the frontend:

```ts
export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    video_credits: number;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};
```

### 7. Expand Tests

Update `tests/Feature/Auth/RegistrationTest.php` with comprehensive tests:

- Registration page renders successfully (existing)
- New users can register with valid data including ToS acceptance (update existing to include `terms`)
- New users start with 1 free video credit after registration
- Registration fails without ToS acceptance
- Registration fails with mismatched passwords
- Registration fails with duplicate email
- Registration fails with missing required fields
- Registered users are redirected to email verification (since `MustVerifyEmail` is now active)

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Actions/Fortify/CreateNewUser.php` -- The Fortify action that validates input and creates the user. Must be modified to validate `terms` and set `video_credits = 1`.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` -- The User Eloquent model. Must implement `MustVerifyEmail` and add `video_credits` to `$fillable`.
- `/Users/young/Nextcloud/dev/Itervel/app/Concerns/PasswordValidationRules.php` -- Trait providing password validation rules used by `CreateNewUser`. No changes needed, but referenced for context.
- `/Users/young/Nextcloud/dev/Itervel/app/Concerns/ProfileValidationRules.php` -- Trait providing name/email validation rules used by `CreateNewUser`. No changes needed, but referenced for context.
- `/Users/young/Nextcloud/dev/Itervel/app/Providers/FortifyServiceProvider.php` -- Configures Fortify actions and views including `registerView`. No changes needed, but referenced for understanding the registration flow.
- `/Users/young/Nextcloud/dev/Itervel/app/Providers/AppServiceProvider.php` -- Configures `Password::defaults()` for production password strength requirements. Referenced for understanding password validation behavior.
- `/Users/young/Nextcloud/dev/Itervel/config/fortify.php` -- Fortify configuration. `Features::registration()` and `Features::emailVerification()` are already enabled. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/auth/register.tsx` -- The registration form React component. Must be modified to add the Terms of Service checkbox.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/auth/login.tsx` -- The login page. Referenced as a pattern for checkbox usage (`Checkbox` component with `name` prop).
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/auth/verify-email.tsx` -- The email verification prompt page. Already exists and will be shown to unverified users after `MustVerifyEmail` is enabled. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/checkbox.tsx` -- The Radix-based Checkbox UI component. Used in the login form and will be reused for the ToS checkbox.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/input-error.tsx` -- Error display component used for showing validation errors below form fields. Already used in the register page.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/text-link.tsx` -- Styled Inertia Link component. Used for the "Terms of Service" link text.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/auth.ts` -- TypeScript type definitions for `User` and `Auth`. Must add `video_credits` to the `User` type.
- `/Users/young/Nextcloud/dev/Itervel/database/migrations/0001_01_01_000000_create_users_table.php` -- The original users migration. Referenced for understanding the current schema.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` -- The User model factory. Must add `video_credits` to the default state.
- `/Users/young/Nextcloud/dev/Itervel/database/seeders/DatabaseSeeder.php` -- The database seeder. No changes needed but referenced for context.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Auth/RegistrationTest.php` -- Existing registration tests. Must be significantly expanded.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Auth/EmailVerificationTest.php` -- Existing email verification tests. Referenced for understanding verification test patterns.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration. All Feature tests use `RefreshDatabase` automatically.
- `/Users/young/Nextcloud/dev/Itervel/bootstrap/app.php` -- Application bootstrap. Referenced for middleware configuration. No changes needed.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/database/migrations/YYYY_MM_DD_HHMMSS_add_video_credits_to_users_table.php` -- Migration to add the `video_credits` unsigned integer column to the users table with a default of 0. The exact filename will be generated by `php artisan make:migration`.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: registration-backend-dev
    - Role: Creates the database migration, updates the User model (MustVerifyEmail + fillable), updates CreateNewUser action (terms validation + video_credits), and updates the UserFactory
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: registration-frontend-dev
    - Role: Updates the registration form to add the Terms of Service checkbox, updates TypeScript types to include video_credits on the User type
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: registration-test-dev
    - Role: Expands the registration test suite to cover Terms of Service validation, video credit assignment, email verification prompting, and various validation failure scenarios
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: registration-reviewer
    - Role: Validates the complete registration feature against acceptance criteria, runs all tests, checks types, runs linting and formatting, verifies the migration runs cleanly
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

### 1. Create Database Migration and Update Backend Models

- **Task ID**: backend-migration-and-models
- **Depends On**: none
- **Assigned To**: registration-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- Run `php artisan make:migration add_video_credits_to_users_table --table=users --no-interaction` to create the migration file
- In the migration's `up()` method, add: `$table->unsignedInteger('video_credits')->default(0)->after('password');`
- In the migration's `down()` method, add: `$table->dropColumn('video_credits');`
- Update `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php`:
    - Uncomment the `use Illuminate\Contracts\Auth\MustVerifyEmail;` import on line 5
    - Change the class declaration to `class User extends Authenticatable implements MustVerifyEmail`
    - Add `'video_credits'` to the `$fillable` array (after `'password'`)
- Update `/Users/young/Nextcloud/dev/Itervel/app/Actions/Fortify/CreateNewUser.php`:
    - Add `'terms' => ['accepted'],` to the validation rules array (after the password rules)
    - Add `'video_credits' => 1,` to the `User::create()` array
- Update `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php`:
    - Add `'video_credits' => 0,` to the `definition()` return array
- Run the migration: `php artisan migrate --no-interaction`
- Run `vendor/bin/pint --dirty` to fix any formatting issues
- Verify the changes by running the existing registration test: `php artisan test tests/Feature/Auth/RegistrationTest.php --compact` (note: the existing test will fail because it does not send `terms` -- this is expected and will be fixed in the test task)

### 2. Update Registration Frontend

- **Task ID**: frontend-registration-form
- **Depends On**: none
- **Assigned To**: registration-frontend-dev
- **Agent Type**: coder
- **Parallel**: true
- Update `/Users/young/Nextcloud/dev/Itervel/resources/js/types/auth.ts`:
    - Add `video_credits: number;` to the `User` type (after `email_verified_at`)
- Update `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/auth/register.tsx`:
    - Add import for `Checkbox` from `@/components/ui/checkbox` (already imported in the login page -- follow that pattern)
    - Add a Terms of Service checkbox group between the password confirmation field and the submit button
    - The checkbox group should contain:
        - A `<div className="flex items-center space-x-3">` container (matching the login page's "Remember me" pattern)
        - A `<Checkbox id="terms" name="terms" required tabIndex={5} />` component
        - A `<Label htmlFor="terms">` with text "I agree to the " followed by a `<TextLink>` with text "Terms of Service" linking to `#` (placeholder URL)
        - An `<InputError message={errors.terms} />` below the container for validation error display
    - Update the submit button's `tabIndex` from `5` to `6`
    - Update the "Already have an account?" link's `tabIndex` from `6` to `7`
    - Note: The Inertia `<Form>` component with a native `<Checkbox>` (Radix) needs the `name="terms"` attribute. The Radix Checkbox renders a hidden input with `value="on"` when checked, which satisfies Laravel's `accepted` validation rule.
- Run `npm run types` to verify TypeScript types compile correctly
- Run `npm run lint` to verify no linting issues

### 3. Write Comprehensive Registration Tests

- **Task ID**: write-registration-tests
- **Depends On**: backend-migration-and-models, frontend-registration-form
- **Assigned To**: registration-test-dev
- **Agent Type**: coder
- **Parallel**: false
- Replace the content of `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Auth/RegistrationTest.php` with comprehensive tests
- Follow the existing Pest test patterns from sibling test files (e.g., `AuthenticationTest.php`, `EmailVerificationTest.php`)
- Include the following tests:
    - `test('registration screen can be rendered')` -- GET `route('register')` returns 200 (keep existing)
    - `test('new users can register')` -- POST with valid data including `'terms' => 'on'`, assert authenticated, assert redirect to dashboard, assert `video_credits` is `1` (update existing)
    - `test('new users receive 1 free video credit')` -- POST valid registration, query the created user, assert `$user->video_credits` equals `1`
    - `test('registration requires terms acceptance')` -- POST without `terms` field, assert session has errors for `terms`, assert guest (not authenticated)
    - `test('registration fails when terms are not accepted')` -- POST with `'terms' => ''` or `'terms' => 'off'`, assert session has errors for `terms`
    - `test('registration requires name')` -- POST without name, assert session has errors for `name`
    - `test('registration requires email')` -- POST without email, assert session has errors for `email`
    - `test('registration requires password')` -- POST without password, assert session has errors for `password`
    - `test('registration requires password confirmation')` -- POST with mismatched `password` and `password_confirmation`, assert session has errors for `password`
    - `test('registration fails with duplicate email')` -- Create a user first, then POST registration with the same email, assert session has errors for `email`
    - `test('registered user is prompted to verify email')` -- After successful registration, attempt to access a `verified` middleware route (e.g., `route('dashboard')`), assert redirect to `route('verification.notice')` since the user has not verified their email
- Use `User::factory()` where needed for creating pre-existing users
- Use `$this->post(route('register.store'), [...])` for form submissions
- Use `expect()` assertions following existing patterns
- Run the tests: `php artisan test tests/Feature/Auth/RegistrationTest.php --compact`
- Ensure all tests pass

### 4. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: backend-migration-and-models, frontend-registration-form, write-registration-tests
- **Assigned To**: registration-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed below
- Verify registration tests pass: `php artisan test tests/Feature/Auth/RegistrationTest.php --compact`
- Verify email verification tests still pass: `php artisan test tests/Feature/Auth/EmailVerificationTest.php --compact`
- Verify all auth tests pass: `php artisan test tests/Feature/Auth --compact`
- Run the full test suite to ensure no regressions: `php artisan test --compact`
- Run TypeScript type checking: `npm run types`
- Run ESLint: `npm run lint`
- Run Prettier check: `npm run format:check`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Verify the migration file exists and adds the `video_credits` column
- Verify `User` model implements `MustVerifyEmail`
- Verify `User` model has `video_credits` in `$fillable`
- Verify `CreateNewUser` validates `terms` and sets `video_credits => 1`
- Verify `UserFactory` includes `video_credits` in its default state
- Verify the registration form includes a Terms of Service checkbox with `name="terms"`
- Verify the `User` TypeScript type includes `video_credits: number`
- Verify `InputError` is shown for `terms` validation errors on the frontend
- Confirm all acceptance criteria are met

## Acceptance Criteria

- The registration page renders successfully at the `/register` route
- The registration form includes fields for: name, email, password, password confirmation, and Terms of Service checkbox
- Submitting without checking the Terms of Service checkbox results in a validation error displayed on the form
- Successful registration creates a user with `video_credits = 1`
- Successful registration authenticates the user and redirects to the dashboard
- After registration, accessing routes protected by the `verified` middleware redirects the user to the email verification page (since the user has not yet verified their email)
- Registration fails with appropriate validation errors for: missing name, missing email, invalid email, duplicate email, missing password, password too weak (in production), mismatched password confirmation, unchecked terms
- The `User` model implements `MustVerifyEmail` contract
- The `video_credits` column exists on the `users` table as an unsigned integer with default 0
- All registration tests pass
- All existing tests pass without regressions
- TypeScript types compile without errors
- ESLint and Prettier checks pass
- PHP code passes Pint formatting

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run registration tests
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

- The `MustVerifyEmail` interface import is already present in the User model as a commented-out line (`// use Illuminate\Contracts\Auth\MustVerifyEmail;`). This was intentionally left by the Laravel starter kit for easy activation.
- The `Password::defaults()` configuration in `AppServiceProvider` only enforces strict password requirements in production. In testing, the default password rules apply (just `required`, `string`, `confirmed`). The test data using `'password'` as the password value will work in tests.
- The Radix `Checkbox` component renders a hidden `<input type="hidden" name="terms" value="on">` when checked. When unchecked, no value is sent. Laravel's `accepted` validation rule checks that the value is `"yes"`, `"on"`, `1`, `"1"`, `true`, or `"true"` -- so `"on"` from the Radix checkbox satisfies this rule.
- The Terms of Service page itself is not part of this feature (it would be Feature E001-F073 based on the epic). The link in the registration form will use a placeholder `#` href for now.
- Email verification is already fully implemented in the codebase (verify-email page, verification routes, verification test). This feature only needs to enable `MustVerifyEmail` on the User model to activate the redirect behavior.
- The `video_credits` column default is `0` (not `1`) intentionally. Credits are explicitly assigned in `CreateNewUser` to make the business logic clear and testable. If a user were somehow created outside of the registration flow, they would not receive a free credit.
- All commands should be run inside the Docker container. Use `make shell` to enter the container, or prefix with `docker compose exec app`.

# Feature: Free Credit for New Users

**Epic**: E005-credits-and-billing.md
**Feature**: E005-F002
**Dependencies**: E002-F001

## Task Description

Free Credit for New Users ensures that every newly registered user begins their experience on the platform with 1 free video credit. This credit allows them to generate one free trial video, lowering the barrier to entry and giving users a taste of the platform before purchasing additional credits.

**What it does**: Gives every new user 1 free credit upon registration.

**Expected outcome**: When a user creates an account, they start with 1 credit, allowing them to generate one free trial video.

This feature depends on E001-F001 (User Registration), which adds the `video_credits` column to the `users` table, updates the `CreateNewUser` action to set `video_credits = 1`, adds `video_credits` to the User model's `$fillable` array and UserFactory, and updates the TypeScript `User` type. Since E001-F001 handles all the foundational database and registration-flow changes, E001-F055 focuses on:

1. **Verifying** the free credit assignment works correctly end-to-end after E001-F001 is built
2. **Adding credit helper methods** to the User model (`hasCredits()`, `hasSufficientCredits()`, `deductCredits()`, `addCredits()`) that downstream credit features (F054 Credit Balance Display, F056 Credit Deduction on Completion, F057 Credit Refund on Failure, F058 Low Balance Warning, F059 Insufficient Credits Block) will need
3. **Writing focused unit tests** for the credit helper methods
4. **Writing an integration test** that specifically validates the new user free credit flow from registration through to having a spendable credit

## Objective

Verify that the free credit mechanism from E001-F001 works correctly, and build out the User model's credit management API (helper methods for checking, adding, and deducting credits) with comprehensive tests. When complete, the User model will have a clean, tested interface for credit operations that all downstream credit-related features can rely on.

## Solution Approach

### 1. Credit Helper Methods on the User Model

Add the following methods to `App\Models\User` to create a clean API for credit operations. These methods encapsulate the credit logic so that controllers and jobs never manipulate `video_credits` directly.

```php
/**
 * Check if the user has any credits available.
 */
public function hasCredits(): bool
{
    return $this->video_credits > 0;
}

/**
 * Check if the user has at least the specified number of credits.
 */
public function hasSufficientCredits(int $amount = 1): bool
{
    return $this->video_credits >= $amount;
}

/**
 * Deduct credits from the user's balance.
 *
 * @throws \InvalidArgumentException
 * @throws \RuntimeException
 */
public function deductCredits(int $amount = 1): void
{
    if ($amount <= 0) {
        throw new \InvalidArgumentException('Credit amount must be positive.');
    }

    if (! $this->hasSufficientCredits($amount)) {
        throw new \RuntimeException('Insufficient credits.');
    }

    $this->decrement('video_credits', $amount);
}

/**
 * Add credits to the user's balance.
 *
 * @throws \InvalidArgumentException
 */
public function addCredits(int $amount): void
{
    if ($amount <= 0) {
        throw new \InvalidArgumentException('Credit amount must be positive.');
    }

    $this->increment('video_credits', $amount);
}
```

Key design decisions:

- `deductCredits()` and `addCredits()` use Eloquent's `increment()`/`decrement()` methods, which issue atomic SQL `UPDATE ... SET video_credits = video_credits +/- N` queries. This prevents race conditions when multiple processes try to modify credits simultaneously.
- `deductCredits()` throws `\RuntimeException` when credits are insufficient rather than silently failing. This forces callers to check `hasSufficientCredits()` first or handle the exception.
- `addCredits()` throws `\InvalidArgumentException` for non-positive amounts to prevent accidental zero or negative additions.
- Methods accept an `$amount` parameter for flexibility (some features may deduct more than 1 credit in the future), but default to `1`.

### 2. Cast `video_credits` to Integer

Add `video_credits` to the model's `casts()` method to ensure it is always treated as an integer:

```php
protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'two_factor_confirmed_at' => 'datetime',
        'video_credits' => 'integer',
    ];
}
```

### 3. Unit Tests for Credit Methods

Create a dedicated unit test file `tests/Unit/Models/UserCreditTest.php` using Pest. Since credit helper methods interact with the database (via `increment`/`decrement`), these should be Feature tests that use `RefreshDatabase`. However, the `hasCredits()` and `hasSufficientCredits()` methods are pure property checks and can be tested at a unit level by creating User instances with specific `video_credits` values.

Given the project convention where Feature tests use `RefreshDatabase` automatically and most tests are Feature tests, place these in `tests/Feature/Credits/UserCreditTest.php`.

Tests to write:

- `test('new user created via registration has 1 free credit')` -- POST to register route with valid data (including `terms`), assert the created user has `video_credits === 1`
- `test('user created via factory has 0 credits by default')` -- Assert factory default
- `test('hasCredits returns true when user has credits')` -- Create user with `video_credits => 1`, assert `hasCredits()` is true
- `test('hasCredits returns false when user has no credits')` -- Create user with `video_credits => 0`, assert `hasCredits()` is false
- `test('hasSufficientCredits checks against specified amount')` -- Create user with `video_credits => 3`, assert `hasSufficientCredits(3)` is true, `hasSufficientCredits(4)` is false
- `test('deductCredits reduces the user credit balance')` -- Create user with `video_credits => 3`, call `deductCredits(1)`, assert `video_credits === 2`
- `test('deductCredits throws exception when insufficient credits')` -- Create user with `video_credits => 0`, assert calling `deductCredits()` throws `\RuntimeException`
- `test('deductCredits throws exception for non-positive amount')` -- Assert calling `deductCredits(0)` throws `\InvalidArgumentException`
- `test('addCredits increases the user credit balance')` -- Create user with `video_credits => 1`, call `addCredits(5)`, assert `video_credits === 6`
- `test('addCredits throws exception for non-positive amount')` -- Assert calling `addCredits(0)` throws `\InvalidArgumentException`
- `test('credit operations are atomic')` -- Create user with `video_credits => 5`, call `deductCredits(2)`, refresh from DB, assert `video_credits === 3`

### 4. Verify E001-F001 Integration

Run the existing registration tests from E001-F001 to confirm the free credit assignment works. If any registration test explicitly checks for `video_credits === 1`, verify it passes. If not, the test in step 3 (`new user created via registration has 1 free credit`) covers this gap.

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` -- The User Eloquent model. Credit helper methods (`hasCredits`, `hasSufficientCredits`, `deductCredits`, `addCredits`) will be added here. The `video_credits` cast will also be added to the `casts()` method. E001-F001 will have already added `video_credits` to `$fillable`.
- `/Users/young/Nextcloud/dev/Itervel/app/Actions/Fortify/CreateNewUser.php` -- The Fortify registration action. E001-F001 will have already added `'video_credits' => 1` here. Referenced for understanding how credits are initially assigned.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` -- The User factory. E001-F001 will have already added `'video_credits' => 0` to the default state. Referenced for tests.
- `/Users/young/Nextcloud/dev/Itervel/database/migrations/0001_01_01_000000_create_users_table.php` -- The original users migration. Referenced for understanding the schema (E001-F001 adds `video_credits` via a separate migration).
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Auth/RegistrationTest.php` -- Existing registration tests (expanded by E001-F001). Referenced to avoid duplicating tests.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration. All Feature tests use `RefreshDatabase` automatically.
- `/Users/young/Nextcloud/dev/Itervel/app/Concerns/ProfileValidationRules.php` -- Trait providing name/email validation rules. Referenced for understanding registration validation.
- `/Users/young/Nextcloud/dev/Itervel/app/Concerns/PasswordValidationRules.php` -- Trait providing password validation rules. Referenced for understanding registration validation.
- `/Users/young/Nextcloud/dev/Itervel/config/fortify.php` -- Fortify configuration. Registration and email verification features are enabled. Referenced for understanding the registration flow.
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Main web routes. The dashboard route uses `['auth', 'verified']` middleware. Referenced for understanding post-registration redirect behavior.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/auth.ts` -- TypeScript User type definition. E001-F001 will have already added `video_credits: number`. Referenced for frontend type verification.
- `/Users/young/Nextcloud/dev/Itervel/bootstrap/app.php` -- Application bootstrap with middleware configuration. Referenced for understanding middleware stack.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Credits/UserCreditTest.php` -- Pest feature tests for the User model's credit helper methods and the free credit registration flow. Tests cover `hasCredits()`, `hasSufficientCredits()`, `deductCredits()`, `addCredits()`, factory defaults, and the registration-to-credit integration.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: credit-backend-dev
    - Role: Adds credit helper methods (`hasCredits`, `hasSufficientCredits`, `deductCredits`, `addCredits`) to the User model, adds the `video_credits` integer cast, and verifies that E001-F001's changes (fillable, factory, CreateNewUser) are in place
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: credit-test-dev
    - Role: Creates comprehensive tests for the credit helper methods and the free credit registration integration in `tests/Feature/Credits/UserCreditTest.php`
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: credit-reviewer
    - Role: Validates the complete credit feature against acceptance criteria, runs all tests, checks types, runs linting and formatting, verifies credit methods work correctly
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

### 1. Add Credit Helper Methods to User Model

- **Task ID**: add-credit-methods
- **Depends On**: none
- **Assigned To**: credit-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- Verify that E001-F001's changes are in place on the User model: `video_credits` is in `$fillable`, `MustVerifyEmail` is implemented. If not present, flag this to the team lead -- do NOT add them yourself as they belong to E001-F001.
- Add `'video_credits' => 'integer'` to the `casts()` method in `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php`, placing it after the existing `'two_factor_confirmed_at' => 'datetime'` entry
- Add the following four public methods to the User model, placing them after the `casts()` method:
    - `hasCredits(): bool` -- returns `$this->video_credits > 0`
    - `hasSufficientCredits(int $amount = 1): bool` -- returns `$this->video_credits >= $amount`
    - `deductCredits(int $amount = 1): void` -- validates amount is positive, checks sufficient credits, then calls `$this->decrement('video_credits', $amount)`. Throws `\InvalidArgumentException` for non-positive amounts, `\RuntimeException` for insufficient credits.
    - `addCredits(int $amount): void` -- validates amount is positive, then calls `$this->increment('video_credits', $amount)`. Throws `\InvalidArgumentException` for non-positive amounts.
- Add PHPDoc blocks for each method following the project's convention (see existing methods in the model)
- Run `vendor/bin/pint --dirty` to format the changes
- Run `php artisan test tests/Feature/Auth/RegistrationTest.php --compact` to verify registration still works (the existing tests from E001-F001 should pass)

### 2. Write Credit Tests

- **Task ID**: write-credit-tests
- **Depends On**: add-credit-methods
- **Assigned To**: credit-test-dev
- **Agent Type**: coder
- **Parallel**: false
- Create the test directory and file: run `php artisan make:test Credits/UserCreditTest --pest --no-interaction`
- This creates `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Credits/UserCreditTest.php`
- Write the following tests in the file, using `App\Models\User` and following the project's existing Pest test patterns (see `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Auth/RegistrationTest.php` and the testing patterns in the project rules):
    - `test('new user created via registration has 1 free credit')` -- POST to `route('register.store')` with `['name' => 'Test User', 'email' => 'test@example.com', 'password' => 'password', 'password_confirmation' => 'password', 'terms' => 'on']`, then query `User::where('email', 'test@example.com')->first()` and assert `expect($user->video_credits)->toBe(1)`
    - `test('user created via factory has 0 credits by default')` -- `$user = User::factory()->create()`, assert `expect($user->video_credits)->toBe(0)`
    - `test('hasCredits returns true when user has credits')` -- Create user with `['video_credits' => 1]`, assert `expect($user->hasCredits())->toBeTrue()`
    - `test('hasCredits returns false when user has no credits')` -- Create user with `['video_credits' => 0]`, assert `expect($user->hasCredits())->toBeFalse()`
    - `test('hasSufficientCredits checks against specified amount')` -- Create user with `['video_credits' => 3]`, assert `expect($user->hasSufficientCredits(3))->toBeTrue()` and `expect($user->hasSufficientCredits(4))->toBeFalse()`
    - `test('hasSufficientCredits defaults to checking for 1 credit')` -- Create user with `['video_credits' => 1]`, assert `expect($user->hasSufficientCredits())->toBeTrue()`; create another with `['video_credits' => 0]`, assert `expect($user2->hasSufficientCredits())->toBeFalse()`
    - `test('deductCredits reduces the user credit balance')` -- Create user with `['video_credits' => 3]`, call `$user->deductCredits(1)`, refresh user from DB, assert `expect($user->video_credits)->toBe(2)`
    - `test('deductCredits can deduct multiple credits')` -- Create user with `['video_credits' => 5]`, call `$user->deductCredits(3)`, refresh from DB, assert `expect($user->video_credits)->toBe(2)`
    - `test('deductCredits throws exception when insufficient credits')` -- Create user with `['video_credits' => 0]`, assert `expect(fn () => $user->deductCredits())->toThrow(\RuntimeException::class, 'Insufficient credits.')`
    - `test('deductCredits throws exception for non-positive amount')` -- Create user with `['video_credits' => 5]`, assert `expect(fn () => $user->deductCredits(0))->toThrow(\InvalidArgumentException::class)` and `expect(fn () => $user->deductCredits(-1))->toThrow(\InvalidArgumentException::class)`
    - `test('addCredits increases the user credit balance')` -- Create user with `['video_credits' => 1]`, call `$user->addCredits(5)`, refresh from DB, assert `expect($user->video_credits)->toBe(6)`
    - `test('addCredits throws exception for non-positive amount')` -- Create user with `['video_credits' => 1]`, assert `expect(fn () => $user->addCredits(0))->toThrow(\InvalidArgumentException::class)` and `expect(fn () => $user->addCredits(-1))->toThrow(\InvalidArgumentException::class)`
    - `test('credit operations persist to the database')` -- Create user with `['video_credits' => 5]`, call `$user->deductCredits(2)`, fresh-load user from DB (`User::find($user->id)`), assert `expect($freshUser->video_credits)->toBe(3)`
- Run the tests: `php artisan test tests/Feature/Credits/UserCreditTest.php --compact`
- Ensure all tests pass
- Run `vendor/bin/pint --dirty` to format the test file

### 3. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: add-credit-methods, write-credit-tests
- **Assigned To**: credit-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed below
- Verify the User model has the four credit helper methods: `hasCredits()`, `hasSufficientCredits()`, `deductCredits()`, `addCredits()`
- Verify the User model casts `video_credits` to `integer`
- Verify `video_credits` is in the User model's `$fillable` array (from E001-F001)
- Verify `UserFactory` has `'video_credits' => 0` in its default state (from E001-F001)
- Verify `CreateNewUser` sets `'video_credits' => 1` (from E001-F001)
- Verify credit tests pass: `php artisan test tests/Feature/Credits/UserCreditTest.php --compact`
- Verify registration tests still pass: `php artisan test tests/Feature/Auth/RegistrationTest.php --compact`
- Verify all auth tests pass: `php artisan test tests/Feature/Auth --compact`
- Run the full test suite for regression check: `php artisan test --compact`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Run TypeScript type checking: `npm run types`
- Run ESLint: `npm run lint`
- Confirm all acceptance criteria are met

## Acceptance Criteria

- Every new user created through the registration flow starts with exactly 1 free video credit (`video_credits === 1`)
- Users created via the factory default to 0 credits (`video_credits === 0`), confirming the free credit is explicitly granted during registration (not via database default)
- The User model has a `hasCredits()` method that returns `true` when `video_credits > 0` and `false` otherwise
- The User model has a `hasSufficientCredits(int $amount = 1)` method that checks if the user has at least the specified number of credits
- The User model has a `deductCredits(int $amount = 1)` method that atomically decrements `video_credits` and throws exceptions for invalid or insufficient amounts
- The User model has an `addCredits(int $amount)` method that atomically increments `video_credits` and throws exceptions for invalid amounts
- The `video_credits` attribute is cast to `integer` on the User model
- All credit helper methods are covered by tests in `tests/Feature/Credits/UserCreditTest.php`
- All existing registration and auth tests pass without regressions
- PHP code passes Pint formatting
- TypeScript types compile without errors
- ESLint checks pass

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run credit tests
php artisan test tests/Feature/Credits/UserCreditTest.php --compact

# Run registration tests (verify E001-F001 integration)
php artisan test tests/Feature/Auth/RegistrationTest.php --compact

# Run all auth tests
php artisan test tests/Feature/Auth --compact

# Run full test suite for regression check
php artisan test --compact

# PHP code formatting
vendor/bin/pint --dirty

# TypeScript type checking
npm run types

# ESLint linting
npm run lint
```

## Notes

- This feature depends on E001-F001 (User Registration) being built first. E001-F001 creates the `video_credits` column migration, adds it to the User model's `$fillable`, updates the `UserFactory`, adds `'video_credits' => 1` to `CreateNewUser`, and updates the TypeScript `User` type. E001-F055 builds on top of these changes by adding credit management methods and dedicated tests.
- The credit helper methods use Eloquent's `increment()` and `decrement()` which issue atomic SQL queries (`UPDATE users SET video_credits = video_credits + N`). This is important for preventing race conditions when credits are being deducted by video completion jobs while simultaneously being added by purchase webhooks.
- The database column default is `0` (not `1`). The free credit is explicitly assigned in `CreateNewUser::create()` with `'video_credits' => 1`. This intentional design means users created outside the normal registration flow (e.g., admin-created accounts, seeded users) do not automatically get a free credit unless explicitly given one.
- Downstream features that will consume the credit helper methods added here:
    - **E001-F054** (Credit Balance Display) will read `video_credits` to show in the nav bar
    - **E001-F056** (Credit Deduction on Completion) will call `deductCredits()`
    - **E001-F057** (Credit Refund on Failure) will call `addCredits()`
    - **E001-F058** (Low Balance Warning) will use `hasSufficientCredits()` or check `video_credits` directly
    - **E001-F059** (Insufficient Credits Block) will use `hasCredits()` or `hasSufficientCredits()`
    - **E001-F060** (Credit Package Purchase) will call `addCredits()`
- All commands should be run inside the Docker container. Use `make shell` to enter the container, or prefix with `docker compose exec app`.

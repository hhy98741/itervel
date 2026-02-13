# Feature: Account Deletion

**Epic**: E003-user-profile-and-account-management.md
**Feature**: E003-F005
**Epic depends on**: E002-user-authentication.md
**Feature depends on**: E002-F003

## Task Description

Account Deletion allows authenticated users to permanently delete their account and all associated data from the Itervel platform. This supports GDPR compliance by ensuring users can exercise their "right to erasure."

**What it does**: Allows users to permanently delete their account and all associated data.

**Expected outcome**: The user requests account deletion. All their data, videos, projects, and files are removed. This supports GDPR compliance.

The existing codebase already has a fully functional basic account deletion flow:

- **Backend**: `ProfileController::destroy()` in `app/Http/Controllers/Settings/ProfileController.php` handles the deletion. It logs out the user, deletes the user model, invalidates the session, and regenerates the CSRF token. The `ProfileDeleteRequest` form request validates the user's current password before allowing deletion.
- **Frontend**: `resources/js/components/delete-user.tsx` renders a "Delete account" section with a warning box and a confirmation dialog. The dialog requires the user to enter their password before confirming. It uses Inertia's `<Form>` component with the Wayfinder-generated `ProfileController.destroy.form()` action.
- **Routes**: `DELETE /settings/profile` is defined in `routes/settings.php` with `['auth', 'verified']` middleware, named `profile.destroy`.
- **Tests**: `tests/Feature/Settings/ProfileUpdateTest.php` contains two account deletion tests: `'user can delete their account'` and `'correct password must be provided to delete account'`.

The current implementation performs a simple `$user->delete()` which only removes the user record from the `users` table. For GDPR compliance and the broader platform requirements, the deletion process needs to be enhanced to handle cascading deletion of all user-associated data. As the platform grows (with features like projects, videos, files, credits, transactions, etc.), the deletion logic must comprehensively clean up all related resources.

**Dependency on E001-F003 (User Login)**: That feature configures authentication, session management, rate limiting, and remember-me duration. The account deletion flow requires the user to be authenticated and verified (enforced by the existing route middleware). All auth infrastructure will be in place after E001-F003 is built.

Since this is an early feature in the platform's lifecycle, and models for projects, videos, and files do not yet exist, the approach is to:

1. Create a dedicated `DeleteUserAccount` action class that encapsulates the complete deletion logic
2. Set up the architecture so that as new models and relationships are added (projects, videos, files, transactions, etc.), their cascading deletion can be added to this single action class
3. Handle file storage cleanup (for any files on disk or cloud storage)
4. Ensure the existing frontend and tests continue to work with the refactored backend
5. Add comprehensive tests covering the deletion action and edge cases

## Objective

Refactor the existing basic account deletion into a robust, GDPR-compliant deletion system that uses a dedicated action class to encapsulate all user data cleanup logic. The action class will serve as the single point of responsibility for account deletion, making it easy to extend as new user-owned models and file storage are added to the platform. All existing functionality (password confirmation, UI, route) will continue to work unchanged.

## Solution Approach

### 1. Create a Dedicated DeleteUserAccount Action Class

Following the existing pattern of action classes in `app/Actions/Fortify/` (like `CreateNewUser` and `ResetUserPassword`), create a new action class at `app/Actions/DeleteUserAccount.php`. This action encapsulates all deletion logic in one place rather than spreading it across the controller.

```php
namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteUserAccount
{
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            $this->deleteUserFiles($user);
            $this->deleteRelatedRecords($user);
            $user->delete();
        });
    }

    protected function deleteUserFiles(User $user): void
    {
        // Delete any files stored on disk for this user.
        // As the platform grows, this will include video files,
        // thumbnails, brand guides, voiceover files, etc.
        $userDirectory = "users/{$user->id}";

        if (Storage::disk('local')->exists($userDirectory)) {
            Storage::disk('local')->deleteDirectory($userDirectory);
        }

        if (Storage::disk('public')->exists($userDirectory)) {
            Storage::disk('public')->deleteDirectory($userDirectory);
        }
    }

    protected function deleteRelatedRecords(User $user): void
    {
        // Delete all user-related records that are not handled
        // by database cascading foreign keys.
        // As new models are added (projects, videos, transactions, etc.),
        // their deletion logic should be added here.

        // Personal access tokens (if any)
        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        // Notifications
        $user->notifications()->delete();
    }
}
```

### 2. Refactor ProfileController::destroy() to Use the Action

The controller's `destroy` method should delegate to the new action class instead of calling `$user->delete()` directly. This keeps the controller thin and the deletion logic centralized.

```php
public function destroy(ProfileDeleteRequest $request, DeleteUserAccount $action): RedirectResponse
{
    $user = $request->user();

    Auth::logout();

    $action->delete($user);

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/');
}
```

### 3. No Frontend Changes Needed

The existing `delete-user.tsx` component is already fully functional and well-designed:

- It has a clear warning section with red styling
- It uses a confirmation dialog requiring password entry
- It submits via Inertia's `<Form>` component to `ProfileController.destroy`
- It handles errors and processing states

No changes are needed to the frontend since the API contract (DELETE `/settings/profile` with a `password` field) remains identical.

### 4. No Route Changes Needed

The existing route in `routes/settings.php` is correct:

```php
Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
```

It already requires `['auth', 'verified']` middleware, which ensures only authenticated and email-verified users can delete their accounts.

### 5. Comprehensive Testing

The existing tests verify basic deletion and password validation. Additional tests should cover:

- The `DeleteUserAccount` action class directly (unit-style tests)
- That file storage cleanup is triggered during deletion
- That notifications are cleaned up
- That the user is fully removed from the database after deletion
- That the action wraps deletion in a database transaction
- That all existing tests continue to pass

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/ProfileController.php` -- Contains the existing `destroy()` method that currently calls `$user->delete()` directly. Must be refactored to inject and use the new `DeleteUserAccount` action class.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/Settings/ProfileDeleteRequest.php` -- Validates the user's current password before deletion. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/app/Concerns/PasswordValidationRules.php` -- Provides the `currentPasswordRules()` method used by `ProfileDeleteRequest`. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` -- The User model. Uses `Notifiable` trait which provides `notifications()` relationship. Referenced for understanding what data needs cleanup.
- `/Users/young/Nextcloud/dev/Itervel/routes/settings.php` -- Contains the `DELETE /settings/profile` route with `['auth', 'verified']` middleware. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/delete-user.tsx` -- The frontend component for account deletion with confirmation dialog. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/profile.tsx` -- The profile settings page that renders the `DeleteUser` component. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/ProfileUpdateTest.php` -- Contains existing account deletion tests. The existing tests should continue to pass without modification.
- `/Users/young/Nextcloud/dev/Itervel/app/Actions/Fortify/CreateNewUser.php` -- Example of the action class pattern used in the codebase. Referenced for structural conventions.
- `/Users/young/Nextcloud/dev/Itervel/app/Actions/Fortify/ResetUserPassword.php` -- Another example of the action class pattern. Referenced for structural conventions.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` -- User factory for creating test users. Default password is `'password'`. Referenced for test setup.
- `/Users/young/Nextcloud/dev/Itervel/app/Providers/AppServiceProvider.php` -- Application service provider. Referenced for context. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/bootstrap/app.php` -- Application bootstrap. Referenced for understanding middleware stack. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration. Feature tests automatically use `RefreshDatabase`. Referenced for test setup context.

### New Files

- `app/Actions/DeleteUserAccount.php` -- New action class that encapsulates all account deletion logic. Contains a `delete()` method that wraps the entire deletion process in a database transaction: cleans up user files from storage, deletes related records (notifications, tokens, and future model data), and finally deletes the user record itself. This is the single point of responsibility for account deletion across the platform.
- `tests/Feature/Settings/AccountDeletionTest.php` -- New dedicated test file for comprehensive account deletion tests. Separates deletion tests from profile update tests for better organization. Tests the `DeleteUserAccount` action directly, verifies file cleanup, database transaction behavior, and edge cases. Created via `php artisan make:test Settings/AccountDeletionTest --pest`.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: deletion-backend-dev
    - Role: Creates the DeleteUserAccount action class, refactors the ProfileController to use it, and ensures the deletion pipeline is correct
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: deletion-test-dev
    - Role: Creates a comprehensive test suite for the account deletion feature covering the action class, controller integration, file cleanup, and edge cases
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: deletion-reviewer
    - Role: Validates the complete implementation against acceptance criteria, runs all tests, checks formatting, and verifies no regressions
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Create DeleteUserAccount Action Class and Refactor ProfileController

- **Task ID**: create-delete-action
- **Depends On**: none
- **Assigned To**: deletion-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- Read `/Users/young/Nextcloud/dev/Itervel/app/Actions/Fortify/CreateNewUser.php` and `/Users/young/Nextcloud/dev/Itervel/app/Actions/Fortify/ResetUserPassword.php` to understand the action class pattern used in this codebase
- Read `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/ProfileController.php` to understand the current deletion flow
- Read `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` to understand the User model's traits and relationships
- Create the new action class at `/Users/young/Nextcloud/dev/Itervel/app/Actions/DeleteUserAccount.php` using `php artisan make:class Actions/DeleteUserAccount --no-interaction`:

    ```php
    <?php

    namespace App\Actions;

    use App\Models\User;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Storage;

    class DeleteUserAccount
    {
        /**
         * Permanently delete the user's account and all associated data.
         */
        public function delete(User $user): void
        {
            DB::transaction(function () use ($user) {
                $this->deleteUserFiles($user);
                $this->deleteRelatedRecords($user);
                $user->delete();
            });
        }

        /**
         * Delete all files stored on disk for this user.
         */
        protected function deleteUserFiles(User $user): void
        {
            $userDirectory = "users/{$user->id}";

            if (Storage::disk('local')->exists($userDirectory)) {
                Storage::disk('local')->deleteDirectory($userDirectory);
            }

            if (Storage::disk('public')->exists($userDirectory)) {
                Storage::disk('public')->deleteDirectory($userDirectory);
            }
        }

        /**
         * Delete all related database records not handled by cascade constraints.
         */
        protected function deleteRelatedRecords(User $user): void
        {
            $user->notifications()->delete();

            if (method_exists($user, 'tokens')) {
                $user->tokens()->delete();
            }
        }
    }
    ```

- Refactor `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/ProfileController.php` to use the new action class:
    - Add `use App\Actions\DeleteUserAccount;` import
    - Change the `destroy` method signature to inject the action: `public function destroy(ProfileDeleteRequest $request, DeleteUserAccount $action): RedirectResponse`
    - Replace `$user->delete();` with `$action->delete($user);`
    - The full updated `destroy` method should be:

        ```php
        public function destroy(ProfileDeleteRequest $request, DeleteUserAccount $action): RedirectResponse
        {
            $user = $request->user();

            Auth::logout();

            $action->delete($user);

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/');
        }
        ```

- Run the existing deletion tests to verify the refactoring does not break anything: `php artisan test tests/Feature/Settings/ProfileUpdateTest.php --compact --filter="delete"`
- Run `vendor/bin/pint --dirty` to fix any formatting issues

### 2. Write Comprehensive Account Deletion Tests

- **Task ID**: write-deletion-tests
- **Depends On**: create-delete-action
- **Assigned To**: deletion-test-dev
- **Agent Type**: coder
- **Parallel**: false
- Read the existing tests in `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/ProfileUpdateTest.php` to understand the existing account deletion test patterns
- Read sibling test files like `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/PasswordUpdateTest.php` for consistent test patterns
- Read the newly created `/Users/young/Nextcloud/dev/Itervel/app/Actions/DeleteUserAccount.php` action class to understand what needs to be tested
- Create a new test file using: `php artisan make:test Settings/AccountDeletionTest --pest --no-interaction`
- Write the following tests in `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/AccountDeletionTest.php`:

- **Test: user can delete their account** (mirrors existing test but in the new file):

    ```php
    test('user can delete their account', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        expect($user->fresh())->toBeNull();
    });
    ```

- **Test: correct password must be provided to delete account**:

    ```php
    test('correct password must be provided to delete account', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        expect($user->fresh())->not->toBeNull();
    });
    ```

- **Test: password is required to delete account**:

    ```php
    test('password is required to delete account', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'));

        $response->assertSessionHasErrors('password');
        expect($user->fresh())->not->toBeNull();
    });
    ```

- **Test: guests cannot delete accounts**:

    ```php
    test('guests cannot delete accounts', function () {
        $response = $this->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

        $response->assertRedirect(route('login'));
    });
    ```

- **Test: delete action removes user notifications**:

    ```php
    test('account deletion removes user notifications', function () {
        $user = User::factory()->create();

        // Create a database notification for the user
        $user->notify(new \Illuminate\Notifications\DatabaseNotification());
        // Or use a simpler approach: insert directly
        DB::table('notifications')->insert([
            'id' => Str::uuid(),
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => $user->id,
            'data' => json_encode(['message' => 'test']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(DB::table('notifications')->where('notifiable_id', $user->id)->count())->toBe(1);

        $this->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        expect(DB::table('notifications')->where('notifiable_id', $user->id)->count())->toBe(0);
    });
    ```

- **Test: delete action cleans up user files from storage**:

    ```php
    test('account deletion cleans up user files from storage', function () {
        $user = User::factory()->create();

        // Create a test file in the user's directory
        Storage::disk('local')->put("users/{$user->id}/test-file.txt", 'test content');

        expect(Storage::disk('local')->exists("users/{$user->id}/test-file.txt"))->toBeTrue();

        $this->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        expect(Storage::disk('local')->exists("users/{$user->id}"))->toBeFalse();
    });
    ```

- **Test: delete action cleans up public storage files**:

    ```php
    test('account deletion cleans up public storage files', function () {
        $user = User::factory()->create();

        Storage::disk('public')->put("users/{$user->id}/avatar.png", 'fake image');

        expect(Storage::disk('public')->exists("users/{$user->id}/avatar.png"))->toBeTrue();

        $this->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        expect(Storage::disk('public')->exists("users/{$user->id}"))->toBeFalse();
    });
    ```

- **Test: delete action works when user has no files**:

    ```php
    test('account deletion works when user has no files', function () {
        $user = User::factory()->create();

        expect(Storage::disk('local')->exists("users/{$user->id}"))->toBeFalse();

        $response = $this->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('home'));
        expect($user->fresh())->toBeNull();
    });
    ```

- **Test: DeleteUserAccount action can be used directly**:

    ```php
    test('DeleteUserAccount action deletes user and associated data', function () {
        $user = User::factory()->create();
        $userId = $user->id;

        Storage::disk('local')->put("users/{$userId}/file.txt", 'content');

        $action = new \App\Actions\DeleteUserAccount();
        $action->delete($user);

        expect(User::find($userId))->toBeNull();
        expect(Storage::disk('local')->exists("users/{$userId}"))->toBeFalse();
    });
    ```

- **Test: user session is invalidated after deletion**:

    ```php
    test('user session is invalidated after account deletion', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $this->assertGuest();
    });
    ```

- Use `Storage::fake('local')` and `Storage::fake('public')` in the storage-related tests to avoid writing to the real filesystem. Wrap them appropriately (e.g., call `Storage::fake('local')` at the start of each relevant test).
- Ensure all necessary imports are included at the top of the test file: `use App\Models\User;`, `use Illuminate\Support\Facades\DB;`, `use Illuminate\Support\Facades\Storage;`, `use Illuminate\Support\Str;`
- Run the new test file: `php artisan test tests/Feature/Settings/AccountDeletionTest.php --compact`
- Also run the original profile test to ensure no regressions: `php artisan test tests/Feature/Settings/ProfileUpdateTest.php --compact`
- Run `vendor/bin/pint --dirty` to fix any formatting issues in the test file
- If any test fails, debug and fix until all pass

### 3. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-delete-action, write-deletion-tests
- **Assigned To**: deletion-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed below
- Verify that `/Users/young/Nextcloud/dev/Itervel/app/Actions/DeleteUserAccount.php` exists and contains: `DB::transaction()` wrapping, `deleteUserFiles()` method with `Storage::disk('local')` and `Storage::disk('public')` cleanup, `deleteRelatedRecords()` method that deletes notifications, and the main `delete()` method that accepts a `User` parameter
- Verify that `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/ProfileController.php` injects `DeleteUserAccount` in the `destroy` method signature and delegates deletion to `$action->delete($user)` instead of `$user->delete()`
- Verify that no frontend files were changed (the `delete-user.tsx` component and `profile.tsx` page should be untouched)
- Verify that no route files were changed (`routes/settings.php` should be untouched)
- Verify the new test file exists at `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/AccountDeletionTest.php`
- Run the account deletion tests: `php artisan test tests/Feature/Settings/AccountDeletionTest.php --compact`
- Run the profile update tests: `php artisan test tests/Feature/Settings/ProfileUpdateTest.php --compact`
- Run all settings tests: `php artisan test tests/Feature/Settings --compact`
- Run the full test suite to verify no regressions: `php artisan test --compact`
- Run TypeScript type checking: `npm run types`
- Run ESLint: `npm run lint`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Confirm all acceptance criteria are met

## Acceptance Criteria

- Users can delete their account by entering their current password in the confirmation dialog
- Account deletion removes the user record from the database
- Account deletion removes all user files from local storage (directory `users/{id}/`)
- Account deletion removes all user files from public storage (directory `users/{id}/`)
- Account deletion removes all user notifications from the database
- Account deletion gracefully handles cases where the user has no files or notifications
- The entire deletion process is wrapped in a database transaction for atomicity
- The user is logged out and their session is invalidated after deletion
- The user is redirected to the home page (`/`) after deletion
- Incorrect password is rejected with a validation error
- Missing password field is rejected with a validation error
- Unauthenticated users cannot access the deletion endpoint
- The `DeleteUserAccount` action class exists at `app/Actions/DeleteUserAccount.php` and can be used independently (not coupled to the HTTP layer)
- All existing profile update and deletion tests continue to pass
- All new account deletion tests pass
- The full test suite passes without regressions
- PHP code passes Pint formatting
- No changes to frontend files (the existing UI already handles the feature correctly)

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run new account deletion tests
php artisan test tests/Feature/Settings/AccountDeletionTest.php --compact

# Run existing profile tests (ensure no regressions)
php artisan test tests/Feature/Settings/ProfileUpdateTest.php --compact

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

- The `DeleteUserAccount` action class is designed to be extended as new features are added. When models like Project, Video, Transaction, etc. are created in later features (E001-F010 through E001-F090), their deletion logic should be added to the `deleteRelatedRecords()` method in this action class. Similarly, when new file storage patterns are introduced, their cleanup should be added to `deleteUserFiles()`.
- The action class uses `DB::transaction()` to ensure atomicity. If any part of the deletion fails (e.g., a database constraint violation), the entire operation is rolled back and the user's account remains intact.
- The `notifications` table may not exist in all environments (it requires a migration that ships with Laravel but may not be run). The `deleteRelatedRecords()` method should handle this gracefully. If the `notifications()` relationship returns no results or the table does not exist, it should not cause an error. The tests should create the notifications table if needed.
- File storage cleanup uses directory-based deletion (`deleteDirectory`), assuming a convention of `users/{user_id}/` as the base directory for all user-owned files. This convention should be adopted by all future features that store files for users.
- The existing two tests in `ProfileUpdateTest.php` for account deletion should remain in place. They serve as integration tests for the profile settings page flow. The new `AccountDeletionTest.php` file provides more granular testing of the deletion action itself and edge cases.
- The `delete-user.tsx` frontend component already has proper `data-test` attributes (`data-test="delete-user-button"` and `data-test="confirm-delete-user-button"`) which can be used for future end-to-end testing.
- All commands should be run inside the Docker container. Use `make shell` to enter the container, or prefix with `docker compose exec app`.

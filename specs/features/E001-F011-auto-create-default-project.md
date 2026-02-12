# Feature: Auto-Create Default Project

**Epic**: E001-ai-powered-faceless-video-creation-platform.md
**Feature**: E001-F011
**Dependencies**: E001-F010

## Task Description

Auto-Create Default Project automatically provisions a starter project for new users the first time they log in, removing friction from the onboarding flow. Instead of landing on an empty projects page and having to manually create their first project, users are immediately ready to start creating videos.

**What it does**: Automatically creates a starter project for new users when they first log in.

**Expected outcome**: Upon first login, a project named "My Channel" is automatically created and set as the default project, so the user can start creating videos immediately.

This feature builds on E001-F010 (Create Project), which establishes the `Project` model, `projects` table, `ProjectFactory`, and `User::projects()` relationship. E001-F010 does NOT include an `is_default` column on the projects table, so this feature must add it via a new migration. The `is_default` column is required by the PRD schema and will also be used by E001-F083 (Set Default Project) later.

The implementation uses a Laravel event listener attached to `Illuminate\Auth\Events\Login`. On every login, the listener checks whether the user has any projects. If they have zero projects (indicating a first-time experience), it creates a "My Channel" project with `is_default = true`. This approach is idempotent -- subsequent logins do nothing because the user already has projects.

**Why listen to `Login` instead of `Registered`**: The `Login` event is more robust for this use case. Registration might not always result in an immediate authenticated session (e.g., if email verification is required before login). By listening to `Login`, we guarantee the project exists the moment the user first accesses the authenticated portion of the application. The idempotent check (`projects()->count() === 0`) ensures it only fires once regardless of how many times the user logs in.

**Dependency on E001-F010**: This feature assumes the `Project` model, `projects` table migration, `ProjectFactory`, `User::projects()` HasMany relationship, and `User::is_paid` column all exist. The listener creates a project using the same `projects()->create()` pattern established by `ProjectController::store()`.

## Objective

Implement a `CreateDefaultProject` event listener that listens to `Illuminate\Auth\Events\Login`, adds an `is_default` boolean column to the `projects` table via migration, updates the `Project` model to include `is_default` in fillable/casts, creates the "My Channel" default project on first login with `is_default = true`, and includes comprehensive Pest feature tests proving the behavior.

## Solution Approach

### 1. Database: Add `is_default` column to projects table

Create a migration that adds an `is_default` boolean column to the `projects` table. This column defaults to `false` and is used to mark which project is the user's default (pre-selected when creating videos).

```php
Schema::table('projects', function (Blueprint $table) {
    $table->boolean('is_default')->default(false)->after('speaking_pace');
});
```

### 2. Update Project Model

Add `is_default` to the `$fillable` array and `casts()` method on `App\Models\Project`:

```php
protected $fillable = [
    'name',
    'target_audience',
    'tone',
    'speaking_pace',
    'is_default',  // add this
];

protected function casts(): array
{
    return [
        'speaking_pace' => 'integer',
        'is_default' => 'boolean',  // add this
    ];
}
```

### 3. Event Listener: CreateDefaultProject

Create `App\Listeners\CreateDefaultProject` that listens to `Illuminate\Auth\Events\Login`:

```php
<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

class CreateDefaultProject
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        // Only create a default project if the user has no projects yet
        if ($user->projects()->count() > 0) {
            return;
        }

        $user->projects()->create([
            'name' => 'My Channel',
            'is_default' => true,
        ]);
    }
}
```

### 4. Register Listener in AppServiceProvider

In Laravel 12, event listeners are auto-discovered if they follow conventions, but since `CreateDefaultProject` listens to a framework event (`Illuminate\Auth\Events\Login`), the cleanest approach is to use the `Event` facade in `AppServiceProvider::boot()`:

```php
use App\Listeners\CreateDefaultProject;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;

// In boot() method:
Event::listen(Login::class, CreateDefaultProject::class);
```

Alternatively, Laravel 12 supports auto-discovery of listeners if the listener has a `handle` method typed with the event class. However, explicit registration in the service provider is more visible and follows the principle of least surprise.

### 5. Update ProjectFactory

Add `is_default` to the `ProjectFactory::definition()` return array (defaulting to `false`) so tests can use `Project::factory()->create(['is_default' => true])`:

```php
// In definition():
'is_default' => false,
```

### 6. Tests

Write comprehensive Pest feature tests in `tests/Feature/AutoCreateDefaultProjectTest.php` covering:

- A default project "My Channel" is created on first login
- The project has `is_default = true`
- The project belongs to the authenticated user
- Subsequent logins do not create additional projects
- Users who already have projects do not get a new default project created
- The listener is idempotent (login multiple times, still only one auto-created project)

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` -- Contains the User model. After E001-F010, it will have a `projects(): HasMany` relationship. No changes needed by this feature.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/Project.php` -- After E001-F010, this will exist with `$fillable` and `casts()`. Must add `is_default` to both.
- `/Users/young/Nextcloud/dev/Itervel/app/Providers/AppServiceProvider.php` -- Must register the `Login` event listener mapping to `CreateDefaultProject`.
- `/Users/young/Nextcloud/dev/Itervel/app/Actions/Fortify/CreateNewUser.php` -- Reference for understanding user creation flow. The `Registered` event is fired after this action. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/app/Providers/FortifyServiceProvider.php` -- Reference for understanding how Fortify is configured. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/config/fortify.php` -- Fortify configuration showing `home` redirect is `/dashboard`. Reference for understanding post-login flow.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` -- Reference for factory patterns. After E001-F010, it will have an `is_paid` field and `paid()` state.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/ProjectFactory.php` -- After E001-F010, this will exist. Must add `is_default` to the `definition()` return array.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Auth/AuthenticationTest.php` -- Reference for login test patterns. Shows how `$this->post(route('login.store'), ...)` works.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/DashboardTest.php` -- Reference for simple feature test patterns.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration. All Feature tests use `RefreshDatabase`.
- `/Users/young/Nextcloud/dev/Itervel/bootstrap/app.php` -- Middleware and routing configuration. Reference only.
- `/Users/young/Nextcloud/dev/Itervel/bootstrap/providers.php` -- Lists service providers. Reference only; `AppServiceProvider` is already registered.
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Web routes. Reference for understanding the dashboard route and auth middleware. No changes needed.

### New Files

- `database/migrations/xxxx_xx_xx_xxxxxx_add_is_default_to_projects_table.php` -- Migration to add `is_default` boolean column to the `projects` table (defaults to `false`).
- `app/Listeners/CreateDefaultProject.php` -- Event listener that handles `Illuminate\Auth\Events\Login` and creates a "My Channel" project with `is_default = true` when the user has zero projects.
- `tests/Feature/AutoCreateDefaultProjectTest.php` -- Pest feature tests for the auto-create default project behavior.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: default-project-backend-dev
    - Role: Creates the migration for `is_default` column, creates the `CreateDefaultProject` listener, registers the listener in `AppServiceProvider`, updates the `Project` model and `ProjectFactory` to include `is_default`
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: default-project-test-dev
    - Role: Writes comprehensive Pest feature tests proving the auto-create default project behavior on login, idempotency, and edge cases
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: default-project-reviewer
    - Role: Validates the complete feature against acceptance criteria, runs all tests, checks formatting
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Add `is_default` Column, Create Listener, Register Event, and Update Model/Factory

- **Task ID**: create-default-project-backend
- **Depends On**: none
- **Assigned To**: default-project-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- IMPORTANT: This feature depends on E001-F010 (Create Project). Before starting, verify the following files exist from E001-F010: `app/Models/Project.php`, `database/factories/ProjectFactory.php`, and that `app/Models/User.php` has a `projects()` relationship. If any of these are missing, the executing agent should flag a dependency issue.
- Create a new migration for the `is_default` column. Run inside the Docker container (`docker compose exec app`):
    ```bash
    php artisan make:migration add_is_default_to_projects_table --table=projects --no-interaction
    ```
- Edit the generated migration:
    - In `up()`: `$table->boolean('is_default')->default(false)->after('speaking_pace');`
    - In `down()`: `$table->dropColumn('is_default');`
- Edit `/Users/young/Nextcloud/dev/Itervel/app/Models/Project.php`:
    - Add `'is_default'` to the `$fillable` array
    - Add `'is_default' => 'boolean'` to the `casts()` method return array
- Edit the `ProjectFactory` at `/Users/young/Nextcloud/dev/Itervel/database/factories/ProjectFactory.php`:
    - Add `'is_default' => false` to the `definition()` return array
- Create the `CreateDefaultProject` listener. Run inside the Docker container:
    ```bash
    php artisan make:listener CreateDefaultProject --event=Illuminate\\Auth\\Events\\Login --no-interaction
    ```
- Edit the generated `/Users/young/Nextcloud/dev/Itervel/app/Listeners/CreateDefaultProject.php`:
    - Ensure the `handle(Login $event): void` method contains:

        ```php
        $user = $event->user;

        if ($user->projects()->count() > 0) {
            return;
        }

        $user->projects()->create([
            'name' => 'My Channel',
            'is_default' => true,
        ]);
        ```

    - Ensure proper imports: `use Illuminate\Auth\Events\Login;`

- Edit `/Users/young/Nextcloud/dev/Itervel/app/Providers/AppServiceProvider.php`:
    - Add imports at the top: `use App\Listeners\CreateDefaultProject;`, `use Illuminate\Auth\Events\Login;`, `use Illuminate\Support\Facades\Event;`
    - In the `boot()` method, add after the `$this->configureDefaults()` call: `Event::listen(Login::class, CreateDefaultProject::class);`
- Run the migration inside the Docker container: `php artisan migrate`
- Run `vendor/bin/pint --dirty` to fix any PHP formatting issues
- Verify no errors by running: `php artisan event:list --event=Login`

### 2. Write Comprehensive Auto-Create Default Project Tests

- **Task ID**: write-default-project-tests
- **Depends On**: create-default-project-backend
- **Assigned To**: default-project-test-dev
- **Agent Type**: coder
- **Parallel**: false
- Create the test file inside the Docker container:
    ```bash
    php artisan make:test AutoCreateDefaultProjectTest --pest --no-interaction
    ```
- Read the existing test patterns in `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Auth/AuthenticationTest.php` and `/Users/young/Nextcloud/dev/Itervel/tests/Feature/DashboardTest.php` to follow conventions
- Edit `/Users/young/Nextcloud/dev/Itervel/tests/Feature/AutoCreateDefaultProjectTest.php` with the following tests:

    ```php
    <?php

    use App\Models\Project;
    use App\Models\User;

    test('a default project is created on first login', function () {
        $user = User::factory()->create();

        expect($user->projects()->count())->toBe(0);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        expect($user->projects()->count())->toBe(1);

        $project = $user->projects()->first();
        expect($project->name)->toBe('My Channel');
        expect($project->is_default)->toBeTrue();
    });

    test('default project belongs to the authenticated user', function () {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $project = $user->projects()->first();
        expect($project->user_id)->toBe($user->id);
    });

    test('no additional project is created on subsequent logins', function () {
        $user = User::factory()->create();

        // First login
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        expect($user->projects()->count())->toBe(1);

        // Logout
        $this->post(route('logout'));

        // Second login
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        expect($user->projects()->count())->toBe(1);
    });

    test('no default project is created if user already has projects', function () {
        $user = User::factory()->create();
        Project::factory()->for($user)->create(['name' => 'Existing Project']);

        expect($user->projects()->count())->toBe(1);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        expect($user->projects()->count())->toBe(1);
        expect($user->projects()->where('name', 'My Channel')->exists())->toBeFalse();
    });

    test('default project has correct default values for optional fields', function () {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $project = $user->projects()->first();
        expect($project->target_audience)->toBeNull();
        expect($project->tone)->toBeNull();
        expect($project->speaking_pace)->toBeNull();
    });

    test('other users are not affected when a new user logs in', function () {
        $existingUser = User::factory()->create();
        Project::factory()->for($existingUser)->count(2)->create();

        $newUser = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $newUser->email,
            'password' => 'password',
        ]);

        expect($existingUser->projects()->count())->toBe(2);
        expect($newUser->projects()->count())->toBe(1);
    });

    test('is_default column on project can be set to true', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['is_default' => true]);

        expect($project->is_default)->toBeTrue();
    });

    test('is_default column on project defaults to false', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        expect($project->is_default)->toBeFalse();
    });
    ```

- Run the tests inside the Docker container: `php artisan test tests/Feature/AutoCreateDefaultProjectTest.php --compact`
- Fix any failing tests until all pass
- Run `vendor/bin/pint --dirty` to fix formatting

### 3. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-default-project-backend, write-default-project-tests
- **Assigned To**: default-project-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed below
- Verify auto-create default project tests pass: `php artisan test tests/Feature/AutoCreateDefaultProjectTest.php --compact`
- Run the full test suite for regression check: `php artisan test --compact`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Verify the following files exist and are correct:
    - `app/Listeners/CreateDefaultProject.php` -- has `handle(Login $event)` method that checks `$user->projects()->count() > 0` and creates "My Channel" with `is_default = true`
    - `app/Providers/AppServiceProvider.php` -- has `Event::listen(Login::class, CreateDefaultProject::class)` in `boot()`
    - `app/Models/Project.php` -- has `is_default` in `$fillable` and `casts()`
    - `database/factories/ProjectFactory.php` -- has `'is_default' => false` in `definition()`
    - Migration for `is_default` column exists with correct `up()` and `down()` methods
    - `tests/Feature/AutoCreateDefaultProjectTest.php` -- has all test cases listed above
- Verify event listener is registered: `php artisan event:list --event=Login`
- Confirm all acceptance criteria are met

## Acceptance Criteria

- Upon first login, a project named "My Channel" is automatically created for the user
- The auto-created project has `is_default` set to `true`
- The auto-created project belongs to the authenticated user
- Optional project fields (`target_audience`, `tone`, `speaking_pace`) are `null` on the auto-created project
- Subsequent logins do NOT create additional projects (idempotent)
- Users who already have projects (e.g., manually created before logging out) do NOT get a new default project
- The `is_default` boolean column exists on the `projects` table and defaults to `false`
- The `Project` model includes `is_default` in `$fillable` and `casts()`
- The `ProjectFactory` includes `is_default => false` in its definition
- The listener is registered in `AppServiceProvider` and appears in `php artisan event:list`
- Other users' projects are not affected when a new user logs in
- All auto-create default project tests pass
- All existing tests continue to pass (no regressions)
- PHP code passes Pint formatting

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run auto-create default project tests
php artisan test tests/Feature/AutoCreateDefaultProjectTest.php --compact

# Run full test suite for regression check
php artisan test --compact

# Verify event listener is registered
php artisan event:list --event=Login

# PHP code formatting
vendor/bin/pint --dirty
```

## Notes

- **Why `Login` event instead of `Registered` event**: The `Registered` event fires during the registration action, but Fortify does not always auto-login users after registration (e.g., when email verification is required). Using the `Login` event guarantees the project is created the moment the user first gains access to the authenticated application. The idempotent check (`projects()->count() === 0`) ensures it only fires once.
- **`is_default` column scope**: The `is_default` column added by this feature is per-project, not per-user. A user should have at most one project with `is_default = true` at any time. Enforcement of this constraint (ensuring only one default per user) will be handled by E001-F083 (Set Default Project). For now, this feature simply sets `is_default = true` on the auto-created project.
- **Relationship to E001-F010**: This feature depends on E001-F010 having already created the `Project` model, `projects` table, `ProjectFactory`, `User::projects()` relationship, and `User::is_paid` column. If E001-F010 has not been built yet, this feature cannot be implemented.
- **Relationship to E001-F083**: E001-F083 (Set Default Project) will later add the ability for users to change which project is their default. The `is_default` column added here lays the groundwork for that feature.
- **PRD alignment**: The PRD database schema includes `is_default` on the projects table. E001-F010 omitted it (focusing on the core create flow), so this feature adds it. This is intentional and aligns with the PRD.
- **All `php artisan` commands should be run inside the Docker container.** Use `docker compose exec app` to prefix commands, or `make shell` to enter the container.
- **The `CreateDefaultProject` listener is synchronous** (not queued). Creating a single project row is fast and should not be offloaded to a queue. This ensures the project is immediately available after login.

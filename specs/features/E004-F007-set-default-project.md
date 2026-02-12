# Feature: Set Default Project

**Epic**: E004-project-management.md
**Feature**: E004-F007
**Dependencies**: E004-F001

## Task Description

Set Default Project lets authenticated users designate one of their projects as the "default" project. The default project is automatically pre-selected when creating new videos, reducing friction in the video creation workflow. Instead of manually selecting a project each time, users set their preferred project once and it carries forward.

**What it does**: Lets users designate one project as their default, which is pre-selected when creating new videos.

**Expected outcome**: The user sets a project as default. When starting a new video, this project is automatically selected in the project dropdown.

This feature builds on E001-F010 (Create Project), which establishes the `Project` model, `projects` table, `ProjectController`, `ProjectFactory`, `User::projects()` relationship, and routes. E001-F011 (Auto-Create Default Project) adds the `is_default` boolean column to the `projects` table and sets it to `true` on the auto-created "My Channel" project during first login. E001-F012 (List and Switch Projects) adds session-based active project switching and the project switcher dropdown in the sidebar.

The `is_default` column already exists on the `projects` table (added by E001-F011), and the `Project` model already includes `is_default` in its `$fillable` and `casts()` arrays. This feature adds a backend endpoint and frontend UI to allow users to change which project is their default, with the business rule that exactly one project per user can be the default at any time.

The "set default" action is a targeted operation: when a user sets project B as default, the system must unset the current default (project A) and set the new default (project B) in a single atomic operation. This prevents the user from having zero or multiple defaults simultaneously.

The frontend integrates into two locations:

1. The project switcher dropdown (created by E001-F012) -- adds a "Set as default" action for non-default projects and a visual "Default" indicator for the current default.
2. The projects index page (created by E001-F010) -- adds a "Set as default" button on each project card and a badge indicating which is the default.

## Objective

Implement a `setDefault` endpoint on the `ProjectController` that atomically sets a project as the user's default (unsetting any previous default), add corresponding frontend UI in the project switcher dropdown and projects index page showing and allowing changes to the default project, update the fallback logic in `HandleInertiaRequests` to properly use the default project, and write comprehensive Pest feature tests covering all scenarios including authorization, atomicity, and edge cases.

## Solution Approach

### 1. Backend: Add `setDefault` Method to ProjectController

Add a `setDefault` method to the existing `ProjectController` that:

1. Verifies the user owns the project (authorization)
2. Unsets `is_default` on all other projects belonging to the user
3. Sets `is_default = true` on the specified project
4. Redirects back with a flash message

The operation should be wrapped in a database transaction to ensure atomicity:

```php
public function setDefault(Request $request, Project $project): RedirectResponse
{
    if ($project->user_id !== $request->user()->id) {
        abort(403);
    }

    DB::transaction(function () use ($request, $project) {
        // Unset all current defaults for this user
        $request->user()->projects()
            ->where('is_default', true)
            ->update(['is_default' => false]);

        // Set the new default
        $project->update(['is_default' => true]);
    });

    return back();
}
```

### 2. Route: Add set-default endpoint

Add a new POST route:

```php
Route::post('projects/{project}/set-default', [ProjectController::class, 'setDefault'])
    ->name('projects.set-default');
```

### 3. User Model: Add Helper Method

Add a `defaultProject()` method to the `User` model for convenient access:

```php
public function defaultProject(): HasOne
{
    return $this->hasOne(Project::class)->where('is_default', true);
}
```

This provides a clean API: `$user->defaultProject` returns the default project or null. This is useful for the video creation flow where the default project needs to be pre-selected.

### 4. Frontend: Update Project Switcher Dropdown

Enhance the `ProjectSwitcher` component (created by E001-F012) to:

- Show a star/badge next to the default project name
- Add a "Set as default" menu item for non-default projects in the dropdown
- Use `router.post()` to call the `projects.set-default` endpoint

### 5. Frontend: Update Projects Index Page

Enhance the projects index page (created by E001-F010) to:

- Show a "Default" badge on the default project card
- Add a "Set as default" button on non-default project cards
- Disable/hide the button on the already-default project
- Show a success indication after setting a new default

### 6. Shared Data: Include `is_default` in Project Data

The `HandleInertiaRequests` middleware (updated by E001-F012) already shares `is_default` in the `projects` array and `currentProject` object. No changes needed to the shared data structure -- the frontend can read `is_default` from the existing shared data.

The `resolveCurrentProject` fallback logic (added by E001-F012) already falls back to the default project (`where('is_default', true)->first()`) when no session project exists, which is the correct behavior.

### 7. Tests

Write comprehensive Pest feature tests covering:

- Setting a project as default
- Only the user's own projects can be set as default
- Setting a new default unsets the previous default (atomicity)
- Guests cannot access the endpoint
- Setting default on a nonexistent project returns 404
- Setting default on another user's project returns 403
- The `defaultProject()` relationship returns the correct project
- A user with no default project returns null from `defaultProject()`

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/ProjectController.php` -- Will exist after E001-F010. Must add the `setDefault` method for handling the set-default POST request. Currently has `index`, `create`, `store` methods (from E001-F010) and `switchProject` method (from E001-F012).
- `/Users/young/Nextcloud/dev/Itervel/app/Models/Project.php` -- Will exist after E001-F010 with `user()` relationship, `$fillable` (including `is_default` from E001-F011), and `casts()` (including `is_default` from E001-F011). Reference for model structure.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` -- Has `projects(): HasMany` relationship (from E001-F010). Must add `defaultProject(): HasOne` relationship for convenient access to the default project.
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Contains project routes (from E001-F010, E001-F012). Must add the `projects/{project}/set-default` POST route.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Middleware/HandleInertiaRequests.php` -- Updated by E001-F012 to share `currentProject` and `projects` data with all pages. Already includes `is_default` in the shared data. No changes needed for shared data, but the `resolveCurrentProject` fallback already uses `is_default` correctly.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/project-switcher.tsx` -- Will exist after E001-F012. Must be enhanced to show a default indicator and "Set as default" action for each non-default project in the dropdown.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/projects/index.tsx` -- Will exist after E001-F010, enhanced by E001-F012. Must add a "Default" badge and "Set as default" button to project cards.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/index.ts` -- Contains `SharedData` type with `currentProject` and `projects` fields (after E001-F012). The `ProjectSummary` type (from E001-F012) already includes `is_default: boolean`. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/badge.tsx` -- Badge UI component for showing the "Default" indicator on project cards and in the switcher dropdown.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/dropdown-menu.tsx` -- DropdownMenu components used by the project switcher. Reference for adding new menu items.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/button.tsx` -- Button UI component for the "Set as default" action on project cards.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/card.tsx` -- Card UI component used by the projects index page.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/ProjectFactory.php` -- Will exist after E001-F010, with `is_default => false` in definition (from E001-F011). Needed for creating test projects with and without `is_default` set.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` -- Reference for factory patterns. Has `paid()` state (from E001-F010).
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Controller.php` -- Base abstract controller class.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/ProfileController.php` -- Reference for controller patterns (imports, return types, Inertia rendering).
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/ProfileUpdateTest.php` -- Reference for comprehensive feature test patterns with Pest.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/DashboardTest.php` -- Reference for simple feature test patterns.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration. All Feature tests use `RefreshDatabase`.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/profile.tsx` -- Reference for Inertia page with form submission, Wayfinder actions, and Inertia `<Form>` component pattern.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/user-menu-content.tsx` -- Reference for how `router` is used for POST actions in dropdown menu items (e.g., logout uses `Link` with `href` and `as="button"`).
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/nav-user.tsx` -- Reference for DropdownMenu + SidebarMenuButton pattern used in sidebar components.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/lib/utils.ts` -- Utility functions including `cn()` helper for conditional class names.

### New Files

- `tests/Feature/SetDefaultProjectTest.php` -- Pest feature tests for the set-default-project functionality covering authorization, atomicity, edge cases, and the `defaultProject()` relationship.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: set-default-backend-dev
    - Role: Adds `setDefault` method to ProjectController, adds the set-default route to web.php, adds `defaultProject()` HasOne relationship to User model, ensures DB transaction wrapping for atomicity
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: set-default-frontend-dev
    - Role: Enhances the project switcher dropdown with default indicator and "Set as default" action, enhances the projects index page with default badge and "Set as default" button
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: set-default-test-dev
    - Role: Writes comprehensive Pest feature tests covering set-default endpoint authorization, atomicity, edge cases, and the defaultProject() relationship
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: set-default-reviewer
    - Role: Validates the complete feature against acceptance criteria, runs all tests, checks TypeScript types, runs linting and formatting
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Add setDefault Endpoint, Route, and User Model Relationship

- **Task ID**: create-set-default-backend
- **Depends On**: none
- **Assigned To**: set-default-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- IMPORTANT: This feature depends on E001-F010 (Create Project). Before starting, verify the following exist:
    - `app/Models/Project.php` exists with `user()` relationship and `is_default` in `$fillable` and `casts()`
    - `app/Models/User.php` has `projects(): HasMany` relationship
    - `app/Http/Controllers/ProjectController.php` exists with controller methods
    - Routes `projects.index`, `projects.create`, `projects.store` exist
    - If any are missing, flag a dependency issue and stop.
- Edit `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php`:
    - Add `use Illuminate\Database\Eloquent\Relations\HasOne;` import
    - Add a `defaultProject(): HasOne` relationship method:
        ```php
        /**
         * Get the user's default project.
         */
        public function defaultProject(): HasOne
        {
            return $this->hasOne(Project::class)->where('is_default', true);
        }
        ```
    - Ensure `use App\Models\Project;` import is present (it should be from E001-F010, but verify)
- Edit `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/ProjectController.php`:
    - Add `use Illuminate\Support\Facades\DB;` import at the top (if not already present)
    - Add `use App\Models\Project;` import (if not already present)
    - Add a new `setDefault` method after the existing methods:

        ```php
        /**
         * Set a project as the user's default project.
         */
        public function setDefault(Request $request, Project $project): RedirectResponse
        {
            if ($project->user_id !== $request->user()->id) {
                abort(403);
            }

            DB::transaction(function () use ($request, $project) {
                $request->user()->projects()
                    ->where('is_default', true)
                    ->update(['is_default' => false]);

                $project->update(['is_default' => true]);
            });

            return back();
        }
        ```

- Edit `/Users/young/Nextcloud/dev/Itervel/routes/web.php`:
    - Inside the existing `Route::middleware(['auth', 'verified'])->group()` block that contains the project routes, add:
        ```php
        Route::post('projects/{project}/set-default', [ProjectController::class, 'setDefault'])->name('projects.set-default');
        ```
- Run `vendor/bin/pint --dirty` to fix any PHP formatting issues
- Verify the new route is registered: `php artisan route:list --name=projects.set-default`
- Verify all existing project routes still work: `php artisan route:list --name=projects`

### 2. Enhance Frontend with Default Project Indicator and Set Default Action

- **Task ID**: create-set-default-frontend
- **Depends On**: create-set-default-backend
- **Assigned To**: set-default-frontend-dev
- **Agent Type**: coder
- **Parallel**: false
- IMPORTANT: Before starting, verify the following frontend files exist from prior features:
    - `resources/js/components/project-switcher.tsx` (from E001-F012)
    - `resources/js/pages/projects/index.tsx` (from E001-F010)
    - `resources/js/types/index.ts` has `ProjectSummary` type with `is_default` field (from E001-F012)
    - If `project-switcher.tsx` does not exist, the frontend changes must be limited to the projects index page. If `projects/index.tsx` does not exist, flag a dependency issue.
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/components/project-switcher.tsx` to understand its current structure
- Enhance `/Users/young/Nextcloud/dev/Itervel/resources/js/components/project-switcher.tsx`:
    - Add `import { Star } from 'lucide-react';` (for the default indicator icon)
    - In each project's `DropdownMenuItem`, add a star icon next to the default project's name:
        ```tsx
        {
            project.is_default && (
                <Star className="size-3 shrink-0 fill-current text-yellow-500" />
            );
        }
        ```
    - Add a "Set as default" action for non-default projects. After the main project list, for each non-default project, add a contextual action. The cleanest approach is to add a secondary action within each dropdown item, or add a right-click / hover sub-action. For simplicity and consistency with the existing dropdown pattern, add a small icon button or use `router.post()` when clicking a "Set as default" option:
        ```tsx
        {
            !project.is_default && project.id !== currentProject?.id && (
                <button
                    onClick={(e) => {
                        e.stopPropagation();
                        router.post(
                            `/projects/${project.id}/set-default`,
                            {},
                            {
                                preserveScroll: true,
                                preserveState: true,
                            },
                        );
                    }}
                    className="ml-auto text-xs text-muted-foreground hover:text-foreground"
                    title="Set as default"
                >
                    <Star className="size-3" />
                </button>
            );
        }
        ```
    - Alternatively, a cleaner approach is to add a `DropdownMenuSub` or simply show the star icon filled for the default project and outlined for others -- clicking the outlined star sets it as default. Choose the approach that best fits the existing component's structure.
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/projects/index.tsx` to understand its current structure
- Enhance `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/projects/index.tsx`:
    - Add `import { router } from '@inertiajs/react';` (if not already imported)
    - Add `import { Badge } from '@/components/ui/badge';` (if not already imported)
    - Add `import { Star } from 'lucide-react';`
    - On each project card, show a "Default" badge when `project.is_default` is true:
        ```tsx
        {
            project.is_default && (
                <Badge variant="secondary" className="gap-1">
                    <Star className="size-3 fill-current" />
                    Default
                </Badge>
            );
        }
        ```
    - For non-default project cards, add a "Set as default" button:
        ```tsx
        {
            !project.is_default && (
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => {
                        router.post(
                            `/projects/${project.id}/set-default`,
                            {},
                            {
                                preserveScroll: true,
                            },
                        );
                    }}
                >
                    Set as default
                </Button>
            );
        }
        ```
    - Ensure the props interface for the page includes the project data with `is_default` field. The projects are passed from the controller and should already include `is_default` since it is in the model's attributes.
- Run `npm run build` to generate Wayfinder routes and compile assets
- Verify no TypeScript errors: `npm run types`
- Run `npm run lint` and fix any issues with `npm run lint:fix`
- Run `npm run format` to ensure Prettier formatting

### 3. Write Comprehensive Set Default Project Feature Tests

- **Task ID**: write-set-default-tests
- **Depends On**: create-set-default-backend
- **Assigned To**: set-default-test-dev
- **Agent Type**: coder
- **Parallel**: true (can run in parallel with task 2)
- Create the test file inside the Docker container: `php artisan make:test SetDefaultProjectTest --pest --no-interaction`
- Read existing test files for pattern reference: `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/ProfileUpdateTest.php` and `/Users/young/Nextcloud/dev/Itervel/tests/Feature/DashboardTest.php`
- Edit `/Users/young/Nextcloud/dev/Itervel/tests/Feature/SetDefaultProjectTest.php` with the following tests:

    ```php
    <?php

    use App\Models\Project;
    use App\Models\User;

    test('guests are redirected to login when setting default project', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->post(route('projects.set-default', $project));

        $response->assertRedirect(route('login'));
    });

    test('authenticated user can set their own project as default', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['is_default' => false]);

        $response = $this->actingAs($user)
            ->post(route('projects.set-default', $project));

        $response->assertRedirect();

        $project->refresh();
        expect($project->is_default)->toBeTrue();
    });

    test('setting a new default unsets the previous default', function () {
        $user = User::factory()->create();
        $projectA = Project::factory()->for($user)->create(['is_default' => true]);
        $projectB = Project::factory()->for($user)->create(['is_default' => false]);

        $response = $this->actingAs($user)
            ->post(route('projects.set-default', $projectB));

        $response->assertRedirect();

        $projectA->refresh();
        $projectB->refresh();

        expect($projectA->is_default)->toBeFalse();
        expect($projectB->is_default)->toBeTrue();
    });

    test('only one project is default after setting default', function () {
        $user = User::factory()->create();
        $projects = Project::factory()->for($user)->count(5)->create(['is_default' => false]);
        $projects->first()->update(['is_default' => true]);

        $targetProject = $projects->last();

        $this->actingAs($user)
            ->post(route('projects.set-default', $targetProject));

        $defaultCount = $user->projects()->where('is_default', true)->count();
        expect($defaultCount)->toBe(1);

        $targetProject->refresh();
        expect($targetProject->is_default)->toBeTrue();
    });

    test('user cannot set another users project as default', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherProject = Project::factory()->for($otherUser)->create(['is_default' => false]);

        $response = $this->actingAs($user)
            ->post(route('projects.set-default', $otherProject));

        $response->assertForbidden();

        $otherProject->refresh();
        expect($otherProject->is_default)->toBeFalse();
    });

    test('setting default on nonexistent project returns 404', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('projects.set-default', ['project' => 99999]));

        $response->assertNotFound();
    });

    test('setting already-default project as default is idempotent', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['is_default' => true]);

        $response = $this->actingAs($user)
            ->post(route('projects.set-default', $project));

        $response->assertRedirect();

        $project->refresh();
        expect($project->is_default)->toBeTrue();

        $defaultCount = $user->projects()->where('is_default', true)->count();
        expect($defaultCount)->toBe(1);
    });

    test('setting default does not affect other users projects', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $userProject = Project::factory()->for($user)->create(['is_default' => false]);
        $otherDefault = Project::factory()->for($otherUser)->create(['is_default' => true]);

        $this->actingAs($user)
            ->post(route('projects.set-default', $userProject));

        $otherDefault->refresh();
        expect($otherDefault->is_default)->toBeTrue();
    });

    test('user defaultProject relationship returns the default project', function () {
        $user = User::factory()->create();
        $regularProject = Project::factory()->for($user)->create(['is_default' => false]);
        $defaultProject = Project::factory()->for($user)->create(['is_default' => true]);

        expect($user->defaultProject)->not->toBeNull();
        expect($user->defaultProject->id)->toBe($defaultProject->id);
    });

    test('user defaultProject relationship returns null when no default exists', function () {
        $user = User::factory()->create();
        Project::factory()->for($user)->create(['is_default' => false]);

        expect($user->defaultProject)->toBeNull();
    });

    test('user defaultProject relationship returns null when user has no projects', function () {
        $user = User::factory()->create();

        expect($user->defaultProject)->toBeNull();
    });

    test('setting default updates the defaultProject relationship result', function () {
        $user = User::factory()->create();
        $projectA = Project::factory()->for($user)->create(['is_default' => true]);
        $projectB = Project::factory()->for($user)->create(['is_default' => false]);

        expect($user->defaultProject->id)->toBe($projectA->id);

        $this->actingAs($user)
            ->post(route('projects.set-default', $projectB));

        // Refresh the relationship
        $user->unsetRelation('defaultProject');

        expect($user->defaultProject->id)->toBe($projectB->id);
    });

    test('projects index page includes is_default in project data', function () {
        $user = User::factory()->create();
        $defaultProject = Project::factory()->for($user)->create(['is_default' => true, 'name' => 'Default One']);
        $otherProject = Project::factory()->for($user)->create(['is_default' => false, 'name' => 'Other One']);

        $response = $this->actingAs($user)->get(route('projects.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('projects/index')
            ->has('projects', 2)
        );
    });
    ```

- Run the tests inside the Docker container: `php artisan test tests/Feature/SetDefaultProjectTest.php --compact`
- Fix any failing tests until all pass
- Run `vendor/bin/pint --dirty` to fix formatting

### 4. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-set-default-backend, create-set-default-frontend, write-set-default-tests
- **Assigned To**: set-default-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed below
- Verify set-default tests pass: `php artisan test tests/Feature/SetDefaultProjectTest.php --compact`
- Run the full test suite for regression check: `php artisan test --compact`
- Run TypeScript type checking: `npm run types`
- Run ESLint: `npm run lint`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Verify the following files exist and are correct:
    - `app/Http/Controllers/ProjectController.php` has `setDefault(Request $request, Project $project)` method wrapped in `DB::transaction`
    - `app/Models/User.php` has `defaultProject(): HasOne` relationship returning `$this->hasOne(Project::class)->where('is_default', true)`
    - `routes/web.php` has `projects/{project}/set-default` POST route named `projects.set-default`
    - `resources/js/components/project-switcher.tsx` shows a default indicator (star icon) on the default project and offers a "Set as default" action on non-default projects
    - `resources/js/pages/projects/index.tsx` shows a "Default" badge on the default project card and a "Set as default" button on non-default cards
    - `tests/Feature/SetDefaultProjectTest.php` has all test cases covering authorization, atomicity, idempotency, cross-user isolation, relationship helper, and edge cases
- Verify routes exist: `php artisan route:list --name=projects`
- Confirm all acceptance criteria are met

## Acceptance Criteria

- Authenticated users can set any of their own projects as the default via POST to `projects/{project}/set-default`
- Setting a new default automatically unsets the previous default (only one default per user at any time)
- Setting an already-default project as default is idempotent and does not cause errors
- Users cannot set another user's project as default (returns 403)
- Guests are redirected to login when accessing the set-default endpoint
- Setting default on a nonexistent project returns 404
- The set-default operation is atomic (wrapped in a database transaction)
- Setting a default does not affect other users' default projects
- The `User::defaultProject()` HasOne relationship returns the correct default project or null
- The project switcher dropdown shows a visual indicator (star icon) for the default project
- The project switcher dropdown offers a "Set as default" action for non-default projects
- The projects index page shows a "Default" badge on the default project card
- The projects index page shows a "Set as default" button on non-default project cards
- All set-default tests pass
- All existing tests continue to pass (no regressions)
- PHP code passes Pint formatting
- TypeScript passes type checking
- ESLint reports no errors

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run set-default specific tests
php artisan test tests/Feature/SetDefaultProjectTest.php --compact

# Run full test suite for regression check
php artisan test --compact

# Verify routes are registered
php artisan route:list --name=projects

# TypeScript type checking
npm run types

# ESLint linting
npm run lint

# PHP code formatting
vendor/bin/pint --dirty
```

## Notes

- **Dependency on E001-F010**: E001-F010 creates the `Project` model, `projects` table, `ProjectController`, `ProjectFactory`, `User::projects()` relationship, and routes. This feature extends the existing `ProjectController` with a new `setDefault` method.
- **Dependency awareness for E001-F011**: E001-F011 adds the `is_default` boolean column to the `projects` table and includes it in the `Project` model's `$fillable` and `casts()`. This feature does NOT need to create the `is_default` column -- it already exists. If E001-F011 has not been built yet, the `is_default` column will not exist, and this feature must flag a dependency issue.
- **Relationship to E001-F012**: E001-F012 adds the project switcher dropdown and the `resolveCurrentProject` fallback logic in `HandleInertiaRequests`. The fallback logic already falls back to the default project (`is_default = true`), so this feature completes the circle: users can now change which project the fallback selects.
- **Atomicity**: The `setDefault` method wraps the two update operations (unset old default, set new default) in a `DB::transaction`. This prevents a race condition where a user could end up with zero or multiple defaults if two requests arrive simultaneously.
- **No new migration needed**: The `is_default` column is already on the `projects` table (from E001-F011). This feature only adds a controller method, a route, a User model relationship, and frontend UI changes.
- **The `defaultProject()` relationship uses a `HasOne` with a `where` clause**. This is the standard Laravel pattern for a conditional has-one. It returns `null` when no project has `is_default = true`, which is the expected behavior for users who have never set a default or whose default was deleted.
- **Frontend Wayfinder routes**: The `projects.set-default` route will generate a Wayfinder function after `npm run build`. Until Wayfinder generates the route, the frontend components use `router.post()` with a constructed URL string (`/projects/${project.id}/set-default`). This is the established pattern when routes are new and Wayfinder has not yet regenerated.
- **All `php artisan` commands should be run inside the Docker container.** Use `docker compose exec app` to prefix commands, or `make shell` to enter the container.
- **Video creation pre-selection**: The feature description mentions that the default project is "pre-selected when creating new videos." The video creation feature does not exist yet. When it is built, the create-video form should read `$user->defaultProject` (or the `currentProject` from shared data, which already falls back to the default) to pre-select the project dropdown. This feature lays the groundwork; the actual pre-selection integration happens in the video creation feature.

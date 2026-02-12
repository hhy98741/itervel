# Feature: Project-Level Settings Inheritance

**Epic**: E001-ai-powered-faceless-video-creation-platform.md
**Feature**: E001-F082
**Dependencies**: E001-F013

## Task Description

Project-Level Settings Inheritance ensures that when a user starts creating a new video within a project, the project's configured settings (target audience, tone, speaking pace) are automatically pre-filled as defaults in the video creation form. This eliminates repetitive configuration when users create multiple videos within the same project -- they configure their project once and every new video inherits those settings.

**What it does**: Applies project-level settings (target audience, tone, speaking pace, etc.) as defaults for every video created in that project.

**Expected outcome**: When a user starts a new video in a project, the project's settings are automatically pre-filled, saving time on repeated configuration.

This feature depends on E001-F013 (Edit Project Settings), which itself depends on E001-F010 (Create Project). After those features are built:

- The `Project` model exists with columns: `name`, `target_audience`, `tone`, `speaking_pace` (plus `is_default` from E001-F011).
- The `ProjectController` has `index`, `create`, `store`, `edit`, `update` methods.
- The project edit page exists at `/projects/{project}/edit` with a pre-populated form.
- The `UpdateProjectRequest` handles ownership authorization and validation.
- The `User` model has `projects()` HasMany relationship and `is_paid` boolean.
- E001-F012 (List and Switch Projects) shares `currentProject` and `projects` via Inertia shared data through `HandleInertiaRequests`.
- E001-F007 (User Default Preferences) adds user-level defaults (`default_video_length`, `default_speaking_pace`, `default_script_iterations`) to the `users` table.

The "settings inheritance" concept involves a priority chain for pre-filling video creation forms:

1. **Project settings** (highest priority for project-specific fields): `target_audience`, `tone`, `speaking_pace` from the active project.
2. **User preferences** (fallback for user-level defaults): `default_video_length`, `default_speaking_pace`, `default_script_iterations` from the user.
3. **System defaults** (fallback when neither project nor user has set a value).

For the `speaking_pace` field specifically, the project setting takes priority over the user's `default_speaking_pace` if set. This gives users fine-grained control: they can set a global default speaking pace on their profile and override it per-project.

This feature does NOT create a new video creation page (that will be built by E001-F017 Topic Input and E001-F070 Step-by-Step Wizard). Instead, it creates the backend infrastructure -- a service/helper that resolves inherited settings for a given project -- and provides a dedicated API endpoint that returns the resolved settings for the active project. It also adds a frontend utility hook that video creation pages can use to access pre-filled defaults. This makes the inheritance logic reusable across all future video creation features.

## Objective

Implement a `ProjectSettingsResolver` service class that merges project settings with user preferences and system defaults, expose an API endpoint that returns the resolved settings for the current active project, create a frontend React hook (`useProjectDefaults`) that fetches and provides these resolved settings, add comprehensive Pest feature tests covering the resolution priority chain, and ensure the infrastructure is ready for consumption by future video creation features.

## Solution Approach

### 1. Backend: ProjectSettingsResolver Service Class

Create a dedicated service class that encapsulates the settings resolution logic. This follows the Single Responsibility principle and makes the logic testable and reusable across controllers.

```php
// app/Services/ProjectSettingsResolver.php
namespace App\Services;

use App\Models\Project;
use App\Models\User;

class ProjectSettingsResolver
{
    /**
     * System-level defaults for video creation settings.
     */
    private const SYSTEM_DEFAULTS = [
        'target_audience' => null,
        'tone' => null,
        'speaking_pace' => 150,
        'video_length' => 10,
        'script_iterations' => 2,
    ];

    /**
     * Resolve the effective settings for a video in the given project.
     *
     * Priority chain:
     * 1. Project settings (for project-specific fields)
     * 2. User preferences (for user-level defaults)
     * 3. System defaults
     *
     * @return array{target_audience: string|null, tone: string|null, speaking_pace: int, video_length: int, script_iterations: int}
     */
    public function resolve(Project $project, User $user): array
    {
        return [
            'target_audience' => $project->target_audience ?? self::SYSTEM_DEFAULTS['target_audience'],
            'tone' => $project->tone ?? self::SYSTEM_DEFAULTS['tone'],
            'speaking_pace' => $project->speaking_pace
                ?? $user->default_speaking_pace
                ?? self::SYSTEM_DEFAULTS['speaking_pace'],
            'video_length' => $user->default_video_length
                ?? self::SYSTEM_DEFAULTS['video_length'],
            'script_iterations' => $user->default_script_iterations
                ?? self::SYSTEM_DEFAULTS['script_iterations'],
        ];
    }
}
```

Key decisions:

- `target_audience` and `tone` are project-only settings (the user model does not have these fields). They come from the project or are null.
- `speaking_pace` exists on both the project (project-specific) and the user (global default via `default_speaking_pace` from E001-F007). The project value takes priority.
- `video_length` and `script_iterations` are user-only preferences (from E001-F007). They don't exist on the project model.
- System defaults provide a final fallback for numeric fields. The values match the defaults set in E001-F007's migration (`default_video_length` => 10, `default_speaking_pace` => 150, `default_script_iterations` => 2).

### 2. Backend: VideoDefaultsController

Create a lightweight controller that returns the resolved settings as JSON for the active project. This is consumed by the frontend hook via an Inertia visit or a direct fetch.

```php
// app/Http/Controllers/VideoDefaultsController.php
namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ProjectSettingsResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VideoDefaultsController extends Controller
{
    public function __construct(
        private ProjectSettingsResolver $resolver,
    ) {
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        if ($project->user_id !== $request->user()->id) {
            abort(403);
        }

        return response()->json(
            $this->resolver->resolve($project, $request->user())
        );
    }
}
```

### 3. Route

Add a route for fetching video defaults for a specific project:

```php
Route::get('projects/{project}/video-defaults', [VideoDefaultsController::class, 'show'])
    ->name('projects.video-defaults');
```

This sits in the `auth` + `verified` middleware group alongside other project routes.

### 4. Frontend: useProjectDefaults Hook

Create a React hook that fetches the resolved settings for a given project. This hook can be used by any video creation page/form to pre-fill fields.

```tsx
// resources/js/hooks/use-project-defaults.ts
import { useState, useEffect, useCallback } from 'react';

export interface ProjectDefaults {
    target_audience: string | null;
    tone: string | null;
    speaking_pace: number;
    video_length: number;
    script_iterations: number;
}

export function useProjectDefaults(projectId: number | null) {
    const [defaults, setDefaults] = useState<ProjectDefaults | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchDefaults = useCallback(async (id: number) => {
        setLoading(true);
        setError(null);
        try {
            // Use Wayfinder-generated route
            const response = await fetch(`/projects/${id}/video-defaults`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (!response.ok) {
                throw new Error('Failed to fetch project defaults');
            }
            const data: ProjectDefaults = await response.json();
            setDefaults(data);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Unknown error');
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        if (projectId) {
            fetchDefaults(projectId);
        } else {
            setDefaults(null);
        }
    }, [projectId, fetchDefaults]);

    return {
        defaults,
        loading,
        error,
        refetch: () => projectId && fetchDefaults(projectId),
    };
}
```

### 5. Alternative: Inline Props Approach

As a simpler alternative to the JSON endpoint + hook, the resolved settings can be passed directly as Inertia page props when rendering a video creation page. Since the video creation pages don't exist yet, both approaches should be supported:

- The `ProjectSettingsResolver` service can be injected into any controller that renders a video creation page, resolving defaults at render time and passing them as props.
- The JSON endpoint + hook provides a way to fetch defaults dynamically (e.g., when the user switches projects in a dropdown on the video creation page without a full page reload).

Both approaches share the same `ProjectSettingsResolver` service, which is the core of this feature.

### 6. Integration with HandleInertiaRequests (Shared Data)

To make the active project's resolved settings available globally (so any page can access them without a separate API call), the resolved settings are also shared via Inertia's shared data. This extends the `currentProject` shared data (from E001-F012) with a `defaults` property.

```php
// In HandleInertiaRequests::share()
'currentProjectDefaults' => fn () => $request->user() && $this->resolveCurrentProject($request)
    ? app(ProjectSettingsResolver::class)->resolve(
        Project::find($request->session()->get('current_project_id')),
        $request->user()
    )
    : null,
```

This means every authenticated page has access to `usePage().props.currentProjectDefaults` without an additional API call.

### 7. TypeScript Types

Add `ProjectDefaults` type and update `SharedData` to include `currentProjectDefaults`.

### 8. Tests

Write comprehensive tests covering:

- Settings resolution with all project fields set
- Settings resolution with partial project fields (fallback to user preferences)
- Settings resolution with no project fields (fallback to user preferences then system defaults)
- Speaking pace priority chain (project > user > system)
- The JSON endpoint returns correct resolved settings
- Authorization: users can only fetch defaults for their own projects
- Guests cannot access the endpoint
- Integration with Inertia shared data

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Http/Middleware/HandleInertiaRequests.php` -- Must be updated to share `currentProjectDefaults` with all authenticated pages via Inertia shared data. After E001-F012 this already shares `currentProject` and `projects`; this feature extends it with resolved default settings.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/Project.php` -- Will exist after E001-F010 with `target_audience`, `tone`, `speaking_pace` columns. The `ProjectSettingsResolver` reads these fields to resolve inherited settings.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` -- Will have `default_video_length`, `default_speaking_pace`, `default_script_iterations` columns after E001-F007. The resolver reads these as fallbacks when project settings are null.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Controller.php` -- Base abstract controller. The new `VideoDefaultsController` extends this.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/ProfileController.php` -- Reference for controller patterns (constructor, imports, return types).
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Must add the `projects/{project}/video-defaults` GET route inside the authenticated/verified middleware group.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/index.ts` -- Must export the new `ProjectDefaults` type and update `SharedData` to include `currentProjectDefaults`.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/hooks/use-appearance.ts` -- Reference for custom React hook patterns used in the project (state management, side effects).
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/dashboard.tsx` -- Reference for Inertia page component patterns accessing shared data.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/profile.tsx` -- Reference for form patterns using Wayfinder actions, `defaultValue` for pre-population, and how shared data is accessed via `usePage().props`.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` -- Will have `paid()` state after E001-F010. Will need to set user preference fields (`default_video_length`, `default_speaking_pace`, `default_script_iterations`) in certain test scenarios.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/ProjectFactory.php` -- Will exist after E001-F010 with `target_audience`, `tone`, `speaking_pace` fields. Used in tests to create projects with various settings configurations.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/ProfileUpdateTest.php` -- Reference for comprehensive feature test patterns (Arrange-Act-Assert, actingAs, assertions).
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration. All Feature tests use RefreshDatabase automatically.
- `/Users/young/Nextcloud/dev/Itervel/bootstrap/app.php` -- Middleware and routing configuration reference.
- `/Users/young/Nextcloud/dev/Itervel/app/Providers/AppServiceProvider.php` -- Service provider. The `ProjectSettingsResolver` can be auto-resolved by Laravel's container without explicit registration, but this is the place to add bindings if needed.

### New Files

- `app/Services/ProjectSettingsResolver.php` -- Service class that resolves the effective video creation settings by merging project settings, user preferences, and system defaults according to the priority chain. Contains a `resolve(Project, User): array` method and `SYSTEM_DEFAULTS` constants.
- `app/Http/Controllers/VideoDefaultsController.php` -- Controller with a single `show(Request, Project): JsonResponse` method that returns the resolved settings for a project. Uses constructor property promotion to inject `ProjectSettingsResolver`.
- `resources/js/hooks/use-project-defaults.ts` -- React hook that fetches resolved project defaults from the JSON endpoint. Returns `{ defaults, loading, error, refetch }`. Used by future video creation pages to pre-fill form fields.
- `resources/js/types/project-defaults.ts` -- TypeScript type definition for `ProjectDefaults` interface with `target_audience`, `tone`, `speaking_pace`, `video_length`, and `script_iterations` fields.
- `tests/Feature/ProjectSettingsInheritanceTest.php` -- Pest feature tests covering the JSON endpoint (authorization, response structure, correct resolution), and the settings priority chain (project > user > system).
- `tests/Unit/ProjectSettingsResolverTest.php` -- Pest unit tests covering the `ProjectSettingsResolver::resolve()` method in isolation with various combinations of project settings, user preferences, and system defaults.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: settings-inheritance-backend
    - Role: Creates the ProjectSettingsResolver service class, VideoDefaultsController, route registration, and updates HandleInertiaRequests to share resolved defaults via Inertia shared data
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: settings-inheritance-frontend
    - Role: Creates the useProjectDefaults React hook, ProjectDefaults TypeScript type, updates SharedData type definition, and builds the npm assets to verify Wayfinder route generation
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: settings-inheritance-tester
    - Role: Writes comprehensive Pest unit and feature tests covering the ProjectSettingsResolver priority chain, the JSON endpoint authorization and response, and the Inertia shared data integration
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: settings-inheritance-reviewer
    - Role: Validates the complete feature against acceptance criteria, runs all tests, checks TypeScript types, runs linting and formatting, verifies the resolution priority chain works correctly
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Create ProjectSettingsResolver Service, VideoDefaultsController, Route, and Update HandleInertiaRequests

- **Task ID**: create-backend-settings-resolver
- **Depends On**: none
- **Assigned To**: settings-inheritance-backend
- **Agent Type**: coder
- **Parallel**: true
- Read the existing `Project` model at `/Users/young/Nextcloud/dev/Itervel/app/Models/Project.php` to confirm the available settings fields (`target_audience`, `tone`, `speaking_pace`)
- Read the `User` model at `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` to understand the user preference fields that will exist after E001-F007 (`default_video_length`, `default_speaking_pace`, `default_script_iterations`)
- Read `/Users/young/Nextcloud/dev/Itervel/app/Http/Middleware/HandleInertiaRequests.php` to understand the current shared data structure and how `currentProject` is shared (after E001-F012)
- Read `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/ProfileController.php` for controller pattern reference
- Create the `app/Services/` directory if it does not exist, then create `/Users/young/Nextcloud/dev/Itervel/app/Services/ProjectSettingsResolver.php`:

    ```php
    <?php

    namespace App\Services;

    use App\Models\Project;
    use App\Models\User;

    class ProjectSettingsResolver
    {
        /**
         * System-level defaults for video creation settings.
         *
         * @var array{target_audience: null, tone: null, speaking_pace: int, video_length: int, script_iterations: int}
         */
        private const SYSTEM_DEFAULTS = [
            'target_audience' => null,
            'tone' => null,
            'speaking_pace' => 150,
            'video_length' => 10,
            'script_iterations' => 2,
        ];

        /**
         * Resolve the effective video creation settings for the given project and user.
         *
         * Priority chain:
         * 1. Project settings (target_audience, tone, speaking_pace)
         * 2. User preferences (default_speaking_pace, default_video_length, default_script_iterations)
         * 3. System defaults
         *
         * @return array{target_audience: string|null, tone: string|null, speaking_pace: int, video_length: int, script_iterations: int}
         */
        public function resolve(Project $project, User $user): array
        {
            return [
                'target_audience' => $project->target_audience ?? self::SYSTEM_DEFAULTS['target_audience'],
                'tone' => $project->tone ?? self::SYSTEM_DEFAULTS['tone'],
                'speaking_pace' => $project->speaking_pace
                    ?? $user->default_speaking_pace
                    ?? self::SYSTEM_DEFAULTS['speaking_pace'],
                'video_length' => $user->default_video_length
                    ?? self::SYSTEM_DEFAULTS['video_length'],
                'script_iterations' => $user->default_script_iterations
                    ?? self::SYSTEM_DEFAULTS['script_iterations'],
            ];
        }

        /**
         * Get the system default values.
         *
         * @return array{target_audience: null, tone: null, speaking_pace: int, video_length: int, script_iterations: int}
         */
        public static function systemDefaults(): array
        {
            return self::SYSTEM_DEFAULTS;
        }
    }
    ```

- Create `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/VideoDefaultsController.php` using `php artisan make:controller VideoDefaultsController --no-interaction` inside the Docker container, then edit it:

    ```php
    <?php

    namespace App\Http\Controllers;

    use App\Models\Project;
    use App\Services\ProjectSettingsResolver;
    use Illuminate\Http\JsonResponse;
    use Illuminate\Http\Request;

    class VideoDefaultsController extends Controller
    {
        public function __construct(
            private ProjectSettingsResolver $resolver,
        ) {
        }

        /**
         * Return the resolved video creation defaults for the given project.
         */
        public function show(Request $request, Project $project): JsonResponse
        {
            if ($project->user_id !== $request->user()->id) {
                abort(403);
            }

            return response()->json(
                $this->resolver->resolve($project, $request->user())
            );
        }
    }
    ```

- Edit `/Users/young/Nextcloud/dev/Itervel/routes/web.php` to add the video defaults route:
    - Add `use App\Http\Controllers\VideoDefaultsController;` import at the top
    - Add inside the authenticated/verified middleware group where other project routes are registered:
        ```php
        Route::get('projects/{project}/video-defaults', [VideoDefaultsController::class, 'show'])
            ->name('projects.video-defaults');
        ```
- Edit `/Users/young/Nextcloud/dev/Itervel/app/Http/Middleware/HandleInertiaRequests.php` to share `currentProjectDefaults` with all authenticated pages:
    - Add `use App\Models\Project;` and `use App\Services\ProjectSettingsResolver;` imports
    - In the `share()` method's return array, add:

        ```php
        'currentProjectDefaults' => function () use ($request) {
            $user = $request->user();
            if (! $user) {
                return null;
            }

            $projectId = $request->session()->get('current_project_id');
            $project = $projectId ? $user->projects()->find($projectId) : null;

            if (! $project) {
                $project = $user->projects()->where('is_default', true)->first()
                    ?? $user->projects()->first();
            }

            if (! $project) {
                return null;
            }

            return app(ProjectSettingsResolver::class)->resolve($project, $user);
        },
        ```

    - Note: After E001-F012, the `HandleInertiaRequests` will already have a `resolveCurrentProject()` helper method. If that method exists, refactor to reuse it instead of duplicating the project resolution logic. The key addition is wrapping the resolved project with the `ProjectSettingsResolver::resolve()` call.

- Run `vendor/bin/pint --dirty` to format all changed PHP files
- Verify the route is registered: `php artisan route:list --name=video-defaults`
- Verify no PHP errors: `php artisan route:list`

### 2. Create Frontend Hook, Types, and Update SharedData

- **Task ID**: create-frontend-hook-and-types
- **Depends On**: create-backend-settings-resolver
- **Assigned To**: settings-inheritance-frontend
- **Agent Type**: coder
- **Parallel**: false
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/types/index.ts` to understand current type exports and SharedData structure
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/hooks/use-appearance.ts` (or any existing hook file) for hook patterns used in this project
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/profile.tsx` for patterns of accessing shared data via `usePage().props`
- Run `npm run build` to generate Wayfinder routes for the new `VideoDefaultsController.show` endpoint
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/types/project-defaults.ts`:
    ```typescript
    export type ProjectDefaults = {
        target_audience: string | null;
        tone: string | null;
        speaking_pace: number;
        video_length: number;
        script_iterations: number;
    };
    ```
- Edit `/Users/young/Nextcloud/dev/Itervel/resources/js/types/index.ts`:
    - Add `export type * from './project-defaults';` to the type exports
    - Update the `SharedData` type to include `currentProjectDefaults`:

        ```typescript
        import type { ProjectDefaults } from './project-defaults';

        export type SharedData = {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            currentProjectDefaults: ProjectDefaults | null;
            [key: string]: unknown;
        };
        ```

    - Note: After E001-F012, `SharedData` will already have `currentProject` and `projects` fields. Add `currentProjectDefaults` alongside those.

- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/hooks/use-project-defaults.ts`:

    ```typescript
    import { useState, useEffect, useCallback } from 'react';
    import type { ProjectDefaults } from '@/types';

    /**
     * Fetches resolved video creation defaults for a given project.
     *
     * This hook calls the /projects/{id}/video-defaults endpoint which
     * resolves settings using the priority chain:
     * 1. Project settings (target_audience, tone, speaking_pace)
     * 2. User preferences (default_speaking_pace, default_video_length, default_script_iterations)
     * 3. System defaults
     *
     * For most cases, use `usePage().props.currentProjectDefaults` from Inertia
     * shared data instead. This hook is useful when switching projects dynamically
     * without a full page reload.
     */
    export function useProjectDefaults(projectId: number | null) {
        const [defaults, setDefaults] = useState<ProjectDefaults | null>(null);
        const [loading, setLoading] = useState(false);
        const [error, setError] = useState<string | null>(null);

        const fetchDefaults = useCallback(async (id: number) => {
            setLoading(true);
            setError(null);
            try {
                const response = await fetch(`/projects/${id}/video-defaults`, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error('Failed to fetch project defaults');
                }

                const data: ProjectDefaults = await response.json();
                setDefaults(data);
            } catch (err) {
                setError(err instanceof Error ? err.message : 'Unknown error');
            } finally {
                setLoading(false);
            }
        }, []);

        useEffect(() => {
            if (projectId) {
                fetchDefaults(projectId);
            } else {
                setDefaults(null);
            }
        }, [projectId, fetchDefaults]);

        return {
            defaults,
            loading,
            error,
            refetch: () => {
                if (projectId) {
                    fetchDefaults(projectId);
                }
            },
        };
    }
    ```

- Run `npm run build` to compile assets and verify Wayfinder route generation
- Run `npm run types` to verify no TypeScript errors
- Run `npm run lint` and `npm run format` to fix any linting/formatting issues

### 3. Write Comprehensive Unit and Feature Tests

- **Task ID**: write-settings-inheritance-tests
- **Depends On**: create-backend-settings-resolver
- **Assigned To**: settings-inheritance-tester
- **Agent Type**: coder
- **Parallel**: true (can run in parallel with task 2)
- Read `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/ProfileUpdateTest.php` for feature test patterns
- Read `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` for Pest configuration (Feature tests use RefreshDatabase automatically)
- Read `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` for available factory states
- Read `/Users/young/Nextcloud/dev/Itervel/database/factories/ProjectFactory.php` (created by E001-F010) for project factory definition
- Create `/Users/young/Nextcloud/dev/Itervel/tests/Unit/ProjectSettingsResolverTest.php` using `php artisan make:test ProjectSettingsResolverTest --pest --unit --no-interaction`:

    ```php
    <?php

    use App\Models\Project;
    use App\Models\User;
    use App\Services\ProjectSettingsResolver;

    beforeEach(function () {
        $this->resolver = new ProjectSettingsResolver();
    });

    test('resolves all settings from project when fully configured', function () {
        $user = User::factory()->create([
            'default_video_length' => 12,
            'default_speaking_pace' => 160,
            'default_script_iterations' => 3,
        ]);
        $project = Project::factory()->for($user)->create([
            'target_audience' => 'Tech enthusiasts aged 25-40',
            'tone' => 'Professional',
            'speaking_pace' => 180,
        ]);

        $result = $this->resolver->resolve($project, $user);

        expect($result)->toBe([
            'target_audience' => 'Tech enthusiasts aged 25-40',
            'tone' => 'Professional',
            'speaking_pace' => 180,
            'video_length' => 12,
            'script_iterations' => 3,
        ]);
    });

    test('falls back to user preferences when project settings are null', function () {
        $user = User::factory()->create([
            'default_video_length' => 15,
            'default_speaking_pace' => 170,
            'default_script_iterations' => 4,
        ]);
        $project = Project::factory()->for($user)->create([
            'target_audience' => null,
            'tone' => null,
            'speaking_pace' => null,
        ]);

        $result = $this->resolver->resolve($project, $user);

        expect($result['target_audience'])->toBeNull();
        expect($result['tone'])->toBeNull();
        expect($result['speaking_pace'])->toBe(170);
        expect($result['video_length'])->toBe(15);
        expect($result['script_iterations'])->toBe(4);
    });

    test('falls back to system defaults when both project and user settings are null', function () {
        $user = User::factory()->create([
            'default_video_length' => null,
            'default_speaking_pace' => null,
            'default_script_iterations' => null,
        ]);
        $project = Project::factory()->for($user)->create([
            'target_audience' => null,
            'tone' => null,
            'speaking_pace' => null,
        ]);

        $result = $this->resolver->resolve($project, $user);

        expect($result)->toBe([
            'target_audience' => null,
            'tone' => null,
            'speaking_pace' => 150,
            'video_length' => 10,
            'script_iterations' => 2,
        ]);
    });

    test('project speaking pace takes priority over user default speaking pace', function () {
        $user = User::factory()->create([
            'default_speaking_pace' => 160,
        ]);
        $project = Project::factory()->for($user)->create([
            'speaking_pace' => 200,
        ]);

        $result = $this->resolver->resolve($project, $user);

        expect($result['speaking_pace'])->toBe(200);
    });

    test('user speaking pace is used when project speaking pace is null', function () {
        $user = User::factory()->create([
            'default_speaking_pace' => 175,
        ]);
        $project = Project::factory()->for($user)->create([
            'speaking_pace' => null,
        ]);

        $result = $this->resolver->resolve($project, $user);

        expect($result['speaking_pace'])->toBe(175);
    });

    test('system default speaking pace is used when both project and user are null', function () {
        $user = User::factory()->create([
            'default_speaking_pace' => null,
        ]);
        $project = Project::factory()->for($user)->create([
            'speaking_pace' => null,
        ]);

        $result = $this->resolver->resolve($project, $user);

        expect($result['speaking_pace'])->toBe(150);
    });

    test('partial project settings are merged correctly', function () {
        $user = User::factory()->create([
            'default_video_length' => 8,
            'default_speaking_pace' => 140,
            'default_script_iterations' => 1,
        ]);
        $project = Project::factory()->for($user)->create([
            'target_audience' => 'Beginners',
            'tone' => null,
            'speaking_pace' => 190,
        ]);

        $result = $this->resolver->resolve($project, $user);

        expect($result['target_audience'])->toBe('Beginners');
        expect($result['tone'])->toBeNull();
        expect($result['speaking_pace'])->toBe(190);
        expect($result['video_length'])->toBe(8);
        expect($result['script_iterations'])->toBe(1);
    });

    test('system defaults method returns expected values', function () {
        $defaults = ProjectSettingsResolver::systemDefaults();

        expect($defaults)->toBe([
            'target_audience' => null,
            'tone' => null,
            'speaking_pace' => 150,
            'video_length' => 10,
            'script_iterations' => 2,
        ]);
    });

    test('resolve returns array with all expected keys', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $result = $this->resolver->resolve($project, $user);

        expect($result)->toHaveKeys([
            'target_audience',
            'tone',
            'speaking_pace',
            'video_length',
            'script_iterations',
        ]);
    });
    ```

- Note: The Unit tests need database access for factory usage. Since E001-F007 adds `default_video_length`, `default_speaking_pace`, `default_script_iterations` as non-nullable columns with defaults, in the tests we explicitly set these values. If E001-F007 has not been built yet when this feature is implemented, these columns won't exist. In that case, the resolver should gracefully handle missing attributes by using optional chaining (`$user->default_speaking_pace ?? ...`), and the tests should be adjusted to omit the user preference columns. The tests above assume E001-F007 is built. If not, the user preference fields can be omitted and the resolver will fall through to system defaults.
- Create `/Users/young/Nextcloud/dev/Itervel/tests/Feature/ProjectSettingsInheritanceTest.php` using `php artisan make:test ProjectSettingsInheritanceTest --pest --no-interaction`:

    ```php
    <?php

    use App\Models\Project;
    use App\Models\User;

    // --- Video Defaults Endpoint Tests ---

    test('guests cannot access video defaults endpoint', function () {
        $project = Project::factory()->for(User::factory())->create();

        $response = $this->getJson(route('projects.video-defaults', $project));

        $response->assertUnauthorized();
    });

    test('authenticated user can fetch video defaults for their project', function () {
        $user = User::factory()->create([
            'default_video_length' => 12,
            'default_speaking_pace' => 160,
            'default_script_iterations' => 3,
        ]);
        $project = Project::factory()->for($user)->create([
            'target_audience' => 'Developers',
            'tone' => 'Casual',
            'speaking_pace' => 175,
        ]);

        $response = $this->actingAs($user)->getJson(route('projects.video-defaults', $project));

        $response->assertOk();
        $response->assertExactJson([
            'target_audience' => 'Developers',
            'tone' => 'Casual',
            'speaking_pace' => 175,
            'video_length' => 12,
            'script_iterations' => 3,
        ]);
    });

    test('user cannot fetch video defaults for another users project', function () {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $response = $this->actingAs($otherUser)->getJson(route('projects.video-defaults', $project));

        $response->assertForbidden();
    });

    test('video defaults endpoint returns system defaults when project has no settings', function () {
        $user = User::factory()->create([
            'default_video_length' => null,
            'default_speaking_pace' => null,
            'default_script_iterations' => null,
        ]);
        $project = Project::factory()->for($user)->create([
            'target_audience' => null,
            'tone' => null,
            'speaking_pace' => null,
        ]);

        $response = $this->actingAs($user)->getJson(route('projects.video-defaults', $project));

        $response->assertOk();
        $response->assertJson([
            'target_audience' => null,
            'tone' => null,
            'speaking_pace' => 150,
            'video_length' => 10,
            'script_iterations' => 2,
        ]);
    });

    test('video defaults endpoint respects speaking pace priority chain', function () {
        $user = User::factory()->create([
            'default_speaking_pace' => 160,
        ]);
        $project = Project::factory()->for($user)->create([
            'speaking_pace' => 200,
        ]);

        $response = $this->actingAs($user)->getJson(route('projects.video-defaults', $project));

        $response->assertOk();
        $response->assertJsonFragment(['speaking_pace' => 200]);
    });

    test('video defaults endpoint falls back to user speaking pace when project has none', function () {
        $user = User::factory()->create([
            'default_speaking_pace' => 185,
        ]);
        $project = Project::factory()->for($user)->create([
            'speaking_pace' => null,
        ]);

        $response = $this->actingAs($user)->getJson(route('projects.video-defaults', $project));

        $response->assertOk();
        $response->assertJsonFragment(['speaking_pace' => 185]);
    });

    test('video defaults returns 404 for nonexistent project', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(route('projects.video-defaults', ['project' => 99999]));

        $response->assertNotFound();
    });

    test('video defaults includes all expected keys in response', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->getJson(route('projects.video-defaults', $project));

        $response->assertOk();
        $response->assertJsonStructure([
            'target_audience',
            'tone',
            'speaking_pace',
            'video_length',
            'script_iterations',
        ]);
    });

    test('video defaults merges project-only and user-only fields correctly', function () {
        $user = User::factory()->create([
            'default_video_length' => 15,
            'default_speaking_pace' => 140,
            'default_script_iterations' => 5,
        ]);
        $project = Project::factory()->for($user)->create([
            'target_audience' => 'Students',
            'tone' => 'Educational',
            'speaking_pace' => null,
        ]);

        $response = $this->actingAs($user)->getJson(route('projects.video-defaults', $project));

        $response->assertOk();
        $response->assertExactJson([
            'target_audience' => 'Students',
            'tone' => 'Educational',
            'speaking_pace' => 140,
            'video_length' => 15,
            'script_iterations' => 5,
        ]);
    });
    ```

- Run the unit tests: `php artisan test tests/Unit/ProjectSettingsResolverTest.php --compact`
- Run the feature tests: `php artisan test tests/Feature/ProjectSettingsInheritanceTest.php --compact`
- Fix any failing tests until all pass
- Run `vendor/bin/pint --dirty` to format the test files

### 4. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-backend-settings-resolver, create-frontend-hook-and-types, write-settings-inheritance-tests
- **Assigned To**: settings-inheritance-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed below
- Verify unit tests pass: `php artisan test tests/Unit/ProjectSettingsResolverTest.php --compact`
- Verify feature tests pass: `php artisan test tests/Feature/ProjectSettingsInheritanceTest.php --compact`
- Run the full test suite: `php artisan test --compact`
- Run TypeScript type checking: `npm run types`
- Run ESLint: `npm run lint`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Verify the following files exist and are correct:
    - `app/Services/ProjectSettingsResolver.php` has `resolve()` method and `SYSTEM_DEFAULTS` constant with correct priority chain logic
    - `app/Http/Controllers/VideoDefaultsController.php` has `show()` method with ownership check and uses constructor property promotion for `ProjectSettingsResolver`
    - Route `projects.video-defaults` is registered: `php artisan route:list --name=video-defaults`
    - `app/Http/Middleware/HandleInertiaRequests.php` shares `currentProjectDefaults` in the `share()` method
    - `resources/js/types/project-defaults.ts` exports `ProjectDefaults` type with all 5 fields
    - `resources/js/types/index.ts` exports `ProjectDefaults` type and `SharedData` includes `currentProjectDefaults`
    - `resources/js/hooks/use-project-defaults.ts` exports `useProjectDefaults` hook with correct fetch logic, loading, error, and refetch
    - `tests/Unit/ProjectSettingsResolverTest.php` has at least 8 tests covering the priority chain
    - `tests/Feature/ProjectSettingsInheritanceTest.php` has at least 9 tests covering authorization, response structure, and resolution logic
- Confirm all acceptance criteria are met
- Verify that the resolver correctly handles the case where E001-F007 user preference columns may not yet exist (graceful null fallthrough)

## Acceptance Criteria

- A `ProjectSettingsResolver` service class exists that resolves video creation settings by merging project settings, user preferences, and system defaults
- The resolution priority chain works correctly: project settings override user preferences, which override system defaults
- For `speaking_pace`: project value > user `default_speaking_pace` > system default (150)
- For `target_audience` and `tone`: project value > null (these are project-only fields)
- For `video_length` and `script_iterations`: user preferences > system defaults (these are user-only fields)
- A JSON endpoint exists at `GET /projects/{project}/video-defaults` that returns resolved settings
- The endpoint requires authentication and returns 401 for guests
- The endpoint returns 403 when a user tries to access another user's project defaults
- The endpoint returns 404 for nonexistent projects
- The endpoint response contains all 5 keys: `target_audience`, `tone`, `speaking_pace`, `video_length`, `script_iterations`
- The `HandleInertiaRequests` middleware shares `currentProjectDefaults` with all authenticated pages
- The `currentProjectDefaults` shared data is `null` for unauthenticated users or users with no projects
- A `useProjectDefaults` React hook exists for dynamically fetching defaults when switching projects
- A `ProjectDefaults` TypeScript type is exported from `@/types`
- The `SharedData` type includes `currentProjectDefaults: ProjectDefaults | null`
- All unit tests pass covering the priority chain with various configurations
- All feature tests pass covering the endpoint authorization, response, and resolution
- All existing tests continue to pass (no regressions)
- PHP code passes Pint formatting
- TypeScript passes type checking
- ESLint reports no errors

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run unit tests for the resolver
php artisan test tests/Unit/ProjectSettingsResolverTest.php --compact

# Run feature tests for the endpoint and integration
php artisan test tests/Feature/ProjectSettingsInheritanceTest.php --compact

# Run full test suite for regression check
php artisan test --compact

# Verify routes are registered
php artisan route:list --name=video-defaults

# TypeScript type checking
npm run types

# ESLint linting
npm run lint

# PHP code formatting
vendor/bin/pint --dirty
```

## Notes

- This feature creates the infrastructure (service class, endpoint, hook) that future video creation features will consume. The actual video creation pages (E001-F017 Topic Input, E001-F070 Step-by-Step Wizard) do not exist yet. When those features are built, they should:
    1. Access `currentProjectDefaults` from Inertia shared data (`usePage().props.currentProjectDefaults`) to pre-fill form fields on initial render.
    2. Use the `useProjectDefaults` hook if they need to dynamically refresh defaults when the user switches projects without a full page navigation.
- The `ProjectSettingsResolver` uses PHP's null coalescing operator (`??`) for the priority chain. This means a project `speaking_pace` of `0` would NOT fall through to user preferences (since `0` is not null). This is intentional -- if a project explicitly sets a value, it should be respected even if it's falsy. The project settings columns are nullable, so "not set" is represented as `null`, not `0`.
- E001-F007 (User Default Preferences) adds `default_video_length`, `default_speaking_pace`, and `default_script_iterations` as non-nullable columns with database-level defaults. However, the resolver uses null coalescing defensively in case those columns are nullable or the feature hasn't been built yet. The system defaults in `SYSTEM_DEFAULTS` match E001-F007's database defaults (10 minutes, 150 WPM, 2 iterations).
- The `HandleInertiaRequests` update for `currentProjectDefaults` depends on E001-F012's session-based active project tracking. If E001-F012 hasn't been built, the resolver can still be tested via the explicit JSON endpoint (which takes a `{project}` route parameter). The Inertia shared data part can be added when E001-F012's infrastructure exists.
- The `systemDefaults()` static method on `ProjectSettingsResolver` is provided as a convenience for other parts of the system that need to know the base defaults without resolving against a specific project/user (e.g., for displaying help text like "Default: 150 WPM").
- All `php artisan` commands should be run inside the Docker container. Use `make shell` to enter the container, or prefix with `docker compose exec app`.

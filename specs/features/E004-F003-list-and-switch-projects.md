# Feature: List and Switch Projects

**Epic**: E004-project-management.md
**Feature**: E004-F003
**Dependencies**: E004-F001

## Task Description

List and Switch Projects provides authenticated users with the ability to see all of their projects in a dropdown interface and switch the active (currently selected) project. This is a core navigation feature -- it appears in the sidebar and governs which project's context is active throughout the application. Each project in the list displays its video count so users can see at a glance how much content each project contains.

**What it does**: Shows all of a user's projects and lets them switch between them.

**Expected outcome**: The user sees all their projects in a dropdown or tab interface and can switch the active project. Each project shows its video count.

This feature builds on E001-F010 (Create Project), which establishes the `Project` model, `projects` table, `ProjectController` with index/create/store actions, `ProjectFactory`, `User::projects()` relationship, routes at `/projects`, and the sidebar "Projects" nav item. E001-F011 (Auto-Create Default Project) adds the `is_default` column to projects and creates a "My Channel" default project on first login.

The "active project" concept requires session-based state. When a user switches projects, the selected project ID is stored in the session and shared with all frontend pages via Inertia shared data. This allows any page in the application to know which project is currently active. The active project defaults to the user's default project (`is_default = true`) or their most recently created project if no default is set.

The video count requirement presents a consideration: the Video model does not exist yet (it will be created in a later feature). The implementation should use `withCount('videos')` on the Project model, which will return 0 for all projects until the Video model and `videos` relationship are established. This is safe because Eloquent's `withCount` gracefully handles undefined relationships by adding a `videos_count` attribute of 0. However, to avoid a runtime error when the `videos` table does not yet exist, a safer approach is to conditionally include the count only when the `videos` relationship is defined on the Project model, or to simply add a `videos()` HasMany relationship stub that returns an empty relationship. The simplest and cleanest approach is to add the `videos()` relationship on the Project model now (pointing to a not-yet-existing Video model) and use `withCount('videos')` -- since Eloquent's `withCount` generates a subquery, it will fail if the `videos` table does not exist. Therefore, the safest approach is to NOT use `withCount` yet, and instead pass `videos_count` as `0` for all projects. When the Video model and table are created later, the controller can be updated to use `withCount('videos')`. The frontend should still display the count so the UI is ready.

**Dependency on E001-F010 (Create Project)**: E001-F010 creates the Project model, migration, factory, controller, routes, and frontend pages. This feature extends the existing `ProjectController` and adds new functionality -- a `switchProject` endpoint, session-based active project tracking, and a sidebar project switcher dropdown component.

## Objective

Implement a project switcher dropdown in the sidebar that shows all of the user's projects (with video counts, defaulting to 0 until videos exist), allows switching the active project via a POST endpoint that stores the selection in the session, shares the current project and project list with all authenticated pages via Inertia shared data, and includes comprehensive Pest feature tests covering all switching, session persistence, and authorization scenarios.

## Solution Approach

### 1. Backend: Active Project Session Management

Add a `switchProject` method to the existing `ProjectController` that accepts a project ID, verifies the user owns the project, stores the project ID in the session, and redirects back. Also add a method to resolve the current active project from the session (falling back to the user's default project or first project).

The session key will be `current_project_id`. This is set when:

- The user explicitly switches projects via the new endpoint
- Automatically resolved on each request via the Inertia middleware (fallback logic)

```php
// In ProjectController
public function switchProject(Request $request, Project $project): RedirectResponse
{
    // Route model binding + authorization
    if ($project->user_id !== $request->user()->id) {
        abort(403);
    }

    $request->session()->put('current_project_id', $project->id);

    return back();
}
```

### 2. Route: Add switch project endpoint

Add a new POST route for switching projects:

```php
Route::post('projects/{project}/switch', [ProjectController::class, 'switchProject'])
    ->name('projects.switch');
```

### 3. Inertia Shared Data: Current Project and Project List

Update `HandleInertiaRequests::share()` to include the current project and all user projects for authenticated users. This data is needed by the sidebar project switcher on every page.

```php
// In HandleInertiaRequests::share()
'currentProject' => fn () => $request->user()
    ? $this->resolveCurrentProject($request)
    : null,
'projects' => fn () => $request->user()
    ? $request->user()->projects()->latest()->get()->map(fn ($project) => [
        'id' => $project->id,
        'name' => $project->name,
        'is_default' => $project->is_default,
        'videos_count' => 0, // Placeholder until Video model exists
    ])
    : [],
```

The `resolveCurrentProject` helper method:

```php
private function resolveCurrentProject(Request $request): ?array
{
    $user = $request->user();
    $projectId = $request->session()->get('current_project_id');

    $project = null;

    if ($projectId) {
        $project = $user->projects()->find($projectId);
    }

    // Fallback: default project, then first project
    if (!$project) {
        $project = $user->projects()->where('is_default', true)->first()
            ?? $user->projects()->first();

        // Store in session for future requests
        if ($project) {
            $request->session()->put('current_project_id', $project->id);
        }
    }

    return $project ? [
        'id' => $project->id,
        'name' => $project->name,
        'is_default' => $project->is_default,
        'videos_count' => 0,
    ] : null;
}
```

### 4. Frontend: Project Switcher Component

Create a `ProjectSwitcher` component that renders in the sidebar header area. It uses a `DropdownMenu` (already available) to show all projects with their video counts. The currently active project is highlighted. Clicking a different project submits a POST to the switch endpoint.

The component pattern follows the existing `NavUser` component which uses `DropdownMenu` with `SidebarMenuButton`. The project switcher should appear in the `SidebarHeader` area, above the main navigation.

```tsx
// Simplified structure
function ProjectSwitcher() {
    const { currentProject, projects } = usePage<SharedData>().props;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <SidebarMenuButton size="lg">
                    <FolderKanban />
                    <span>{currentProject?.name ?? 'Select Project'}</span>
                    <ChevronsUpDown className="ml-auto" />
                </SidebarMenuButton>
            </DropdownMenuTrigger>
            <DropdownMenuContent>
                {projects.map((project) => (
                    <DropdownMenuItem
                        key={project.id}
                        onClick={() => switchProject(project.id)}
                    >
                        {project.name}
                        <Badge variant="secondary">
                            {project.videos_count}
                        </Badge>
                        {project.id === currentProject?.id && <CheckIcon />}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
```

### 5. Update Sidebar to Include Project Switcher

Replace the static logo/header area in `AppSidebar` with the project switcher component, placed in the `SidebarHeader` section. The project switcher becomes the primary navigation context element.

### 6. TypeScript Types

Update `SharedData` to include `currentProject` and `projects`. Add a `ProjectSummary` type for the shared data format (lighter than the full `Project` type since it only includes fields needed for the switcher).

### 7. Update Projects Index Page

Enhance the existing projects index page (created by E001-F010) to show `videos_count` on each project card. Add a visual indicator for the currently active project. Add a "Switch" button on each project card.

### 8. Tests

Write comprehensive tests covering:

- Switching to a valid project stores it in the session
- Switching to another user's project returns 403
- Switching to a nonexistent project returns 404
- Session persists across requests
- Default project is auto-selected when no session exists
- Fallback to first project when no default exists
- Shared data includes currentProject and projects for authenticated users
- Shared data is null/empty for guests

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/ProjectController.php` -- Will exist after E001-F010. Must add `switchProject` method for handling project switching via POST.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Middleware/HandleInertiaRequests.php` -- Must add `currentProject` and `projects` to the shared data array so all pages have access to project context.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/Project.php` -- Will exist after E001-F010 with user() relationship, fillable, and casts. After E001-F011 will have `is_default` field. Reference for model structure.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` -- Will have `projects()` HasMany relationship after E001-F010. Reference for user-project relationship.
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Must add the `projects/{project}/switch` POST route inside the authenticated/verified middleware group.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/app-sidebar.tsx` -- Must integrate the `ProjectSwitcher` component in the `SidebarHeader` area, replacing or augmenting the current logo section.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/nav-user.tsx` -- Reference for `DropdownMenu` + `SidebarMenuButton` pattern used in the sidebar. The project switcher follows this exact pattern.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/dropdown-menu.tsx` -- Existing `DropdownMenu`, `DropdownMenuContent`, `DropdownMenuTrigger`, `DropdownMenuItem`, `DropdownMenuLabel`, `DropdownMenuSeparator` components used by the project switcher.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/badge.tsx` -- `Badge` component for displaying video counts next to project names.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/sidebar.tsx` -- Sidebar primitives (`SidebarMenu`, `SidebarMenuItem`, `SidebarMenuButton`, `useSidebar`) used by the switcher.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/app-sidebar-header.tsx` -- Reference for sidebar header layout. The project switcher may be placed alongside or near this area.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/app-logo.tsx` -- Currently rendered in sidebar header. Will coexist with the project switcher.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/index.ts` -- Must update `SharedData` type to include `currentProject` and `projects` fields.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/auth.ts` -- Reference for existing shared data types.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/navigation.ts` -- Reference for navigation types.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/hooks/use-current-url.ts` -- Reference for custom hook pattern; may inspire a `use-current-project` hook if needed.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/hooks/use-mobile.tsx` -- Reference for hook used by `NavUser` for responsive dropdown positioning.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/projects/index.tsx` -- Will exist after E001-F010. Must be enhanced to show video counts and active project indicator.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/dashboard.tsx` -- Reference for page component pattern with AppLayout, Head, breadcrumbs.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/profile.tsx` -- Reference for Inertia page with form submission and Wayfinder actions.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/app-layout.tsx` -- App layout wrapper; no changes needed but reference for understanding how shared data flows.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/app/app-sidebar-layout.tsx` -- Sidebar layout showing how `AppSidebar` is rendered; reference for where project switcher appears.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/lib/utils.ts` -- Utility functions including `cn()` helper for conditional class names.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/ProfileController.php` -- Reference for controller patterns (imports, return types, Inertia rendering).
- `/Users/young/Nextcloud/dev/Itervel/routes/settings.php` -- Reference for route grouping patterns with middleware.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/DashboardTest.php` -- Reference for simple feature test patterns.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/ProfileUpdateTest.php` -- Reference for comprehensive feature test patterns with Pest.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration. All Feature tests use `RefreshDatabase`.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` -- Will have `is_paid` field and `paid()` state after E001-F010. Reference for factory patterns.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/ProjectFactory.php` -- Will exist after E001-F010. Will have `is_default` after E001-F011. Needed for creating test projects.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/user-menu-content.tsx` -- Reference for how Inertia `router` is used for navigation actions with `DropdownMenuItem`.

### New Files

- `resources/js/components/project-switcher.tsx` -- Sidebar dropdown component showing all user projects with video counts, active project indicator, and switch functionality. Uses `DropdownMenu` and Inertia router for switching.
- `tests/Feature/ProjectSwitchTest.php` -- Pest feature tests for project switching, session persistence, authorization, fallback behavior, and shared data.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: project-switch-backend-dev
    - Role: Adds switchProject method to ProjectController, adds switch route to web.php, updates HandleInertiaRequests to share currentProject and projects data with resolveCurrentProject helper
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: project-switch-frontend-dev
    - Role: Creates the ProjectSwitcher component, integrates it into the sidebar, updates TypeScript types for SharedData, enhances the projects index page to show video counts and active project indicator
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: project-switch-test-dev
    - Role: Writes comprehensive Pest feature tests covering project switching, session persistence, authorization, fallback behavior, and Inertia shared data
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: project-switch-reviewer
    - Role: Validates the complete feature against acceptance criteria, runs all tests, checks TypeScript types, runs linting and formatting
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Add Switch Endpoint, Route, and Shared Data to Backend

- **Task ID**: create-switch-backend
- **Depends On**: none
- **Assigned To**: project-switch-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- IMPORTANT: This feature depends on E001-F010 (Create Project) and E001-F011 (Auto-Create Default Project). Before starting, verify the following exist:
    - `app/Models/Project.php` exists with `user()` relationship and `is_default` in fillable/casts
    - `app/Models/User.php` has `projects()` HasMany relationship
    - `app/Http/Controllers/ProjectController.php` exists with `index`, `create`, `store` methods
    - Routes `projects.index`, `projects.create`, `projects.store` exist
    - If any are missing, flag a dependency issue.
- Edit `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/ProjectController.php`:
    - Add `use App\Models\Project;` import if not already present
    - Add a new `switchProject` method:

        ```php
        public function switchProject(Request $request, Project $project): RedirectResponse
        {
            if ($project->user_id !== $request->user()->id) {
                abort(403);
            }

            $request->session()->put('current_project_id', $project->id);

            return back();
        }
        ```

    - Ensure `App\Models\Project` is imported

- Edit `/Users/young/Nextcloud/dev/Itervel/routes/web.php`:
    - Inside the existing `Route::middleware(['auth', 'verified'])->group()` block that contains the project routes, add:
        ```php
        Route::post('projects/{project}/switch', [ProjectController::class, 'switchProject'])->name('projects.switch');
        ```
- Edit `/Users/young/Nextcloud/dev/Itervel/app/Http/Middleware/HandleInertiaRequests.php`:
    - Add `use App\Models\Project;` import at the top
    - In the `share()` method's return array, add these entries after `'sidebarOpen'`:

        ```php
        'currentProject' => function () use ($request) {
            if (! $request->user()) {
                return null;
            }

            return $this->resolveCurrentProject($request);
        },
        'projects' => function () use ($request) {
            if (! $request->user()) {
                return [];
            }

            return $request->user()->projects()->latest()->get()->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'is_default' => $project->is_default ?? false,
                'videos_count' => 0,
            ])->values()->all();
        },
        ```

    - Add a private `resolveCurrentProject` method to the class:

        ```php
        /**
         * Resolve the user's currently active project from session, falling back to defaults.
         *
         * @return array<string, mixed>|null
         */
        private function resolveCurrentProject(Request $request): ?array
        {
            $user = $request->user();
            $projectId = $request->session()->get('current_project_id');

            $project = null;

            if ($projectId) {
                $project = $user->projects()->find($projectId);
            }

            if (! $project) {
                $project = $user->projects()->where('is_default', true)->first()
                    ?? $user->projects()->latest()->first();

                if ($project) {
                    $request->session()->put('current_project_id', $project->id);
                }
            }

            if (! $project) {
                return null;
            }

            return [
                'id' => $project->id,
                'name' => $project->name,
                'is_default' => $project->is_default ?? false,
                'videos_count' => 0,
            ];
        }
        ```

- Run `vendor/bin/pint --dirty` to fix any PHP formatting issues
- Verify the new route is registered: `php artisan route:list --name=projects.switch`
- Verify all existing project routes still work: `php artisan route:list --name=projects`

### 2. Create Project Switcher Component, Update Sidebar, Update Types, and Enhance Projects Index

- **Task ID**: create-frontend-switcher
- **Depends On**: create-switch-backend
- **Assigned To**: project-switch-frontend-dev
- **Agent Type**: coder
- **Parallel**: false
- First, update TypeScript types in `/Users/young/Nextcloud/dev/Itervel/resources/js/types/index.ts`:
    - Add a `ProjectSummary` type export (either inline or in a separate file) and update `SharedData`:

        ```typescript
        export type ProjectSummary = {
            id: number;
            name: string;
            is_default: boolean;
            videos_count: number;
        };

        export type SharedData = {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            currentProject: ProjectSummary | null;
            projects: ProjectSummary[];
            [key: string]: unknown;
        };
        ```

    - Keep the existing `export type * from './auth';`, `export type * from './navigation';`, `export type * from './ui';` lines

- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/components/project-switcher.tsx`:
    - Read `/Users/young/Nextcloud/dev/Itervel/resources/js/components/nav-user.tsx` for the exact pattern of using `DropdownMenu` with `SidebarMenuButton` in the sidebar
    - Import necessary components:
        - `{ router, usePage }` from `@inertiajs/react`
        - `{ ChevronsUpDown, Check, FolderKanban, Plus }` from `lucide-react`
        - `{ DropdownMenu, DropdownMenuContent, DropdownMenuTrigger, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator }` from `@/components/ui/dropdown-menu`
        - `{ SidebarMenu, SidebarMenuButton, SidebarMenuItem, useSidebar }` from `@/components/ui/sidebar`
        - `{ Badge }` from `@/components/ui/badge`
        - `{ useIsMobile }` from `@/hooks/use-mobile`
        - `type { SharedData }` from `@/types`
    - Import Wayfinder routes for project switching and projects index:
        - The switch route will be generated as `@/routes/projects` after `npm run build` -- it will export a `switchProject` function that takes `{ project: number }` parameter
        - For the "View All" link, import projects index route from `@/routes/projects`
    - Build the component following the `NavUser` pattern:

        ```tsx
        export function ProjectSwitcher() {
            const { currentProject, projects } = usePage<SharedData>().props;
            const { state } = useSidebar();
            const isMobile = useIsMobile();

            const handleSwitch = (projectId: number) => {
                router.post(
                    `/projects/${projectId}/switch`,
                    {},
                    { preserveScroll: true, preserveState: true },
                );
            };

            return (
                <SidebarMenu>
                    <SidebarMenuItem>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <SidebarMenuButton
                                    size="lg"
                                    className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                                >
                                    <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                                        <FolderKanban className="size-4" />
                                    </div>
                                    <div className="grid flex-1 text-left text-sm leading-tight">
                                        <span className="truncate font-medium">
                                            {currentProject?.name ??
                                                'No Project'}
                                        </span>
                                        <span className="truncate text-xs text-muted-foreground">
                                            {currentProject
                                                ? `${currentProject.videos_count} videos`
                                                : 'Select a project'}
                                        </span>
                                    </div>
                                    <ChevronsUpDown className="ml-auto size-4" />
                                </SidebarMenuButton>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                                align="start"
                                side={
                                    isMobile
                                        ? 'bottom'
                                        : state === 'collapsed'
                                          ? 'right'
                                          : 'bottom'
                                }
                                sideOffset={4}
                            >
                                <DropdownMenuLabel className="text-xs text-muted-foreground">
                                    Projects
                                </DropdownMenuLabel>
                                {projects.map((project) => (
                                    <DropdownMenuItem
                                        key={project.id}
                                        onClick={() => handleSwitch(project.id)}
                                        className="gap-2 p-2"
                                    >
                                        <div className="flex size-6 items-center justify-center rounded-sm border">
                                            <FolderKanban className="size-4 shrink-0" />
                                        </div>
                                        <span className="flex-1 truncate">
                                            {project.name}
                                        </span>
                                        <Badge
                                            variant="secondary"
                                            className="ml-auto"
                                        >
                                            {project.videos_count}
                                        </Badge>
                                        {project.id === currentProject?.id && (
                                            <Check className="size-4 shrink-0" />
                                        )}
                                    </DropdownMenuItem>
                                ))}
                                <DropdownMenuSeparator />
                                <DropdownMenuItem asChild>
                                    <a href="/projects" className="gap-2 p-2">
                                        <Plus className="size-4" />
                                        <span>Manage projects</span>
                                    </a>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </SidebarMenuItem>
                </SidebarMenu>
            );
        }
        ```

    - NOTE: The Wayfinder route for `projects.switch` will be auto-generated after `npm run build`. Until then, the component can use a direct URL string `/projects/${projectId}/switch` with `router.post()`. After Wayfinder generates, it can be replaced with the generated route function. Using `router.post()` directly with the URL is acceptable.

- Edit `/Users/young/Nextcloud/dev/Itervel/resources/js/components/app-sidebar.tsx`:
    - Add `import { ProjectSwitcher } from '@/components/project-switcher';`
    - In the `SidebarHeader` section, add the `ProjectSwitcher` component. The current content of `SidebarHeader` is a logo link. Place the `ProjectSwitcher` below the logo or replace the logo link:
        ```tsx
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" asChild>
                        <Link href={dashboard()} prefetch>
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <ProjectSwitcher />
        </SidebarHeader>
        ```
- Enhance `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/projects/index.tsx` (created by E001-F010):
    - Read the existing file first to understand its current structure
    - Import `usePage` from `@inertiajs/react` and `type { SharedData }` from `@/types`
    - Import `Badge` from `@/components/ui/badge`
    - Import `router` from `@inertiajs/react`
    - Update each project card to show `videos_count` using a `Badge` component (display `0 videos` for now)
    - Add a visual indicator (e.g., a border color or badge) for the currently active project by comparing `project.id` against `currentProject?.id` from shared data
    - Add a "Switch to this project" button on each project card that calls `router.post(/projects/${project.id}/switch)` -- or use a simple link/button pattern
    - If the project is already the active project, show "Active" instead of the switch button
- Run `npm run build` to generate Wayfinder routes and compile assets
- Verify no TypeScript errors: `npm run types`
- Run `npm run lint` and fix any issues with `npm run lint:fix`
- Run `npm run format` to ensure Prettier formatting

### 3. Write Comprehensive Project Switch Feature Tests

- **Task ID**: write-switch-tests
- **Depends On**: create-switch-backend
- **Assigned To**: project-switch-test-dev
- **Agent Type**: coder
- **Parallel**: true (can run in parallel with task 2)
- Create `/Users/young/Nextcloud/dev/Itervel/tests/Feature/ProjectSwitchTest.php` using `php artisan make:test ProjectSwitchTest --pest --no-interaction`
- Read existing test files for pattern reference: `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/ProfileUpdateTest.php` and `/Users/young/Nextcloud/dev/Itervel/tests/Feature/DashboardTest.php`
- Write the following tests:

    ```php
    <?php

    use App\Models\Project;
    use App\Models\User;

    // --- Switch Project Endpoint Tests ---

    test('authenticated user can switch to their own project', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)
            ->post(route('projects.switch', $project));

        $response->assertRedirect();
        expect(session('current_project_id'))->toBe($project->id);
    });

    test('user cannot switch to another users project', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherProject = Project::factory()->for($otherUser)->create();

        $response = $this->actingAs($user)
            ->post(route('projects.switch', $otherProject));

        $response->assertForbidden();
        expect(session()->has('current_project_id'))->toBeFalse();
    });

    test('guest cannot switch projects', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->post(route('projects.switch', $project));

        $response->assertRedirect(route('login'));
    });

    test('switching to nonexistent project returns 404', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('projects.switch', ['project' => 99999]));

        $response->assertNotFound();
    });

    test('switching project updates session and persists across requests', function () {
        $user = User::factory()->create();
        $projectA = Project::factory()->for($user)->create(['name' => 'Project A']);
        $projectB = Project::factory()->for($user)->create(['name' => 'Project B']);

        $this->actingAs($user)
            ->post(route('projects.switch', $projectA));

        expect(session('current_project_id'))->toBe($projectA->id);

        // Switch to project B
        $this->actingAs($user)
            ->post(route('projects.switch', $projectB));

        expect(session('current_project_id'))->toBe($projectB->id);
    });

    // --- Shared Data / Inertia Tests ---

    test('dashboard includes currentProject and projects in shared data for authenticated user', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create([
            'name' => 'My Channel',
            'is_default' => true,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('currentProject')
            ->where('currentProject.id', $project->id)
            ->where('currentProject.name', 'My Channel')
            ->has('projects', 1)
            ->where('projects.0.id', $project->id)
            ->where('projects.0.name', 'My Channel')
            ->where('projects.0.videos_count', 0)
        );
    });

    test('shared data shows null currentProject and empty projects for guests', function () {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('currentProject', null)
            ->where('projects', [])
        );
    });

    test('default project is auto-selected when no session exists', function () {
        $user = User::factory()->create();
        $regularProject = Project::factory()->for($user)->create([
            'name' => 'Regular Project',
            'is_default' => false,
        ]);
        $defaultProject = Project::factory()->for($user)->create([
            'name' => 'Default Project',
            'is_default' => true,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('currentProject.id', $defaultProject->id)
            ->where('currentProject.name', 'Default Project')
        );

        expect(session('current_project_id'))->toBe($defaultProject->id);
    });

    test('falls back to latest project when no default exists and no session', function () {
        $user = User::factory()->create();
        $olderProject = Project::factory()->for($user)->create([
            'name' => 'Older Project',
            'is_default' => false,
            'created_at' => now()->subDay(),
        ]);
        $newerProject = Project::factory()->for($user)->create([
            'name' => 'Newer Project',
            'is_default' => false,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('currentProject.id', $newerProject->id)
            ->where('currentProject.name', 'Newer Project')
        );
    });

    test('currentProject is null when user has no projects', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('currentProject', null)
        );
    });

    test('session project is used when it exists and belongs to user', function () {
        $user = User::factory()->create();
        $projectA = Project::factory()->for($user)->create(['name' => 'Project A', 'is_default' => true]);
        $projectB = Project::factory()->for($user)->create(['name' => 'Project B', 'is_default' => false]);

        // First, switch to project B
        $this->actingAs($user)
            ->post(route('projects.switch', $projectB));

        // Now check that dashboard uses project B (not the default A)
        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('currentProject.id', $projectB->id)
            ->where('currentProject.name', 'Project B')
        );
    });

    test('stale session project falls back to default when project is deleted', function () {
        $user = User::factory()->create();
        $projectA = Project::factory()->for($user)->create(['name' => 'Project A', 'is_default' => false]);
        $projectB = Project::factory()->for($user)->create(['name' => 'Project B', 'is_default' => true]);

        // Switch to project A
        $this->actingAs($user)
            ->post(route('projects.switch', $projectA));

        expect(session('current_project_id'))->toBe($projectA->id);

        // Delete project A
        $projectA->delete();

        // Dashboard should fall back to default project B
        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('currentProject.id', $projectB->id)
            ->where('currentProject.name', 'Project B')
        );
    });

    test('projects in shared data include all user projects', function () {
        $user = User::factory()->create();
        Project::factory()->for($user)->count(3)->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->has('projects', 3)
        );
    });

    test('projects in shared data do not include other users projects', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Project::factory()->for($user)->create(['name' => 'My Project']);
        Project::factory()->for($otherUser)->create(['name' => 'Other Project']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->has('projects', 1)
            ->where('projects.0.name', 'My Project')
        );
    });

    test('each project in shared data includes videos_count', function () {
        $user = User::factory()->create();
        Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->has('projects.0.videos_count')
            ->where('projects.0.videos_count', 0)
        );
    });

    test('projects index page is accessible and includes shared project data', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['is_default' => true]);

        $response = $this->actingAs($user)->get(route('projects.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('currentProject')
            ->where('currentProject.id', $project->id)
        );
    });
    ```

- Run the tests: `php artisan test tests/Feature/ProjectSwitchTest.php --compact`
- Fix any failing tests until all pass
- Run `vendor/bin/pint --dirty` to fix formatting

### 4. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-switch-backend, create-frontend-switcher, write-switch-tests
- **Assigned To**: project-switch-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed below
- Verify project switch tests pass: `php artisan test tests/Feature/ProjectSwitchTest.php --compact`
- Run the full test suite to check for regressions: `php artisan test --compact`
- Run TypeScript type checking: `npm run types`
- Run ESLint: `npm run lint`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Verify the following files exist and are correct:
    - `app/Http/Controllers/ProjectController.php` has `switchProject(Request $request, Project $project)` method
    - `app/Http/Middleware/HandleInertiaRequests.php` shares `currentProject` and `projects` with all pages
    - `app/Http/Middleware/HandleInertiaRequests.php` has `resolveCurrentProject` method with fallback logic
    - `routes/web.php` has `projects/{project}/switch` POST route named `projects.switch`
    - `resources/js/components/project-switcher.tsx` renders a dropdown with all user projects, video counts, active indicator, and switch functionality
    - `resources/js/components/app-sidebar.tsx` includes `<ProjectSwitcher />` in the sidebar header
    - `resources/js/types/index.ts` has `ProjectSummary` type and updated `SharedData` type with `currentProject` and `projects`
    - `resources/js/pages/projects/index.tsx` shows video counts and active project indicator
    - `tests/Feature/ProjectSwitchTest.php` has all test cases covering switching, authorization, session persistence, fallback, and shared data
- Verify routes exist: `php artisan route:list --name=projects`
- Confirm all acceptance criteria are met

## Acceptance Criteria

- Authenticated users see a project switcher dropdown in the sidebar header showing all their projects
- Each project in the dropdown displays its name and video count (0 until videos feature is built)
- The currently active project is visually indicated with a check mark in the dropdown
- Clicking a different project in the dropdown switches the active project via POST and stores the selection in the session
- The active project persists across page navigations (session-based)
- When no session exists, the user's default project (`is_default = true`) is auto-selected
- When no default project exists, the latest project is auto-selected as fallback
- When the user has no projects, `currentProject` is null and the switcher shows "No Project"
- When a session references a deleted project, the fallback logic recovers gracefully to the default or latest project
- Users cannot switch to another user's project (returns 403)
- Guests cannot access the switch endpoint (redirected to login)
- Switching to a nonexistent project returns 404
- `currentProject` and `projects` are shared with ALL authenticated Inertia pages via `HandleInertiaRequests`
- Guest pages receive `null` for `currentProject` and `[]` for `projects`
- The projects index page shows video counts and an active project indicator per card
- The "Manage projects" link in the dropdown navigates to the projects index page
- All project switch tests pass
- All existing tests continue to pass (no regressions)
- PHP code passes Pint formatting
- TypeScript passes type checking
- ESLint reports no errors

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run project switch specific tests
php artisan test tests/Feature/ProjectSwitchTest.php --compact

# Run all project-related tests
php artisan test tests/Feature/ProjectTest.php tests/Feature/ProjectSwitchTest.php --compact

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

- **Video count is 0 for now**: The Video model and `videos` table do not exist yet. The `videos_count` is hardcoded to 0 in the shared data. When the Video model is created later (with a `project_id` foreign key), the `HandleInertiaRequests` middleware should be updated to use `withCount('videos')` on the projects query. The frontend already displays the count, so only the backend query needs updating.
- **Session-based active project**: The active project is stored in the session rather than a database column. This is simpler, avoids an additional migration, and allows different browser sessions to have different active projects. The trade-off is that the active project resets when the session expires, but the fallback logic handles this gracefully.
- **Relationship to E001-F010**: E001-F010 creates the foundational Project model, routes, and pages. This feature extends the controller with `switchProject`, adds the session-based switching mechanism, and integrates the project switcher into the global sidebar.
- **Relationship to E001-F011**: E001-F011 adds the `is_default` column and auto-creates a default project on first login. The fallback logic in `resolveCurrentProject` uses `is_default` to determine which project to auto-select when no session exists.
- **Relationship to E001-F013 (Edit Project Settings)**: Edit will allow changing project name, which will be reflected in the switcher immediately on next page load since the switcher reads from the shared data.
- **Relationship to E001-F014 (Delete Project)**: When a project is deleted, if it was the active project in the session, the fallback logic will detect the stale reference and recover to the default/latest project.
- **Relationship to E001-F083 (Set Default Project)**: That feature will allow users to change which project is their default. The switcher's fallback logic already supports this by querying `is_default = true`.
- **Wayfinder route generation**: The `projects.switch` route will generate a Wayfinder function at `@/routes/projects/{project}/switch` or similar path after `npm run build`. Until Wayfinder generates the route, the frontend component should use `router.post()` with a constructed URL string. This is the established pattern when routes are new and Wayfinder has not yet regenerated.
- **The `resolveCurrentProject` method uses lazy closures** (wrapped in `function () use ($request) { ... }`) in the shared data. This ensures the database queries are only executed when the data is actually needed, following Inertia's lazy evaluation pattern for shared data.
- **All `php artisan` commands should be run inside the Docker container.** Use `docker compose exec app` to prefix commands, or `make shell` to enter the container.
- **The `ProjectSwitcher` component pattern follows `NavUser`**: Both use `DropdownMenu` with `SidebarMenuButton` as the trigger. The project switcher is placed in `SidebarHeader` while `NavUser` is in `SidebarFooter`. This provides visual consistency and follows the shadcn/ui sidebar pattern for workspace/team switchers.

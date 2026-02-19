# Feature: Create Project

**Epic**: E004-project-management.md
**Feature**: E004-F001
**Epic depends on**: E002-user-authentication.md
**Feature depends on**: E002-F003

## Task Description

Create Project allows authenticated users to create separate projects to organize their videos by YouTube channel or content category. A project acts as a container with its own name and optional settings (target audience, tone, speaking pace) that can later be inherited by videos created within it.

**What it does**: Lets users create separate projects to organize videos by YouTube channel or content category.

**Expected outcome**: The user creates a named project with optional settings like target audience, tone, and speaking pace. Free users can have 1 project; paid users can have unlimited projects.

This feature introduces the first domain model beyond the User model. It requires a new `Project` model with a `projects` table, a new `ProjectController` with `index`, `create`, and `store` actions, associated Form Request validation, a new frontend page with a creation form, and comprehensive tests.

The project limit enforcement (1 for free users, unlimited for paid users) requires a way to distinguish free vs paid users. Since there is no subscription/payment system yet, the simplest approach is to add a boolean `is_paid` column on the `users` table (defaulting to `false`). This allows the limit logic to be implemented now and will be updated later when the payment/subscription system is built (E001-F060+). Alternatively, the limit can be determined by checking if the user has ever purchased credits. For simplicity and decoupling, a boolean flag is the cleanest approach.

**Dependency on E001-F003 (User Login)**: The login feature configures authentication sessions, rate limiting, and remember-me behavior. All project routes require the `auth` and `verified` middleware, so users must be logged in to create projects. The login feature ensures the auth infrastructure is fully configured before this feature builds on it.

## Objective

Implement the Project model with a `projects` database table, a ProjectController with index/create/store actions, Form Request validation enforcing the project limit (1 for free, unlimited for paid), an Inertia React page for listing and creating projects, sidebar navigation integration, and comprehensive Pest feature tests covering all CRUD and authorization scenarios.

## Solution Approach

### 1. Database: Project model and migration

Create a `projects` migration with the following schema:

```php
Schema::create('projects', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->string('target_audience')->nullable();
    $table->string('tone')->nullable();
    $table->unsignedSmallInteger('speaking_pace')->nullable(); // words per minute
    $table->timestamps();

    $table->index('user_id');
});
```

Add a `is_paid` boolean column to the `users` table to support the project limit enforcement:

```php
Schema::table('users', function (Blueprint $table) {
    $table->boolean('is_paid')->default(false)->after('password');
});
```

### 2. Eloquent Model: Project

The `Project` model belongs to a `User`, and the `User` has many `Project`s.

```php
// app/Models/Project.php
class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'target_audience',
        'tone',
        'speaking_pace',
    ];

    protected function casts(): array
    {
        return [
            'speaking_pace' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

Add a `projects()` relationship and `is_paid` cast to the `User` model:

```php
// In User model
public function projects(): HasMany
{
    return $this->hasMany(Project::class);
}

// In casts() method
'is_paid' => 'boolean',
```

### 3. Form Request Validation: StoreProjectRequest

Create `App\Http\Requests\StoreProjectRequest` with validation rules and project limit enforcement:

```php
class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        // Paid users have unlimited projects
        if ($user->is_paid) {
            return true;
        }

        // Free users can have at most 1 project
        return $user->projects()->count() < 1;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'target_audience' => ['nullable', 'string', 'max:255'],
            'tone' => ['nullable', 'string', 'max:255'],
            'speaking_pace' => ['nullable', 'integer', 'min:80', 'max:300'],
        ];
    }
}
```

When authorization fails, Laravel automatically returns a 403 response. The frontend should handle this by showing an upgrade prompt.

### 4. Controller: ProjectController

Create `App\Http\Controllers\ProjectController` with index, create, and store methods:

```php
class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('projects/index', [
            'projects' => $request->user()->projects()->latest()->get(),
            'canCreateProject' => $request->user()->is_paid || $request->user()->projects()->count() < 1,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('projects/create');
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $request->user()->projects()->create($request->validated());

        return to_route('projects.index');
    }
}
```

### 5. Routes

Add project routes to `routes/web.php` inside the authenticated and verified middleware group:

```php
use App\Http\Controllers\ProjectController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
});
```

### 6. Frontend: Projects Index Page

Create `resources/js/pages/projects/index.tsx` that lists existing projects and provides a "New Project" button. Uses `AppLayout` and the existing `Card`, `Button`, and `Heading` components.

### 7. Frontend: Create Project Page

Create `resources/js/pages/projects/create.tsx` with a form containing:

- Name (required text input)
- Target Audience (optional text input)
- Tone (optional text input)
- Speaking Pace (optional number input, words per minute)

Use Inertia's `<Form>` component with Wayfinder-generated actions for the store route.

### 8. Sidebar Navigation

Update `resources/js/components/app-sidebar.tsx` to add a "Projects" nav item in the main navigation using the Wayfinder-generated route.

### 9. TypeScript Types

Add a `Project` type to `resources/js/types/index.ts` or a new `project.ts` file:

```typescript
export type Project = {
    id: number;
    name: string;
    target_audience: string | null;
    tone: string | null;
    speaking_pace: number | null;
    created_at: string;
    updated_at: string;
};
```

### 10. Textarea Component

The form needs a textarea for optional fields like target audience. Since there is no Textarea UI component yet, create one following the existing Input component pattern.

### 11. Factory and Seeder

Create a `ProjectFactory` for test setup and a `ProjectSeeder` for development data.

### 12. Tests

Write comprehensive Pest feature tests in `tests/Feature/ProjectTest.php` covering:

- Projects index page displays for authenticated users
- Guests are redirected to login
- Project can be created with valid data
- Project creation requires a name
- Project creation validates field constraints
- Free users cannot create more than 1 project (403 response)
- Paid users can create unlimited projects
- Project settings (target_audience, tone, speaking_pace) are stored correctly
- Project belongs to the authenticated user

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` -- Must add `projects()` HasMany relationship and `is_paid` cast. Currently the only model in the project.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Controller.php` -- Base abstract controller that ProjectController will extend.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/ProfileController.php` -- Reference for controller patterns (constructor, imports, return types, Inertia rendering).
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/Settings/ProfileUpdateRequest.php` -- Reference for Form Request patterns (array-syntax rules, return types).
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Must add project routes in authenticated/verified middleware group.
- `/Users/young/Nextcloud/dev/Itervel/routes/settings.php` -- Reference for route grouping patterns with middleware.
- `/Users/young/Nextcloud/dev/Itervel/database/migrations/0001_01_01_000000_create_users_table.php` -- Reference for migration patterns. The new `is_paid` column migration alters this table.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` -- Must add `is_paid` to default state (false) and add a `paid()` factory state.
- `/Users/young/Nextcloud/dev/Itervel/database/seeders/DatabaseSeeder.php` -- Reference for seeder patterns. May call ProjectSeeder.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/dashboard.tsx` -- Reference for Inertia page component pattern (AppLayout, breadcrumbs, Head, Wayfinder routes).
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/profile.tsx` -- Reference for form page pattern (Form component, Wayfinder actions, InputError, Label, Input, Button).
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/app-sidebar.tsx` -- Must add "Projects" nav item to `mainNavItems` array.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/heading.tsx` -- Reusable heading component for page titles.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/input-error.tsx` -- Reusable validation error display component.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/card.tsx` -- Card UI component for project listing cards.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/button.tsx` -- Button UI component.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/input.tsx` -- Input UI component for form fields.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/label.tsx` -- Label UI component for form fields.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/select.tsx` -- Select UI component (potential use for tone dropdown).
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/index.ts` -- Must export new Project type and update SharedData if needed.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/auth.ts` -- User type definition. May need updating if `is_paid` is exposed to frontend.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/navigation.ts` -- NavItem type for sidebar nav.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/app-layout.tsx` -- App layout wrapper used by all authenticated pages.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/lib/utils.ts` -- Utility functions (cn helper).
- `/Users/young/Nextcloud/dev/Itervel/bootstrap/app.php` -- Middleware and routing configuration. Reference only.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/DashboardTest.php` -- Reference for simple feature test patterns.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/ProfileUpdateTest.php` -- Reference for comprehensive CRUD test patterns.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration. All Feature tests use RefreshDatabase.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Middleware/HandleInertiaRequests.php` -- Shares auth data with frontend. Reference only; no changes needed.
- `/Users/young/Nextcloud/dev/Itervel/config/fortify.php` -- Fortify configuration. Reference for understanding middleware setup.

### New Files

- `database/migrations/xxxx_xx_xx_xxxxxx_create_projects_table.php` -- Migration for the `projects` table (id, user_id, name, target_audience, tone, speaking_pace, timestamps).
- `database/migrations/xxxx_xx_xx_xxxxxx_add_is_paid_to_users_table.php` -- Migration to add `is_paid` boolean to the `users` table.
- `app/Models/Project.php` -- Eloquent model for projects with fillable fields, casts, and user() relationship.
- `app/Http/Controllers/ProjectController.php` -- Controller with index, create, and store methods.
- `app/Http/Requests/StoreProjectRequest.php` -- Form Request with authorization (project limit check) and validation rules.
- `database/factories/ProjectFactory.php` -- Factory for creating test projects.
- `database/seeders/ProjectSeeder.php` -- Seeder for development project data.
- `resources/js/pages/projects/index.tsx` -- Inertia page listing user's projects with a "New Project" button.
- `resources/js/pages/projects/create.tsx` -- Inertia page with a form to create a new project.
- `resources/js/types/project.ts` -- TypeScript type definition for the Project model.
- `resources/js/components/ui/textarea.tsx` -- Textarea UI component following the existing Input pattern.
- `tests/Feature/ProjectTest.php` -- Pest feature tests for project creation.
- `tests/Browser/ProjectTest.php` -- Pest browser tests: smoke test for projects index and create pages, dark mode spot check, and core project creation flow test using `data-test` selectors.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: project-backend-dev
    - Role: Creates the database migrations, Project model, ProjectFactory, ProjectSeeder, StoreProjectRequest, ProjectController, updates the User model, updates routes, and updates the UserFactory with `is_paid` and `paid()` state
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: project-frontend-dev
    - Role: Creates the projects index page, create project page, Textarea UI component, Project TypeScript type, updates the sidebar navigation, and updates the User TypeScript type
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: project-test-dev
    - Role: Writes comprehensive Pest feature tests covering all project creation scenarios including authorization, validation, and limit enforcement
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: project-reviewer
    - Role: Validates the complete feature against acceptance criteria, runs all tests, checks TypeScript types, runs linting and formatting
    - Agent Type: reviewer
    - Resume: false

- Browser Test Developer
    - Name: project-browser-test-dev
    - Role: Writes Pest browser tests (smoke tests, dark mode checks, interactive flow tests) for the projects index and create pages
    - Agent Type: coder
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Create Database Migrations, Project Model, Factory, Seeder, and Update User Model

- **Task ID**: create-backend-foundation
- **Depends On**: none
- **Assigned To**: project-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- Use `php artisan make:model Project -mfs --no-interaction` inside the Docker container (`make shell` or `docker compose exec app`) to create the Project model, migration, factory, and seeder
- Edit the generated migration to define the `projects` table schema:
    - `$table->id()`
    - `$table->foreignId('user_id')->constrained()->cascadeOnDelete()`
    - `$table->string('name')`
    - `$table->string('target_audience')->nullable()`
    - `$table->string('tone')->nullable()`
    - `$table->unsignedSmallInteger('speaking_pace')->nullable()` with a comment "words per minute"
    - `$table->timestamps()`
    - `$table->index('user_id')`
- Create a second migration for the `is_paid` column on users: `php artisan make:migration add_is_paid_to_users_table --table=users --no-interaction`
    - In `up()`: `$table->boolean('is_paid')->default(false)->after('password')`
    - In `down()`: `$table->dropColumn('is_paid')`
- Edit `/Users/young/Nextcloud/dev/Itervel/app/Models/Project.php`:
    - Add `use HasFactory` trait
    - Set `$fillable` to `['name', 'target_audience', 'tone', 'speaking_pace']`
    - Add `casts()` method returning `['speaking_pace' => 'integer']`
    - Add `user(): BelongsTo` relationship method returning `$this->belongsTo(User::class)`
    - Add proper import statements: `use Illuminate\Database\Eloquent\Relations\BelongsTo`
- Edit `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php`:
    - Add `use Illuminate\Database\Eloquent\Relations\HasMany` import
    - Add `projects(): HasMany` relationship method returning `$this->hasMany(Project::class)`
    - Add `'is_paid' => 'boolean'` to the `casts()` method return array
    - Add `'is_paid'` to the `$fillable` array
- Edit the generated `database/factories/ProjectFactory.php`:
    - Set `definition()` to return: `['name' => fake()->words(3, true), 'target_audience' => fake()->optional()->sentence(), 'tone' => fake()->optional()->randomElement(['Professional', 'Casual', 'Educational', 'Entertaining', 'Inspirational']), 'speaking_pace' => fake()->optional()->numberBetween(100, 250)]`
- Edit `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php`:
    - Add `'is_paid' => false` to the `definition()` return array
    - Add a `paid(): static` factory state method that returns `$this->state(fn (array $attributes) => ['is_paid' => true])`
- Edit the generated `database/seeders/ProjectSeeder.php`:
    - Import `App\Models\User` and `App\Models\Project`
    - Create a test project for the first user: `$user = User::first(); if ($user) { Project::factory()->for($user)->create(['name' => 'My Channel']); }`
- Create `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/StoreProjectRequest.php` using `php artisan make:request StoreProjectRequest --no-interaction`
    - Set `authorize()` to check: if user `is_paid`, return true; otherwise return `$this->user()->projects()->count() < 1`
    - Set `rules()` to return array-syntax validation: `'name' => ['required', 'string', 'max:255'], 'target_audience' => ['nullable', 'string', 'max:255'], 'tone' => ['nullable', 'string', 'max:255'], 'speaking_pace' => ['nullable', 'integer', 'min:80', 'max:300']`
- Create `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/ProjectController.php` using `php artisan make:controller ProjectController --no-interaction`
    - Add `index(Request $request): Response` method that renders `'projects/index'` with `'projects' => $request->user()->projects()->latest()->get()` and `'canCreateProject' => $request->user()->is_paid || $request->user()->projects()->count() < 1`
    - Add `create(Request $request): Response` method that renders `'projects/create'`
    - Add `store(StoreProjectRequest $request): RedirectResponse` method that creates the project via `$request->user()->projects()->create($request->validated())` and redirects to `route('projects.index')`
    - Import: `use App\Http\Requests\StoreProjectRequest`, `use Illuminate\Http\RedirectResponse`, `use Illuminate\Http\Request`, `use Inertia\Inertia`, `use Inertia\Response`
- Edit `/Users/young/Nextcloud/dev/Itervel/routes/web.php`:
    - Add `use App\Http\Controllers\ProjectController;` import at the top
    - Add inside a new `Route::middleware(['auth', 'verified'])->group()` block (or append to the existing dashboard route block):
        ```php
        Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('projects/create', [ProjectController::class, 'create'])->name('projects.create');
        Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
        ```
- Run `php artisan migrate` to apply the new migrations
- Run `vendor/bin/pint --dirty` to fix any formatting issues
- Verify no errors by running: `php artisan route:list --name=projects`

### 2. Create Frontend Pages, Components, Types, and Update Sidebar Navigation

- **Task ID**: create-frontend-pages
- **Depends On**: create-backend-foundation
- **Assigned To**: project-frontend-dev
- **Agent Type**: coder
- **Parallel**: false
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/types/project.ts` with the Project type:
    ```typescript
    export type Project = {
        id: number;
        name: string;
        target_audience: string | null;
        tone: string | null;
        speaking_pace: number | null;
        created_at: string;
        updated_at: string;
    };
    ```
- Edit `/Users/young/Nextcloud/dev/Itervel/resources/js/types/index.ts` to add: `export type * from './project';`
- Edit `/Users/young/Nextcloud/dev/Itervel/resources/js/types/auth.ts` to add `is_paid?: boolean;` to the `User` type (before the index signature)
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/textarea.tsx` following the Input component pattern:

    ```tsx
    import * as React from 'react';
    import { cn } from '@/lib/utils';

    function Textarea({
        className,
        ...props
    }: React.ComponentProps<'textarea'>) {
        return (
            <textarea
                data-slot="textarea"
                className={cn(
                    'flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 md:text-sm dark:aria-invalid:ring-destructive/40',
                    className,
                )}
                {...props}
            />
        );
    }

    export { Textarea };
    ```

- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/projects/index.tsx`:
    - Import `Head`, `Link` from `@inertiajs/react`
    - Import `AppLayout` from `@/layouts/app-layout`
    - Import `Heading` from `@/components/heading`
    - Import `Button` from `@/components/ui/button`
    - Import `Card`, `CardHeader`, `CardTitle`, `CardDescription`, `CardContent` from `@/components/ui/card`
    - Import Wayfinder route for projects index from `@/routes` (will be auto-generated after build)
    - Define breadcrumbs array with "Projects" linking to the projects index route
    - Accept props: `{ projects: Project[]; canCreateProject: boolean }`
    - Render inside `AppLayout` with `Head title="Projects"`
    - Show a `Heading` with title "Projects" and description "Manage your video projects"
    - If `canCreateProject` is true, show a "New Project" button linking to the create page
    - If no projects exist, show an empty state with a message like "No projects yet" and a "Create your first project" button
    - Map projects into Card components showing name, target_audience, tone, and speaking_pace
    - Add `data-test` attributes to interactive elements for browser testing:
        - `data-test="new-project-button"` on the "New Project" button
        - `data-test="create-first-project-button"` on the empty state "Create your first project" button
        - `data-test="project-card"` on each project Card component
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/projects/create.tsx`:
    - Import `Form`, `Head` from `@inertiajs/react`
    - Import `AppLayout` from `@/layouts/app-layout`
    - Import `Heading` from `@/components/heading`
    - Import `Button` from `@/components/ui/button`
    - Import `Input` from `@/components/ui/input`
    - Import `Label` from `@/components/ui/label`
    - Import `Textarea` from `@/components/ui/textarea`
    - Import `InputError` from `@/components/input-error`
    - Import Wayfinder ProjectController store action from `@/actions/App/Http/Controllers/ProjectController`
    - Import route for projects index from `@/routes`
    - Define breadcrumbs array: "Projects" -> projects index, "New Project" -> current page
    - Use Inertia `<Form>` component with `{...ProjectController.store.form()}` method pattern
    - Render form fields:
        - Name: required Input field
        - Target Audience: optional Textarea field with placeholder like "e.g., Tech enthusiasts aged 25-40"
        - Tone: optional Input field with placeholder like "e.g., Professional, Casual, Educational"
        - Speaking Pace: optional Input (type="number") with min=80 max=300 placeholder="e.g., 165 words per minute"
    - Show InputError under each field for validation errors
    - Submit button says "Create Project" and is disabled during processing
    - Include a "Cancel" link back to projects index
    - Add `data-test` attributes to interactive elements for browser testing:
        - `data-test="project-name-input"` on the Name input field
        - `data-test="target-audience-input"` on the Target Audience textarea field
        - `data-test="tone-input"` on the Tone input field
        - `data-test="speaking-pace-input"` on the Speaking Pace input field
        - `data-test="submit-project-button"` on the "Create Project" submit button
        - `data-test="cancel-button"` on the Cancel link
- Edit `/Users/young/Nextcloud/dev/Itervel/resources/js/components/app-sidebar.tsx`:
    - Add `import { FolderKanban } from 'lucide-react'` (or another appropriate icon like `Layers` or `FolderOpen`)
    - Import the Wayfinder route for projects index (will be available as `@/routes/projects` after Wayfinder generates)
    - Add a new entry to `mainNavItems` array after Dashboard:
        ```typescript
        {
            title: 'Projects',
            href: index(), // from @/routes/projects
            icon: FolderKanban,
        },
        ```
- Run `npm run build` to generate Wayfinder routes and compile assets
- Verify no TypeScript errors: `npm run types`
- Run `npm run lint` and fix any issues

### 3. Write Comprehensive Project Feature Tests

- **Task ID**: write-project-tests
- **Depends On**: create-backend-foundation
- **Assigned To**: project-test-dev
- **Agent Type**: coder
- **Parallel**: true (can run in parallel with task 2)
- Create `/Users/young/Nextcloud/dev/Itervel/tests/Feature/ProjectTest.php` using `php artisan make:test ProjectTest --pest --no-interaction`
- Read existing test files for pattern reference: `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/ProfileUpdateTest.php` and `/Users/young/Nextcloud/dev/Itervel/tests/Feature/DashboardTest.php`
- Write the following tests:

    ```php
    <?php

    use App\Models\Project;
    use App\Models\User;

    test('guests are redirected to login from projects page', function () {
        $response = $this->get(route('projects.index'));
        $response->assertRedirect(route('login'));
    });

    test('authenticated users can view the projects index', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('projects.index'));

        $response->assertOk();
    });

    test('projects index shows user projects', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'My Test Channel']);

        $response = $this->actingAs($user)->get(route('projects.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('projects/index')
            ->has('projects', 1)
            ->where('projects.0.name', 'My Test Channel')
        );
    });

    test('projects index does not show other users projects', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Project::factory()->for($otherUser)->create();

        $response = $this->actingAs($user)->get(route('projects.index'));

        $response->assertInertia(fn ($page) => $page
            ->has('projects', 0)
        );
    });

    test('create project page is displayed', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('projects.create'));

        $response->assertOk();
    });

    test('project can be created with valid data', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'My Channel',
            'target_audience' => 'Tech enthusiasts',
            'tone' => 'Professional',
            'speaking_pace' => 165,
        ]);

        $response->assertRedirect(route('projects.index'));
        $response->assertSessionHasNoErrors();

        expect($user->projects()->count())->toBe(1);
        $project = $user->projects()->first();
        expect($project->name)->toBe('My Channel');
        expect($project->target_audience)->toBe('Tech enthusiasts');
        expect($project->tone)->toBe('Professional');
        expect($project->speaking_pace)->toBe(165);
    });

    test('project can be created with only required fields', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'Minimal Project',
        ]);

        $response->assertRedirect(route('projects.index'));
        $response->assertSessionHasNoErrors();

        $project = $user->projects()->first();
        expect($project->name)->toBe('Minimal Project');
        expect($project->target_audience)->toBeNull();
        expect($project->tone)->toBeNull();
        expect($project->speaking_pace)->toBeNull();
    });

    test('project creation requires a name', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('projects.store'), [
            'name' => '',
        ]);

        $response->assertSessionHasErrors('name');
    });

    test('project name cannot exceed 255 characters', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('projects.store'), [
            'name' => str_repeat('a', 256),
        ]);

        $response->assertSessionHasErrors('name');
    });

    test('speaking pace must be at least 80', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'Test Project',
            'speaking_pace' => 50,
        ]);

        $response->assertSessionHasErrors('speaking_pace');
    });

    test('speaking pace cannot exceed 300', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'Test Project',
            'speaking_pace' => 350,
        ]);

        $response->assertSessionHasErrors('speaking_pace');
    });

    test('speaking pace must be an integer', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'Test Project',
            'speaking_pace' => 'fast',
        ]);

        $response->assertSessionHasErrors('speaking_pace');
    });

    test('free users cannot create more than one project', function () {
        $user = User::factory()->create(['is_paid' => false]);
        Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'Second Project',
        ]);

        $response->assertForbidden();
        expect($user->projects()->count())->toBe(1);
    });

    test('paid users can create unlimited projects', function () {
        $user = User::factory()->paid()->create();
        Project::factory()->for($user)->count(5)->create();

        $response = $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'Another Project',
        ]);

        $response->assertRedirect(route('projects.index'));
        expect($user->projects()->count())->toBe(6);
    });

    test('project belongs to the authenticated user', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'My Project',
        ]);

        $project = Project::first();
        expect($project->user_id)->toBe($user->id);
    });

    test('projects index shows canCreateProject as true for free user with no projects', function () {
        $user = User::factory()->create(['is_paid' => false]);

        $response = $this->actingAs($user)->get(route('projects.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('canCreateProject', true)
        );
    });

    test('projects index shows canCreateProject as false for free user with existing project', function () {
        $user = User::factory()->create(['is_paid' => false]);
        Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->get(route('projects.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('canCreateProject', false)
        );
    });

    test('projects index shows canCreateProject as true for paid user with projects', function () {
        $user = User::factory()->paid()->create();
        Project::factory()->for($user)->count(3)->create();

        $response = $this->actingAs($user)->get(route('projects.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('canCreateProject', true)
        );
    });

    test('deleting a user cascades to their projects', function () {
        $user = User::factory()->create();
        Project::factory()->for($user)->count(3)->create();

        $user->delete();

        expect(Project::count())->toBe(0);
    });
    ```

- Run the tests: `php artisan test tests/Feature/ProjectTest.php --compact`
- Fix any failing tests until all pass
- Run `vendor/bin/pint --dirty` to fix formatting

### 4. Write Browser Tests

- **Task ID**: write-browser-tests
- **Depends On**: create-frontend-pages, create-backend-foundation
- **Assigned To**: project-browser-test-dev
- **Agent Type**: coder
- **Parallel**: false
- Create `tests/Browser/ProjectTest.php`
- Write smoke tests for each new page route:
    - Visit `/projects` as an authenticated user, assert no JavaScript errors
    - Visit `/projects/create` as an authenticated user, assert no JavaScript errors
- Write a dark mode spot check for the projects index page:
    - Visit `/projects`, switch to dark color scheme, assert no JavaScript errors
- Write an interactive flow test for creating a project:
    - Visit `/projects`, click `[data-test="new-project-button"]`
    - Fill in `[data-test="project-name-input"]` with a project name
    - Fill in `[data-test="target-audience-input"]` with a target audience
    - Fill in `[data-test="tone-input"]` with a tone
    - Fill in `[data-test="speaking-pace-input"]` with a speaking pace value
    - Click `[data-test="submit-project-button"]`
    - Assert redirected to projects index and the new project appears
- Write an empty state test:
    - Visit `/projects` with no projects, assert `[data-test="create-first-project-button"]` is visible
- Use `data-test` selectors for all element interactions
- Ensure all browser tests use `assertNoJavaScriptErrors()`
- Run browser tests: `php artisan test tests/Browser/ProjectTest.php --compact`

### 5. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-backend-foundation, create-frontend-pages, write-project-tests, write-browser-tests
- **Assigned To**: project-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed below
- Verify project tests pass: `php artisan test tests/Feature/ProjectTest.php --compact`
- Run the full test suite: `php artisan test --compact`
- Run TypeScript type checking: `npm run types`
- Run ESLint: `npm run lint`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Verify the following files exist and are correct:
    - `app/Models/Project.php` has fillable, casts, and user() relationship
    - `app/Models/User.php` has projects() relationship and is_paid cast
    - `app/Http/Controllers/ProjectController.php` has index, create, store methods
    - `app/Http/Requests/StoreProjectRequest.php` has authorization (project limit) and validation rules
    - Migration for `projects` table exists with correct schema
    - Migration for `is_paid` column on users exists
    - `database/factories/ProjectFactory.php` has correct definition
    - `database/factories/UserFactory.php` has `is_paid` field and `paid()` state
    - `resources/js/pages/projects/index.tsx` renders project list with create button
    - `resources/js/pages/projects/create.tsx` renders project creation form
    - `resources/js/components/ui/textarea.tsx` exists
    - `resources/js/types/project.ts` has Project type definition
    - `resources/js/components/app-sidebar.tsx` has Projects nav item
    - Routes are correctly defined: `projects.index`, `projects.create`, `projects.store`
- Run browser tests: `php artisan test tests/Browser/ProjectTest.php --compact`
- Verify `data-test` attributes exist on interactive elements in `resources/js/pages/projects/index.tsx` and `resources/js/pages/projects/create.tsx`
- Verify routes exist: `php artisan route:list --name=projects`
- Confirm all acceptance criteria are met

## Acceptance Criteria

- Authenticated users can view the projects index page at `/projects`
- Guests are redirected to the login page when accessing project routes
- Users can create a project with a name and optional settings (target audience, tone, speaking pace)
- Project name is required and cannot exceed 255 characters
- Target audience and tone are optional and cannot exceed 255 characters
- Speaking pace is optional, must be an integer between 80 and 300
- Free users (is_paid = false) can create at most 1 project; additional attempts return 403
- Paid users (is_paid = true) can create unlimited projects
- The projects index page shows a `canCreateProject` flag reflecting the user's ability to create more projects
- Created projects belong to the authenticated user
- Users cannot see other users' projects
- Deleting a user cascades to delete their projects
- The "Projects" nav item appears in the sidebar
- The create project form uses Wayfinder-generated routes for the store action
- All project tests pass
- All existing tests continue to pass (no regressions)
- PHP code passes Pint formatting
- TypeScript passes type checking
- All new pages have smoke tests (no JavaScript errors)
- Dark mode spot check passes for the projects index page
- Core project creation flow passes browser tests using `data-test` selectors
- Interactive frontend elements have `data-test` attributes
- All browser tests pass
- ESLint reports no errors

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run project-specific tests
php artisan test tests/Feature/ProjectTest.php --compact

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

# Run browser tests
php artisan test tests/Browser/ProjectTest.php --compact
```

## Notes

- The `is_paid` column on the `users` table is a simplified approach for distinguishing free vs paid users. When the payment/subscription system is built later (E001-F060+), this may be replaced or augmented with a more sophisticated approach (e.g., checking subscription status or credit purchase history). For now, the boolean flag provides a clean, testable interface.
- The `speaking_pace` field stores words per minute as an integer. The range of 80-300 covers slow (80 WPM) to very fast (300 WPM) speaking rates. The default for YouTube narration is typically 150-180 WPM.
- E001-F011 (Auto-Create Default Project) will automatically create a "My Channel" project on first login, so users will not typically see an empty projects page. However, the empty state should still be handled gracefully.
- E001-F012 (List and Switch Projects) will expand the project listing with switching functionality and a dropdown/tab interface.
- E001-F013 (Edit Project Settings) will add update functionality to the ProjectController.
- E001-F014 (Delete Project) will add delete functionality with confirmation.
- E001-F082 (Project-Level Settings Inheritance) will use the project settings (target_audience, tone, speaking_pace) as defaults for video creation.
- The `ProjectFactory` uses `fake()->optional()` for nullable fields to create a mix of fully-configured and minimally-configured projects in tests and seeders.
- The `Textarea` UI component follows the exact same pattern as the existing `Input` component but for multi-line text. It uses the same Tailwind classes and data-slot attribute convention.
- All `php artisan` commands should be run inside the Docker container. Use `make shell` to enter the container, or prefix with `docker compose exec app`.
- After the backend is built and `npm run build` is executed, Wayfinder will auto-generate route functions in `resources/js/routes/projects/` and action functions in `resources/js/actions/App/Http/Controllers/ProjectController/`. The frontend code should import from these generated paths.

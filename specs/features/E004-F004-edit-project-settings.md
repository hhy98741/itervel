# Feature: Edit Project Settings

**Epic**: E004-project-management.md
**Feature**: E004-F004
**Dependencies**: E004-F001

## Task Description

Edit Project Settings allows authenticated users to update a project's name and configuration settings after it has been created. A project contains settings like target audience, tone, and speaking pace that influence future video creation within that project. This feature adds `edit` and `update` actions to the existing `ProjectController` (created by E001-F010), a new Form Request for update validation, an Inertia React edit page, and comprehensive tests.

**What it does**: Lets users update a project's name and settings (target audience, tone, thumbnail style, music preference, etc.).

**Expected outcome**: The user modifies project details and the changes are saved. Future videos in this project use the updated settings.

This feature builds on E001-F010 (Create Project), which establishes the `Project` model, `projects` table (with columns: `name`, `target_audience`, `tone`, `speaking_pace`), `ProjectController` (with `index`, `create`, `store` actions), `StoreProjectRequest`, `ProjectFactory`, routes (`projects.index`, `projects.create`, `projects.store`), and frontend pages for listing and creating projects. This feature extends that foundation with edit/update functionality and authorization to ensure users can only edit their own projects.

The epic description mentions additional settings fields beyond the initial schema (thumbnail style, music preference). Since E001-F010 defines the initial schema with `name`, `target_audience`, `tone`, and `speaking_pace`, this feature will work with those existing columns. Adding new columns like `thumbnail_style` and `music_preference` would be a schema change best addressed by the features that actually consume those settings (e.g., E001-F030 Thumbnail Concept Generation, E001-F035 Background Music). For now, the edit page will allow updating the four existing project fields.

## Objective

Implement the ability for authenticated users to edit and update their own projects by adding an `edit` and `update` method to `ProjectController`, a `UpdateProjectRequest` Form Request with ownership authorization and validation rules, two new routes (`projects.edit` and `projects.update`), an Inertia React edit page pre-populated with the project's current values, navigation from the projects index to the edit page, and comprehensive Pest feature tests covering authorization, validation, and successful updates.

## Solution Approach

### 1. Authorization: Users can only edit their own projects

The `UpdateProjectRequest` Form Request will handle authorization by checking that the authenticated user owns the project. This follows the existing pattern where `StoreProjectRequest` handles authorization in `authorize()`. The route will use implicit model binding (`{project}`) so the Project model is resolved automatically.

```php
// app/Http/Requests/UpdateProjectRequest.php
class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->id === $this->route('project')->user_id;
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

### 2. Controller: Add edit and update methods to ProjectController

The existing `ProjectController` (from E001-F010) has `index`, `create`, and `store` methods. This feature adds:

```php
public function edit(Project $project): Response
{
    // Authorization will be handled by UpdateProjectRequest on the update route
    // but we also need to check ownership on the edit (GET) route
    if ($project->user_id !== auth()->id()) {
        abort(403);
    }

    return Inertia::render('projects/edit', [
        'project' => $project,
    ]);
}

public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
{
    $project->update($request->validated());

    return to_route('projects.edit', $project);
}
```

Alternatively, a `ProjectPolicy` could be used, but to keep things simple and consistent with the existing pattern in `StoreProjectRequest`, ownership is checked inline in `edit()` and via the Form Request's `authorize()` in `update()`. If a policy is added in E001-F014 (Delete Project), it can be refactored at that point.

### 3. Routes: Add edit and update routes

Add to the existing project routes group in `routes/web.php` (created by E001-F010):

```php
Route::get('projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
Route::patch('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
```

Using `PATCH` for the update follows the same convention as the profile update route (`PATCH settings/profile`).

### 4. Frontend: Edit Project Page

Create `resources/js/pages/projects/edit.tsx` following the same patterns as `resources/js/pages/settings/profile.tsx`:

- Uses `AppLayout` for the authenticated layout
- Uses `<Form>` from `@inertiajs/react` with Wayfinder-generated `ProjectController.update.form()`
- Pre-populates fields with `defaultValue` from the project prop
- Shows validation errors with `InputError`
- Shows a "Saved" transition on success (matching the profile page pattern)
- Includes breadcrumbs: "Projects" > project name > "Edit"

### 5. Frontend: Link from projects index to edit page

The projects index page (created by E001-F010) lists projects as cards. Each project card should include an "Edit" link/button that navigates to the edit page. This requires updating `resources/js/pages/projects/index.tsx` to add an edit link to each project card.

### 6. Tests

Write comprehensive Pest feature tests covering:

- Edit page displays for the project owner
- Edit page returns 403 for non-owners
- Guests are redirected to login
- Project can be updated with valid data
- All fields are updated correctly
- Validation rules are enforced (name required, max lengths, speaking_pace range)
- Non-owners cannot update a project (403)
- Successful update redirects back to edit page

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/ProfileController.php` -- Reference for the edit/update controller pattern. Shows how `edit()` renders an Inertia page and `update()` validates, saves, and redirects.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/Settings/ProfileUpdateRequest.php` -- Reference for Form Request pattern with array-syntax validation rules.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/Settings/PasswordUpdateRequest.php` -- Reference for Form Request pattern with validation rules.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Controller.php` -- Base abstract controller that all controllers extend.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` -- User model with `projects()` relationship (added by E001-F010).
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Must add `projects.edit` and `projects.update` routes to the existing project routes group.
- `/Users/young/Nextcloud/dev/Itervel/routes/settings.php` -- Reference for route grouping patterns with middleware and PATCH method usage.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/profile.tsx` -- Primary reference for the edit form page pattern. Shows `<Form>` with Wayfinder actions, `defaultValue` for pre-population, `InputError`, `Transition` for "Saved" feedback, and `preserveScroll`.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/password.tsx` -- Additional reference for form page with Wayfinder action imports and error handling.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/dashboard.tsx` -- Reference for breadcrumb patterns and basic page layout.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/heading.tsx` -- Reusable heading component used for page section titles.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/input-error.tsx` -- Reusable validation error display component.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/button.tsx` -- Button UI component.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/input.tsx` -- Input UI component for text fields.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/label.tsx` -- Label UI component for form fields.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/card.tsx` -- Card UI component used in the projects index page (to be updated with edit link).
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/app-layout.tsx` -- App layout wrapper for authenticated pages.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/index.ts` -- TypeScript type exports (Project type added by E001-F010).
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/navigation.ts` -- BreadcrumbItem type definition.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/lib/utils.ts` -- Utility functions (cn helper).
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/ProfileUpdateTest.php` -- Reference for comprehensive CRUD test patterns with Pest.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration. All Feature tests use RefreshDatabase.
- `/Users/young/Nextcloud/dev/Itervel/bootstrap/app.php` -- Middleware and routing configuration reference.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` -- User factory for test setup. Has `paid()` state (added by E001-F010).

### New Files

- `app/Http/Requests/UpdateProjectRequest.php` -- Form Request with ownership authorization (`authorize()` checks `user_id` matches authenticated user) and validation rules matching the store request (name required, optional target_audience, tone, speaking_pace with integer range 80-300).
- `resources/js/pages/projects/edit.tsx` -- Inertia React page component with a pre-populated form for editing project settings. Uses `<Form>` with Wayfinder-generated `ProjectController.update.form()`, shows validation errors, and provides "Saved" feedback on success.
- `tests/Feature/ProjectUpdateTest.php` -- Pest feature tests covering edit page display, authorization (ownership), validation, successful updates, and redirect behavior.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: edit-project-backend
    - Role: Adds `edit` and `update` methods to the existing ProjectController, creates the UpdateProjectRequest Form Request with ownership authorization and validation rules, and registers the new routes in web.php
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: edit-project-frontend
    - Role: Creates the projects/edit.tsx Inertia page component with a pre-populated form, updates the projects/index.tsx page to add edit links to each project card, and ensures Wayfinder route generation
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: edit-project-tester
    - Role: Writes comprehensive Pest feature tests covering authorization, validation, and successful update scenarios for project editing
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: edit-project-reviewer
    - Role: Validates the complete feature against acceptance criteria, runs all tests, checks TypeScript types, runs linting and formatting
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Add Backend Routes, Form Request, and Controller Methods

- **Task ID**: create-backend-edit-update
- **Depends On**: none
- **Assigned To**: edit-project-backend
- **Agent Type**: coder
- **Parallel**: true
- Read the existing `ProjectController` at `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/ProjectController.php` (created by E001-F010) to understand the current structure
- Read the existing `StoreProjectRequest` at `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/StoreProjectRequest.php` for validation patterns
- Read the existing `Project` model at `/Users/young/Nextcloud/dev/Itervel/app/Models/Project.php` to confirm the fillable fields
- Create the `UpdateProjectRequest` using `php artisan make:request UpdateProjectRequest --no-interaction` inside the Docker container (`make shell` or `docker compose exec app`)
- Edit `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/UpdateProjectRequest.php`:
    - Set `authorize()` to check ownership: `return $this->user()->id === $this->route('project')->user_id;`
    - Set `rules()` to return:
        ```php
        return [
            'name' => ['required', 'string', 'max:255'],
            'target_audience' => ['nullable', 'string', 'max:255'],
            'tone' => ['nullable', 'string', 'max:255'],
            'speaking_pace' => ['nullable', 'integer', 'min:80', 'max:300'],
        ];
        ```
    - Add proper imports for `Illuminate\Contracts\Validation\ValidationRule`
- Edit `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/ProjectController.php` to add:
    - Import `App\Http\Requests\UpdateProjectRequest` and `App\Models\Project`
    - An `edit(Project $project): Response` method:

        ```php
        public function edit(Project $project): Response
        {
            if ($project->user_id !== auth()->id()) {
                abort(403);
            }

            return Inertia::render('projects/edit', [
                'project' => $project,
            ]);
        }
        ```

    - An `update(UpdateProjectRequest $request, Project $project): RedirectResponse` method:

        ```php
        public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
        {
            $project->update($request->validated());

            return to_route('projects.edit', $project);
        }
        ```

- Edit `/Users/young/Nextcloud/dev/Itervel/routes/web.php` to add the new routes inside the existing `Route::middleware(['auth', 'verified'])` group where the other project routes are registered:
    ```php
    Route::get('projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
    Route::patch('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    ```
- Run `vendor/bin/pint --dirty` to format any changed PHP files
- Verify routes are registered: `php artisan route:list --name=projects`
- Confirm there are no PHP errors: `php artisan tinker --execute="echo 'ok';"`

### 2. Create Frontend Edit Page and Update Projects Index

- **Task ID**: create-frontend-edit-page
- **Depends On**: create-backend-edit-update
- **Assigned To**: edit-project-frontend
- **Agent Type**: coder
- **Parallel**: false
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/profile.tsx` for the edit form pattern with `<Form>`, `defaultValue`, `InputError`, and `Transition`
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/projects/index.tsx` (created by E001-F010) to understand the project listing page structure
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/projects/create.tsx` (created by E001-F010) to understand the project form pattern
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/types/project.ts` (created by E001-F010) for the Project TypeScript type
- Run `npm run build` first to generate the Wayfinder routes for the new `edit` and `update` controller methods
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/projects/edit.tsx` with the following structure:

    ```tsx
    import { Transition } from '@headlessui/react';
    import { Form, Head, Link } from '@inertiajs/react';
    import Heading from '@/components/heading';
    import InputError from '@/components/input-error';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import AppLayout from '@/layouts/app-layout';
    import type { BreadcrumbItem, Project } from '@/types';
    import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
    // Import Wayfinder routes for breadcrumb links
    import { index } from '@/routes/projects';

    interface EditProjectProps {
        project: Project;
    }

    export default function Edit({ project }: EditProjectProps) {
        const breadcrumbs: BreadcrumbItem[] = [
            {
                title: 'Projects',
                href: index().url,
            },
            {
                title: project.name,
                href: ProjectController.edit({ project: project.id }).url,
            },
        ];

        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title={`Edit ${project.name}`} />

                <div className="px-4 py-6">
                    <Heading
                        title="Edit Project"
                        description="Update your project name and settings"
                    />

                    <div className="max-w-xl">
                        <Form
                            {...ProjectController.update.form({
                                project: project.id,
                            })}
                            options={{
                                preserveScroll: true,
                            }}
                            className="space-y-6"
                        >
                            {({ processing, recentlySuccessful, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">
                                            Project name
                                        </Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            defaultValue={project.name}
                                            required
                                            placeholder="Project name"
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="target_audience">
                                            Target audience
                                        </Label>
                                        <Input
                                            id="target_audience"
                                            name="target_audience"
                                            defaultValue={
                                                project.target_audience ?? ''
                                            }
                                            placeholder="e.g., Tech enthusiasts aged 25-40"
                                        />
                                        <InputError
                                            message={errors.target_audience}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="tone">Tone</Label>
                                        <Input
                                            id="tone"
                                            name="tone"
                                            defaultValue={project.tone ?? ''}
                                            placeholder="e.g., Professional, Casual, Educational"
                                        />
                                        <InputError message={errors.tone} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="speaking_pace">
                                            Speaking pace (words per minute)
                                        </Label>
                                        <Input
                                            id="speaking_pace"
                                            name="speaking_pace"
                                            type="number"
                                            min={80}
                                            max={300}
                                            defaultValue={
                                                project.speaking_pace ?? ''
                                            }
                                            placeholder="e.g., 165"
                                        />
                                        <InputError
                                            message={errors.speaking_pace}
                                        />
                                    </div>

                                    <div className="flex items-center gap-4">
                                        <Button disabled={processing}>
                                            Save changes
                                        </Button>

                                        <Transition
                                            show={recentlySuccessful}
                                            enter="transition ease-in-out"
                                            enterFrom="opacity-0"
                                            leave="transition ease-in-out"
                                            leaveTo="opacity-0"
                                        >
                                            <p className="text-sm text-neutral-600">
                                                Saved
                                            </p>
                                        </Transition>
                                    </div>
                                </>
                            )}
                        </Form>
                    </div>
                </div>
            </AppLayout>
        );
    }
    ```

- The actual Wayfinder import paths may vary. After running `npm run build`, check the generated files in `resources/js/actions/App/Http/Controllers/ProjectController/` and `resources/js/routes/projects/` to confirm the correct import paths. Adjust imports accordingly.
- Note: The `Form` Wayfinder integration for `update` requires the project parameter for the route. Check how the Wayfinder-generated function handles route parameters (e.g., `ProjectController.update.form({ project: project.id })` or `ProjectController.update.form(project.id)`). Follow the pattern from the generated file.
- Edit `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/projects/index.tsx` to add an "Edit" link to each project card:
    - Import `Link` from `@inertiajs/react` (if not already imported)
    - Import `ProjectController` from Wayfinder actions (or the `edit` route from `@/routes/projects`)
    - On each project card, add an "Edit" button or link that navigates to `ProjectController.edit({ project: project.id })` or the equivalent Wayfinder route
    - Example addition to each Card:
        ```tsx
        <Link href={ProjectController.edit({ project: project.id })}>
            <Button variant="ghost" size="sm">
                Edit
            </Button>
        </Link>
        ```
- Run `npm run build` to compile assets and verify Wayfinder route generation
- Run `npm run types` to verify no TypeScript errors
- Run `npm run lint` and `npm run format` to fix any linting/formatting issues

### 3. Write Comprehensive Feature Tests for Project Edit and Update

- **Task ID**: write-edit-tests
- **Depends On**: create-backend-edit-update
- **Assigned To**: edit-project-tester
- **Agent Type**: coder
- **Parallel**: true (can run in parallel with task 2)
- Read `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/ProfileUpdateTest.php` for test patterns
- Read `/Users/young/Nextcloud/dev/Itervel/tests/Feature/ProjectTest.php` (created by E001-F010) for project test patterns
- Read the `ProjectFactory` at `/Users/young/Nextcloud/dev/Itervel/database/factories/ProjectFactory.php` for available factory methods
- Create `/Users/young/Nextcloud/dev/Itervel/tests/Feature/ProjectUpdateTest.php` using `php artisan make:test ProjectUpdateTest --pest --no-interaction`
- Write the following tests:

    ```php
    <?php

    use App\Models\Project;
    use App\Models\User;

    // --- Edit Page Tests ---

    test('guests are redirected to login from project edit page', function () {
        $project = Project::factory()->for(User::factory())->create();

        $response = $this->get(route('projects.edit', $project));

        $response->assertRedirect(route('login'));
    });

    test('project edit page is displayed for the owner', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->get(route('projects.edit', $project));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('projects/edit')
            ->has('project')
            ->where('project.id', $project->id)
            ->where('project.name', $project->name)
        );
    });

    test('project edit page returns 403 for non-owner', function () {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $response = $this->actingAs($otherUser)->get(route('projects.edit', $project));

        $response->assertForbidden();
    });

    // --- Update Tests ---

    test('guests cannot update a project', function () {
        $project = Project::factory()->for(User::factory())->create();

        $response = $this->patch(route('projects.update', $project), [
            'name' => 'Updated Name',
        ]);

        $response->assertRedirect(route('login'));
    });

    test('project can be updated with valid data', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create([
            'name' => 'Original Name',
            'target_audience' => 'Original Audience',
            'tone' => 'Original Tone',
            'speaking_pace' => 150,
        ]);

        $response = $this->actingAs($user)->patch(route('projects.update', $project), [
            'name' => 'Updated Name',
            'target_audience' => 'Updated Audience',
            'tone' => 'Updated Tone',
            'speaking_pace' => 180,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('projects.edit', $project));

        $project->refresh();
        expect($project->name)->toBe('Updated Name');
        expect($project->target_audience)->toBe('Updated Audience');
        expect($project->tone)->toBe('Updated Tone');
        expect($project->speaking_pace)->toBe(180);
    });

    test('project can be updated with only required fields', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create([
            'name' => 'Original',
            'target_audience' => 'Some Audience',
            'tone' => 'Professional',
            'speaking_pace' => 150,
        ]);

        $response = $this->actingAs($user)->patch(route('projects.update', $project), [
            'name' => 'Updated Name Only',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('projects.edit', $project));

        $project->refresh();
        expect($project->name)->toBe('Updated Name Only');
        expect($project->target_audience)->toBeNull();
        expect($project->tone)->toBeNull();
        expect($project->speaking_pace)->toBeNull();
    });

    test('project optional fields can be cleared', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create([
            'name' => 'My Project',
            'target_audience' => 'Some Audience',
            'tone' => 'Casual',
            'speaking_pace' => 160,
        ]);

        $response = $this->actingAs($user)->patch(route('projects.update', $project), [
            'name' => 'My Project',
            'target_audience' => null,
            'tone' => null,
            'speaking_pace' => null,
        ]);

        $response->assertSessionHasNoErrors();

        $project->refresh();
        expect($project->target_audience)->toBeNull();
        expect($project->tone)->toBeNull();
        expect($project->speaking_pace)->toBeNull();
    });

    test('non-owner cannot update a project', function () {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::factory()->for($owner)->create(['name' => 'Original']);

        $response = $this->actingAs($otherUser)->patch(route('projects.update', $project), [
            'name' => 'Hacked Name',
        ]);

        $response->assertForbidden();
        expect($project->refresh()->name)->toBe('Original');
    });

    // --- Validation Tests ---

    test('project update requires a name', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->patch(route('projects.update', $project), [
            'name' => '',
        ]);

        $response->assertSessionHasErrors('name');
    });

    test('project name cannot exceed 255 characters on update', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->patch(route('projects.update', $project), [
            'name' => str_repeat('a', 256),
        ]);

        $response->assertSessionHasErrors('name');
    });

    test('target audience cannot exceed 255 characters on update', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->patch(route('projects.update', $project), [
            'name' => 'Valid Name',
            'target_audience' => str_repeat('a', 256),
        ]);

        $response->assertSessionHasErrors('target_audience');
    });

    test('tone cannot exceed 255 characters on update', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->patch(route('projects.update', $project), [
            'name' => 'Valid Name',
            'tone' => str_repeat('a', 256),
        ]);

        $response->assertSessionHasErrors('tone');
    });

    test('speaking pace must be at least 80 on update', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->patch(route('projects.update', $project), [
            'name' => 'Valid Name',
            'speaking_pace' => 50,
        ]);

        $response->assertSessionHasErrors('speaking_pace');
    });

    test('speaking pace cannot exceed 300 on update', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->patch(route('projects.update', $project), [
            'name' => 'Valid Name',
            'speaking_pace' => 350,
        ]);

        $response->assertSessionHasErrors('speaking_pace');
    });

    test('speaking pace must be an integer on update', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->patch(route('projects.update', $project), [
            'name' => 'Valid Name',
            'speaking_pace' => 'fast',
        ]);

        $response->assertSessionHasErrors('speaking_pace');
    });

    test('updating a project does not affect other projects', function () {
        $user = User::factory()->create();
        $project1 = Project::factory()->for($user)->create(['name' => 'Project 1']);
        $project2 = Project::factory()->for($user)->create(['name' => 'Project 2']);

        $this->actingAs($user)->patch(route('projects.update', $project1), [
            'name' => 'Updated Project 1',
        ]);

        expect($project2->refresh()->name)->toBe('Project 2');
    });
    ```

- Run the tests: `php artisan test tests/Feature/ProjectUpdateTest.php --compact`
- Fix any failing tests until all pass
- Run `vendor/bin/pint --dirty` to format the test file

### 4. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-backend-edit-update, create-frontend-edit-page, write-edit-tests
- **Assigned To**: edit-project-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed below
- Verify project update tests pass: `php artisan test tests/Feature/ProjectUpdateTest.php --compact`
- Verify existing project tests still pass: `php artisan test tests/Feature/ProjectTest.php --compact`
- Run the full test suite: `php artisan test --compact`
- Run TypeScript type checking: `npm run types`
- Run ESLint: `npm run lint`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Verify the following files exist and are correct:
    - `app/Http/Requests/UpdateProjectRequest.php` has `authorize()` checking ownership and `rules()` with proper validation
    - `app/Http/Controllers/ProjectController.php` has `edit()` and `update()` methods alongside the existing `index`, `create`, `store`
    - Routes `projects.edit` and `projects.update` are registered: `php artisan route:list --name=projects`
    - `resources/js/pages/projects/edit.tsx` renders a pre-populated form with all project fields
    - `resources/js/pages/projects/index.tsx` has edit links on each project card
    - `tests/Feature/ProjectUpdateTest.php` has comprehensive tests covering authorization, validation, and successful updates
- Confirm all acceptance criteria are met

## Acceptance Criteria

- Authenticated project owners can view the edit page for their project at `/projects/{project}/edit`
- The edit form is pre-populated with the project's current values (name, target_audience, tone, speaking_pace)
- Users can update all project fields (name, target_audience, tone, speaking_pace) and changes are persisted
- Users can clear optional fields (target_audience, tone, speaking_pace) by submitting null/empty values
- Non-owners receive a 403 Forbidden response when attempting to view or update another user's project
- Guests are redirected to the login page when accessing project edit/update routes
- Project name is required and cannot exceed 255 characters
- Target audience and tone are optional and cannot exceed 255 characters
- Speaking pace is optional, must be an integer between 80 and 300
- Successful update redirects back to the edit page with a "Saved" confirmation
- The projects index page includes an "Edit" link/button for each project card
- Updating a project does not affect other projects
- All project update tests pass
- All existing tests continue to pass (no regressions)
- PHP code passes Pint formatting
- TypeScript passes type checking
- ESLint reports no errors

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run project update tests
php artisan test tests/Feature/ProjectUpdateTest.php --compact

# Run existing project tests (from E001-F010)
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
```

## Notes

- This feature depends on E001-F010 (Create Project) which creates the Project model, migration, factory, seeder, ProjectController (with index/create/store), StoreProjectRequest, routes (projects.index, projects.create, projects.store), frontend pages (projects/index, projects/create), the Textarea component, and the Project TypeScript type. All of these must exist before this feature can be built.
- The epic mentions additional settings like "thumbnail style" and "music preference" in the feature description. These fields are not part of the initial schema defined in E001-F010. They should be added as separate migrations when the features that consume them are built (E001-F030 for thumbnails, E001-F035 for music). When those fields are added, the edit form and validation can be extended to include them.
- The `PATCH` HTTP method is used for the update route, consistent with the profile update route pattern in `routes/settings.php`.
- Authorization is handled via two mechanisms: the `edit()` method checks ownership inline with `abort(403)`, and the `update()` method delegates to `UpdateProjectRequest::authorize()`. This could be consolidated into a `ProjectPolicy` in a future feature (e.g., E001-F014 Delete Project) if the authorization logic needs to be shared across multiple actions.
- The `defaultValue` approach (not `value`) is used for form fields because the `<Form>` component from Inertia manages form state internally. Using `defaultValue` allows the form to be pre-populated while still allowing user edits without controlled state management.
- E001-F082 (Project-Level Settings Inheritance) will use the settings edited through this feature as defaults for new videos created within the project.
- All `php artisan` commands should be run inside the Docker container. Use `make shell` to enter the container, or prefix with `docker compose exec app`.

# Feature: Delete Project

**Epic**: E004-project-management.md
**Feature**: E004-F005
**Epic depends on**: E002-user-authentication.md
**Feature depends on**: E004-F001

## Task Description

Delete Project allows authenticated users to permanently delete a project they no longer need. Deleting a project also cascades to delete all associated videos within that project. As a safeguard, the user must type the exact project name to confirm the deletion, preventing accidental data loss.

**What it does**: Allows users to delete a project they no longer need.

**Expected outcome**: The user is warned that deleting the project will also delete all associated videos. They must type the project name to confirm. Once confirmed, the project and its videos are permanently removed.

This feature builds on E001-F010 (Create Project) which establishes the `Project` model, `projects` table (with columns: `name`, `target_audience`, `tone`, `speaking_pace`), `ProjectController` (with `index`, `create`, `store` actions, plus `edit` and `update` from E001-F013), `StoreProjectRequest`, `ProjectFactory`, routes, and frontend pages. The `projects` table has a `cascadeOnDelete()` foreign key on `user_id`, meaning deleting a user already cascades to their projects. However, there is no Video model yet -- the video-related features (E001-F050+) have not been built. The deletion logic should be designed to handle cascading video deletion when the Video model is introduced later.

Since the Video model does not yet exist, the current implementation will:

1. Add a `destroy` method to `ProjectController` with ownership authorization
2. Create a `DeleteProjectRequest` Form Request that validates ownership and requires the user to type the project name for confirmation
3. Add a `DELETE projects/{project}` route
4. Create a frontend confirmation dialog that warns about associated video deletion and requires typing the project name
5. Write comprehensive tests covering authorization, validation (project name confirmation), and successful deletion

The project name confirmation pattern follows a well-established UX pattern (similar to GitHub repository deletion) where users must type an exact string to confirm a destructive action. This is different from the account deletion pattern (which uses password confirmation) because project deletion is less severe and doesn't require re-authentication -- just intentional confirmation.

## Objective

Implement the ability for authenticated users to permanently delete their own projects by adding a `destroy` method to `ProjectController`, a `DeleteProjectRequest` Form Request with ownership authorization and project-name-confirmation validation, a new `DELETE projects/{project}` route, a frontend delete confirmation component with a dialog that warns about associated video deletion and requires typing the project name, and comprehensive Pest feature tests covering authorization, validation, and cascading deletion scenarios.

## Solution Approach

### 1. Form Request: DeleteProjectRequest

Create a Form Request that handles both authorization (ownership check) and validation (project name confirmation). The user must submit a field called `project_name` that exactly matches the project's actual name.

```php
// app/Http/Requests/DeleteProjectRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->id === $this->route('project')->user_id;
    }

    public function rules(): array
    {
        return [
            'project_name' => ['required', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('project_name') !== $this->route('project')->name) {
                $validator->errors()->add(
                    'project_name',
                    'The project name does not match.'
                );
            }
        });
    }
}
```

Using `withValidator()` with `after()` allows a custom validation check that compares the submitted `project_name` against the actual project's name. This is cleaner than a closure-based rule and keeps the comparison logic within the Form Request.

### 2. Controller: Add destroy method to ProjectController

The existing `ProjectController` (from E001-F010 and E001-F013) has `index`, `create`, `store`, `edit`, and `update` methods. This feature adds a `destroy` method:

```php
public function destroy(DeleteProjectRequest $request, Project $project): RedirectResponse
{
    $project->delete();

    return to_route('projects.index');
}
```

The method is intentionally simple because:

- Authorization is handled by the Form Request's `authorize()` method
- Project name confirmation is handled by the Form Request's validation
- Cascading deletion of videos will be handled by database foreign key constraints when the Video model is introduced (the Video migration should use `$table->foreignId('project_id')->constrained()->cascadeOnDelete()`)
- For now, with no Video model, `$project->delete()` is sufficient

### 3. Route: Add destroy route

Add to the existing project routes group in `routes/web.php`:

```php
Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
```

### 4. Frontend: Delete Project Component

Create a reusable `DeleteProject` component (following the pattern of the existing `DeleteUser` component at `resources/js/components/delete-user.tsx`). This component:

- Shows a warning section with red styling about the consequences of deletion
- Uses a Dialog for confirmation
- Requires the user to type the project name to enable the delete button
- Uses Inertia's `<Form>` component with Wayfinder-generated `ProjectController.destroy.form()`

The component uses React's `useState` to track the typed project name and compares it against the actual project name to enable/disable the delete button. This provides immediate client-side feedback while the server-side validation in `DeleteProjectRequest` acts as the authoritative check.

### 5. Frontend: Integrate delete into project settings

The delete component should be added to the project edit page (`resources/js/pages/projects/edit.tsx`, created by E001-F013), placed below the edit form. This follows the same pattern as the profile settings page where `DeleteUser` is placed below the profile update form. Alternatively, it could be a separate section on the project index page, but placing it on the edit page is more natural -- users editing a project's settings can also choose to delete it there.

### 6. Tests

Write comprehensive tests covering:

- Guests are redirected to login when attempting to delete a project
- Project owner can delete their own project
- Non-owners receive 403 when attempting to delete another user's project
- Project name confirmation is required
- Incorrect project name is rejected with a validation error
- Successful deletion removes the project from the database
- Successful deletion redirects to the projects index
- Deleting a project does not affect other projects belonging to the same user
- Deleting a project does not affect other users' projects

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/ProfileController.php` -- Reference for the destroy method pattern. Shows how `destroy()` handles deletion with a Form Request for validation.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/Settings/ProfileDeleteRequest.php` -- Reference for deletion Form Request pattern. Uses password validation for account deletion.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/delete-user.tsx` -- Primary reference for the delete confirmation dialog pattern. Shows the warning box, Dialog component usage, Form submission, and error handling. The DeleteProject component should follow this exact pattern but use project name confirmation instead of password confirmation.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/dialog.tsx` -- Dialog, DialogTrigger, DialogContent, DialogTitle, DialogDescription, DialogFooter, DialogClose components used for the confirmation modal.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/input.tsx` -- Input component for the project name confirmation field.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/button.tsx` -- Button component (including `variant="destructive"` for delete actions).
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/heading.tsx` -- Heading component for section titles within the delete component.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/input-error.tsx` -- InputError component for displaying validation errors.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Controller.php` -- Base abstract controller that ProjectController extends.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` -- User model with `projects()` relationship (added by E001-F010).
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Must add the `projects.destroy` route to the existing project routes group.
- `/Users/young/Nextcloud/dev/Itervel/routes/settings.php` -- Reference for route patterns, specifically the `DELETE` method usage for `profile.destroy`.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/profile.tsx` -- Reference for how the delete component is integrated into a settings page (the `<DeleteUser />` component is rendered below the profile form).
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/app-layout.tsx` -- App layout wrapper for authenticated pages.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/index.ts` -- TypeScript type exports (Project type added by E001-F010).
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/navigation.ts` -- BreadcrumbItem type definition.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/lib/utils.ts` -- Utility functions (cn helper).
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/ProfileUpdateTest.php` -- Reference for deletion test patterns (`'user can delete their account'` and `'correct password must be provided to delete account'`).
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration. All Feature tests use RefreshDatabase.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` -- User factory for test setup. Has `paid()` state (added by E001-F010).
- `/Users/young/Nextcloud/dev/Itervel/bootstrap/app.php` -- Middleware and routing configuration reference.

### New Files

- `app/Http/Requests/DeleteProjectRequest.php` -- Form Request with ownership authorization (`authorize()` checks `user_id` matches authenticated user) and validation requiring the `project_name` field to exactly match the project's actual name. Uses `withValidator()` with `after()` callback for the name comparison.
- `resources/js/components/delete-project.tsx` -- React component rendering a delete project warning section and confirmation dialog. Follows the exact pattern of `delete-user.tsx` but replaces password confirmation with project name confirmation. Accepts a `project` prop (with `id` and `name`). Uses Inertia `<Form>` with Wayfinder-generated `ProjectController.destroy.form()`. Shows a text input where the user must type the project name, with the delete button disabled until the typed name matches.
- `tests/Feature/ProjectDeleteTest.php` -- Pest feature tests covering guest redirect, owner deletion, non-owner 403, project name confirmation validation, successful deletion and redirect, and isolation from other projects.
- `tests/Browser/ProjectDeleteTest.php` -- Pest browser tests: smoke test for the delete section on the edit page, dark mode spot check, and delete confirmation dialog flow test using `data-test` selectors.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: delete-project-backend
    - Role: Creates the DeleteProjectRequest Form Request with ownership authorization and project name confirmation validation, adds the destroy method to the existing ProjectController, and registers the destroy route in web.php
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: delete-project-frontend
    - Role: Creates the DeleteProject React component with a confirmation dialog requiring the user to type the project name, integrates it into the project edit page, and ensures Wayfinder route generation
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: delete-project-tester
    - Role: Writes comprehensive Pest feature tests covering authorization, project name confirmation validation, successful deletion, and isolation from other projects
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: delete-project-reviewer
    - Role: Validates the complete feature against acceptance criteria, runs all tests, checks TypeScript types, runs linting and formatting
    - Agent Type: reviewer
    - Resume: false

- Browser Test Developer
    - Name: delete-project-browser-test-dev
    - Role: Writes Pest browser tests (smoke tests, dark mode checks, interactive flow tests) for the project delete confirmation dialog
    - Agent Type: coder
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Add Backend Route, Form Request, and Controller Destroy Method

- **Task ID**: create-backend-destroy
- **Depends On**: none
- **Assigned To**: delete-project-backend
- **Agent Type**: coder
- **Parallel**: true
- Read the existing `ProjectController` at `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/ProjectController.php` (created by E001-F010 and extended by E001-F013) to understand the current structure and imports
- Read the existing `ProfileDeleteRequest` at `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/Settings/ProfileDeleteRequest.php` for deletion form request patterns
- Read the existing `ProfileController::destroy()` at `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/ProfileController.php` for the deletion controller pattern
- Read the existing `Project` model at `/Users/young/Nextcloud/dev/Itervel/app/Models/Project.php` to confirm fillable fields and relationships
- Create the `DeleteProjectRequest` using `php artisan make:request DeleteProjectRequest --no-interaction` inside the Docker container (`make shell` or `docker compose exec app`)
- Edit `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/DeleteProjectRequest.php`:
    - Set `authorize()` to check ownership: `return $this->user()->id === $this->route('project')->user_id;`
    - Set `rules()` to return:
        ```php
        return [
            'project_name' => ['required', 'string'],
        ];
        ```
    - Add a `withValidator($validator): void` method with an `after()` callback that checks if the submitted `project_name` matches the route-bound project's name:
        ```php
        public function withValidator($validator): void
        {
            $validator->after(function ($validator) {
                if ($this->input('project_name') !== $this->route('project')->name) {
                    $validator->errors()->add(
                        'project_name',
                        'The project name does not match.'
                    );
                }
            });
        }
        ```
    - Add proper imports: `use Illuminate\Foundation\Http\FormRequest;`
- Edit `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/ProjectController.php` to add:
    - Import `App\Http\Requests\DeleteProjectRequest` (if not already imported; `App\Models\Project` should already be imported from E001-F013)
    - A `destroy(DeleteProjectRequest $request, Project $project): RedirectResponse` method:

        ```php
        /**
         * Delete the specified project.
         */
        public function destroy(DeleteProjectRequest $request, Project $project): RedirectResponse
        {
            $project->delete();

            return to_route('projects.index');
        }
        ```

- Edit `/Users/young/Nextcloud/dev/Itervel/routes/web.php` to add the new route inside the existing `Route::middleware(['auth', 'verified'])` group where the other project routes are registered:
    ```php
    Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
    ```
- Run `vendor/bin/pint --dirty` to format any changed PHP files
- Verify routes are registered: `php artisan route:list --name=projects`
- Confirm there are no PHP errors: `php artisan tinker --execute="echo 'ok';"`

### 2. Create Frontend Delete Project Component and Integrate into Edit Page

- **Task ID**: create-frontend-delete
- **Depends On**: create-backend-destroy
- **Assigned To**: delete-project-frontend
- **Agent Type**: coder
- **Parallel**: false
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/components/delete-user.tsx` carefully -- this is the primary pattern to follow. The DeleteProject component should mirror this structure but replace password confirmation with project name confirmation.
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/profile.tsx` to see how `<DeleteUser />` is integrated at the bottom of the settings page
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/projects/edit.tsx` (created by E001-F013) to understand the project edit page structure where the delete component will be integrated
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/dialog.tsx` for the Dialog component API
- Run `npm run build` first to generate the Wayfinder routes for the new `destroy` controller method
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/components/delete-project.tsx` with the following structure:

    ```tsx
    import { Form } from '@inertiajs/react';
    import { useRef, useState } from 'react';
    import Heading from '@/components/heading';
    import InputError from '@/components/input-error';
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogClose,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogTitle,
        DialogTrigger,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import ProjectController from '@/actions/App/Http/Controllers/ProjectController';

    interface DeleteProjectProps {
        project: {
            id: number;
            name: string;
        };
    }

    export default function DeleteProject({ project }: DeleteProjectProps) {
        const nameInput = useRef<HTMLInputElement>(null);
        const [confirmName, setConfirmName] = useState('');
        const nameMatches = confirmName === project.name;

        return (
            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Delete project"
                    description="Permanently delete this project and all of its resources"
                />
                <div className="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
                    <div className="relative space-y-0.5 text-red-600 dark:text-red-100">
                        <p className="font-medium">Warning</p>
                        <p className="text-sm">
                            Deleting this project will permanently remove it and
                            all associated videos. This action cannot be undone.
                        </p>
                    </div>

                    <Dialog>
                        <DialogTrigger asChild>
                            <Button variant="destructive">
                                Delete project
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogTitle>
                                Are you sure you want to delete this project?
                            </DialogTitle>
                            <DialogDescription>
                                This will permanently delete the project{' '}
                                <strong className="text-foreground">
                                    {project.name}
                                </strong>{' '}
                                and all of its associated videos. This action
                                cannot be undone. Please type{' '}
                                <strong className="text-foreground">
                                    {project.name}
                                </strong>{' '}
                                to confirm.
                            </DialogDescription>

                            <Form
                                {...ProjectController.destroy.form({
                                    project: project.id,
                                })}
                                options={{
                                    preserveScroll: true,
                                }}
                                onError={() => nameInput.current?.focus()}
                                resetOnSuccess
                                className="space-y-6"
                            >
                                {({
                                    resetAndClearErrors,
                                    processing,
                                    errors,
                                }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="project_name">
                                                Type{' '}
                                                <span className="font-semibold">
                                                    {project.name}
                                                </span>{' '}
                                                to confirm
                                            </Label>

                                            <Input
                                                id="project_name"
                                                name="project_name"
                                                ref={nameInput}
                                                placeholder={project.name}
                                                autoComplete="off"
                                                value={confirmName}
                                                onChange={(e) =>
                                                    setConfirmName(
                                                        e.target.value,
                                                    )
                                                }
                                            />

                                            <InputError
                                                message={errors.project_name}
                                            />
                                        </div>

                                        <DialogFooter className="gap-2">
                                            <DialogClose asChild>
                                                <Button
                                                    variant="secondary"
                                                    onClick={() => {
                                                        resetAndClearErrors();
                                                        setConfirmName('');
                                                    }}
                                                >
                                                    Cancel
                                                </Button>
                                            </DialogClose>

                                            <Button
                                                variant="destructive"
                                                disabled={
                                                    processing || !nameMatches
                                                }
                                                asChild
                                            >
                                                <button type="submit">
                                                    Delete project
                                                </button>
                                            </Button>
                                        </DialogFooter>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>
        );
    }
    ```

- Note: The `Form` component uses `value` and `onChange` (controlled input) instead of `defaultValue` because the component needs to track the typed value in `useState` for client-side comparison to enable/disable the delete button. The actual form submission sends the input's `name="project_name"` value to the server.
- Add `data-test` attributes to interactive elements for browser testing:
    - `data-test="delete-project-trigger"` on the "Delete project" button that opens the dialog
    - `data-test="delete-confirm-input"` on the project name confirmation input field
    - `data-test="delete-confirm-submit"` on the "Delete project" submit button inside the dialog
    - `data-test="delete-cancel-button"` on the "Cancel" button inside the dialog
- Note: The actual Wayfinder import path for `ProjectController.destroy.form()` may vary. After running `npm run build`, check the generated files in `resources/js/actions/App/Http/Controllers/ProjectController/` to confirm the correct import path. The destroy method needs the project parameter for the route URL (e.g., `ProjectController.destroy.form({ project: project.id })`). Adjust the import accordingly.
- Edit `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/projects/edit.tsx` (created by E001-F013) to integrate the delete component:
    - Import `DeleteProject` from `@/components/delete-project`
    - Add `<DeleteProject project={project} />` below the edit form, following the same pattern as `resources/js/pages/settings/profile.tsx` which renders `<DeleteUser />` below the profile form
    - The structure should be something like:

        ```tsx
        {/* ... existing edit form ... */}
        </div>

        <DeleteProject project={project} />
        ```

    - Make sure the `project` prop is properly passed. The edit page already receives a `project` prop from the controller (added by E001-F013). The DeleteProject component only needs `id` and `name`, which are available on the Project type.

- Run `npm run build` to compile assets and verify Wayfinder route generation
- Run `npm run types` to verify no TypeScript errors
- Run `npm run lint` and `npm run format` to fix any linting/formatting issues

### 3. Write Comprehensive Feature Tests for Project Deletion

- **Task ID**: write-delete-tests
- **Depends On**: create-backend-destroy
- **Assigned To**: delete-project-tester
- **Agent Type**: coder
- **Parallel**: true (can run in parallel with task 2)
- Read `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/ProfileUpdateTest.php` for deletion test patterns (specifically `'user can delete their account'` and `'correct password must be provided to delete account'`)
- Read the existing project tests (created by E001-F010 and E001-F013) for project test patterns
- Read the `ProjectFactory` at `/Users/young/Nextcloud/dev/Itervel/database/factories/ProjectFactory.php` for available factory methods
- Read the newly created `DeleteProjectRequest` at `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/DeleteProjectRequest.php` to understand the validation being tested
- Create `/Users/young/Nextcloud/dev/Itervel/tests/Feature/ProjectDeleteTest.php` using `php artisan make:test ProjectDeleteTest --pest --no-interaction`
- Write the following tests:

    ```php
    <?php

    use App\Models\Project;
    use App\Models\User;

    test('guests are redirected to login when deleting a project', function () {
        $project = Project::factory()->for(User::factory())->create();

        $response = $this->delete(route('projects.destroy', $project), [
            'project_name' => $project->name,
        ]);

        $response->assertRedirect(route('login'));
    });

    test('project owner can delete their project', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'My Channel']);

        $response = $this->actingAs($user)->delete(route('projects.destroy', $project), [
            'project_name' => 'My Channel',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('projects.index'));

        expect(Project::find($project->id))->toBeNull();
    });

    test('non-owner cannot delete another users project', function () {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::factory()->for($owner)->create(['name' => 'Owner Project']);

        $response = $this->actingAs($otherUser)->delete(route('projects.destroy', $project), [
            'project_name' => 'Owner Project',
        ]);

        $response->assertForbidden();
        expect(Project::find($project->id))->not->toBeNull();
    });

    test('project name confirmation is required to delete', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->delete(route('projects.destroy', $project), [
            'project_name' => '',
        ]);

        $response->assertSessionHasErrors('project_name');
        expect(Project::find($project->id))->not->toBeNull();
    });

    test('project name must match exactly to delete', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'My Channel']);

        $response = $this->actingAs($user)->delete(route('projects.destroy', $project), [
            'project_name' => 'my channel',
        ]);

        $response->assertSessionHasErrors('project_name');
        expect(Project::find($project->id))->not->toBeNull();
    });

    test('project name confirmation rejects incorrect name', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'My Channel']);

        $response = $this->actingAs($user)->delete(route('projects.destroy', $project), [
            'project_name' => 'Wrong Name',
        ]);

        $response->assertSessionHasErrors('project_name');
        expect(Project::find($project->id))->not->toBeNull();
    });

    test('deleting a project does not affect other projects by the same user', function () {
        $user = User::factory()->paid()->create();
        $project1 = Project::factory()->for($user)->create(['name' => 'Project One']);
        $project2 = Project::factory()->for($user)->create(['name' => 'Project Two']);

        $this->actingAs($user)->delete(route('projects.destroy', $project1), [
            'project_name' => 'Project One',
        ]);

        expect(Project::find($project1->id))->toBeNull();
        expect(Project::find($project2->id))->not->toBeNull();
        expect($project2->refresh()->name)->toBe('Project Two');
    });

    test('deleting a project does not affect other users projects', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $userProject = Project::factory()->for($user)->create(['name' => 'User Project']);
        $otherProject = Project::factory()->for($otherUser)->create(['name' => 'Other Project']);

        $this->actingAs($user)->delete(route('projects.destroy', $userProject), [
            'project_name' => 'User Project',
        ]);

        expect(Project::find($userProject->id))->toBeNull();
        expect(Project::find($otherProject->id))->not->toBeNull();
    });

    test('project deletion redirects to projects index', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Delete Me']);

        $response = $this->actingAs($user)->delete(route('projects.destroy', $project), [
            'project_name' => 'Delete Me',
        ]);

        $response->assertRedirect(route('projects.index'));
    });

    test('project name field must be provided to delete', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->delete(route('projects.destroy', $project));

        $response->assertSessionHasErrors('project_name');
        expect(Project::find($project->id))->not->toBeNull();
    });

    test('deleted project no longer appears in user project count', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'To Delete']);

        expect($user->projects()->count())->toBe(1);

        $this->actingAs($user)->delete(route('projects.destroy', $project), [
            'project_name' => 'To Delete',
        ]);

        expect($user->projects()->count())->toBe(0);
    });
    ```

- Run the tests: `php artisan test tests/Feature/ProjectDeleteTest.php --compact`
- Fix any failing tests until all pass
- Run `vendor/bin/pint --dirty` to format the test file

### 4. Write Browser Tests

- **Task ID**: write-browser-tests
- **Depends On**: create-frontend-delete, create-backend-destroy
- **Assigned To**: delete-project-browser-test-dev
- **Agent Type**: coder
- **Parallel**: false
- Create `tests/Browser/ProjectDeleteTest.php`
- Write a smoke test for the edit page with delete section:
    - Create a user with a project, visit `/projects/{project}/edit`, assert no JavaScript errors
    - Assert `[data-test="delete-project-trigger"]` is visible
- Write a dark mode spot check for the edit page with delete section:
    - Visit the edit page, switch to dark color scheme, assert no JavaScript errors
- Write an interactive flow test for the delete confirmation dialog:
    - Visit `/projects/{project}/edit`
    - Click `[data-test="delete-project-trigger"]` to open the dialog
    - Assert the dialog is visible with the project name
    - Assert `[data-test="delete-confirm-submit"]` is disabled (name not yet typed)
    - Type the project name into `[data-test="delete-confirm-input"]`
    - Assert `[data-test="delete-confirm-submit"]` becomes enabled
    - Click `[data-test="delete-confirm-submit"]`
    - Assert redirected to `/projects`
- Write a cancel flow test:
    - Click `[data-test="delete-project-trigger"]` to open the dialog
    - Click `[data-test="delete-cancel-button"]`
    - Assert the dialog closes and the project still exists
- Use `data-test` selectors for all element interactions
- Ensure all browser tests use `assertNoJavaScriptErrors()`
- Run browser tests: `php artisan test tests/Browser/ProjectDeleteTest.php --compact`

### 5. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-backend-destroy, create-frontend-delete, write-delete-tests, write-browser-tests
- **Assigned To**: delete-project-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed below
- Verify project deletion tests pass: `php artisan test tests/Feature/ProjectDeleteTest.php --compact`
- Verify existing project tests still pass: `php artisan test tests/Feature/ProjectTest.php --compact` (from E001-F010)
- Verify existing project update tests still pass: `php artisan test tests/Feature/ProjectUpdateTest.php --compact` (from E001-F013)
- Run the full test suite: `php artisan test --compact`
- Run TypeScript type checking: `npm run types`
- Run ESLint: `npm run lint`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Verify the following files exist and are correct:
    - `app/Http/Requests/DeleteProjectRequest.php` has `authorize()` checking ownership and validation with project name confirmation via `withValidator()` and `after()`
    - `app/Http/Controllers/ProjectController.php` has `destroy()` method alongside the existing `index`, `create`, `store`, `edit`, `update`
    - Route `projects.destroy` is registered: `php artisan route:list --name=projects`
    - `resources/js/components/delete-project.tsx` exists with Dialog confirmation, project name input, and Wayfinder Form integration
    - `resources/js/pages/projects/edit.tsx` renders the `<DeleteProject />` component below the edit form
    - `tests/Feature/ProjectDeleteTest.php` has comprehensive tests covering all scenarios
- Run browser tests: `php artisan test tests/Browser/ProjectDeleteTest.php --compact`
- Verify `data-test` attributes exist on interactive elements in `resources/js/components/delete-project.tsx`
- Confirm all acceptance criteria are met

## Acceptance Criteria

- Authenticated project owners can delete their own project at `DELETE /projects/{project}`
- Non-owners receive a 403 Forbidden response when attempting to delete another user's project
- Guests are redirected to the login page when attempting to delete a project
- The user must type the exact project name (case-sensitive) to confirm deletion
- An incorrect or missing project name is rejected with a validation error
- Successful deletion permanently removes the project from the database
- Successful deletion redirects the user to the projects index page
- Deleting a project does not affect other projects belonging to the same user
- Deleting a project does not affect other users' projects
- The project edit page displays a "Delete project" section with a red warning box below the edit form
- The delete confirmation dialog clearly warns that all associated videos will also be deleted
- The delete confirmation dialog shows the project name and requires the user to type it
- The delete button in the dialog is disabled until the typed name matches the project name
- All project deletion tests pass
- All existing tests continue to pass (no regressions)
- PHP code passes Pint formatting
- TypeScript passes type checking
- All new pages/components have smoke tests (no JavaScript errors)
- Dark mode spot check passes for the edit page with delete section
- Delete confirmation dialog flow passes browser tests using `data-test` selectors
- Interactive frontend elements have `data-test` attributes
- All browser tests pass
- ESLint reports no errors

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run project deletion tests
php artisan test tests/Feature/ProjectDeleteTest.php --compact

# Run existing project tests (from E001-F010)
php artisan test tests/Feature/ProjectTest.php --compact

# Run existing project update tests (from E001-F013)
php artisan test tests/Feature/ProjectUpdateTest.php --compact

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
php artisan test tests/Browser/ProjectDeleteTest.php --compact
```

## Notes

- This feature depends on E001-F010 (Create Project) which creates the Project model, migration, factory, seeder, ProjectController (with index/create/store), StoreProjectRequest, routes, frontend pages, the Project TypeScript type, and the Textarea component. It also depends indirectly on E001-F013 (Edit Project Settings) which adds edit/update methods and the edit page where the delete component will be placed. All of these must exist before this feature can be built.
- The Video model does not exist yet. The warning about "all associated videos will be deleted" is forward-looking UX text. When the Video model is introduced (E001-F050+), its migration should include `$table->foreignId('project_id')->constrained()->cascadeOnDelete()` to ensure database-level cascading. At that point, no changes to the deletion logic are needed -- `$project->delete()` will automatically cascade to videos via the foreign key constraint.
- The project name confirmation is case-sensitive. This is intentional to increase the intentionality of the action. If the project is named "My Channel", typing "my channel" will not work.
- The `DeleteProject` component uses a controlled input (`value` + `onChange` + `useState`) rather than an uncontrolled input (`defaultValue`) because it needs to compare the typed value against the project name in real time to enable/disable the delete button. This is different from the edit form fields which use `defaultValue`.
- The delete section is placed on the project edit page following the established pattern where `delete-user.tsx` appears on the profile settings page. This keeps destructive actions grouped with the settings they destroy.
- The `withValidator()` approach in `DeleteProjectRequest` is preferred over a custom validation rule because it allows access to the route-bound project model within the validation logic, making the name comparison straightforward.
- All `php artisan` commands should be run inside the Docker container. Use `make shell` to enter the container, or prefix with `docker compose exec app`.
- After the backend is built and `npm run build` is executed, Wayfinder will auto-generate the `destroy` method in `resources/js/actions/App/Http/Controllers/ProjectController/`. The frontend code should import from this generated path.

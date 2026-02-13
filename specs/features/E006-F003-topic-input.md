# Feature: Topic Input

**Epic**: E006-brand-guide-and-video-input.md
**Feature**: E006-F003
**Epic depends on**: E004-project-management.md
**Feature depends on**: E004-F001

## Task Description

Topic Input lets the user enter the main topic or concept for their video. This is the first step in the video creation workflow and establishes the foundational data model (`Video`) that all subsequent video creation features build upon. The user types a topic description (100-500 characters) into a text field, and this topic drives all subsequent content generation (titles, outlines, scripts, etc.).

**What it does**: Lets the user enter the main topic or concept for their video.

**Expected outcome**: The user types a topic description (100-500 characters) into a text field. This topic drives all subsequent content generation.

This feature creates the `Video` Eloquent model, migration, factory, `VideoController`, `StoreVideoRequest` Form Request, an Inertia React page for the video creation input step, and routes. It is the foundational building block for the entire video creation pipeline. Later epics (E009-E015) add AI generation, rendering, and output capabilities.

**Dependency on E004-F001 (Create Project)**: The Create Project feature establishes the `Project` model, migration, factory, and routes. Videos belong to a project, so the `videos` table has a `project_id` foreign key referencing the `projects` table.

## Objective

Implement the video creation topic input by creating the `Video` model with a `topic` column, a `videos` database table with appropriate columns and foreign keys, a `VideoFactory` for testing, a `VideoController` with `create` (show form) and `store` (save topic) actions, a `StoreVideoRequest` with ownership authorization and topic validation, an Inertia React page for entering the video topic, routes for the video creation flow, and comprehensive Pest feature tests.

## Solution Approach

### 1. Database: Create videos table

Create a migration for the `videos` table:

```php
Schema::create('videos', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->text('topic');
    $table->string('status')->default('draft');
    $table->timestamps();
});
```

Key design decisions:

- `project_id` with cascadeOnDelete: when a project is deleted, all its videos are deleted too (consistent with E004-F005 Delete Project)
- `user_id` with cascadeOnDelete: denormalized ownership for direct access without joining through projects, and supports account deletion (E003-F005)
- `topic` as `text`: accommodates 100-500 characters comfortably, no need for a length constraint at the DB level (handled by validation)
- `status` as `string` with default `'draft'`: the video starts as a draft when the topic is entered. Later features will transition through states (processing, completed, failed)

### 2. Video Model

Create the `Video` Eloquent model:

```php
class Video extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'user_id',
        'topic',
        'status',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

### 3. Update User and Project Models

Add `videos()` relationship to both User and Project models:

```php
// On User model
public function videos(): HasMany
{
    return $this->hasMany(Video::class);
}

// On Project model
public function videos(): HasMany
{
    return $this->hasMany(Video::class);
}
```

### 4. VideoFactory

Create a factory for the Video model:

```php
class VideoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => User::factory(),
            'topic' => fake()->paragraph(2),
            'status' => 'draft',
        ];
    }

    public function forProjectAndUser(Project $project, User $user): static
    {
        return $this->state(fn () => [
            'project_id' => $project->id,
            'user_id' => $user->id,
        ]);
    }
}
```

### 5. Form Request: StoreVideoRequest

Create validation for the topic input:

```php
class StoreVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $this->user()->id === $project->user_id;
    }

    public function rules(): array
    {
        return [
            'topic' => ['required', 'string', 'min:100', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'topic.min' => 'The topic must be at least 100 characters.',
            'topic.max' => 'The topic must not exceed 500 characters.',
        ];
    }
}
```

### 6. VideoController

Create a controller with `create` and `store` methods:

```php
class VideoController extends Controller
{
    public function create(Project $project): Response
    {
        if ($project->user_id !== auth()->id()) {
            abort(403);
        }

        return Inertia::render('videos/create', [
            'project' => $project->only(['id', 'name']),
        ]);
    }

    public function store(StoreVideoRequest $request, Project $project): RedirectResponse
    {
        $video = Video::create([
            'project_id' => $project->id,
            'user_id' => $request->user()->id,
            'topic' => $request->validated('topic'),
        ]);

        return to_route('videos.show', $video);
    }
}
```

### 7. Routes

Add video creation routes to `routes/web.php` inside the authenticated and verified middleware group:

```php
use App\Http\Controllers\VideoController;

Route::get('projects/{project}/videos/create', [VideoController::class, 'create'])->name('videos.create');
Route::post('projects/{project}/videos', [VideoController::class, 'store'])->name('videos.store');
Route::get('videos/{video}', [VideoController::class, 'show'])->name('videos.show');
```

### 8. Frontend: Video Creation Topic Input Page

Create `resources/js/pages/videos/create.tsx` with:

- A text area for the topic (100-500 characters)
- A character counter showing current/max characters
- Validation error display
- Submit button
- Breadcrumbs: "Projects" > project name > "New Video"

Use Inertia's `useForm` hook for form state management:

```tsx
const { data, setData, post, processing, errors } = useForm({
    topic: '',
});

const handleSubmit = (e: FormEvent) => {
    e.preventDefault();
    post(VideoController.store({ project: project.id }));
};
```

### 9. TypeScript Types

Create a `Video` type in `resources/js/types/video.ts`:

```typescript
export type Video = {
    id: number;
    project_id: number;
    user_id: number;
    topic: string;
    status: string;
    created_at: string;
    updated_at: string;
};
```

### 10. Tests

Write comprehensive Pest feature tests covering:

- Video creation page renders for the project owner
- Video creation page returns 403 for non-owners
- Guests are redirected to login
- Video can be created with a valid topic (100-500 chars)
- Topic at exactly 100 characters is accepted
- Topic at exactly 500 characters is accepted
- Topic under 100 characters is rejected
- Topic over 500 characters is rejected
- Topic is required (empty string rejected)
- Created video belongs to the correct project and user
- Created video has status 'draft'
- User is redirected to the video show page after creation

## Relevant Files

Use these files to complete the task:

- `/Users/young/dev/Itervel/app/Http/Controllers/Settings/ProfileController.php` -- Reference for controller patterns (Inertia rendering, return types, authorization checks).
- `/Users/young/dev/Itervel/app/Http/Controllers/Controller.php` -- Base abstract controller that VideoController will extend.
- `/Users/young/dev/Itervel/app/Http/Requests/Settings/ProfileUpdateRequest.php` -- Reference for Form Request patterns (array-syntax rules, authorize method).
- `/Users/young/dev/Itervel/app/Models/User.php` -- User model. Must add `videos()` relationship. After E004-F001, has `projects()` relationship.
- `/Users/young/dev/Itervel/routes/web.php` -- Must add video creation routes inside the authenticated/verified middleware group.
- `/Users/young/dev/Itervel/routes/settings.php` -- Reference for route grouping patterns with middleware.
- `/Users/young/dev/Itervel/resources/js/pages/settings/profile.tsx` -- Primary reference for form page patterns (useForm, InputError, Label, Button).
- `/Users/young/dev/Itervel/resources/js/pages/dashboard.tsx` -- Reference for Inertia page component pattern (AppLayout, breadcrumbs, Head).
- `/Users/young/dev/Itervel/resources/js/components/heading.tsx` -- Reusable heading component for page titles.
- `/Users/young/dev/Itervel/resources/js/components/input-error.tsx` -- Reusable validation error display component.
- `/Users/young/dev/Itervel/resources/js/components/ui/button.tsx` -- Button UI component with variant and size props.
- `/Users/young/dev/Itervel/resources/js/components/ui/label.tsx` -- Label UI component for form fields.
- `/Users/young/dev/Itervel/resources/js/layouts/app-layout.tsx` -- App layout wrapper for authenticated pages.
- `/Users/young/dev/Itervel/resources/js/types/index.ts` -- TypeScript type exports.
- `/Users/young/dev/Itervel/resources/js/types/navigation.ts` -- BreadcrumbItem type definition.
- `/Users/young/dev/Itervel/resources/js/lib/utils.ts` -- Utility functions (cn helper).
- `/Users/young/dev/Itervel/database/factories/UserFactory.php` -- User factory for test setup.
- `/Users/young/dev/Itervel/tests/Feature/Settings/ProfileUpdateTest.php` -- Reference for comprehensive CRUD test patterns with Pest.
- `/Users/young/dev/Itervel/tests/Pest.php` -- Pest configuration. All Feature tests use RefreshDatabase.
- `/Users/young/dev/Itervel/bootstrap/app.php` -- Middleware and routing configuration reference.

### New Files

- `database/migrations/xxxx_xx_xx_xxxxxx_create_videos_table.php` -- Migration to create the `videos` table with `project_id`, `user_id`, `topic`, `status`, and timestamps.
- `app/Models/Video.php` -- Video Eloquent model with fillable fields, project/user relationships, and HasFactory trait.
- `database/factories/VideoFactory.php` -- Factory for creating test videos with default state and `forProjectAndUser()` state method.
- `app/Http/Controllers/VideoController.php` -- Controller with `create` (show topic input form) and `store` (validate and save topic) methods.
- `app/Http/Requests/StoreVideoRequest.php` -- Form Request with project ownership authorization and topic validation (required, string, 100-500 chars).
- `resources/js/pages/videos/create.tsx` -- Inertia React page for entering the video topic with character counter, validation errors, and submit.
- `resources/js/types/video.ts` -- TypeScript type definition for the Video model.
- `tests/Feature/VideoCreationTest.php` -- Pest feature tests covering authorization, validation, creation, and redirect behavior.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: topic-input-backend
    - Role: Creates the videos migration, Video model, VideoFactory, VideoController, StoreVideoRequest, updates User/Project models with videos() relationship, registers routes, and runs formatting
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: topic-input-frontend
    - Role: Creates the video creation topic input page with character counter, creates the Video TypeScript type, ensures Wayfinder route generation
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: topic-input-tester
    - Role: Writes comprehensive Pest feature tests covering authorization, topic validation, video creation, and redirect behavior
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: topic-input-reviewer
    - Role: Validates the complete feature against acceptance criteria, runs all tests, checks TypeScript types, runs linting and formatting
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Create Database Migration, Models, Controller, Form Request, Factory, and Routes

- **Task ID**: create-backend-foundation
- **Depends On**: none
- **Assigned To**: topic-input-backend
- **Agent Type**: coder
- **Parallel**: true
- Read the existing `Project` model at `/Users/young/dev/Itervel/app/Models/Project.php` (created by E004-F001) to confirm the current structure and relationships
- Read the existing `User` model at `/Users/young/dev/Itervel/app/Models/User.php` to understand current relationships
- Read `/Users/young/dev/Itervel/routes/web.php` to understand the current route structure
- Read `/Users/young/dev/Itervel/app/Http/Controllers/Settings/ProfileController.php` for controller pattern reference
- Read `/Users/young/dev/Itervel/app/Http/Requests/Settings/ProfileUpdateRequest.php` for Form Request pattern reference
- Create the Video model with migration and factory: `php artisan make:model Video -mf --no-interaction`
- Edit the generated migration file:
    - In `up()`:
        ```php
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('topic');
            $table->string('status')->default('draft');
            $table->timestamps();
        });
        ```
    - In `down()`: `Schema::dropIfExists('videos');`
- Edit `/Users/young/dev/Itervel/app/Models/Video.php`:
    - Add imports: `use Illuminate\Database\Eloquent\Relations\BelongsTo`
    - Add `$fillable`: `['project_id', 'user_id', 'topic', 'status']`
    - Add `project(): BelongsTo` relationship returning `$this->belongsTo(Project::class)`
    - Add `user(): BelongsTo` relationship returning `$this->belongsTo(User::class)`
- Edit `/Users/young/dev/Itervel/app/Models/User.php`:
    - Add import for `use App\Models\Video;` and `use Illuminate\Database\Eloquent\Relations\HasMany;`
    - Add `videos(): HasMany` relationship returning `$this->hasMany(Video::class)`
- Edit `/Users/young/dev/Itervel/app/Models/Project.php`:
    - Add import for `use App\Models\Video;` and `use Illuminate\Database\Eloquent\Relations\HasMany;` (if not already imported)
    - Add `videos(): HasMany` relationship returning `$this->hasMany(Video::class)`
- Edit `/Users/young/dev/Itervel/database/factories/VideoFactory.php`:
    - Add imports: `use App\Models\Project;`, `use App\Models\User;`
    - Set `definition()`:
        ```php
        return [
            'project_id' => Project::factory(),
            'user_id' => User::factory(),
            'topic' => fake()->paragraph(2),
            'status' => 'draft',
        ];
        ```
    - Add `forProjectAndUser(Project $project, User $user): static` state method
- Create the Form Request: `php artisan make:request StoreVideoRequest --no-interaction`
- Edit `/Users/young/dev/Itervel/app/Http/Requests/StoreVideoRequest.php`:
    - Set `authorize()` to check project ownership: `return $this->user()->id === $this->route('project')->user_id;`
    - Set `rules()`:
        ```php
        return [
            'topic' => ['required', 'string', 'min:100', 'max:500'],
        ];
        ```
    - Add `messages()`:
        ```php
        return [
            'topic.min' => 'The topic must be at least 100 characters.',
            'topic.max' => 'The topic must not exceed 500 characters.',
        ];
        ```
- Create the controller: `php artisan make:controller VideoController --no-interaction`
- Edit `/Users/young/dev/Itervel/app/Http/Controllers/VideoController.php`:
    - Add imports: `use App\Http\Requests\StoreVideoRequest`, `use App\Models\Project`, `use App\Models\Video`, `use Illuminate\Http\RedirectResponse`, `use Inertia\Inertia`, `use Inertia\Response`
    - Add `create(Project $project): Response` method that checks ownership (`abort(403)` if not owner) and renders `'videos/create'` with project data
    - Add `store(StoreVideoRequest $request, Project $project): RedirectResponse` method that creates a Video with project_id, user_id, and topic, then redirects to `videos.show`
    - Add `show(Video $video): Response` placeholder method that checks ownership and renders `'videos/show'` with video data (this is a placeholder for the full video view that later features will build out)
- Edit `/Users/young/dev/Itervel/routes/web.php`:
    - Add `use App\Http\Controllers\VideoController;` import
    - Add inside the existing `Route::middleware(['auth', 'verified'])` group:
        ```php
        Route::get('projects/{project}/videos/create', [VideoController::class, 'create'])->name('videos.create');
        Route::post('projects/{project}/videos', [VideoController::class, 'store'])->name('videos.store');
        Route::get('videos/{video}', [VideoController::class, 'show'])->name('videos.show');
        ```
- Run `php artisan migrate --no-interaction`
- Run `vendor/bin/pint --dirty` to fix any formatting issues
- Verify routes are registered: `php artisan route:list --name=videos`

### 2. Create Frontend Topic Input Page and TypeScript Types

- **Task ID**: create-frontend-page
- **Depends On**: create-backend-foundation
- **Assigned To**: topic-input-frontend
- **Agent Type**: coder
- **Parallel**: false
- Read `/Users/young/dev/Itervel/resources/js/pages/settings/profile.tsx` for the form page pattern reference
- Read `/Users/young/dev/Itervel/resources/js/pages/dashboard.tsx` for the basic page layout pattern
- Read `/Users/young/dev/Itervel/resources/js/components/heading.tsx` for Heading component props
- Read `/Users/young/dev/Itervel/resources/js/components/input-error.tsx` for error display
- Read `/Users/young/dev/Itervel/resources/js/components/ui/button.tsx` for Button variants
- Read `/Users/young/dev/Itervel/resources/js/components/ui/label.tsx` for Label usage
- Run `npm run build` first to generate the Wayfinder routes for the new VideoController methods
- Create `/Users/young/dev/Itervel/resources/js/types/video.ts`:
    ```typescript
    export type Video = {
        id: number;
        project_id: number;
        user_id: number;
        topic: string;
        status: string;
        created_at: string;
        updated_at: string;
    };
    ```
- Create `/Users/young/dev/Itervel/resources/js/pages/videos/create.tsx`:
    - Import dependencies: `Head`, `useForm` from `@inertiajs/react`, Heading, InputError, Button, Label from components, AppLayout, BreadcrumbItem type
    - Import Wayfinder-generated action for VideoController (check generated files in `resources/js/actions/App/Http/Controllers/VideoController/`)
    - Define `VideoCreateProps` interface with `project: { id: number; name: string }`
    - Create a form with:
        - A `<textarea>` for the topic with `name="topic"`, `rows={6}`, and a `placeholder` like "Describe the topic for your video. What should the video be about? Include key points, angles, or perspectives you want covered..."
        - A character counter below the textarea showing `{data.topic.length}/500` with color coding: green (100-400), yellow (400-480), red (480-500), gray (<100)
        - A helper text: "Enter 100-500 characters describing your video topic"
        - `InputError` for validation errors
        - A "Continue" or "Create Video" submit button
    - Use `useForm` hook with `{ topic: '' }`
    - On submit, call `post(VideoController.store({ project: project.id }))`
    - Breadcrumbs: "Projects" > project name > "New Video"
- Also create a minimal `/Users/young/dev/Itervel/resources/js/pages/videos/show.tsx` placeholder page that displays the video topic and status (this will be expanded by later features):
    - Import `Head`, `usePage` from `@inertiajs/react`, AppLayout, Heading
    - Show the video topic in a read-only display
    - Show the video status badge
    - Breadcrumbs: "Projects" > project name > "Video"
- Run `npm run build` to compile assets
- Run `npm run types` to verify no TypeScript errors
- Run `npm run lint` to verify no linting issues

### 3. Write Comprehensive Video Creation Tests

- **Task ID**: write-video-creation-tests
- **Depends On**: create-backend-foundation
- **Assigned To**: topic-input-tester
- **Agent Type**: coder
- **Parallel**: true (can run in parallel with task 2)
- Read `/Users/young/dev/Itervel/tests/Feature/Settings/ProfileUpdateTest.php` for test patterns
- Read `/Users/young/dev/Itervel/tests/Feature/DashboardTest.php` for simple test patterns
- Read `/Users/young/dev/Itervel/database/factories/VideoFactory.php` for factory usage
- Read `/Users/young/dev/Itervel/database/factories/ProjectFactory.php` (created by E004-F001)
- Create `/Users/young/dev/Itervel/tests/Feature/VideoCreationTest.php` using `php artisan make:test VideoCreationTest --pest --no-interaction`
- Write the following tests:

    ```php
    <?php

    use App\Models\Project;
    use App\Models\User;
    use App\Models\Video;

    // --- Page Display Tests ---

    test('guests are redirected to login from video creation page', function () {
        $project = Project::factory()->for(User::factory())->create();

        $response = $this->get(route('videos.create', $project));

        $response->assertRedirect(route('login'));
    });

    test('video creation page is displayed for the project owner', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->get(route('videos.create', $project));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('videos/create')
            ->has('project')
            ->where('project.id', $project->id)
            ->where('project.name', $project->name)
        );
    });

    test('video creation page returns 403 for non-owner', function () {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $response = $this->actingAs($otherUser)->get(route('videos.create', $project));

        $response->assertForbidden();
    });

    // --- Video Creation Tests ---

    test('video can be created with a valid topic', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $topic = str_repeat('a', 150); // 150 chars, within range

        $response = $this->actingAs($user)->post(route('videos.store', $project), [
            'topic' => $topic,
        ]);

        $response->assertSessionHasNoErrors();

        $video = Video::first();
        expect($video)->not->toBeNull();
        expect($video->topic)->toBe($topic);
        expect($video->project_id)->toBe($project->id);
        expect($video->user_id)->toBe($user->id);
        expect($video->status)->toBe('draft');

        $response->assertRedirect(route('videos.show', $video));
    });

    test('topic at exactly 100 characters is accepted', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $topic = str_repeat('a', 100);

        $response = $this->actingAs($user)->post(route('videos.store', $project), [
            'topic' => $topic,
        ]);

        $response->assertSessionHasNoErrors();
        expect(Video::count())->toBe(1);
    });

    test('topic at exactly 500 characters is accepted', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $topic = str_repeat('a', 500);

        $response = $this->actingAs($user)->post(route('videos.store', $project), [
            'topic' => $topic,
        ]);

        $response->assertSessionHasNoErrors();
        expect(Video::count())->toBe(1);
    });

    test('topic under 100 characters is rejected', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $topic = str_repeat('a', 99);

        $response = $this->actingAs($user)->post(route('videos.store', $project), [
            'topic' => $topic,
        ]);

        $response->assertSessionHasErrors('topic');
        expect(Video::count())->toBe(0);
    });

    test('topic over 500 characters is rejected', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $topic = str_repeat('a', 501);

        $response = $this->actingAs($user)->post(route('videos.store', $project), [
            'topic' => $topic,
        ]);

        $response->assertSessionHasErrors('topic');
        expect(Video::count())->toBe(0);
    });

    test('topic is required', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(route('videos.store', $project), [
            'topic' => '',
        ]);

        $response->assertSessionHasErrors('topic');
        expect(Video::count())->toBe(0);
    });

    test('topic must be a string', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(route('videos.store', $project), [
            'topic' => 12345,
        ]);

        $response->assertSessionHasErrors('topic');
    });

    // --- Authorization Tests ---

    test('guests cannot create a video', function () {
        $project = Project::factory()->for(User::factory())->create();

        $response = $this->post(route('videos.store', $project), [
            'topic' => str_repeat('a', 150),
        ]);

        $response->assertRedirect(route('login'));
        expect(Video::count())->toBe(0);
    });

    test('non-owner cannot create a video in another users project', function () {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $response = $this->actingAs($otherUser)->post(route('videos.store', $project), [
            'topic' => str_repeat('a', 150),
        ]);

        $response->assertForbidden();
        expect(Video::count())->toBe(0);
    });

    // --- Video Show Tests ---

    test('video show page is displayed for the video owner', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $video = Video::factory()->forProjectAndUser($project, $user)->create();

        $response = $this->actingAs($user)->get(route('videos.show', $video));

        $response->assertOk();
    });

    test('video show page returns 403 for non-owner', function () {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $video = Video::factory()->forProjectAndUser($project, $owner)->create();

        $response = $this->actingAs($otherUser)->get(route('videos.show', $video));

        $response->assertForbidden();
    });
    ```

- Run the tests: `php artisan test tests/Feature/VideoCreationTest.php --compact`
- Fix any failing tests until all pass
- Run `vendor/bin/pint --dirty` to format the test file

### 4. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-backend-foundation, create-frontend-page, write-video-creation-tests
- **Assigned To**: topic-input-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed below
- Verify video creation tests pass: `php artisan test tests/Feature/VideoCreationTest.php --compact`
- Run the full test suite: `php artisan test --compact`
- Run TypeScript type checking: `npm run types`
- Run ESLint: `npm run lint`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Verify the following files exist and are correct:
    - A migration file creating the `videos` table with `project_id`, `user_id`, `topic`, `status`, and timestamps
    - `app/Models/Video.php` has fillable fields, project/user relationships
    - `app/Models/User.php` has `videos()` relationship
    - `app/Models/Project.php` has `videos()` relationship
    - `app/Http/Controllers/VideoController.php` has `create`, `store`, and `show` methods with ownership checks
    - `app/Http/Requests/StoreVideoRequest.php` has authorization and validation rules (required, string, min:100, max:500)
    - `database/factories/VideoFactory.php` has definition and `forProjectAndUser()` state
    - `resources/js/pages/videos/create.tsx` renders the topic input form with character counter
    - `resources/js/pages/videos/show.tsx` renders the video view placeholder
    - `resources/js/types/video.ts` has the Video TypeScript type
    - Routes are correctly defined: `videos.create`, `videos.store`, `videos.show`
- Verify routes exist: `php artisan route:list --name=videos`
- Confirm all acceptance criteria are met

## Acceptance Criteria

- Authenticated project owners can access the video creation page at `/projects/{project}/videos/create`
- Guests are redirected to the login page when accessing video creation routes
- Non-owners receive a 403 Forbidden response when attempting to create a video in another user's project
- Users can create a video by entering a topic between 100-500 characters
- Topics at exactly 100 characters are accepted
- Topics at exactly 500 characters are accepted
- Topics under 100 characters are rejected with a validation error
- Topics over 500 characters are rejected with a validation error
- An empty topic is rejected with a validation error
- Created videos belong to the correct project and user
- Created videos have a status of 'draft'
- After creation, the user is redirected to the video show page
- The topic input page displays a character counter
- The `Video` model has `project()` and `user()` relationships
- The `User` and `Project` models have `videos()` relationships
- The `VideoFactory` has a `forProjectAndUser()` state method
- The `Video` TypeScript type exists in `resources/js/types/video.ts`
- All video creation tests pass
- All existing tests continue to pass (no regressions)
- PHP code passes Pint formatting
- TypeScript passes type checking
- ESLint reports no errors

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run video creation tests
php artisan test tests/Feature/VideoCreationTest.php --compact

# Run full test suite for regression check
php artisan test --compact

# Verify routes are registered
php artisan route:list --name=videos

# TypeScript type checking
npm run types

# ESLint linting
npm run lint

# PHP code formatting
vendor/bin/pint --dirty
```

## Notes

- This feature creates the foundational `Video` model that all subsequent video creation features build upon. Later features will add columns to the `videos` table (e.g., `title`, `outline`, `script`, `thumbnail_path`, etc.) via separate migrations.
- The `status` column uses a simple string rather than an enum at the database level. This allows flexibility for adding new statuses in later features without requiring a migration. Possible values: `draft`, `processing`, `completed`, `failed`. Validation of status values should be handled at the application level.
- The `user_id` column is denormalized (the user could be derived from `project.user_id`). This is intentional for performance (no join needed for ownership checks) and for the cascade delete chain (when a user account is deleted via E003-F005, all their videos are deleted directly).
- The video `show` route uses `videos/{video}` (not nested under projects) for cleaner URLs and simpler routing. The ownership check is done in the controller by comparing `$video->user_id` with the authenticated user.
- The `create` route is nested under projects (`projects/{project}/videos/create`) because the user selects a project first and then creates a video in it.
- The character counter on the frontend is purely visual -- it does not prevent typing beyond 500 characters (the backend validation handles enforcement). The counter helps users understand the constraints.
- All `php artisan` commands should be run inside the Docker container. Use `make shell` to enter the container, or prefix with `docker compose exec app`.

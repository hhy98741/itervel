# Feature: Reference URL Input

**Epic**: E006-brand-guide-and-video-input.md
**Feature**: E006-F004
**Epic depends on**: E004-project-management.md
**Feature depends on**: E006-F003

## Task Description

Reference URL Input allows users to provide up to 3 reference URLs (articles or YouTube videos) to inform the AI's research when generating video content. These URLs serve as source material that the AI can draw upon for facts, perspectives, and structure. The user pastes URLs into the video creation form, and the system validates the URL format and checks basic accessibility before accepting them.

**What it does**: Allows users to provide up to 3 reference URLs (articles or YouTube videos) to inform the AI's research.

**Expected outcome**: The user pastes up to 3 URLs. The system validates the URL format and checks accessibility.

This feature builds on E006-F003 (Topic Input), which creates the `Video` model, `VideoController`, and video creation infrastructure. It adds a `video_references` table for storing reference URLs with their processing status, a `VideoReference` model, an update to the video creation flow to accept reference URLs after the topic is entered, and comprehensive tests.

The reference URLs are stored in a separate `video_references` table rather than as JSON on the video record because each URL has its own processing lifecycle (pending → processing → completed/failed) tracked independently. This separation supports the async processing in E006-F005 and the graceful failure handling in E006-F006.

**Dependency on E006-F003 (Topic Input)**: E006-F003 creates the `Video` model, `videos` table, `VideoController`, `VideoFactory`, `StoreVideoRequest`, and video creation routes. This feature adds a new model (`VideoReference`), a new migration (`video_references`), updates to the `VideoController` to handle reference URL submission, and a new page for the reference URL input step.

## Objective

Implement reference URL input by creating the `VideoReference` model and `video_references` database table, a `VideoReferenceFactory` for testing, an `AddReferencesRequest` Form Request with URL validation, updating the `VideoController` with a references step, creating an Inertia React page for entering reference URLs, adding routes for the references step, and writing comprehensive Pest feature tests.

## Solution Approach

### 1. Database: Create video_references table

Create a migration for the `video_references` table:

```php
Schema::create('video_references', function (Blueprint $table) {
    $table->id();
    $table->foreignId('video_id')->constrained()->cascadeOnDelete();
    $table->string('url', 2048);
    $table->string('type')->default('article');
    $table->text('extracted_content')->nullable();
    $table->string('processing_status')->default('pending');
    $table->text('processing_error')->nullable();
    $table->timestamp('processed_at')->nullable();
    $table->timestamps();
});
```

Key design decisions:

- `video_id` with cascadeOnDelete: when a video is deleted, its references are cleaned up
- `url` as `string(2048)`: URLs can be long; 2048 is a practical maximum supported by most browsers
- `type` as string: `article` or `youtube`, detected from the URL pattern
- `extracted_content` as nullable text: populated by E006-F005 (Reference URL Processing)
- `processing_status` as string: `pending`, `processing`, `completed`, `failed`
- `processing_error` as nullable text: populated by E006-F006 (Graceful URL Processing Failure) when processing fails
- `processed_at` as nullable timestamp: set when processing completes or fails

### 2. VideoReference Model

```php
class VideoReference extends Model
{
    use HasFactory;

    protected $fillable = [
        'video_id',
        'url',
        'type',
        'extracted_content',
        'processing_status',
        'processing_error',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }
}
```

### 3. Update Video Model

Add a `references()` relationship to the Video model:

```php
public function references(): HasMany
{
    return $this->hasMany(VideoReference::class);
}
```

### 4. VideoReferenceFactory

```php
class VideoReferenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'video_id' => Video::factory(),
            'url' => fake()->url(),
            'type' => 'article',
            'processing_status' => 'pending',
        ];
    }

    public function youtube(): static
    {
        return $this->state(fn () => [
            'url' => 'https://www.youtube.com/watch?v=' . fake()->regexify('[A-Za-z0-9_-]{11}'),
            'type' => 'youtube',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'extracted_content' => fake()->paragraphs(3, true),
            'processing_status' => 'completed',
            'processed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'processing_status' => 'failed',
            'processing_error' => 'Connection timed out after 30 seconds.',
            'processed_at' => now(),
        ]);
    }
}
```

### 5. Form Request: AddReferencesRequest

```php
class AddReferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $video = $this->route('video');

        return $this->user()->id === $video->user_id;
    }

    public function rules(): array
    {
        return [
            'urls' => ['present', 'array', 'max:3'],
            'urls.*' => ['required', 'url:http,https', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'urls.max' => 'You can provide up to 3 reference URLs.',
            'urls.*.url' => 'Each reference must be a valid URL.',
            'urls.*.max' => 'Each URL must not exceed 2048 characters.',
        ];
    }
}
```

The `present` rule ensures the `urls` field is included in the request (even if empty array), and `array` + `max:3` limits to 3 entries. Each entry must be a valid HTTP/HTTPS URL.

### 6. URL Type Detection

Add a helper method to detect whether a URL is a YouTube video or an article:

```php
// In VideoReference model or a helper
public static function detectType(string $url): string
{
    $host = parse_url($url, PHP_URL_HOST);

    if ($host && preg_match('/(?:youtube\.com|youtu\.be)$/i', $host)) {
        return 'youtube';
    }

    return 'article';
}
```

### 7. Update VideoController

Add a `references` method (GET) to show the reference URL input form, and an `addReferences` method (POST) to save the URLs:

```php
public function references(Video $video): Response
{
    if ($video->user_id !== auth()->id()) {
        abort(403);
    }

    return Inertia::render('videos/references', [
        'video' => $video->only(['id', 'topic']),
        'references' => $video->references->map(fn ($ref) => $ref->only(['id', 'url', 'type', 'processing_status'])),
    ]);
}

public function addReferences(AddReferencesRequest $request, Video $video): RedirectResponse
{
    // Remove existing references and replace with new ones
    $video->references()->delete();

    foreach ($request->validated('urls') as $url) {
        $video->references()->create([
            'url' => $url,
            'type' => VideoReference::detectType($url),
        ]);
    }

    return to_route('videos.references', $video);
}
```

### 8. Routes

Add reference URL routes to `routes/web.php`:

```php
Route::get('videos/{video}/references', [VideoController::class, 'references'])->name('videos.references');
Route::post('videos/{video}/references', [VideoController::class, 'addReferences'])->name('videos.references.store');
```

### 9. Frontend: Reference URL Input Page

Create `resources/js/pages/videos/references.tsx` with:

- Up to 3 URL input fields
- An "Add URL" button to add more fields (starting with 1, max 3)
- A "Remove" button next to each URL field
- URL format validation hint
- Validation error display per URL
- Submit button to save references
- A "Skip" link to continue without references
- Breadcrumbs: "Projects" > "Video" > "References"

### 10. Update Video Show Page

After E006-F003 created a placeholder show page, update it to display reference URLs if present, with their processing status.

### 11. Tests

Write comprehensive tests covering:

- References page renders for the video owner
- References page returns 403 for non-owners
- Guests are redirected to login
- Valid URLs are accepted and saved
- YouTube URLs are detected and typed correctly
- Article URLs are detected and typed correctly
- More than 3 URLs are rejected
- Invalid URL format is rejected
- Empty URL in the array is rejected
- References can be updated (replaced)
- Skip (empty urls array) is accepted
- Existing references are cleared when submitting new ones

## Relevant Files

Use these files to complete the task:

- `/Users/young/dev/Itervel/app/Http/Controllers/VideoController.php` -- Video controller created by E006-F003. Must add `references` and `addReferences` methods.
- `/Users/young/dev/Itervel/app/Models/Video.php` -- Video model created by E006-F003. Must add `references()` relationship.
- `/Users/young/dev/Itervel/app/Models/User.php` -- User model. Referenced for ownership checks.
- `/Users/young/dev/Itervel/app/Http/Requests/StoreVideoRequest.php` -- Reference for Form Request patterns created by E006-F003.
- `/Users/young/dev/Itervel/app/Http/Controllers/Settings/ProfileController.php` -- Reference for controller patterns.
- `/Users/young/dev/Itervel/app/Http/Requests/Settings/ProfileUpdateRequest.php` -- Reference for Form Request patterns.
- `/Users/young/dev/Itervel/routes/web.php` -- Must add reference URL routes inside the authenticated/verified middleware group.
- `/Users/young/dev/Itervel/resources/js/pages/videos/create.tsx` -- Video creation page created by E006-F003. Referenced for consistent styling.
- `/Users/young/dev/Itervel/resources/js/pages/videos/show.tsx` -- Video show page created by E006-F003. Must be updated to display references.
- `/Users/young/dev/Itervel/resources/js/pages/settings/profile.tsx` -- Reference for form page patterns.
- `/Users/young/dev/Itervel/resources/js/components/heading.tsx` -- Heading component.
- `/Users/young/dev/Itervel/resources/js/components/input-error.tsx` -- Validation error display.
- `/Users/young/dev/Itervel/resources/js/components/ui/button.tsx` -- Button component.
- `/Users/young/dev/Itervel/resources/js/components/ui/input.tsx` -- Input component for URL fields.
- `/Users/young/dev/Itervel/resources/js/components/ui/label.tsx` -- Label component.
- `/Users/young/dev/Itervel/resources/js/layouts/app-layout.tsx` -- App layout wrapper.
- `/Users/young/dev/Itervel/resources/js/types/video.ts` -- Video TypeScript type created by E006-F003. Must be updated.
- `/Users/young/dev/Itervel/resources/js/types/index.ts` -- TypeScript type exports.
- `/Users/young/dev/Itervel/resources/js/lib/utils.ts` -- Utility functions (cn helper).
- `/Users/young/dev/Itervel/database/factories/VideoFactory.php` -- Video factory created by E006-F003.
- `/Users/young/dev/Itervel/database/factories/UserFactory.php` -- User factory for test setup.
- `/Users/young/dev/Itervel/tests/Feature/VideoCreationTest.php` -- Video creation tests created by E006-F003. Referenced for test patterns.
- `/Users/young/dev/Itervel/tests/Pest.php` -- Pest configuration.

### New Files

- `database/migrations/xxxx_xx_xx_xxxxxx_create_video_references_table.php` -- Migration to create the `video_references` table.
- `app/Models/VideoReference.php` -- VideoReference Eloquent model with fillable fields, casts, video relationship, and `detectType()` static method.
- `database/factories/VideoReferenceFactory.php` -- Factory with default, `youtube()`, `completed()`, and `failed()` state methods.
- `app/Http/Requests/AddReferencesRequest.php` -- Form Request with video ownership authorization and URL array validation.
- `resources/js/pages/videos/references.tsx` -- Inertia React page for entering up to 3 reference URLs with add/remove controls.
- `resources/js/types/video-reference.ts` -- TypeScript type definition for the VideoReference model.
- `tests/Feature/VideoReferenceTest.php` -- Pest feature tests for reference URL input.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: reference-url-backend
    - Role: Creates the video_references migration, VideoReference model, VideoReferenceFactory, AddReferencesRequest, updates VideoController with references/addReferences methods, updates Video model with references() relationship, registers routes
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: reference-url-frontend
    - Role: Creates the reference URL input page with dynamic URL fields, creates VideoReference TypeScript type, updates the video show page to display references
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: reference-url-tester
    - Role: Writes comprehensive Pest feature tests covering authorization, URL validation, type detection, reference management, and edge cases
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: reference-url-reviewer
    - Role: Validates the complete feature against acceptance criteria, runs all tests, checks TypeScript types, runs linting and formatting
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Create Database Migration, Models, Controller Methods, Form Request, Factory, and Routes

- **Task ID**: create-backend-foundation
- **Depends On**: none
- **Assigned To**: reference-url-backend
- **Agent Type**: coder
- **Parallel**: true
- Read the existing `Video` model at `/Users/young/dev/Itervel/app/Models/Video.php` (created by E006-F003)
- Read the existing `VideoController` at `/Users/young/dev/Itervel/app/Http/Controllers/VideoController.php` (created by E006-F003)
- Read `/Users/young/dev/Itervel/routes/web.php` to understand the current route structure
- Create the VideoReference model with migration and factory: `php artisan make:model VideoReference -mf --no-interaction`
- Edit the generated migration file:
    - In `up()`:
        ```php
        Schema::create('video_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2048);
            $table->string('type')->default('article');
            $table->text('extracted_content')->nullable();
            $table->string('processing_status')->default('pending');
            $table->text('processing_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
        ```
    - In `down()`: `Schema::dropIfExists('video_references');`
- Edit `/Users/young/dev/Itervel/app/Models/VideoReference.php`:
    - Add imports: `use Illuminate\Database\Eloquent\Relations\BelongsTo`, `use App\Models\Video`
    - Set `$fillable`: `['video_id', 'url', 'type', 'extracted_content', 'processing_status', 'processing_error', 'processed_at']`
    - Add `casts()` method returning `['processed_at' => 'datetime']`
    - Add `video(): BelongsTo` relationship returning `$this->belongsTo(Video::class)`
    - Add `detectType(string $url): string` static method:

        ```php
        public static function detectType(string $url): string
        {
            $host = parse_url($url, PHP_URL_HOST);

            if ($host && preg_match('/(?:youtube\.com|youtu\.be)$/i', $host)) {
                return 'youtube';
            }

            return 'article';
        }
        ```

- Edit `/Users/young/dev/Itervel/app/Models/Video.php`:
    - Add import: `use App\Models\VideoReference;`
    - Add `references(): HasMany` relationship returning `$this->hasMany(VideoReference::class)`
- Edit `/Users/young/dev/Itervel/database/factories/VideoReferenceFactory.php`:
    - Add imports: `use App\Models\Video`
    - Set `definition()`:
        ```php
        return [
            'video_id' => Video::factory(),
            'url' => fake()->url(),
            'type' => 'article',
            'processing_status' => 'pending',
        ];
        ```
    - Add `youtube(): static` state method that sets url to a YouTube URL pattern and type to 'youtube'
    - Add `completed(): static` state method that sets extracted_content, processing_status to 'completed', and processed_at to now()
    - Add `failed(): static` state method that sets processing_status to 'failed', processing_error message, and processed_at to now()
- Create the Form Request: `php artisan make:request AddReferencesRequest --no-interaction`
- Edit `/Users/young/dev/Itervel/app/Http/Requests/AddReferencesRequest.php`:
    - Set `authorize()` to check video ownership: `return $this->user()->id === $this->route('video')->user_id;`
    - Set `rules()`:
        ```php
        return [
            'urls' => ['present', 'array', 'max:3'],
            'urls.*' => ['required', 'url:http,https', 'max:2048'],
        ];
        ```
    - Add `messages()`:
        ```php
        return [
            'urls.max' => 'You can provide up to 3 reference URLs.',
            'urls.*.url' => 'Each reference must be a valid URL.',
            'urls.*.max' => 'Each URL must not exceed 2048 characters.',
        ];
        ```
- Edit `/Users/young/dev/Itervel/app/Http/Controllers/VideoController.php`:
    - Add imports: `use App\Http\Requests\AddReferencesRequest`, `use App\Models\VideoReference`
    - Add `references(Video $video): Response` method:
        - Check ownership: `if ($video->user_id !== auth()->id()) { abort(403); }`
        - Return `Inertia::render('videos/references', [...])` with video data and existing references
    - Add `addReferences(AddReferencesRequest $request, Video $video): RedirectResponse` method:
        - Delete existing references: `$video->references()->delete();`
        - Loop through validated URLs and create VideoReference for each, using `VideoReference::detectType($url)` for type detection
        - Return redirect to `videos.references` route
- Edit `/Users/young/dev/Itervel/routes/web.php`:
    - Add inside the existing `Route::middleware(['auth', 'verified'])` group:
        ```php
        Route::get('videos/{video}/references', [VideoController::class, 'references'])->name('videos.references');
        Route::post('videos/{video}/references', [VideoController::class, 'addReferences'])->name('videos.references.store');
        ```
- Run `php artisan migrate --no-interaction`
- Run `vendor/bin/pint --dirty`
- Verify routes: `php artisan route:list --name=videos.references`

### 2. Create Frontend Reference URL Input Page and TypeScript Types

- **Task ID**: create-frontend-page
- **Depends On**: create-backend-foundation
- **Assigned To**: reference-url-frontend
- **Agent Type**: coder
- **Parallel**: false
- Read `/Users/young/dev/Itervel/resources/js/pages/videos/create.tsx` for consistent styling with the video creation flow
- Read `/Users/young/dev/Itervel/resources/js/pages/settings/profile.tsx` for form patterns
- Read `/Users/young/dev/Itervel/resources/js/components/ui/input.tsx` for Input component usage
- Read `/Users/young/dev/Itervel/resources/js/components/ui/button.tsx` for Button variants
- Run `npm run build` to generate Wayfinder routes for the new controller methods
- Create `/Users/young/dev/Itervel/resources/js/types/video-reference.ts`:
    ```typescript
    export type VideoReference = {
        id: number;
        video_id: number;
        url: string;
        type: 'article' | 'youtube';
        extracted_content: string | null;
        processing_status: 'pending' | 'processing' | 'completed' | 'failed';
        processing_error: string | null;
        processed_at: string | null;
        created_at: string;
        updated_at: string;
    };
    ```
- Create `/Users/young/dev/Itervel/resources/js/pages/videos/references.tsx`:
    - Define props interface with `video: { id: number; topic: string }` and `references: Array<{ id: number; url: string; type: string; processing_status: string }>`
    - Use `useForm` hook with `{ urls: [''] }` initial state (start with one empty URL field)
    - Implement dynamic URL field management:
        - "Add URL" button that adds an empty string to the urls array (max 3)
        - "Remove" button next to each field (only shown when more than 1 field, or when field has content)
        - Each field is an `<Input>` with `type="url"` and `placeholder="https://example.com/article or https://youtube.com/watch?v=..."`
    - Show validation errors per URL using indexed error keys (`errors['urls.0']`, `errors['urls.1']`, etc.)
    - Show a "Save References" submit button
    - Show a "Skip" link (using Inertia Link) that navigates to the next step without saving references
    - If references already exist (from a previous save), pre-populate the URL fields
    - Display a helper text: "Add up to 3 reference URLs (articles or YouTube videos) to help the AI research your topic"
    - Breadcrumbs: video topic (truncated) > "References"
- Update `/Users/young/dev/Itervel/resources/js/pages/videos/show.tsx`:
    - Add a section to display reference URLs if present
    - Show each reference URL with its type (article/youtube icon or badge) and processing status
- Run `npm run build` to compile assets
- Run `npm run types` to verify no TypeScript errors
- Run `npm run lint` to verify no linting issues

### 3. Write Comprehensive Reference URL Tests

- **Task ID**: write-reference-tests
- **Depends On**: create-backend-foundation
- **Assigned To**: reference-url-tester
- **Agent Type**: coder
- **Parallel**: true (can run in parallel with task 2)
- Read `/Users/young/dev/Itervel/tests/Feature/VideoCreationTest.php` for test patterns
- Read `/Users/young/dev/Itervel/database/factories/VideoFactory.php` and `/Users/young/dev/Itervel/database/factories/VideoReferenceFactory.php`
- Create `/Users/young/dev/Itervel/tests/Feature/VideoReferenceTest.php` using `php artisan make:test VideoReferenceTest --pest --no-interaction`
- Write the following tests:

    ```php
    <?php

    use App\Models\Project;
    use App\Models\User;
    use App\Models\Video;
    use App\Models\VideoReference;

    // --- Page Display Tests ---

    test('guests are redirected to login from references page', function () {
        $video = Video::factory()->create();

        $response = $this->get(route('videos.references', $video));

        $response->assertRedirect(route('login'));
    });

    test('references page is displayed for the video owner', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $video = Video::factory()->forProjectAndUser($project, $user)->create();

        $response = $this->actingAs($user)->get(route('videos.references', $video));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('videos/references')
            ->has('video')
            ->where('video.id', $video->id)
        );
    });

    test('references page returns 403 for non-owner', function () {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $video = Video::factory()->forProjectAndUser($project, $owner)->create();

        $response = $this->actingAs($otherUser)->get(route('videos.references', $video));

        $response->assertForbidden();
    });

    test('references page shows existing references', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $video = Video::factory()->forProjectAndUser($project, $user)->create();
        VideoReference::factory()->for($video)->count(2)->create();

        $response = $this->actingAs($user)->get(route('videos.references', $video));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('references', 2)
        );
    });

    // --- Add References Tests ---

    test('valid article URLs are accepted and saved', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $video = Video::factory()->forProjectAndUser($project, $user)->create();

        $response = $this->actingAs($user)->post(route('videos.references.store', $video), [
            'urls' => [
                'https://example.com/article-1',
                'https://blog.example.com/post/2024/my-article',
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('videos.references', $video));

        expect($video->references()->count())->toBe(2);
        expect($video->references()->first()->type)->toBe('article');
    });

    test('youtube URLs are detected and typed correctly', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $video = Video::factory()->forProjectAndUser($project, $user)->create();

        $this->actingAs($user)->post(route('videos.references.store', $video), [
            'urls' => [
                'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'https://youtu.be/dQw4w9WgXcQ',
            ],
        ]);

        $references = $video->references()->get();
        expect($references)->toHaveCount(2);
        expect($references[0]->type)->toBe('youtube');
        expect($references[1]->type)->toBe('youtube');
    });

    test('mixed article and youtube URLs are saved with correct types', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $video = Video::factory()->forProjectAndUser($project, $user)->create();

        $this->actingAs($user)->post(route('videos.references.store', $video), [
            'urls' => [
                'https://example.com/article',
                'https://www.youtube.com/watch?v=abc123',
                'https://another-blog.com/post',
            ],
        ]);

        $references = $video->references()->orderBy('id')->get();
        expect($references)->toHaveCount(3);
        expect($references[0]->type)->toBe('article');
        expect($references[1]->type)->toBe('youtube');
        expect($references[2]->type)->toBe('article');
    });

    test('references have pending processing status by default', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $video = Video::factory()->forProjectAndUser($project, $user)->create();

        $this->actingAs($user)->post(route('videos.references.store', $video), [
            'urls' => ['https://example.com/article'],
        ]);

        expect($video->references()->first()->processing_status)->toBe('pending');
    });

    test('maximum of 3 URLs are accepted', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $video = Video::factory()->forProjectAndUser($project, $user)->create();

        $response = $this->actingAs($user)->post(route('videos.references.store', $video), [
            'urls' => [
                'https://example.com/1',
                'https://example.com/2',
                'https://example.com/3',
            ],
        ]);

        $response->assertSessionHasNoErrors();
        expect($video->references()->count())->toBe(3);
    });

    test('more than 3 URLs are rejected', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $video = Video::factory()->forProjectAndUser($project, $user)->create();

        $response = $this->actingAs($user)->post(route('videos.references.store', $video), [
            'urls' => [
                'https://example.com/1',
                'https://example.com/2',
                'https://example.com/3',
                'https://example.com/4',
            ],
        ]);

        $response->assertSessionHasErrors('urls');
        expect($video->references()->count())->toBe(0);
    });

    test('invalid URL format is rejected', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $video = Video::factory()->forProjectAndUser($project, $user)->create();

        $response = $this->actingAs($user)->post(route('videos.references.store', $video), [
            'urls' => ['not-a-valid-url'],
        ]);

        $response->assertSessionHasErrors('urls.0');
    });

    test('empty URL in array is rejected', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $video = Video::factory()->forProjectAndUser($project, $user)->create();

        $response = $this->actingAs($user)->post(route('videos.references.store', $video), [
            'urls' => [''],
        ]);

        $response->assertSessionHasErrors('urls.0');
    });

    test('empty urls array is accepted as skipping references', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $video = Video::factory()->forProjectAndUser($project, $user)->create();

        $response = $this->actingAs($user)->post(route('videos.references.store', $video), [
            'urls' => [],
        ]);

        $response->assertSessionHasNoErrors();
        expect($video->references()->count())->toBe(0);
    });

    test('submitting new references replaces existing ones', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $video = Video::factory()->forProjectAndUser($project, $user)->create();
        VideoReference::factory()->for($video)->count(2)->create();

        expect($video->references()->count())->toBe(2);

        $this->actingAs($user)->post(route('videos.references.store', $video), [
            'urls' => ['https://new-reference.com/article'],
        ]);

        expect($video->references()->count())->toBe(1);
        expect($video->references()->first()->url)->toBe('https://new-reference.com/article');
    });

    // --- Authorization Tests ---

    test('guests cannot add references', function () {
        $video = Video::factory()->create();

        $response = $this->post(route('videos.references.store', $video), [
            'urls' => ['https://example.com'],
        ]);

        $response->assertRedirect(route('login'));
    });

    test('non-owner cannot add references to another users video', function () {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $video = Video::factory()->forProjectAndUser($project, $owner)->create();

        $response = $this->actingAs($otherUser)->post(route('videos.references.store', $video), [
            'urls' => ['https://example.com'],
        ]);

        $response->assertForbidden();
        expect($video->references()->count())->toBe(0);
    });

    // --- URL Type Detection Tests ---

    test('youtube.com URLs are detected as youtube type', function () {
        expect(VideoReference::detectType('https://www.youtube.com/watch?v=abc123'))->toBe('youtube');
    });

    test('youtu.be URLs are detected as youtube type', function () {
        expect(VideoReference::detectType('https://youtu.be/abc123'))->toBe('youtube');
    });

    test('non-youtube URLs are detected as article type', function () {
        expect(VideoReference::detectType('https://example.com/article'))->toBe('article');
        expect(VideoReference::detectType('https://blog.medium.com/post'))->toBe('article');
    });
    ```

- Run the tests: `php artisan test tests/Feature/VideoReferenceTest.php --compact`
- Fix any failing tests until all pass
- Run `vendor/bin/pint --dirty`

### 4. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-backend-foundation, create-frontend-page, write-reference-tests
- **Assigned To**: reference-url-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed below
- Verify reference tests pass: `php artisan test tests/Feature/VideoReferenceTest.php --compact`
- Verify video creation tests still pass: `php artisan test tests/Feature/VideoCreationTest.php --compact`
- Run the full test suite: `php artisan test --compact`
- Run TypeScript type checking: `npm run types`
- Run ESLint: `npm run lint`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Verify all files exist and are correct per the acceptance criteria
- Confirm all acceptance criteria are met

## Acceptance Criteria

- Authenticated video owners can access the references page at `/videos/{video}/references`
- Guests are redirected to the login page when accessing reference routes
- Non-owners receive a 403 Forbidden response
- Users can add up to 3 reference URLs
- YouTube URLs (`youtube.com`, `youtu.be`) are detected and stored with type `youtube`
- Non-YouTube URLs are stored with type `article`
- All saved references have `pending` processing status by default
- More than 3 URLs are rejected with a validation error
- Invalid URL formats are rejected with a validation error
- Empty URLs in the array are rejected
- An empty URLs array is accepted (skip references)
- Submitting new references replaces all existing references for that video
- The references page displays existing references if any
- The reference URL input page allows adding and removing URL fields dynamically
- The `VideoReference` model has a `video()` relationship
- The `Video` model has a `references()` relationship
- The `VideoReferenceFactory` has `youtube()`, `completed()`, and `failed()` states
- The `VideoReference` TypeScript type exists
- All reference tests pass
- All existing tests continue to pass (no regressions)
- PHP code passes Pint formatting
- TypeScript passes type checking
- ESLint reports no errors

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run reference URL tests
php artisan test tests/Feature/VideoReferenceTest.php --compact

# Run video creation tests (regression check)
php artisan test tests/Feature/VideoCreationTest.php --compact

# Run full test suite
php artisan test --compact

# Verify routes
php artisan route:list --name=videos.references

# TypeScript type checking
npm run types

# ESLint linting
npm run lint

# PHP code formatting
vendor/bin/pint --dirty
```

## Notes

- The reference URLs are stored in a separate `video_references` table rather than as a JSON column on the `videos` table. This is intentional because each reference has its own processing lifecycle (E006-F005) and error state (E006-F006) that need to be tracked independently. A separate table also makes it easier to query references by status.
- The `addReferences` method deletes all existing references before creating new ones (replace strategy). This is simpler than diffing and more predictable. Since references at this stage have not yet been processed (they are in `pending` status), there is no data loss.
- The URL type detection uses a simple regex pattern matching on the hostname. It does not validate that the YouTube URL contains a valid video ID or that the article URL is reachable. Accessibility checking and content extraction are handled by E006-F005.
- The `url:http,https` validation rule ensures only HTTP and HTTPS URLs are accepted. FTP, mailto, and other URL schemes are rejected.
- The `max:2048` rule on individual URLs prevents excessively long URLs. 2048 characters is the practical maximum supported by most browsers and servers.
- All `php artisan` commands should be run inside the Docker container. Use `make shell` to enter the container, or prefix with `docker compose exec app`.

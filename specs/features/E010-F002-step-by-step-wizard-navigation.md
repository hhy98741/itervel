# Feature: Step-by-Step Wizard Navigation

**Epic**: E010-video-creation-ux.md
**Feature**: E010-F002
**Epic depends on**: E006-brand-guide-and-video-input.md
**Feature depends on**: E010-F001, E010-F004

## Task Description

Guides users through the video creation process in a clear, sequential wizard interface. The user moves through 9 steps: Input, Title Selection, Outline Review, Script Review, Voiceover Selection, Thumbnail Selection, Music Selection, Final Review, and Download. Each step is clearly labeled and the user can see their position in the workflow.

**What it does**: Guides users through the video creation process in a clear, sequential wizard interface.

**Expected outcome**: The user moves through 9 steps: Input, Title Selection, Outline Review, Script Review, Voiceover Selection, Thumbnail Selection, Music Selection, Final Review, and Download. Each step is clearly labeled and the user can see their position in the workflow.

This feature creates the central wizard framework for video creation. It is the main user-facing page where videos are created. The wizard manages step state, renders step content, provides navigation controls (next/back), integrates the progress indicator (E010-F001), and uses the responsive layout infrastructure (E010-F004).

At this stage, since later epics (E011-E015) will build the actual step content (outline generation, thumbnail selection, etc.), this feature creates the wizard framework with placeholder content for each step. Each step placeholder shows the step name and a description of what will be built there. The Input step integrates with the brand guide and topic input features from E006.

This feature requires both backend (controller, routes, Video model) and frontend (wizard page, layout, step components) work.

## Objective

Create a complete video creation wizard page at `/videos/create` with a step-by-step navigation interface. The wizard manages a 9-step workflow with next/back navigation, integrates the `VideoProgressIndicator` from E010-F001, uses responsive layout components from E010-F004, and provides placeholder content for each step that later epics will replace with real functionality.

## Solution Approach

### Backend Architecture

#### Video Model

Create a `Video` model to represent a video being created. This is the core entity that tracks the video creation workflow.

```php
// Migration: create_videos_table
Schema::create('videos', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
    $table->string('title')->nullable();
    $table->string('topic')->nullable();
    $table->text('brand_guide')->nullable();
    $table->json('reference_urls')->nullable();
    $table->string('status')->default('draft'); // draft, processing, completed, failed
    $table->string('current_step')->default('input');
    $table->json('step_data')->nullable(); // Stores per-step selections/data
    $table->timestamps();
});
```

**Note**: The Project model may not exist yet (it's from E004 which is planned but may not be built). Use `nullable()` for `project_id` and handle the case where projects don't exist yet. Check if the `projects` table migration exists before adding the foreign key constraint. If projects don't exist yet, skip the foreign key constraint and just use `$table->unsignedBigInteger('project_id')->nullable()`.

Model:

```php
class Video extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'project_id', 'title', 'topic',
        'brand_guide', 'reference_urls', 'status',
        'current_step', 'step_data',
    ];

    protected function casts(): array
    {
        return [
            'reference_urls' => 'array',
            'step_data' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

Also add the inverse relationship on the User model:

```php
// In User.php
public function videos(): HasMany
{
    return $this->hasMany(Video::class);
}
```

#### Video Creation Controller

Create a `VideoCreationController` that handles the wizard flow:

```php
class VideoCreationController extends Controller
{
    public function create(Request $request): Response
    {
        $video = $request->user()->videos()->create([
            'status' => 'draft',
            'current_step' => 'input',
        ]);

        return redirect()->route('videos.create.step', [
            'video' => $video,
            'step' => 'input',
        ]);
    }

    public function step(Request $request, Video $video, string $step): Response
    {
        $this->authorize('update', $video);

        return Inertia::render('videos/create', [
            'video' => $video,
            'currentStep' => $step,
            'steps' => VIDEO_WIZARD_STEPS, // from a constant or config
        ]);
    }

    public function updateStep(UpdateVideoStepRequest $request, Video $video, string $step): RedirectResponse
    {
        $this->authorize('update', $video);

        $stepData = $video->step_data ?? [];
        $stepData[$step] = $request->validated();
        $video->update([
            'step_data' => $stepData,
            'current_step' => $step,
        ]);

        $nextStep = $this->getNextStep($step);

        return redirect()->route('videos.create.step', [
            'video' => $video,
            'step' => $nextStep,
        ]);
    }
}
```

#### Routes

Add video creation routes to `routes/web.php`:

```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('videos/create', [VideoCreationController::class, 'create'])->name('videos.create');
    Route::get('videos/{video}/create/{step}', [VideoCreationController::class, 'step'])->name('videos.create.step');
    Route::patch('videos/{video}/create/{step}', [VideoCreationController::class, 'updateStep'])->name('videos.create.update-step');
});
```

#### Authorization Policy

Create a `VideoPolicy` to ensure users can only access their own videos:

```php
class VideoPolicy
{
    public function update(User $user, Video $video): bool
    {
        return $user->id === $video->user_id;
    }

    public function view(User $user, Video $video): bool
    {
        return $user->id === $video->user_id;
    }
}
```

#### Form Request

Create an `UpdateVideoStepRequest` that validates step data. For now, it accepts any data since each step will define its own validation in later epics:

```php
class UpdateVideoStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy handles authorization
    }

    public function rules(): array
    {
        return match ($this->route('step')) {
            'input' => [
                'topic' => ['nullable', 'string', 'max:500'],
                'brand_guide' => ['nullable', 'string'],
                'reference_urls' => ['nullable', 'array', 'max:3'],
                'reference_urls.*' => ['url'],
            ],
            default => [],
        };
    }
}
```

### Frontend Architecture

#### Wizard Page

Create the main wizard page at `resources/js/pages/videos/create.tsx`:

```tsx
export default function VideoCreate({
    video,
    currentStep,
    steps,
}: VideoCreateProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Video" />
            <div className="flex flex-col min-[1200px]:flex-row min-[1200px]:gap-8">
                {/* Progress sidebar on desktop, compact bar on mobile */}
                <VideoProgressIndicator
                    steps={wizardSteps}
                    currentStepIndex={currentStepIndex}
                    estimatedTimeRemaining={estimatedTime}
                    className="min-[1200px]:w-64 min-[1200px]:shrink-0"
                />

                {/* Step content area */}
                <div className="flex-1 space-y-6">
                    <WizardStepContent step={currentStep} video={video} />
                    <WizardNavigation
                        currentStep={currentStep}
                        video={video}
                        isFirstStep={currentStepIndex === 0}
                        isLastStep={currentStepIndex === steps.length - 1}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
```

#### Wizard Step Content Component

Create a `WizardStepContent` component that renders the appropriate content for each step. For now, most steps render placeholder content:

```tsx
function WizardStepContent({ step, video }: WizardStepContentProps) {
    switch (step) {
        case 'input':
            return <InputStep video={video} />;
        case 'title-selection':
            return (
                <PlaceholderStep
                    title="Title Selection"
                    description="Select a title for your video. AI-generated titles will appear here."
                />
            );
        // ... etc for each step
        case 'final-review':
            return (
                <PlaceholderStep
                    title="Final Review"
                    description="Review all selections before generating your video."
                />
            );
        case 'download':
            return (
                <PlaceholderStep
                    title="Download"
                    description="Your completed video will be available for download here."
                />
            );
    }
}
```

#### Input Step Component

The Input step is the only step with real content at this stage, since it connects to the brand guide and topic input features from E006. Create an `InputStep` component:

```tsx
function InputStep({ video }: InputStepProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Video Input</CardTitle>
                <CardDescription>
                    Provide the topic and reference materials for your video.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
                {/* Topic input */}
                <div className="grid gap-2">
                    <Label htmlFor="topic">Topic</Label>
                    <Input
                        id="topic"
                        name="topic"
                        placeholder="What is your video about?"
                    />
                    <p className="text-sm text-muted-foreground">
                        100-500 characters
                    </p>
                </div>

                {/* Reference URLs */}
                <div className="grid gap-2">
                    <Label>Reference URLs (optional)</Label>
                    {/* URL input fields */}
                </div>

                {/* Brand guide */}
                <div className="grid gap-2">
                    <Label>Brand Guide (optional)</Label>
                    {/* File upload or text area */}
                </div>
            </CardContent>
        </Card>
    );
}
```

#### Wizard Navigation Component

Create a `WizardNavigation` component with Back and Next/Continue buttons:

```tsx
function WizardNavigation({
    currentStep,
    video,
    isFirstStep,
    isLastStep,
}: WizardNavigationProps) {
    return (
        <div className="flex items-center justify-between border-t pt-4">
            {!isFirstStep && (
                <Button variant="outline" asChild>
                    <Link
                        href={route('videos.create.step', {
                            video: video.id,
                            step: previousStep,
                        })}
                    >
                        Back
                    </Link>
                </Button>
            )}
            <div className="ml-auto">
                <Form {...updateStepRoute.form()}>
                    {({ processing }) => (
                        <Button type="submit" disabled={processing}>
                            {isLastStep ? 'Finish' : 'Continue'}
                        </Button>
                    )}
                </Form>
            </div>
        </div>
    );
}
```

#### Placeholder Step Component

A reusable placeholder for steps that aren't built yet:

```tsx
function PlaceholderStep({
    title,
    description,
}: {
    title: string;
    description: string;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent>
                <div className="flex h-48 items-center justify-center rounded-lg border border-dashed">
                    <p className="text-sm text-muted-foreground">Coming soon</p>
                </div>
            </CardContent>
        </Card>
    );
}
```

#### Sidebar Navigation

Add a "Create Video" link to the app sidebar navigation so users can start the wizard:

```tsx
// In app-sidebar.tsx or nav-main.tsx, add:
{ title: 'Create Video', href: '/videos/create', icon: VideoIcon }
```

### Step Navigation Logic

Step transitions are handled server-side. When the user clicks "Continue":

1. The form submits the current step's data via PATCH to `videos/{video}/create/{step}`
2. The controller validates, saves step data, and redirects to the next step
3. When the user clicks "Back", it's a simple link to the previous step's URL

The controller has a `getNextStep()` helper that uses the `VIDEO_WIZARD_STEPS` constant order to determine the next step. Similarly, the frontend computes the previous step from the step array.

### URL Structure

Each step has its own URL for bookmarkability and browser back support:

- `/videos/42/create/input`
- `/videos/42/create/title-selection`
- `/videos/42/create/outline-review`
- etc.

### Testing Approach

Feature tests for:

1. Creating a new video (POST to `/videos/create`)
2. Viewing each step (GET to `/videos/{id}/create/{step}`)
3. Updating a step (PATCH to `/videos/{id}/create/{step}`)
4. Authorization (users can't access other users' videos)
5. Navigation between steps

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/video-progress-indicator.tsx` -- The progress indicator component from E010-F001. Imported and used in the wizard page.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/constants/video-wizard-steps.ts` -- The shared step constants from E010-F001. Used for step order and labels.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/video.ts` -- Video wizard types from E010-F001. Extended with additional types for the wizard page.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/hooks/use-breakpoint.ts` -- Responsive hooks from E010-F004. Used for responsive wizard layout.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/responsive-container.tsx` -- Responsive container from E010-F004.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/responsive-grid.tsx` -- Responsive grid from E010-F004.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/app-layout.tsx` -- The main app layout wrapper. Wizard page uses this.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/card.tsx` -- Card component for step content containers.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/button.tsx` -- Button component for navigation controls.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/input.tsx` -- Input component for form fields.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/label.tsx` -- Label component for form fields.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/heading.tsx` -- Heading component for step titles.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/profile.tsx` -- Reference for page structure with Inertia Form handling.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/dashboard.tsx` -- Reference for page structure with AppLayout and breadcrumbs.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/ProfileController.php` -- Reference for controller structure with Inertia rendering and form requests.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/Settings/ProfileUpdateRequest.php` -- Reference for form request validation patterns.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` -- User model to add the `videos()` relationship.
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Main routes file where video creation routes will be added.
- `/Users/young/Nextcloud/dev/Itervel/app/Providers/AppServiceProvider.php` -- Reference for service provider patterns (policy registration if needed).
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/ProfileUpdateTest.php` -- Reference for Pest feature test patterns.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` -- Reference for factory patterns.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/app-sidebar.tsx` -- Sidebar navigation where "Create Video" link will be added.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/nav-main.tsx` -- Main navigation items component.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/app/Models/Video.php` -- The Video Eloquent model with fillable fields, casts, and user relationship.
- `/Users/young/Nextcloud/dev/Itervel/database/migrations/xxxx_xx_xx_create_videos_table.php` -- Migration creating the videos table (generated via `php artisan make:model Video -mfs --no-interaction`).
- `/Users/young/Nextcloud/dev/Itervel/database/factories/VideoFactory.php` -- Factory for creating test videos.
- `/Users/young/Nextcloud/dev/Itervel/database/seeders/VideoSeeder.php` -- Seeder for sample videos.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/VideoCreationController.php` -- Controller handling video creation wizard flow.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/UpdateVideoStepRequest.php` -- Form request for step data validation.
- `/Users/young/Nextcloud/dev/Itervel/app/Policies/VideoPolicy.php` -- Authorization policy for video access.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/videos/create.tsx` -- The main wizard page component.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/wizard-step-content.tsx` -- Component that renders the appropriate content for each wizard step.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/wizard-navigation.tsx` -- Back/Next navigation controls for the wizard.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/placeholder-step.tsx` -- Reusable placeholder component for unimplemented steps.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/input-step.tsx` -- The Input step with topic, reference URLs, and brand guide fields.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/VideoCreation/VideoCreationWizardTest.php` -- Pest feature tests for the video creation wizard flow.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: wizard-backend-dev
    - Role: Creates the Video model, migration, factory, seeder, controller, form request, policy, routes, and User model relationship
    - Agent Type: coder
    - Resume: false

- Frontend Developer (Wizard Page)
    - Name: wizard-page-dev
    - Role: Creates the wizard page, step content component, navigation controls, placeholder steps, input step, and sidebar navigation update
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: wizard-test-dev
    - Role: Writes Pest feature tests for the video creation wizard including creation, step navigation, step updates, and authorization
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: wizard-reviewer
    - Role: Validates the complete wizard implementation against acceptance criteria, runs tests, type checks, linting, and reviews code quality
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Create Video Model and Backend Infrastructure

- **Task ID**: create-backend-infrastructure
- **Depends On**: none
- **Assigned To**: wizard-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- Run `docker compose exec app php artisan make:model Video -mfs --no-interaction` to generate the model, migration, factory, and seeder
- Edit the migration to add columns: `user_id` (foreign key to users, cascade on delete), `project_id` (nullable unsigned big integer -- do NOT add foreign key constraint since projects table may not exist yet), `title` (nullable string), `topic` (nullable string), `brand_guide` (nullable text), `reference_urls` (nullable json), `status` (string, default 'draft'), `current_step` (string, default 'input'), `step_data` (nullable json), plus timestamps
- Edit the Video model: set `$fillable`, define `casts()` method for `reference_urls` and `step_data` as `array`, add `user(): BelongsTo` relationship
- Edit `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` to add `videos(): HasMany` relationship (import `HasMany` from `Illuminate\Database\Eloquent\Relations`)
- Edit the VideoFactory to define meaningful defaults: `user_id` from `User::factory()`, `status` as `'draft'`, `current_step` as `'input'`, `title` and `topic` using `fake()->sentence()`, empty arrays for `reference_urls` and `step_data`
- Edit the VideoSeeder to create a few sample videos for the first user
- Run `docker compose exec app php artisan make:controller VideoCreationController --no-interaction`
- Edit VideoCreationController with three methods:
    - `create(Request $request): RedirectResponse` -- Creates a new draft video and redirects to the input step
    - `step(Request $request, Video $video, string $step): Response` -- Renders the wizard page with video data, current step, and step definitions. Validates that `$step` is a valid step ID from the constant list. Uses `$this->authorize('update', $video)`.
    - `updateStep(UpdateVideoStepRequest $request, Video $video, string $step): RedirectResponse` -- Validates step data, saves to `step_data` JSON, updates `current_step`, and redirects to the next step. Uses `$this->authorize('update', $video)`. Add a private `getNextStep(string $step): string` helper and `getPreviousStep(string $step): string` helper that use the step order constant
- Define the step order as a class constant or import from a shared location: `private const STEPS = ['input', 'title-selection', 'outline-review', 'script-review', 'voiceover-selection', 'thumbnail-selection', 'music-selection', 'final-review', 'download']`
- Run `docker compose exec app php artisan make:request UpdateVideoStepRequest --no-interaction`
- Edit UpdateVideoStepRequest with `authorize()` returning `true` and `rules()` using a `match` on `$this->route('step')` for step-specific validation. The `input` step validates: `topic` (nullable, string, max:500), `brand_guide` (nullable, string), `reference_urls` (nullable, array, max:3), `reference_urls.*` (url). All other steps return empty rules for now
- Run `docker compose exec app php artisan make:policy VideoPolicy --model=Video --no-interaction`
- Edit VideoPolicy with `update(User $user, Video $video): bool` returning `$user->id === $video->user_id` and `view(User $user, Video $video): bool` returning the same
- Add routes to `/Users/young/Nextcloud/dev/Itervel/routes/web.php` inside an `auth` and `verified` middleware group: `Route::post('videos/create', ...)`, `Route::get('videos/{video}/create/{step}', ...)`, `Route::patch('videos/{video}/create/{step}', ...)`
- Run `docker compose exec app php artisan migrate` to create the videos table
- Run `docker compose exec app vendor/bin/pint --dirty` for PHP formatting

### 2. Create Wizard Frontend Page and Components

- **Task ID**: create-wizard-frontend
- **Depends On**: create-backend-infrastructure
- **Assigned To**: wizard-page-dev
- **Agent Type**: coder
- **Parallel**: false
- Read the following files for patterns: `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/dashboard.tsx`, `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/profile.tsx`, `/Users/young/Nextcloud/dev/Itervel/resources/js/components/video-progress-indicator.tsx`, `/Users/young/Nextcloud/dev/Itervel/resources/js/constants/video-wizard-steps.ts`, `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/card.tsx`
- Read the Wayfinder actions generated for VideoCreationController (check `resources/js/actions/` after `npm run build`)
- Add video-related types to `/Users/young/Nextcloud/dev/Itervel/resources/js/types/video.ts`: `Video` type (matching the Eloquent model fields: `id`, `user_id`, `project_id`, `title`, `topic`, `brand_guide`, `reference_urls`, `status`, `current_step`, `step_data`, `created_at`, `updated_at`), `VideoCreateProps` (with `video: Video`, `currentStep: string`)
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/components/placeholder-step.tsx` -- A Card with `CardHeader` (title, description) and `CardContent` containing a dashed-border empty state with "Coming soon" text. Named export `PlaceholderStep`
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/components/input-step.tsx` -- The Input step form using Card layout with fields for topic (textarea, 100-500 chars), reference URLs (up to 3 URL inputs with add/remove), and brand guide (textarea). All fields use `name` attributes for Inertia form submission. Named export `InputStep`
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/components/wizard-step-content.tsx` -- A switch/map component that renders the right step component based on the `step` prop. Uses `InputStep` for 'input' and `PlaceholderStep` for all other steps with appropriate titles and descriptions. Named export `WizardStepContent`
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/components/wizard-navigation.tsx` -- Navigation bar with Back (outline button, `Link` to previous step URL) and Continue/Finish (primary button, either a `Link` or `Form` submit). Shows Back only when not on first step. Shows "Finish" text on last step. Named export `WizardNavigation`
- Create the page directory: `resources/js/pages/videos/`
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/videos/create.tsx` -- The main wizard page:
    - Receives `video` and `currentStep` props from the controller
    - Imports `VideoProgressIndicator` from `@/components/video-progress-indicator`
    - Imports `WizardStepContent` from `@/components/wizard-step-content`
    - Imports `WizardNavigation` from `@/components/wizard-navigation`
    - Imports `VIDEO_WIZARD_STEPS` from `@/constants/video-wizard-steps`
    - Computes `wizardSteps` with status based on `currentStep` (steps before current = 'complete', current = 'active', after = 'upcoming')
    - Uses `AppLayout` with breadcrumbs: Dashboard > Create Video
    - Desktop layout: `flex-row` with progress indicator sidebar (w-64) and content area (flex-1)
    - Tablet/Mobile layout: `flex-col` with compact progress bar then content below
    - Uses `min-[1200px]:flex-row` and `min-[1200px]:gap-8` for the responsive split
- Update sidebar navigation to include "Create Video":
    - Read `/Users/young/Nextcloud/dev/Itervel/resources/js/components/app-sidebar.tsx` and `/Users/young/Nextcloud/dev/Itervel/resources/js/components/nav-main.tsx` to understand the navigation structure
    - Add a "Create Video" nav item with appropriate icon (e.g., `PlusCircle` or `Video` from lucide-react)
- Run `npm run build` to generate Wayfinder actions
- Run `npm run types` to verify TypeScript compiles
- Run `npm run lint` to verify ESLint passes

### 3. Write Feature Tests

- **Task ID**: write-wizard-tests
- **Depends On**: create-backend-infrastructure, create-wizard-frontend
- **Assigned To**: wizard-test-dev
- **Agent Type**: coder
- **Parallel**: false
- Read `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/ProfileUpdateTest.php` for Pest test patterns
- Read `/Users/young/Nextcloud/dev/Itervel/database/factories/VideoFactory.php` for factory usage
- Create test directory: `tests/Feature/VideoCreation/`
- Create `/Users/young/Nextcloud/dev/Itervel/tests/Feature/VideoCreation/VideoCreationWizardTest.php` using `php artisan make:test --pest VideoCreation/VideoCreationWizardTest` (run inside Docker)
- Write the following tests:
    - `test('authenticated user can create a new video')` -- POST to `videos/create`, assert redirect to the input step URL, assert a video record was created with status 'draft' and current_step 'input'
    - `test('unauthenticated user cannot create a video')` -- POST to `videos/create` without auth, assert redirect to login
    - `test('user can view the input step')` -- Create a video via factory, GET the input step URL, assert OK and Inertia component is `videos/create`
    - `test('user can view each wizard step')` -- Dataset with all 9 step IDs. Create a video, GET each step URL, assert OK
    - `test('user cannot view another user video step')` -- Create a video for user A, act as user B, GET step URL, assert 403
    - `test('user can update the input step')` -- PATCH to input step with topic data, assert redirect to title-selection step, assert step_data was saved
    - `test('user can navigate back to previous step')` -- Create a video, GET a middle step, assert the page renders (back navigation is client-side link)
    - `test('invalid step returns 404')` -- GET a non-existent step slug, assert 404
    - `test('video model belongs to user')` -- Unit-style: create video with factory, assert `$video->user->is($user)` relationship works
    - `test('video factory creates valid video')` -- Create via factory, assert all expected attributes exist
- Run `docker compose exec app php artisan test tests/Feature/VideoCreation/ --compact`
- Run `docker compose exec app php artisan test --compact` to check for regressions

### 4. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-backend-infrastructure, create-wizard-frontend, write-wizard-tests
- **Assigned To**: wizard-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Read the feature plan at `/Users/young/Nextcloud/dev/Itervel/specs/features/E010-F002-step-by-step-wizard-navigation.md`
- Read all created/modified files listed in the New Files section
- Run all validation commands
- Verify the Video model has correct fillable, casts, and relationships
- Verify the migration creates all required columns
- Verify the controller has create, step, and updateStep methods with proper authorization
- Verify the policy restricts access to video owners
- Verify routes are registered with correct middleware
- Verify the wizard page renders with progress indicator and step content
- Verify navigation controls work (Back/Continue)
- Verify sidebar has "Create Video" link
- Verify all tests pass
- Confirm all acceptance criteria are met
- Run PHP formatting: `docker compose exec app vendor/bin/pint --dirty`

## Acceptance Criteria

- A `Video` Eloquent model exists with `user_id`, `title`, `topic`, `brand_guide`, `reference_urls`, `status`, `current_step`, and `step_data` fields
- The Video model has a `user()` BelongsTo relationship and the User model has a `videos()` HasMany relationship
- A `VideoFactory` creates valid test videos
- A `VideoCreationController` with `create`, `step`, and `updateStep` methods exists
- A `VideoPolicy` restricts video access to the owning user
- Routes exist: POST `videos/create`, GET `videos/{video}/create/{step}`, PATCH `videos/{video}/create/{step}`
- The wizard page renders at `/videos/{id}/create/{step}` with the progress indicator and step content
- All 9 steps are accessible via their URL slug
- The Input step shows form fields for topic, reference URLs, and brand guide
- Non-input steps show placeholder content with step name and description
- Back/Continue navigation buttons work correctly between steps
- The progress indicator shows correct step statuses (complete/active/upcoming) based on current step
- The sidebar navigation includes a "Create Video" link
- The wizard uses responsive layout: side-by-side on desktop (1200px+), stacked on tablet/mobile
- Each step has its own URL for bookmarkability and browser back support
- Users cannot access other users' video wizard
- All Pest feature tests pass
- TypeScript type checking passes (`npm run types`)
- ESLint passes (`npm run lint`)
- PHP formatting passes (`vendor/bin/pint --dirty`)

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run video creation tests
docker compose exec app php artisan test tests/Feature/VideoCreation/ --compact

# Run full test suite for regression check
docker compose exec app php artisan test --compact

# TypeScript type checking
npm run types

# ESLint linting
npm run lint

# Prettier formatting check
npm run format:check

# PHP code formatting
docker compose exec app vendor/bin/pint --dirty
```

## Notes

- The `project_id` column is nullable and has no foreign key constraint because the `projects` table may not exist yet (E004 epic). When E004 is implemented, a migration should add the foreign key constraint.
- Step content is intentionally placeholder for most steps. Later epics will replace placeholders: E011 (outline/script), E012 (thumbnail), E013 (audio/voiceover), E014 (video assembly), E015 (downloads). Each of those epics should update the relevant step component in the wizard.
- The `step_data` JSON column stores per-step selections as a flexible key-value store. This avoids needing separate columns for every piece of step data and allows easy extension as steps are built out.
- The `status` field on the Video model tracks the overall video lifecycle: `draft` (in wizard), `processing` (generating), `completed` (done), `failed` (error). Only `draft` is used in this feature; other statuses will be set by later epics.
- The Input step's form fields (topic, reference URLs, brand guide) align with E006 feature specs but are simplified here. E006 implementation may enhance these with file upload for brand guides, URL validation feedback, etc.
- The controller validates the step slug against the known step list and returns 404 for invalid steps. This prevents URL manipulation issues.
- Authorization uses Laravel's policy system. The `$this->authorize('update', $video)` call in the controller automatically resolves the `VideoPolicy` via convention.

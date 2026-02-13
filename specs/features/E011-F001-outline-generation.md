# Feature: Outline Generation

**Epic**: E011-outline-and-script-generation.md
**Feature**: E011-F001
**Epic depends on**: E009-ai-configuration-and-title-generation.md
**Feature depends on**: None

## Task Description

Uses AI to create a structured video outline from the selected title and logline, with sections, timing estimates, and key points. This is the first feature in the outline and script generation epic and establishes the outline generation pipeline that feeds into script writing.

**What it does**: Uses AI to create a structured video outline from the selected title and logline, with sections, timing estimates, and key points.

**Expected outcome**: The user sees an outline containing a hook (30 seconds), 3-5 main sections, and a closing. Each section has a title, estimated duration, bullet-point key points, and engagement/build-up notes.

This feature follows the same AI generation pattern established by E009-F003 (Title Generation): a dedicated service constructs the prompt, a queue job handles async generation, database columns store the result, and a frontend page displays loading/error/results states with polling.

The outline generation uses the video's selected title and logline (from E009-F004), the topic (from E006-F003), brand guide content (from E006-F001), and reference URLs (from E006-F004/F005) as context for the AI. The model used is determined by `video.getModelForStep('outline')` from the `ai_config` (E009-F001).

The outline step corresponds to step 3 ("Outline Review") in the wizard (E010-F002). The generated outline is stored in the Video model's `generated_outline` JSON column.

## Objective

Create an outline generation pipeline: an `OutlineGenerationService` that builds prompts and parses structured outline responses, a `GenerateOutlineJob` for async processing, database columns for storing the generated outline, an outline controller for triggering generation and viewing results, a frontend outline page showing loading/error/results states with polling, and integration with the wizard step content. When complete, clicking "Generate Outline" produces a structured outline with a hook, 3-5 sections, and closing.

## Solution Approach

### Outline Data Structure

The generated outline is stored as a JSON array of sections:

```json
{
    "hook": {
        "title": "Hook",
        "duration_seconds": 30,
        "key_points": [
            "Opening question that creates curiosity",
            "Preview of the main benefit"
        ],
        "engagement_notes": "Start with a bold claim to prevent early drop-off"
    },
    "sections": [
        {
            "title": "Section 1: The Problem",
            "duration_seconds": 120,
            "key_points": [
                "Describe the pain point",
                "Share a relatable example",
                "Build urgency"
            ],
            "engagement_notes": "Use a personal story to maintain connection"
        },
        {
            "title": "Section 2: The Solution",
            "duration_seconds": 180,
            "key_points": [
                "Introduce the core idea",
                "Break down into 3 steps",
                "Show proof/evidence"
            ],
            "engagement_notes": "Visual demonstration keeps viewers engaged"
        }
    ],
    "closing": {
        "title": "Closing & CTA",
        "duration_seconds": 30,
        "key_points": [
            "Recap key takeaway",
            "Call to action",
            "Tease next video"
        ],
        "engagement_notes": "End with a clear, actionable next step"
    },
    "total_duration_seconds": 480,
    "section_count": 4
}
```

### OutlineGenerationService

Create `app/Services/OutlineGenerationService.php` following the `TitleGenerationService` pattern:

```php
class OutlineGenerationService
{
    public function __construct(private AiClient $aiClient) {}

    public function generate(Video $video): array
    {
        $modelKey = $video->getModelForStep('outline');
        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt = $this->buildUserPrompt($video);
        $response = $this->aiClient->generate($modelKey, $systemPrompt, $userPrompt, 4096);
        return $this->parseResponse($response);
    }
}
```

The system prompt instructs the AI to:

- Create a structured video outline with a hook (30 seconds), 3-5 main sections, and a closing
- Each section includes: title, estimated duration in seconds, bullet-point key points (3-5), and engagement/build-up notes
- Target a total duration appropriate for a YouTube video (5-10 minutes)
- Match the brand voice if a brand guide is provided
- Respond in valid JSON matching the defined structure

The user prompt includes:

- The selected title and logline (from `video.selected_title` and `video.generated_titles` where `ranking === video.selected_title_index`)
- The video topic
- Brand guide content (if available via the video's project)
- Reference material summaries (if available)

### GenerateOutlineJob

Create `app/Jobs/GenerateOutlineJob.php`:

```php
class GenerateOutlineJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public Video $video) {}

    public function handle(OutlineGenerationService $service): void
    {
        $this->video->update(['status' => 'generating_outline']);

        try {
            $outline = $service->generate($this->video);
            $this->video->update([
                'generated_outline' => $outline,
                'status' => 'outline_generated',
            ]);
        } catch (\Throwable $e) {
            $this->video->update([
                'status' => 'outline_generation_failed',
                'generation_error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
```

### Database Changes

Add migration for outline column on the videos table:

```php
Schema::table('videos', function (Blueprint $table) {
    $table->json('generated_outline')->nullable()->after('selected_title');
});
```

### OutlineController

Create `app/Http/Controllers/OutlineController.php`:

```php
class OutlineController extends Controller
{
    public function index(Video $video): Response
    {
        abort_unless($video->user_id === auth()->id(), 403);

        return Inertia::render('videos/outline', [
            'video' => $video->only([
                'id', 'topic', 'selected_title', 'status',
                'generated_outline', 'generation_error',
            ]),
        ]);
    }

    public function generate(Video $video): RedirectResponse
    {
        abort_unless($video->user_id === auth()->id(), 403);

        GenerateOutlineJob::dispatch($video);

        return to_route('videos.outline', $video);
    }
}
```

### Routes

```php
Route::get('videos/{video}/outline', [OutlineController::class, 'index'])->name('videos.outline');
Route::post('videos/{video}/generate-outline', [OutlineController::class, 'generate'])->name('videos.outline.generate');
```

### Frontend: Outline Page

Create `resources/js/pages/videos/outline.tsx` with three states:

1. **Loading** (`status === 'generating_outline'`): Skeleton cards with spinner and "Generating outline..." text. Polls every 3 seconds using `router.reload({ only: ['video'], preserveState: true })`.

2. **Error** (`status === 'outline_generation_failed'`): Destructive Alert with error message and "Retry" button.

3. **Results** (`status === 'outline_generated'`): Display the outline as a vertical list of section cards:
    - **Hook card**: Highlighted with a distinct border/background, shows title "Hook", duration "~30s", key points as bullet list, engagement notes in muted text
    - **Section cards** (3-5): Numbered sections with title, duration, key points, engagement notes
    - **Closing card**: Similar to hook with distinct styling
    - **Summary bar**: Total estimated duration, section count
    - Approve button and "Regenerate" button at the bottom

### Wizard Integration

Update the `WizardStepContent` component (from E010-F002) to render the outline page content for the 'outline-review' step instead of the placeholder. The wizard step navigates to the outline URL when the user reaches this step.

### TypeScript Types

Add to `resources/js/types/video.ts`:

```typescript
export type OutlineSection = {
    title: string;
    duration_seconds: number;
    key_points: string[];
    engagement_notes: string;
};

export type GeneratedOutline = {
    hook: OutlineSection;
    sections: OutlineSection[];
    closing: OutlineSection;
    total_duration_seconds: number;
    section_count: number;
};
```

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Services/AiClient.php` -- The AI client service (from E009-F003). Used by OutlineGenerationService to make API calls.
- `/Users/young/Nextcloud/dev/Itervel/app/Services/TitleGenerationService.php` -- The title generation service (from E009-F003). Template for OutlineGenerationService structure.
- `/Users/young/Nextcloud/dev/Itervel/app/Jobs/GenerateTitlesJob.php` -- The title generation job (from E009-F003). Template for GenerateOutlineJob structure.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/TitleController.php` -- Title controller (from E009-F003). Template for OutlineController structure.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/Video.php` -- Video model. Must add `generated_outline` to fillable/casts.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/VideoFactory.php` -- Video factory. Must add outline-related factory states.
- `/Users/young/Nextcloud/dev/Itervel/config/ai.php` -- AI config (from E009-F001). Defines the 'outline' step with default model and cost.
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Must add outline routes.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/videos/titles.tsx` -- Titles page (from E009-F003). Template for the outline page with loading/error/results states and polling.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/video.ts` -- Video types. Must add outline types.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/card.tsx` -- Card component for section display.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/alert.tsx` -- Alert component for error state.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/skeleton.tsx` -- Skeleton for loading state.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/spinner.tsx` -- Spinner component.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/badge.tsx` -- Badge for duration/section count.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/button.tsx` -- Button component.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/app-layout.tsx` -- App layout wrapper.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/lib/utils.ts` -- cn() utility.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/TitleGenerationTest.php` -- Title generation tests (from E009-F003). Template for outline generation tests with mocked AI.
- `/Users/young/Nextcloud/dev/Itervel/app/Providers/AppServiceProvider.php` -- Service provider. May need to register OutlineGenerationService.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/app/Services/OutlineGenerationService.php` -- Service that builds outline prompts, calls the AI client, and parses the structured JSON outline response.
- `/Users/young/Nextcloud/dev/Itervel/app/Jobs/GenerateOutlineJob.php` -- Queued job that generates the outline asynchronously and updates the video status/data.
- `/Users/young/Nextcloud/dev/Itervel/database/migrations/YYYY_MM_DD_HHMMSS_add_generated_outline_to_videos_table.php` -- Migration to add `generated_outline` JSON column to videos table.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/OutlineController.php` -- Controller with `index` (show outline) and `generate` (dispatch job) methods.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/videos/outline.tsx` -- Inertia React page showing loading/error/outline results with polling.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/OutlineGenerationTest.php` -- Pest feature tests for outline generation with mocked AI responses.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: outline-backend-dev
    - Role: Creates the OutlineGenerationService, GenerateOutlineJob, migration, OutlineController, updates Video model, and adds routes
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: outline-frontend-dev
    - Role: Creates the outline page with loading/error/results states, polling, and section card display. Adds outline TypeScript types
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: outline-test-dev
    - Role: Writes Pest feature tests for outline generation with mocked AI responses, job dispatching, status transitions, and authorization
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: outline-reviewer
    - Role: Validates the complete outline generation implementation against acceptance criteria
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Create Outline Generation Backend

- **Task ID**: create-outline-backend
- **Depends On**: none
- **Assigned To**: outline-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- Read `/Users/young/Nextcloud/dev/Itervel/app/Services/TitleGenerationService.php` and `/Users/young/Nextcloud/dev/Itervel/app/Jobs/GenerateTitlesJob.php` for the established AI generation pattern
- Read `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/TitleController.php` for the controller pattern
- Read `/Users/young/Nextcloud/dev/Itervel/config/ai.php` for the outline step configuration
- Create migration: `docker compose exec app php artisan make:migration add_generated_outline_to_videos_table --table=videos --no-interaction`
    - `up()`: add `$table->json('generated_outline')->nullable()->after('selected_title');`
    - `down()`: drop the column
- Update `/Users/young/Nextcloud/dev/Itervel/app/Models/Video.php`:
    - Add `'generated_outline'` to `$fillable`
    - Add `'generated_outline' => 'array'` to `casts()`
- Update `/Users/young/Nextcloud/dev/Itervel/database/factories/VideoFactory.php`:
    - Add `'generated_outline' => null` to `definition()`
    - Add `withGeneratedOutline(): static` state method that sets `generated_outline` to a sample outline JSON and `status` to `'outline_generated'`
- Create `/Users/young/Nextcloud/dev/Itervel/app/Services/OutlineGenerationService.php`:
    - Constructor: inject `AiClient`
    - `generate(Video $video): array` -- gets model via `$video->getModelForStep('outline')`, builds system and user prompts, calls AI client, parses response
    - System prompt: instructs AI to create a structured outline with hook (30s), 3-5 main sections, closing; each section has title, duration_seconds, key_points (array), engagement_notes; respond in JSON
    - User prompt: includes selected title, logline (extracted from `generated_titles` at `selected_title_index`), topic, brand guide content (if available via `$video->user` or project relation), and reference material
    - `parseResponse(string $response): array` -- extracts JSON, validates structure has hook/sections/closing, calculates total_duration_seconds and section_count
- Create `docker compose exec app php artisan make:job GenerateOutlineJob --no-interaction`
- Edit `/Users/young/Nextcloud/dev/Itervel/app/Jobs/GenerateOutlineJob.php`:
    - Implements `ShouldQueue`, uses `Queueable`
    - Constructor: accepts `Video $video`
    - `handle(OutlineGenerationService $service): void` -- sets status to `generating_outline`, calls service, updates video with outline and status `outline_generated`. On failure: sets status to `outline_generation_failed`, stores error in `generation_error`
- Create `docker compose exec app php artisan make:controller OutlineController --no-interaction`
- Edit `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/OutlineController.php`:
    - `index(Video $video): Response` -- checks ownership (abort 403), renders `videos/outline` with video data
    - `generate(Video $video): RedirectResponse` -- checks ownership (abort 403), dispatches `GenerateOutlineJob`, redirects to `videos.outline`
- Add routes to `/Users/young/Nextcloud/dev/Itervel/routes/web.php` inside auth+verified middleware group:
    - `Route::get('videos/{video}/outline', [OutlineController::class, 'index'])->name('videos.outline');`
    - `Route::post('videos/{video}/generate-outline', [OutlineController::class, 'generate'])->name('videos.outline.generate');`
- Run migration: `docker compose exec app php artisan migrate --no-interaction`
- Run `docker compose exec app vendor/bin/pint --dirty`

### 2. Create Outline Frontend Page

- **Task ID**: create-outline-frontend
- **Depends On**: create-outline-backend
- **Assigned To**: outline-frontend-dev
- **Agent Type**: coder
- **Parallel**: false
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/videos/titles.tsx` for the loading/error/results pattern with polling
- Run `npm run build` to generate Wayfinder routes for OutlineController
- Add to `/Users/young/Nextcloud/dev/Itervel/resources/js/types/video.ts`:
    - `OutlineSection` type with `title: string`, `duration_seconds: number`, `key_points: string[]`, `engagement_notes: string`
    - `GeneratedOutline` type with `hook: OutlineSection`, `sections: OutlineSection[]`, `closing: OutlineSection`, `total_duration_seconds: number`, `section_count: number`
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/videos/outline.tsx`:
    - Import necessary components and types
    - Define page props: `video: { id: number; topic: string; selected_title: string; status: string; generated_outline: GeneratedOutline | null; generation_error: string | null }`
    - Implement 3 states:
        1. **Loading** (`status === 'generating_outline'`): 4-5 Skeleton cards, Spinner, "Generating outline..." text. `useEffect` with `setInterval` polling `router.reload({ only: ['video'], preserveState: true })` every 3 seconds, clear on status change
        2. **Error** (`status === 'outline_generation_failed'`): Destructive `Alert` with error message, "Retry" button POSTing to generate route
        3. **Results** (`status === 'outline_generated'`): Vertical card list:
        - Hook card with `border-primary` highlight, clock icon, "~30s" badge, key points as `<ul>`, engagement notes in `text-muted-foreground italic`
        - Section cards (3-5) numbered, each with title, duration badge, key points list, engagement notes
        - Closing card with distinct styling
        - Summary bar: total duration formatted as "X min Y sec", section count
        - "Approve & Continue" button and "Regenerate" outline button
    - Breadcrumbs: Dashboard > Video > Outline
    - Use `AppLayout` wrapper
- Run `npm run types`
- Run `npm run lint`

### 3. Write Feature Tests

- **Task ID**: write-outline-tests
- **Depends On**: create-outline-backend
- **Assigned To**: outline-test-dev
- **Agent Type**: coder
- **Parallel**: true (can run in parallel with frontend)
- Read `/Users/young/Nextcloud/dev/Itervel/tests/Feature/TitleGenerationTest.php` for test patterns with mocked AI
- Create `docker compose exec app php artisan make:test OutlineGenerationTest --pest --no-interaction`
- Write tests:
    - `test('outline page is displayed for video owner')` -- GET outline route, assert 200 and Inertia component `videos/outline`
    - `test('outline page returns 403 for non-owner')` -- assert 403
    - `test('guests are redirected to login')` -- assert redirect
    - `test('outline generation can be triggered')` -- POST to generate route, assert `GenerateOutlineJob` was dispatched (use `Bus::fake()`)
    - `test('outline generation job updates video with generated outline')` -- mock `AiClient` to return valid outline JSON, run job, assert `generated_outline` stored with correct structure
    - `test('outline generation job sets failure status on error')` -- mock `AiClient` to throw, run job, assert status `outline_generation_failed` and `generation_error` set
    - `test('outline page shows generated outline')` -- create video with `withGeneratedOutline()` state, GET page, assert props have outline data
    - `test('OutlineGenerationService generates valid outline')` -- mock `AiClient`, call service, assert result has hook, sections, closing
    - `test('OutlineGenerationService includes title and topic in prompt')` -- use partial mock on AiClient, assert the user prompt contains the video's selected_title and topic
    - `test('non-owner cannot trigger outline generation')` -- assert 403
    - `test('video factory creates valid outline with state')` -- test factory state
- Run `docker compose exec app php artisan test tests/Feature/OutlineGenerationTest.php --compact`
- Run `docker compose exec app php artisan test --compact`
- Run `docker compose exec app vendor/bin/pint --dirty`

### 4. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-outline-backend, create-outline-frontend, write-outline-tests
- **Assigned To**: outline-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands
- Verify OutlineGenerationService follows TitleGenerationService pattern
- Verify system prompt instructs for hook + 3-5 sections + closing structure
- Verify GenerateOutlineJob handles success and failure status transitions
- Verify migration adds `generated_outline` JSON column
- Verify Video model has outline in fillable/casts
- Verify OutlineController has index and generate methods with authorization
- Verify routes are registered
- Verify frontend has loading/error/results states with polling
- Verify section cards display title, duration, key points, engagement notes
- Verify all tests pass with mocked AI
- Confirm all acceptance criteria are met

## Acceptance Criteria

- An `OutlineGenerationService` exists that constructs prompts and parses structured outline responses
- The service uses the video's configured AI model for the outline step (`getModelForStep('outline')`)
- The service includes selected title, logline, topic, and brand guide in the prompt
- A `GenerateOutlineJob` handles async outline generation via the queue
- The video status transitions: `title_selected` → `generating_outline` → `outline_generated` (or `outline_generation_failed`)
- The `videos` table has a `generated_outline` JSON column
- The generated outline contains: hook (30s), 3-5 main sections, and closing
- Each section has: title, duration_seconds, key_points (array), engagement_notes
- The outline page shows a loading state with skeletons and polling during generation
- The outline page shows an error state with retry option when generation fails
- The outline page shows section cards with all metadata when generation succeeds
- The hook and closing sections are visually distinguished from main sections
- A summary bar shows total estimated duration and section count
- Only video owners can view and trigger outline generation (403 for others)
- Tests mock the AI client (no real API calls in tests)
- All tests pass
- TypeScript compiles without errors
- ESLint passes
- PHP formatting passes

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run outline generation tests
docker compose exec app php artisan test tests/Feature/OutlineGenerationTest.php --compact

# Run full test suite
docker compose exec app php artisan test --compact

# Verify routes
docker compose exec app php artisan route:list --name=videos.outline

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

- This feature follows the exact same pattern as E009-F003 (Title Generation): Service → Job → Controller → Page with polling. The architecture is intentionally repetitive to maintain consistency.
- The outline JSON structure is designed to be easily iterable for section rendering and for feeding into the script generation service (E011-F003).
- The logline is extracted from the `generated_titles` array using the `selected_title_index`. If the video doesn't have a logline (edge case), the service should still work with just the title and topic.
- The brand guide content access pattern depends on whether the video has a project association and whether the project has a brand guide uploaded. The service should handle the case where no brand guide is available.
- Duration estimates in the outline are approximate. They guide the script writer but are not enforced. Total duration is the sum of all section durations.
- The `generation_error` column is shared across all generation steps (titles, outline, script). It stores the most recent error. This is sufficient since only one generation runs at a time.

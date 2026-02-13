# Feature: Script Writing

**Epic**: E011-outline-and-script-generation.md
**Feature**: E011-F003
**Epic depends on**: E009-ai-configuration-and-title-generation.md
**Feature depends on**: E011-F002

## Task Description

Uses AI to generate a full conversational video script based on the approved outline. A 2,000-2,500 word script is generated that follows the outline structure, matches the brand voice (if a brand guide is provided), includes delivery cues like [PAUSE] and [EMPHASIS], and targets the configured speaking pace.

**What it does**: Uses AI to generate a full conversational video script based on the approved outline.

**Expected outcome**: A 2,000-2,500 word script is generated that follows the outline structure, matches the brand voice (if a brand guide is provided), includes delivery cues like [PAUSE] and [EMPHASIS], and targets the configured speaking pace.

This feature creates the script generation pipeline, following the same pattern as outline generation (E011-F001) and title generation (E009-F003). The service takes the approved outline, title, topic, and brand guide as context and generates a full video script. The AI model used is determined by `video.getModelForStep('script')`.

The generated script is stored in a new `generated_script` JSON column on the videos table. The script is structured as an array of sections that map 1:1 to the outline sections, with each section containing the full script text, delivery cues, and word count.

## Objective

Create a script generation pipeline: a `ScriptGenerationService` that uses the approved outline to generate a structured, conversational video script with delivery cues; a `GenerateScriptJob` for async processing; database columns for storing the generated script; a script controller for triggering generation and viewing results; a frontend script page showing loading/error/results states; and integration with the wizard. The script should be 2,000-2,500 words, follow the outline structure, and include delivery cues like [PAUSE] and [EMPHASIS].

## Solution Approach

### Script Data Structure

The generated script is stored as a JSON object:

```json
{
    "sections": [
        {
            "outline_title": "Hook",
            "script_text": "Have you ever wondered why some videos get millions of views while others... [PAUSE] ...barely get a hundred? [EMPHASIS] Today, I'm going to share the exact formula that changed everything for me.",
            "word_count": 38,
            "delivery_cues": ["PAUSE", "EMPHASIS"]
        },
        {
            "outline_title": "Section 1: The Problem",
            "script_text": "Let's start with what most creators get wrong...",
            "word_count": 450,
            "delivery_cues": ["PAUSE", "EMPHASIS", "SLOWER"]
        }
    ],
    "total_word_count": 2350,
    "estimated_duration_seconds": 940,
    "speaking_pace_wpm": 150
}
```

### ScriptGenerationService

Create `app/Services/ScriptGenerationService.php`:

```php
class ScriptGenerationService
{
    public function __construct(private AiClient $aiClient) {}

    public function generate(Video $video): array
    {
        $modelKey = $video->getModelForStep('script');
        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt = $this->buildUserPrompt($video);
        $response = $this->aiClient->generate($modelKey, $systemPrompt, $userPrompt, 8192);
        return $this->parseResponse($response);
    }
}
```

The system prompt instructs the AI to:

- Write a conversational, engaging video script (2,000-2,500 words total)
- Follow the provided outline structure exactly: one script section per outline section
- Use delivery cues inline: `[PAUSE]`, `[EMPHASIS]`, `[SLOWER]`, `[FASTER]`, `[WHISPER]`
- Write for spoken delivery: short sentences, conversational tone, rhetorical questions
- Match the brand voice if a brand guide is provided
- Target a speaking pace of ~150 words per minute
- Respond in valid JSON matching the defined structure

The user prompt includes:

- The approved outline (full JSON)
- The selected title
- The video topic
- Brand guide content (if available)
- Reference material (if available)

The `parseResponse` method:

- Extracts JSON from the response (handling code blocks)
- Validates the sections array matches the outline section count
- Calculates `total_word_count` by summing section word counts
- Calculates `estimated_duration_seconds` using `total_word_count / 150 * 60`
- Sets `speaking_pace_wpm` to 150

### GenerateScriptJob

```php
class GenerateScriptJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public Video $video) {}

    public function handle(ScriptGenerationService $service): void
    {
        $this->video->update(['status' => 'generating_script']);

        try {
            $script = $service->generate($this->video);
            $this->video->update([
                'generated_script' => $script,
                'status' => 'script_generated',
            ]);
        } catch (\Throwable $e) {
            $this->video->update([
                'status' => 'script_generation_failed',
                'generation_error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
```

### Database Changes

```php
Schema::table('videos', function (Blueprint $table) {
    $table->json('generated_script')->nullable()->after('generated_outline');
});
```

### ScriptController

```php
class ScriptController extends Controller
{
    public function index(Video $video): Response
    {
        abort_unless($video->user_id === auth()->id(), 403);

        return Inertia::render('videos/script', [
            'video' => $video->only([
                'id', 'topic', 'selected_title', 'status',
                'generated_script', 'generated_outline', 'generation_error',
            ]),
        ]);
    }

    public function generate(Video $video): RedirectResponse
    {
        abort_unless($video->user_id === auth()->id(), 403);

        GenerateScriptJob::dispatch($video);

        return to_route('videos.script', $video);
    }
}
```

### Routes

```php
Route::get('videos/{video}/script', [ScriptController::class, 'index'])->name('videos.script');
Route::post('videos/{video}/generate-script', [ScriptController::class, 'generate'])->name('videos.script.generate');
```

### Frontend: Script Page

Create `resources/js/pages/videos/script.tsx` with three states:

1. **Loading**: Skeleton text blocks with spinner and "Writing script..." text. Polls every 5 seconds (scripts take longer than titles/outlines).

2. **Error**: Destructive Alert with error message and "Retry" button.

3. **Results**: The full script displayed as section-by-section content:
    - Each section shows the outline title as a header
    - Script text rendered as paragraphs with delivery cues highlighted (e.g., `[PAUSE]` in a muted Badge)
    - Per-section word count badge
    - A sticky summary bar at the bottom: total word count, estimated duration, speaking pace
    - "Continue to Critique" button (proceeds to E011-F004)
    - "Regenerate" button for re-generation

### TypeScript Types

Add to `resources/js/types/video.ts`:

```typescript
export type ScriptSection = {
    outline_title: string;
    script_text: string;
    word_count: number;
    delivery_cues: string[];
};

export type GeneratedScript = {
    sections: ScriptSection[];
    total_word_count: number;
    estimated_duration_seconds: number;
    speaking_pace_wpm: number;
};
```

### Delivery Cue Highlighting

On the frontend, delivery cues in the script text are rendered as highlighted badges:

```tsx
function highlightCues(text: string): ReactNode {
    const parts = text.split(/(\[(?:PAUSE|EMPHASIS|SLOWER|FASTER|WHISPER)\])/g);
    return parts.map((part, i) => {
        if (part.match(/^\[.+\]$/)) {
            return (
                <Badge key={i} variant="secondary" className="mx-1 text-xs">
                    {part}
                </Badge>
            );
        }
        return <span key={i}>{part}</span>;
    });
}
```

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Services/AiClient.php` -- AI client service for API calls.
- `/Users/young/Nextcloud/dev/Itervel/app/Services/OutlineGenerationService.php` -- Outline service from E011-F001. Template for ScriptGenerationService.
- `/Users/young/Nextcloud/dev/Itervel/app/Services/TitleGenerationService.php` -- Title service from E009-F003. Template for service structure.
- `/Users/young/Nextcloud/dev/Itervel/app/Jobs/GenerateOutlineJob.php` -- Outline job from E011-F001. Template for GenerateScriptJob.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/OutlineController.php` -- Outline controller. Template for ScriptController.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/Video.php` -- Video model. Must add `generated_script` to fillable/casts.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/VideoFactory.php` -- Video factory. Must add script-related factory states.
- `/Users/young/Nextcloud/dev/Itervel/config/ai.php` -- AI config with 'script' step definition.
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Must add script routes.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/videos/outline.tsx` -- Outline page. Template for script page patterns.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/video.ts` -- Video types. Must add script types.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/badge.tsx` -- Badge for delivery cue highlighting.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/card.tsx` -- Card for section containers.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/alert.tsx` -- Alert for error state.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/skeleton.tsx` -- Skeleton for loading state.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/app-layout.tsx` -- App layout wrapper.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/OutlineGenerationTest.php` -- Outline tests. Template for script test patterns.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/app/Services/ScriptGenerationService.php` -- Service that generates a full video script from the approved outline using AI.
- `/Users/young/Nextcloud/dev/Itervel/app/Jobs/GenerateScriptJob.php` -- Queued job for async script generation.
- `/Users/young/Nextcloud/dev/Itervel/database/migrations/YYYY_MM_DD_HHMMSS_add_generated_script_to_videos_table.php` -- Migration to add `generated_script` JSON column.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/ScriptController.php` -- Controller with `index` and `generate` methods.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/videos/script.tsx` -- Script page with loading/error/results states.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/ScriptGenerationTest.php` -- Pest feature tests for script generation.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: script-backend-dev
    - Role: Creates ScriptGenerationService, GenerateScriptJob, migration, ScriptController, updates Video model, and adds routes
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: script-frontend-dev
    - Role: Creates the script page with delivery cue highlighting, section display, and loading/error/results states
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: script-test-dev
    - Role: Writes Pest feature tests for script generation with mocked AI
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: script-reviewer
    - Role: Validates the complete script generation implementation
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

### 1. Create Script Generation Backend

- **Task ID**: create-script-backend
- **Depends On**: none
- **Assigned To**: script-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- Read `/Users/young/Nextcloud/dev/Itervel/app/Services/OutlineGenerationService.php` and `/Users/young/Nextcloud/dev/Itervel/app/Jobs/GenerateOutlineJob.php` for the pattern
- Create migration: `docker compose exec app php artisan make:migration add_generated_script_to_videos_table --table=videos --no-interaction`
    - `up()`: add `$table->json('generated_script')->nullable()->after('generated_outline');`
    - `down()`: drop the column
- Update Video model: add `'generated_script'` to `$fillable` and `'generated_script' => 'array'` to `casts()`
- Update VideoFactory: add `'generated_script' => null` to definition, add `withGeneratedScript(): static` state
- Create `/Users/young/Nextcloud/dev/Itervel/app/Services/ScriptGenerationService.php`:
    - Constructor: inject `AiClient`
    - `generate(Video $video): array` -- gets model via `getModelForStep('script')`, builds prompts with outline, title, topic, brand guide, calls AI with 8192 max tokens, parses response
    - System prompt: instructs for 2,000-2,500 word conversational script, delivery cues, spoken-delivery style, ~150 WPM speaking pace, JSON output matching structure
    - User prompt: includes full approved outline JSON, selected title, topic, brand guide, references
    - `parseResponse()`: validates sections match outline count, calculates totals
- Create `docker compose exec app php artisan make:job GenerateScriptJob --no-interaction`
- Edit GenerateScriptJob: implements ShouldQueue, sets status `generating_script`, calls service, stores result with status `script_generated`, handles failures with `script_generation_failed`
- Create `docker compose exec app php artisan make:controller ScriptController --no-interaction`
- Edit ScriptController with `index` and `generate` methods (same pattern as OutlineController)
- Add routes to web.php inside auth+verified group
- Run migration: `docker compose exec app php artisan migrate --no-interaction`
- Run `docker compose exec app vendor/bin/pint --dirty`

### 2. Create Script Frontend Page

- **Task ID**: create-script-frontend
- **Depends On**: create-script-backend
- **Assigned To**: script-frontend-dev
- **Agent Type**: coder
- **Parallel**: false
- Run `npm run build` to generate Wayfinder routes
- Add `ScriptSection` and `GeneratedScript` types to `/Users/young/Nextcloud/dev/Itervel/resources/js/types/video.ts`
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/videos/script.tsx`:
    - Three states: loading (skeleton + polling every 5 seconds), error (Alert + retry), results (section cards with highlighted delivery cues)
    - Create a `highlightCues(text: string)` function that splits script text on delivery cue patterns and renders cues as Badge components
    - Each section card shows: outline title header, script text with highlighted cues, word count badge
    - Summary bar: total word count, estimated duration (formatted as "X min Y sec"), speaking pace
    - "Continue" and "Regenerate" buttons
    - Breadcrumbs: Dashboard > Video > Script
- Run `npm run types` and `npm run lint`

### 3. Write Feature Tests

- **Task ID**: write-script-tests
- **Depends On**: create-script-backend
- **Assigned To**: script-test-dev
- **Agent Type**: coder
- **Parallel**: true
- Create `docker compose exec app php artisan make:test ScriptGenerationTest --pest --no-interaction`
- Write tests:
    - `test('script page is displayed for video owner')` -- assert 200
    - `test('script page returns 403 for non-owner')` -- assert 403
    - `test('script generation can be triggered')` -- assert job dispatched
    - `test('script generation job generates script from outline')` -- mock AiClient, run job, assert script stored
    - `test('script generation job sets failure status on error')` -- mock throw, assert failure status
    - `test('ScriptGenerationService generates valid script')` -- mock AiClient, call service, assert structure
    - `test('ScriptGenerationService includes outline in prompt')` -- verify outline passed to AI
    - `test('generated script has correct word count calculation')` -- verify total_word_count matches sum
    - `test('non-owner cannot trigger script generation')` -- assert 403
- Run tests and full suite
- Run `docker compose exec app vendor/bin/pint --dirty`

### 4. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-script-backend, create-script-frontend, write-script-tests
- **Assigned To**: script-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands
- Verify ScriptGenerationService uses the approved outline as context
- Verify delivery cues are included in the generated script
- Verify the frontend highlights delivery cues as badges
- Verify word count and duration calculations are correct
- Verify all tests pass with mocked AI
- Confirm all acceptance criteria are met

## Acceptance Criteria

- A `ScriptGenerationService` generates a structured script from the approved outline
- The service uses `getModelForStep('script')` for the AI model selection
- The generated script is 2,000-2,500 words (validated in service)
- The script follows the outline structure with one section per outline section
- Delivery cues (`[PAUSE]`, `[EMPHASIS]`, `[SLOWER]`, `[FASTER]`, `[WHISPER]`) are included in the script text
- The script matches the brand voice when a brand guide is provided
- A `GenerateScriptJob` handles async script generation
- Video status transitions: `outline_approved` → `generating_script` → `script_generated` (or `script_generation_failed`)
- The `videos` table has a `generated_script` JSON column
- The script page shows loading state with skeleton and polling (5-second interval)
- The script page shows error state with retry option
- The script page displays the full script with delivery cues highlighted as badges
- Per-section word counts are displayed
- Total word count, estimated duration, and speaking pace are shown in a summary bar
- Only video owners can view and trigger script generation
- Tests mock the AI client
- All tests pass
- TypeScript compiles without errors
- ESLint passes

## Validation Commands

```bash
docker compose exec app php artisan test tests/Feature/ScriptGenerationTest.php --compact
docker compose exec app php artisan test --compact
docker compose exec app php artisan route:list --name=videos.script
npm run types
npm run lint
npm run format:check
docker compose exec app vendor/bin/pint --dirty
```

## Notes

- The `max_tokens` for script generation is set to 8192 (higher than titles/outlines at 2048/4096) because scripts are substantially longer (2,000-2,500 words).
- Polling interval is 5 seconds (vs 3 for titles/outlines) because script generation takes longer due to the larger output.
- The speaking pace of 150 WPM is a standard YouTube speaking rate. The estimated duration is calculated as `total_word_count / 150 * 60` seconds.
- Delivery cues are embedded inline in the script text using bracket notation. The frontend uses regex to split and highlight them. The backend doesn't need to extract or validate individual cues.
- The script JSON structure intentionally maps sections 1:1 with outline sections via `outline_title`. This makes it easy to correlate script content with the outline structure in the review step.

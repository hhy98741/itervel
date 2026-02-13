# Feature: Title Generation

**Epic**: E009-ai-configuration-and-title-generation.md
**Feature**: E009-F003
**Epic depends on**: E006-brand-guide-and-video-input.md
**Feature depends on**: E009-F001

## Task Description

Title Generation uses AI to generate 5 title options for the video, each with a logline, framework used, character count, and click-potential ranking. This is the first feature that performs actual AI generation and establishes the AI service infrastructure for all subsequent generation features (outline, script, thumbnails, etc.).

**What it does**: Uses AI to generate 5 title options for the video, each with a logline, framework used, character count, and click-potential ranking.

**Expected outcome**: The user sees 5 title options ranked 1-5 by predicted click-through rate. Each title includes the title text, a one-sentence logline, the framework used (e.g., "Contrarian Angle"), character count, and reasoning for the ranking.

This feature creates:

1. An **AI client service** (`AiClient`) that wraps the Anthropic PHP SDK for making API calls to Claude models.
2. A **`TitleGenerationService`** that constructs the prompt, calls the AI client, and parses the structured response into title objects.
3. A **`GenerateTitlesJob`** that handles asynchronous title generation via the queue.
4. Database columns on the `videos` table for storing generated titles (`generated_titles` JSON) and updating the video status.
5. A **`TitleController`** with endpoints to trigger generation and display results.
6. A **titles page** (`resources/js/pages/videos/titles.tsx`) showing the generated titles with loading state, ranking, and details.
7. Comprehensive tests including mocked AI interactions.

**Dependency on E009-F001 (AI Model Selection)**: Provides the `ai_config` on the video to determine which model to use for title generation. The `getModelForStep('title')` method returns the configured model key.

## Objective

Establish the AI service infrastructure and implement title generation: install the Anthropic PHP SDK, create an `AiClient` service for API communication, create a `TitleGenerationService` that generates 5 title options with structured metadata, create a queue job for async generation, add `generated_titles` JSON column to videos, build a titles page showing loading state and results, and write comprehensive tests with mocked AI responses.

## Solution Approach

### 1. Install Anthropic PHP SDK

Install the official Anthropic PHP SDK via Composer:

```bash
composer require anthropic-ai/anthropic --no-interaction
```

Add the API key to `.env` and `config/services.php`:

```php
// config/services.php
'anthropic' => [
    'api_key' => env('ANTHROPIC_API_KEY'),
],
```

### 2. AiClient Service

Create `app/Services/AiClient.php` as a thin wrapper around the Anthropic SDK:

```php
namespace App\Services;

use Anthropic\Anthropic;

class AiClient
{
    private Anthropic $client;

    public function __construct()
    {
        $this->client = Anthropic::client(config('services.anthropic.api_key'));
    }

    public function generate(string $modelKey, string $systemPrompt, string $userPrompt, int $maxTokens = 4096): string
    {
        $modelId = config("ai.models.{$modelKey}.id");

        $response = $this->client->messages()->create([
            'model' => $modelId,
            'max_tokens' => $maxTokens,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ]);

        return $response->content[0]->text;
    }
}
```

Register the service as a singleton in `AppServiceProvider`:

```php
$this->app->singleton(AiClient::class);
```

### 3. TitleGenerationService

Create `app/Services/TitleGenerationService.php`:

````php
namespace App\Services;

use App\Models\Video;

class TitleGenerationService
{
    public function __construct(private AiClient $aiClient) {}

    public function generate(Video $video): array
    {
        $modelKey = $video->getModelForStep('title');
        $topic = $video->topic;
        $references = $video->references()->whereNotNull('extracted_content')->get();

        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt = $this->buildUserPrompt($topic, $references);

        $response = $this->aiClient->generate($modelKey, $systemPrompt, $userPrompt, 2048);

        return $this->parseResponse($response);
    }

    private function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
        You are a YouTube title expert. Generate exactly 5 title options for a YouTube video.
        For each title, provide:
        1. title: The title text (max 100 characters)
        2. logline: A one-sentence description of the video's angle
        3. framework: The title framework used (e.g., "How-To", "Listicle", "Contrarian Angle", "Question Hook", "Number + Benefit", "Challenge/Dare", "Story Arc")
        4. character_count: The exact character count of the title
        5. ranking: Your predicted click-through rate ranking (1 = highest potential)
        6. reasoning: A brief explanation of why this title would perform well

        Respond in valid JSON format as an array of 5 objects. Rank from 1 (best) to 5.
        PROMPT;
    }

    private function buildUserPrompt(string $topic, $references): string
    {
        $prompt = "Video topic: {$topic}";

        if ($references->isNotEmpty()) {
            $prompt .= "\n\nReference material:\n";
            foreach ($references as $ref) {
                $prompt .= "- {$ref->url}: {$ref->extracted_content}\n";
            }
        }

        return $prompt;
    }

    private function parseResponse(string $response): array
    {
        $json = $this->extractJson($response);
        $titles = json_decode($json, true);

        if (! is_array($titles) || count($titles) !== 5) {
            throw new \RuntimeException('Invalid title generation response: expected 5 titles');
        }

        // Sort by ranking
        usort($titles, fn ($a, $b) => ($a['ranking'] ?? 99) <=> ($b['ranking'] ?? 99));

        return $titles;
    }

    private function extractJson(string $text): string
    {
        // Extract JSON from markdown code blocks if present
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $matches)) {
            return trim($matches[1]);
        }

        return trim($text);
    }
}
````

### 4. GenerateTitlesJob

Create a queued job for async generation:

```php
namespace App\Jobs;

use App\Models\Video;
use App\Services\TitleGenerationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateTitlesJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public Video $video) {}

    public function handle(TitleGenerationService $service): void
    {
        $this->video->update(['status' => 'generating_titles']);

        try {
            $titles = $service->generate($this->video);

            $this->video->update([
                'generated_titles' => $titles,
                'status' => 'titles_generated',
            ]);
        } catch (\Throwable $e) {
            $this->video->update([
                'status' => 'title_generation_failed',
                'generation_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
```

### 5. Database: Add Title Columns

Add migration for title-related columns on the videos table:

```php
Schema::table('videos', function (Blueprint $table) {
    $table->json('generated_titles')->nullable()->after('ai_config');
    $table->text('generation_error')->nullable()->after('generated_titles');
});
```

### 6. Video Model Updates

```php
// Add to $fillable
'generated_titles',
'generation_error',

// Add to casts()
'generated_titles' => 'array',
```

### 7. TitleController

```php
namespace App\Http\Controllers;

use App\Jobs\GenerateTitlesJob;
use App\Models\Video;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TitleController extends Controller
{
    public function index(Video $video): Response
    {
        abort_unless($video->user_id === auth()->id(), 403);

        return Inertia::render('videos/titles', [
            'video' => $video->only([
                'id', 'topic', 'status', 'generated_titles', 'generation_error',
            ]),
        ]);
    }

    public function generate(Video $video): RedirectResponse
    {
        abort_unless($video->user_id === auth()->id(), 403);

        GenerateTitlesJob::dispatch($video);

        return to_route('videos.titles', $video);
    }
}
```

### 8. Routes

```php
Route::get('videos/{video}/titles', [TitleController::class, 'index'])->name('videos.titles');
Route::post('videos/{video}/generate-titles', [TitleController::class, 'generate'])->name('videos.titles.generate');
```

### 9. Frontend: Titles Page

Create `resources/js/pages/videos/titles.tsx` with:

- **Loading state**: When `video.status === 'generating_titles'`, show a skeleton loading UI with `Spinner` component and "Generating titles..." text. Use Inertia polling (`useEffect` with `router.reload`) to check for updates every 3 seconds.
- **Error state**: When `video.status === 'title_generation_failed'`, show an `Alert` with the error message and a "Retry" button.
- **Results state**: When `video.status === 'titles_generated'`, show 5 title cards ranked 1-5:
    - Each card shows: rank badge (1-5), title text, logline, framework badge, character count, and reasoning
    - Cards are sorted by ranking
    - Rank 1 gets a highlighted border (`border-primary`)
- A "Generate" button to trigger title generation when status is `draft` or for regeneration
- Use Inertia polling: `router.reload({ only: ['video'], preserveState: true })` on an interval when generating

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Models/Video.php` -- Video model. Must add `generated_titles` and `generation_error` to fillable/casts. Has `getModelForStep()` from E009-F001.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/VideoFactory.php` -- Video factory. Must add defaults and states for title generation.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/VideoConfigController.php` -- Config controller (from E009-F001). Referenced for pattern. Update redirect to go to titles page.
- `/Users/young/Nextcloud/dev/Itervel/config/ai.php` -- AI config (from E009-F001). Model definitions used to resolve model IDs.
- `/Users/young/Nextcloud/dev/Itervel/config/services.php` -- Services config. Must add `anthropic` key.
- `/Users/young/Nextcloud/dev/Itervel/app/Providers/AppServiceProvider.php` -- App service provider. Must register `AiClient` as singleton.
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Must add title routes.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/card.tsx` -- Card component for title display.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/badge.tsx` -- Badge component for rank and framework.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/alert.tsx` -- Alert component for error state.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/button.tsx` -- Button component.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/skeleton.tsx` -- Skeleton component for loading state.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/spinner.tsx` -- Spinner component.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/app-layout.tsx` -- App layout.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/lib/utils.ts` -- `cn()` utility.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/ai.ts` -- AI types (from E009-F001).
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/app/Services/AiClient.php` -- Thin wrapper around the Anthropic PHP SDK for making AI API calls.
- `/Users/young/Nextcloud/dev/Itervel/app/Services/TitleGenerationService.php` -- Service that constructs prompts, calls the AI client, and parses title generation responses.
- `/Users/young/Nextcloud/dev/Itervel/app/Jobs/GenerateTitlesJob.php` -- Queued job that generates titles asynchronously and updates the video.
- `/Users/young/Nextcloud/dev/Itervel/database/migrations/YYYY_MM_DD_HHMMSS_add_generated_titles_to_videos_table.php` -- Migration to add `generated_titles` JSON and `generation_error` text columns.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/TitleController.php` -- Controller with `index` (show titles) and `generate` (dispatch job) methods.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/videos/titles.tsx` -- Inertia React page showing loading state, error state, or 5 ranked title cards.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/TitleGenerationTest.php` -- Pest feature tests for title generation endpoints, job, and service.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer (AI Infrastructure)
    - Name: title-ai-backend-dev
    - Role: Installs Anthropic SDK, creates AiClient service, TitleGenerationService, GenerateTitlesJob, database migration, updates Video model, registers service in AppServiceProvider
    - Agent Type: coder
    - Resume: false

- Backend Developer (Controller & Routes)
    - Name: title-controller-dev
    - Role: Creates TitleController with index and generate methods, adds routes, updates VideoConfigController redirect
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: title-frontend-dev
    - Role: Creates the titles page with loading/error/results states, polling for async updates, and ranked title card display
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: title-test-dev
    - Role: Writes Pest feature tests for title generation with mocked AI responses, job dispatching, status transitions, and authorization
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: title-reviewer
    - Role: Validates the complete title generation implementation against acceptance criteria
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Install SDK, Create AI Services, Migration, and Update Models

- **Task ID**: create-ai-infrastructure
- **Depends On**: none
- **Assigned To**: title-ai-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- Install Anthropic PHP SDK: `composer require anthropic-ai/anthropic --no-interaction` (run inside Docker container)
- Add to `.env.example`: `ANTHROPIC_API_KEY=`
- Update `/Users/young/Nextcloud/dev/Itervel/config/services.php`: add `'anthropic' => ['api_key' => env('ANTHROPIC_API_KEY')]`
- Create `/Users/young/Nextcloud/dev/Itervel/app/Services/AiClient.php`:
    - Constructor: create Anthropic client using `config('services.anthropic.api_key')`
    - `generate(string $modelKey, string $systemPrompt, string $userPrompt, int $maxTokens = 4096): string` method that resolves model ID from `config("ai.models.{$modelKey}.id")`, calls `$this->client->messages()->create()`, and returns the text content
- Create `/Users/young/Nextcloud/dev/Itervel/app/Services/TitleGenerationService.php`:
    - Constructor: inject `AiClient`
    - `generate(Video $video): array` method that gets the model for the title step, builds system and user prompts, calls the AI client, and parses the JSON response into an array of 5 title objects
    - System prompt instructs the AI to generate 5 titles with: title, logline, framework, character_count, ranking, reasoning
    - User prompt includes the video topic and any reference material
    - `parseResponse()` extracts JSON (handling markdown code blocks), validates 5 titles, sorts by ranking
- Create migration: `php artisan make:migration add_generated_titles_to_videos_table --table=videos --no-interaction`
    - `up()`: add `$table->json('generated_titles')->nullable()->after('ai_config');` and `$table->text('generation_error')->nullable()->after('generated_titles');`
    - `down()`: drop both columns
- Update `/Users/young/Nextcloud/dev/Itervel/app/Models/Video.php`:
    - Add `'generated_titles'` and `'generation_error'` to `$fillable`
    - Add `'generated_titles' => 'array'` to `casts()`
- Update `/Users/young/Nextcloud/dev/Itervel/database/factories/VideoFactory.php`:
    - Add `'generated_titles' => null` and `'generation_error' => null` to `definition()`
    - Add `withGeneratedTitles(): static` state method that sets `generated_titles` to a sample array of 5 title objects and `status` to `'titles_generated'`
- Register `AiClient` as singleton in `/Users/young/Nextcloud/dev/Itervel/app/Providers/AppServiceProvider.php`: `$this->app->singleton(AiClient::class);`
- Create `/Users/young/Nextcloud/dev/Itervel/app/Jobs/GenerateTitlesJob.php`:
    - Implements `ShouldQueue`, uses `Queueable`
    - Constructor: accepts `Video $video`
    - `handle(TitleGenerationService $service): void` -- sets status to `generating_titles`, calls `$service->generate()`, updates video with generated titles and status `titles_generated`. On failure, sets status to `title_generation_failed` and stores error message
- Run migration: `php artisan migrate --no-interaction`
- Run `vendor/bin/pint --dirty`

### 2. Create TitleController and Routes

- **Task ID**: create-title-controller
- **Depends On**: create-ai-infrastructure
- **Assigned To**: title-controller-dev
- **Agent Type**: coder
- **Parallel**: false
- Create controller: `php artisan make:controller TitleController --no-interaction`
- Edit `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/TitleController.php`:
    - `index(Video $video): Response` -- checks ownership (abort 403), renders `videos/titles` with video data (id, topic, status, generated_titles, generation_error)
    - `generate(Video $video): RedirectResponse` -- checks ownership (abort 403), dispatches `GenerateTitlesJob`, redirects to `videos.titles`
- Edit `/Users/young/Nextcloud/dev/Itervel/routes/web.php`:
    - Add `use App\Http\Controllers\TitleController;`
    - Add routes inside auth+verified middleware group:
        ```php
        Route::get('videos/{video}/titles', [TitleController::class, 'index'])->name('videos.titles');
        Route::post('videos/{video}/generate-titles', [TitleController::class, 'generate'])->name('videos.titles.generate');
        ```
- Update `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/VideoConfigController.php`:
    - Change the redirect in `update()` from `videos.show` to `videos.titles`
- Run `vendor/bin/pint --dirty`
- Run `php artisan route:list --name=videos.titles`
- Run existing tests: `php artisan test --compact`

### 3. Create Frontend Titles Page

- **Task ID**: create-titles-page
- **Depends On**: create-title-controller
- **Assigned To**: title-frontend-dev
- **Agent Type**: coder
- **Parallel**: false
- Run `npm run build` to generate Wayfinder routes for TitleController
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/videos/titles.tsx`:
    - Import `Head`, `router`, `Link` from `@inertiajs/react`
    - Import `useEffect`, `useState` from React
    - Import UI components: `Card`, `CardContent`, `CardHeader`, `CardTitle`, `Badge`, `Button`, `Alert`, `AlertTitle`, `AlertDescription`, `Skeleton`, `Spinner`
    - Import `Heading`, `AppLayout`
    - Import Wayfinder actions for TitleController
    - Import icons: `Trophy`, `RefreshCw`, `AlertCircle` from `lucide-react`
    - Define `TitleOption` type: `{ title: string; logline: string; framework: string; character_count: number; ranking: number; reasoning: string }`
    - Define page props: `video: { id: number; topic: string; status: string; generated_titles: TitleOption[] | null; generation_error: string | null }`
    - Implement 3 states:
        1. **Loading** (`status === 'generating_titles'`): Show 5 `Skeleton` cards with `Spinner` and "Generating title options..." text. Use `useEffect` with `setInterval` to poll `router.reload({ only: ['video'], preserveState: true })` every 3 seconds. Clear interval when status changes.
        2. **Error** (`status === 'title_generation_failed'`): Show an `Alert` (variant destructive) with the `generation_error` message and a "Retry" `Button` that POSTs to generate-titles
        3. **Results** (`status === 'titles_generated'` and `generated_titles` is not null): Render 5 title cards in order of ranking. Each card:
            - Left side: rank number in a circle badge (rank 1 gets `bg-primary text-primary-foreground`, others get `bg-muted`)
            - Title text as `CardTitle` (medium font size)
            - Logline in `text-muted-foreground`
            - Row of metadata: framework `Badge`, character count text, ranking reasoning in collapsible or tooltip
            - Rank 1 card gets a `border-primary` border highlight
    - **Initial state** (`status === 'draft'` or no titles): Show a "Generate Titles" button that POSTs to the generate route
    - Breadcrumbs: "Dashboard" > "Video" > "Titles"
    - A "Regenerate" button at the bottom when titles exist (to re-generate)
- Run `npm run types`
- Run `npm run lint`, fix with `npm run lint:fix`
- Run `npm run build`

### 4. Write Feature Tests

- **Task ID**: write-title-tests
- **Depends On**: create-title-controller
- **Assigned To**: title-test-dev
- **Agent Type**: coder
- **Parallel**: true (can run in parallel with frontend)
- Create test file: `php artisan make:test TitleGenerationTest --pest --no-interaction`
- Write tests in `/Users/young/Nextcloud/dev/Itervel/tests/Feature/TitleGenerationTest.php`:
    - `test('titles page is displayed for video owner')` -- GET titles route, assert 200 and Inertia component
    - `test('titles page returns 403 for non-owner')` -- assert 403
    - `test('guests are redirected to login')` -- assert redirect
    - `test('title generation can be triggered')` -- POST to generate route, assert `GenerateTitlesJob` was dispatched (use `Queue::fake()` or `Bus::fake()`)
    - `test('title generation job updates video status to generating_titles')` -- create video, run job manually with mocked AiClient, assert status changes
    - `test('title generation job stores generated titles on success')` -- mock AiClient to return valid JSON, run job, assert `generated_titles` is stored and has 5 items
    - `test('title generation job sets failure status on error')` -- mock AiClient to throw exception, run job, assert status is `title_generation_failed` and `generation_error` is set
    - `test('titles page shows generated titles')` -- create video with `withGeneratedTitles()` factory state, GET titles page, assert `generated_titles` prop has 5 items
    - `test('TitleGenerationService generates 5 titles')` -- mock AiClient, call service directly, assert result is array of 5 with expected keys
    - `test('TitleGenerationService parses JSON from code blocks')` -- test with response wrapped in `json` blocks
    - `test('TitleGenerationService throws on invalid response')` -- mock invalid JSON, assert RuntimeException
    - `test('non-owner cannot trigger title generation')` -- assert 403
    - `test('AiClient resolves model ID from config')` -- verify the model key to ID resolution
    - For mocking: use `$this->mock(AiClient::class)` or `$this->partialMock()` to mock the AI responses without making real API calls
- Run tests: `php artisan test tests/Feature/TitleGenerationTest.php --compact`
- Run `vendor/bin/pint --dirty`
- Run full test suite: `php artisan test --compact`

### 5. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-ai-infrastructure, create-title-controller, create-titles-page, write-title-tests
- **Assigned To**: title-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands
- Verify Anthropic SDK is installed in `composer.json`
- Verify `config/services.php` has `anthropic` key
- Verify `AiClient` service exists and is registered as singleton
- Verify `TitleGenerationService` constructs prompts and parses responses
- Verify `GenerateTitlesJob` implements `ShouldQueue` and handles success/failure
- Verify migration adds `generated_titles` and `generation_error` columns
- Verify Video model has the new columns in fillable/casts
- Verify `TitleController` has index and generate methods with authorization
- Verify routes are registered
- Verify titles page has loading, error, and results states
- Verify polling is implemented for async updates
- Confirm all acceptance criteria are met

## Acceptance Criteria

- The Anthropic PHP SDK is installed and configured with an API key via environment variable
- The `AiClient` service wraps the Anthropic SDK and resolves model IDs from `config/ai.php`
- The `TitleGenerationService` generates 5 title options with structured metadata (title, logline, framework, character_count, ranking, reasoning)
- The `GenerateTitlesJob` handles async title generation via the queue
- The `videos` table has `generated_titles` (JSON) and `generation_error` (text) columns
- The titles page shows a loading state with skeletons and polling during generation
- The titles page shows an error state with retry option when generation fails
- The titles page shows 5 ranked title cards with all metadata when generation succeeds
- Rank 1 title card is visually highlighted
- Each title card shows: title text, logline, framework badge, character count, and ranking reasoning
- Title generation can be triggered via POST to `/videos/{video}/generate-titles`
- Only video owners can view titles and trigger generation (403 for others)
- Guests are redirected to login
- The video status transitions: `draft` → `generating_titles` → `titles_generated` (or `title_generation_failed`)
- Tests mock the AI client (no real API calls in tests)
- All tests pass
- TypeScript compiles without errors
- ESLint passes
- PHP formatting passes

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run title generation tests
php artisan test tests/Feature/TitleGenerationTest.php --compact

# Run full test suite
php artisan test --compact

# Verify routes
php artisan route:list --name=videos.titles

# TypeScript type checking
npm run types

# ESLint linting
npm run lint

# PHP code formatting
vendor/bin/pint --dirty

# Prettier formatting check
npm run format:check
```

## Notes

- This is the first feature that makes real AI API calls. All tests MUST mock the `AiClient` to avoid hitting the Anthropic API during testing. Use Laravel's built-in mocking: `$this->mock(AiClient::class)`.
- The `ANTHROPIC_API_KEY` environment variable must be set for the service to work. In development, add it to `.env`. In CI/testing, the service should be mocked so no key is needed.
- The `GenerateTitlesJob` uses Laravel's queue system. In development with `QUEUE_CONNECTION=sync`, the job runs synchronously. In production, it should use `redis` or `database` queue driver for async processing.
- The polling on the titles page uses a 3-second interval. This is a reasonable balance between responsiveness and server load. The polling should stop once the status changes from `generating_titles`.
- The system prompt instructs the AI to respond in JSON format. The `parseResponse` method handles both raw JSON and JSON wrapped in markdown code blocks (`json`) since LLMs sometimes add formatting.
- The `TitleGenerationService` includes reference material in the prompt when available (from E006-F004/F005). If no references exist, only the topic is sent.
- The video status values are strings (not an enum) for flexibility. Current values in the pipeline: `draft`, `generating_titles`, `titles_generated`, `title_generation_failed`.
- All commands should be run inside the Docker container.

# Feature: Reference URL Processing

**Epic**: E006-brand-guide-and-video-input.md
**Feature**: E006-F005
**Epic depends on**: E004-project-management.md
**Feature depends on**: E006-F004

## Task Description

Reference URL Processing automatically extracts useful content from the reference URLs the user provided in E006-F004. For articles, the system scrapes the text content. For YouTube videos, it extracts the transcript. Key concepts are pulled out as bullet points. Each URL has a 30-second processing timeout. If extraction fails, the user is warned but can continue without that reference. Extracted content is cached for reuse.

**What it does**: Automatically extracts useful content from the reference URLs the user provided.

**Expected outcome**: For articles, the system scrapes the text content. For YouTube videos, it extracts the transcript. Key concepts are pulled out as bullet points. Each URL has a 30-second processing timeout. If extraction fails, the user is warned but can continue without that reference. Extracted content is cached for reuse.

This feature builds on E006-F004 (Reference URL Input), which creates the `VideoReference` model, `video_references` table, and reference management infrastructure. It adds a queued job (`ProcessVideoReference`) that handles the actual content extraction, a service class (`ReferenceExtractor`) for URL content extraction, integration with Laravel's HTTP client for article scraping, a YouTube transcript extraction approach, caching of extracted content, and the processing status lifecycle on the `VideoReference` model.

**Dependency on E006-F004 (Reference URL Input)**: E006-F004 creates the `VideoReference` model with `url`, `type`, `extracted_content`, `processing_status`, `processing_error`, and `processed_at` columns. This feature implements the processing logic that populates those columns.

## Objective

Implement reference URL content extraction by creating a `ProcessVideoReference` queued job, a `ReferenceExtractor` service with article scraping and YouTube transcript extraction, URL content caching, processing status lifecycle management, automatic job dispatching when references are saved, and comprehensive Pest feature tests covering successful extraction, timeouts, failures, and caching.

## Solution Approach

### 1. ReferenceExtractor Service

Create a service class `App\Services\ReferenceExtractor` responsible for extracting content from URLs. It uses Laravel's HTTP client (`Http::`) for making requests with a 30-second timeout.

```php
class ReferenceExtractor
{
    public function extract(string $url, string $type): string
    {
        return match ($type) {
            'youtube' => $this->extractYouTubeTranscript($url),
            default => $this->extractArticleContent($url),
        };
    }

    protected function extractArticleContent(string $url): string
    {
        $response = Http::timeout(30)->get($url);

        if ($response->failed()) {
            throw new \RuntimeException("Failed to fetch URL: HTTP {$response->status()}");
        }

        $html = $response->body();

        return $this->parseHtmlToText($html);
    }

    protected function extractYouTubeTranscript(string $url): string
    {
        $videoId = $this->extractYouTubeVideoId($url);

        if (! $videoId) {
            throw new \RuntimeException('Could not extract YouTube video ID from URL.');
        }

        // Use YouTube's timedtext API or a third-party transcript service
        $response = Http::timeout(30)->get("https://www.youtube.com/watch?v={$videoId}");

        if ($response->failed()) {
            throw new \RuntimeException("Failed to fetch YouTube page: HTTP {$response->status()}");
        }

        return $this->parseYouTubeTranscript($response->body(), $videoId);
    }

    protected function parseHtmlToText(string $html): string
    {
        // Strip script and style tags, then extract text from common article elements
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/si', '', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/si', '', $html);

        // Try to extract from common article containers
        if (preg_match('/<article[^>]*>(.*?)<\/article>/si', $html, $matches)) {
            $html = $matches[1];
        } elseif (preg_match('/<main[^>]*>(.*?)<\/main>/si', $html, $matches)) {
            $html = $matches[1];
        }

        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);

        return trim(mb_substr($text, 0, 50000));
    }

    protected function extractYouTubeVideoId(string $url): ?string
    {
        $patterns = [
            '/youtube\.com\/watch\?v=([A-Za-z0-9_-]{11})/',
            '/youtu\.be\/([A-Za-z0-9_-]{11})/',
            '/youtube\.com\/embed\/([A-Za-z0-9_-]{11})/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    protected function parseYouTubeTranscript(string $html, string $videoId): string
    {
        // Extract captions/transcript data from YouTube page
        // YouTube embeds caption track URLs in the page source as JSON
        if (preg_match('/"captionTracks":\s*(\[.*?\])/s', $html, $matches)) {
            $tracks = json_decode($matches[1], true);

            if (! empty($tracks)) {
                $trackUrl = $tracks[0]['baseUrl'] ?? null;

                if ($trackUrl) {
                    $transcriptResponse = Http::timeout(30)->get($trackUrl);

                    if ($transcriptResponse->successful()) {
                        return $this->parseTranscriptXml($transcriptResponse->body());
                    }
                }
            }
        }

        throw new \RuntimeException('No transcript available for this YouTube video.');
    }

    protected function parseTranscriptXml(string $xml): string
    {
        $doc = simplexml_load_string($xml);

        if ($doc === false) {
            throw new \RuntimeException('Failed to parse transcript XML.');
        }

        $lines = [];
        foreach ($doc->text as $node) {
            $text = trim(html_entity_decode((string) $node, ENT_QUOTES, 'UTF-8'));
            if ($text !== '') {
                $lines[] = $text;
            }
        }

        return implode(' ', $lines);
    }
}
```

### 2. ProcessVideoReference Job

Create a queued job that processes a single reference:

```php
class ProcessVideoReference implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 60;

    public function __construct(
        public VideoReference $reference,
    ) {}

    public function handle(ReferenceExtractor $extractor): void
    {
        $this->reference->update(['processing_status' => 'processing']);

        try {
            $cacheKey = 'ref_content:' . md5($this->reference->url);

            $content = Cache::remember($cacheKey, now()->addHours(24), function () use ($extractor) {
                return $extractor->extract($this->reference->url, $this->reference->type);
            });

            $this->reference->update([
                'extracted_content' => $content,
                'processing_status' => 'completed',
                'processed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $this->reference->update([
                'processing_status' => 'failed',
                'processing_error' => mb_substr($e->getMessage(), 0, 1000),
                'processed_at' => now(),
            ]);
        }
    }
}
```

Key design decisions:

- `$tries = 1`: no automatic retries. If extraction fails, we record the error and let the user decide (E006-F006).
- `$timeout = 60`: job-level timeout slightly above the 30-second HTTP timeout to allow for processing overhead.
- Cache key uses MD5 of the URL for consistent key generation.
- Cache TTL of 24 hours prevents re-fetching the same URL within a session.
- Exceptions are caught and stored in `processing_error` for display to the user.

### 3. Dispatch Jobs on Reference Creation

Update the `addReferences` method in `VideoController` to dispatch processing jobs after saving references:

```php
public function addReferences(AddReferencesRequest $request, Video $video): RedirectResponse
{
    $video->references()->delete();

    foreach ($request->validated('urls') as $url) {
        $reference = $video->references()->create([
            'url' => $url,
            'type' => VideoReference::detectType($url),
        ]);

        ProcessVideoReference::dispatch($reference);
    }

    return to_route('videos.references', $video);
}
```

### 4. Processing Status Endpoint

Add a method to `VideoController` that returns the current processing status of a video's references (for polling from the frontend):

```php
public function referenceStatuses(Video $video): JsonResponse
{
    if ($video->user_id !== auth()->id()) {
        abort(403);
    }

    return response()->json([
        'references' => $video->references->map(fn ($ref) => [
            'id' => $ref->id,
            'url' => $ref->url,
            'type' => $ref->type,
            'processing_status' => $ref->processing_status,
            'processing_error' => $ref->processing_error,
        ]),
        'all_completed' => $video->references->every(fn ($ref) => in_array($ref->processing_status, ['completed', 'failed'])),
    ]);
}
```

### 5. Route for Status Polling

```php
Route::get('videos/{video}/references/status', [VideoController::class, 'referenceStatuses'])
    ->name('videos.references.status');
```

### 6. Frontend: Processing Status Display

Update `resources/js/pages/videos/references.tsx` to show processing status after references are saved:

- After submission, display each reference with a status indicator (spinner for pending/processing, checkmark for completed, warning for failed)
- Poll the status endpoint every 3 seconds until all references are completed or failed
- Show extracted content preview for completed references
- Show error message for failed references
- Show a "Continue" button when all processing is done

### 7. Tests

Write comprehensive tests covering:

- ProcessVideoReference job processes an article URL successfully
- ProcessVideoReference job processes a YouTube URL successfully
- ProcessVideoReference job handles HTTP failures gracefully
- ProcessVideoReference job handles timeout gracefully
- Processing status transitions correctly (pending → processing → completed/failed)
- Extracted content is stored on the reference
- Error message is stored when processing fails
- Content is cached and reused for duplicate URLs
- Jobs are dispatched when references are saved
- Status endpoint returns correct data
- Status endpoint returns 403 for non-owners

## Relevant Files

Use these files to complete the task:

- `/Users/young/dev/Itervel/app/Http/Controllers/VideoController.php` -- Must update `addReferences` to dispatch jobs and add `referenceStatuses` method.
- `/Users/young/dev/Itervel/app/Models/VideoReference.php` -- VideoReference model created by E006-F004.
- `/Users/young/dev/Itervel/app/Models/Video.php` -- Video model with references() relationship.
- `/Users/young/dev/Itervel/app/Http/Requests/AddReferencesRequest.php` -- Existing form request from E006-F004.
- `/Users/young/dev/Itervel/routes/web.php` -- Must add status polling route.
- `/Users/young/dev/Itervel/resources/js/pages/videos/references.tsx` -- Must update with processing status display and polling.
- `/Users/young/dev/Itervel/resources/js/components/ui/spinner.tsx` -- Spinner component for loading states.
- `/Users/young/dev/Itervel/resources/js/types/video-reference.ts` -- VideoReference TypeScript type from E006-F004.
- `/Users/young/dev/Itervel/database/factories/VideoReferenceFactory.php` -- Factory with states from E006-F004.
- `/Users/young/dev/Itervel/database/factories/VideoFactory.php` -- Video factory from E006-F003.
- `/Users/young/dev/Itervel/database/factories/UserFactory.php` -- User factory.
- `/Users/young/dev/Itervel/tests/Feature/VideoReferenceTest.php` -- Reference tests from E006-F004.
- `/Users/young/dev/Itervel/tests/Pest.php` -- Pest configuration.
- `/Users/young/dev/Itervel/config/queue.php` -- Queue configuration.
- `/Users/young/dev/Itervel/config/cache.php` -- Cache configuration.
- `/Users/young/dev/Itervel/bootstrap/app.php` -- App configuration.

### New Files

- `app/Services/ReferenceExtractor.php` -- Service class for extracting content from article and YouTube URLs using Laravel's HTTP client with 30-second timeouts.
- `app/Jobs/ProcessVideoReference.php` -- Queued job that processes a single VideoReference, uses ReferenceExtractor, handles caching, and updates processing status.
- `tests/Feature/ReferenceProcessingTest.php` -- Pest feature tests for URL processing, job dispatching, status transitions, caching, and the status endpoint.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: ref-processing-backend
    - Role: Creates the ReferenceExtractor service, ProcessVideoReference job, updates VideoController with job dispatching and status endpoint, registers routes
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: ref-processing-frontend
    - Role: Updates the references page with processing status display, polling, and continue button
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: ref-processing-tester
    - Role: Writes comprehensive Pest feature tests covering job processing, status transitions, caching, HTTP mocking, and the status endpoint
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: ref-processing-reviewer
    - Role: Validates the complete feature against acceptance criteria, runs all tests, checks types, linting, and formatting
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Create ReferenceExtractor Service, ProcessVideoReference Job, Update Controller, and Register Routes

- **Task ID**: create-processing-backend
- **Depends On**: none
- **Assigned To**: ref-processing-backend
- **Agent Type**: coder
- **Parallel**: true
- Read the existing `VideoController` at `/Users/young/dev/Itervel/app/Http/Controllers/VideoController.php`
- Read the existing `VideoReference` model at `/Users/young/dev/Itervel/app/Models/VideoReference.php`
- Read `/Users/young/dev/Itervel/routes/web.php` to understand route structure
- Create the service: `php artisan make:class Services/ReferenceExtractor --no-interaction`
- Edit `/Users/young/dev/Itervel/app/Services/ReferenceExtractor.php`:
    - Implement `extract(string $url, string $type): string` method dispatching to type-specific extractors
    - Implement `extractArticleContent(string $url): string` using `Http::timeout(30)->get($url)` with HTML-to-text parsing
    - Implement `extractYouTubeTranscript(string $url): string` with video ID extraction and transcript parsing
    - Implement helper methods: `parseHtmlToText`, `extractYouTubeVideoId`, `parseYouTubeTranscript`, `parseTranscriptXml`
    - Use `RuntimeException` for all extraction failures with descriptive messages
    - Limit extracted content to 50,000 characters using `mb_substr`
- Create the job: `php artisan make:job ProcessVideoReference --no-interaction`
- Edit `/Users/young/dev/Itervel/app/Jobs/ProcessVideoReference.php`:
    - Implement `ShouldQueue` interface with `Queueable` trait
    - Set `$tries = 1` and `$timeout = 60`
    - Accept `VideoReference $reference` in constructor via property promotion
    - In `handle(ReferenceExtractor $extractor)`:
        - Update status to `processing`
        - Use `Cache::remember()` with key `'ref_content:' . md5($this->reference->url)` and 24-hour TTL
        - On success: update `extracted_content`, `processing_status` to `completed`, `processed_at` to `now()`
        - On failure (catch `\Throwable`): update `processing_status` to `failed`, `processing_error`, `processed_at` to `now()`
- Edit `/Users/young/dev/Itervel/app/Http/Controllers/VideoController.php`:
    - Add import: `use App\Jobs\ProcessVideoReference`
    - Update `addReferences` method to dispatch `ProcessVideoReference::dispatch($reference)` after each reference is created
    - Add `referenceStatuses(Video $video): JsonResponse` method that checks ownership and returns reference statuses as JSON with an `all_completed` flag
    - Add import: `use Illuminate\Http\JsonResponse`
- Edit `/Users/young/dev/Itervel/routes/web.php`:
    - Add inside the authenticated/verified middleware group:
        ```php
        Route::get('videos/{video}/references/status', [VideoController::class, 'referenceStatuses'])->name('videos.references.status');
        ```
    - Ensure this route is defined BEFORE the `videos/{video}/references` route to avoid route parameter conflicts
- Run `vendor/bin/pint --dirty`
- Verify routes: `php artisan route:list --name=videos.references`

### 2. Update Frontend with Processing Status Display and Polling

- **Task ID**: update-frontend-status
- **Depends On**: create-processing-backend
- **Assigned To**: ref-processing-frontend
- **Agent Type**: coder
- **Parallel**: false
- Read `/Users/young/dev/Itervel/resources/js/pages/videos/references.tsx`
- Read `/Users/young/dev/Itervel/resources/js/components/ui/spinner.tsx` for loading spinner
- Run `npm run build` to generate Wayfinder routes for the new status endpoint
- Update `/Users/young/dev/Itervel/resources/js/pages/videos/references.tsx`:
    - Add a processing status section that appears after references are saved
    - Show each reference with a status indicator:
        - `pending`: gray dot or clock icon with "Waiting..."
        - `processing`: `<Spinner />` with "Processing..."
        - `completed`: green checkmark with "Done"
        - `failed`: red warning icon with error message
    - Implement polling using `setInterval` every 3 seconds that fetches the status endpoint
    - Stop polling when `all_completed` is `true`
    - Show a "Continue" button when all processing is done (regardless of success/failure)
    - Show extracted content preview (first 200 characters) for completed references
    - Clean up the polling interval on component unmount using `useEffect` cleanup
- Run `npm run build`
- Run `npm run types`
- Run `npm run lint`

### 3. Write Comprehensive Processing Tests

- **Task ID**: write-processing-tests
- **Depends On**: create-processing-backend
- **Assigned To**: ref-processing-tester
- **Agent Type**: coder
- **Parallel**: true (can run in parallel with task 2)
- Read `/Users/young/dev/Itervel/tests/Feature/VideoReferenceTest.php` for test patterns
- Create `/Users/young/dev/Itervel/tests/Feature/ReferenceProcessingTest.php` using `php artisan make:test ReferenceProcessingTest --pest --no-interaction`
- Write the following tests using `Http::fake()` for HTTP mocking, `Queue::fake()` for job assertions, and `Cache::spy()` or `Cache::shouldReceive()` for cache assertions:

    ```php
    <?php

    use App\Jobs\ProcessVideoReference;
    use App\Models\Project;
    use App\Models\User;
    use App\Models\Video;
    use App\Models\VideoReference;
    use App\Services\ReferenceExtractor;
    use Illuminate\Support\Facades\Cache;
    use Illuminate\Support\Facades\Http;
    use Illuminate\Support\Facades\Queue;

    // --- Job Dispatching Tests ---

    test('processing jobs are dispatched when references are saved', function () {
        Queue::fake();

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $video = Video::factory()->forProjectAndUser($project, $user)->create();

        $this->actingAs($user)->post(route('videos.references.store', $video), [
            'urls' => ['https://example.com/article', 'https://youtube.com/watch?v=abc123'],
        ]);

        Queue::assertPushed(ProcessVideoReference::class, 2);
    });

    test('no jobs are dispatched for empty urls array', function () {
        Queue::fake();

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $video = Video::factory()->forProjectAndUser($project, $user)->create();

        $this->actingAs($user)->post(route('videos.references.store', $video), [
            'urls' => [],
        ]);

        Queue::assertNothingPushed();
    });

    // --- Article Processing Tests ---

    test('article URL is processed successfully', function () {
        Http::fake([
            'example.com/*' => Http::response('<html><body><article><p>Article content here.</p></article></body></html>', 200),
        ]);

        $reference = VideoReference::factory()->create([
            'url' => 'https://example.com/article',
            'type' => 'article',
            'processing_status' => 'pending',
        ]);

        $job = new ProcessVideoReference($reference);
        $job->handle(app(ReferenceExtractor::class));

        $reference->refresh();
        expect($reference->processing_status)->toBe('completed');
        expect($reference->extracted_content)->toContain('Article content here.');
        expect($reference->processed_at)->not->toBeNull();
        expect($reference->processing_error)->toBeNull();
    });

    test('article processing handles HTTP failure', function () {
        Http::fake([
            'example.com/*' => Http::response('Server Error', 500),
        ]);

        $reference = VideoReference::factory()->create([
            'url' => 'https://example.com/broken',
            'type' => 'article',
            'processing_status' => 'pending',
        ]);

        $job = new ProcessVideoReference($reference);
        $job->handle(app(ReferenceExtractor::class));

        $reference->refresh();
        expect($reference->processing_status)->toBe('failed');
        expect($reference->processing_error)->toContain('500');
        expect($reference->processed_at)->not->toBeNull();
    });

    test('article processing handles connection timeout', function () {
        Http::fake([
            'example.com/*' => Http::response('', 200)->throw(new \Illuminate\Http\Client\ConnectionException('Connection timed out')),
        ]);

        $reference = VideoReference::factory()->create([
            'url' => 'https://example.com/slow',
            'type' => 'article',
            'processing_status' => 'pending',
        ]);

        $job = new ProcessVideoReference($reference);
        $job->handle(app(ReferenceExtractor::class));

        $reference->refresh();
        expect($reference->processing_status)->toBe('failed');
        expect($reference->processing_error)->not->toBeNull();
    });

    // --- Processing Status Lifecycle ---

    test('processing status transitions from pending to processing to completed', function () {
        Http::fake([
            '*' => Http::response('<html><body><p>Content</p></body></html>', 200),
        ]);

        $reference = VideoReference::factory()->create([
            'processing_status' => 'pending',
        ]);

        expect($reference->processing_status)->toBe('pending');

        $job = new ProcessVideoReference($reference);
        $job->handle(app(ReferenceExtractor::class));

        $reference->refresh();
        expect($reference->processing_status)->toBe('completed');
    });

    test('processing status transitions from pending to processing to failed on error', function () {
        Http::fake([
            '*' => Http::response('', 500),
        ]);

        $reference = VideoReference::factory()->create([
            'processing_status' => 'pending',
        ]);

        $job = new ProcessVideoReference($reference);
        $job->handle(app(ReferenceExtractor::class));

        $reference->refresh();
        expect($reference->processing_status)->toBe('failed');
    });

    // --- Caching Tests ---

    test('extracted content is cached for duplicate URLs', function () {
        Http::fake([
            'example.com/*' => Http::response('<html><body><p>Cached content</p></body></html>', 200),
        ]);

        $reference1 = VideoReference::factory()->create([
            'url' => 'https://example.com/same-article',
            'type' => 'article',
        ]);

        $job1 = new ProcessVideoReference($reference1);
        $job1->handle(app(ReferenceExtractor::class));

        $reference2 = VideoReference::factory()->create([
            'url' => 'https://example.com/same-article',
            'type' => 'article',
        ]);

        $job2 = new ProcessVideoReference($reference2);
        $job2->handle(app(ReferenceExtractor::class));

        // Both references should have the same content
        expect($reference1->refresh()->extracted_content)->toBe($reference2->refresh()->extracted_content);

        // HTTP should only have been called once (second was served from cache)
        Http::assertSentCount(1);
    });

    // --- Status Endpoint Tests ---

    test('status endpoint returns reference statuses for video owner', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $video = Video::factory()->forProjectAndUser($project, $user)->create();
        VideoReference::factory()->for($video)->count(2)->create(['processing_status' => 'completed']);

        $response = $this->actingAs($user)->getJson(route('videos.references.status', $video));

        $response->assertOk();
        $response->assertJsonCount(2, 'references');
        $response->assertJson(['all_completed' => true]);
    });

    test('status endpoint returns all_completed false when processing is pending', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $video = Video::factory()->forProjectAndUser($project, $user)->create();
        VideoReference::factory()->for($video)->create(['processing_status' => 'completed']);
        VideoReference::factory()->for($video)->create(['processing_status' => 'pending']);

        $response = $this->actingAs($user)->getJson(route('videos.references.status', $video));

        $response->assertJson(['all_completed' => false]);
    });

    test('status endpoint returns 403 for non-owner', function () {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $video = Video::factory()->forProjectAndUser($project, $owner)->create();

        $response = $this->actingAs($otherUser)->getJson(route('videos.references.status', $video));

        $response->assertForbidden();
    });

    test('status endpoint redirects guests to login', function () {
        $video = Video::factory()->create();

        $response = $this->getJson(route('videos.references.status', $video));

        $response->assertUnauthorized();
    });

    // --- Content Extraction Tests ---

    test('html content is stripped of scripts and styles', function () {
        $extractor = app(ReferenceExtractor::class);

        Http::fake([
            '*' => Http::response('<html><head><style>body{}</style></head><body><script>alert("xss")</script><p>Clean content</p></body></html>', 200),
        ]);

        $content = $extractor->extract('https://example.com', 'article');
        expect($content)->toContain('Clean content');
        expect($content)->not->toContain('alert');
        expect($content)->not->toContain('body{}');
    });

    test('extracted content is limited to 50000 characters', function () {
        $longContent = str_repeat('a', 60000);

        Http::fake([
            '*' => Http::response("<html><body><p>{$longContent}</p></body></html>", 200),
        ]);

        $extractor = app(ReferenceExtractor::class);
        $content = $extractor->extract('https://example.com', 'article');

        expect(mb_strlen($content))->toBeLessThanOrEqual(50000);
    });
    ```

- Run the tests: `php artisan test tests/Feature/ReferenceProcessingTest.php --compact`
- Fix any failing tests until all pass
- Run `vendor/bin/pint --dirty`

### 4. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-processing-backend, update-frontend-status, write-processing-tests
- **Assigned To**: ref-processing-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed below
- Verify processing tests pass: `php artisan test tests/Feature/ReferenceProcessingTest.php --compact`
- Verify reference tests still pass: `php artisan test tests/Feature/VideoReferenceTest.php --compact`
- Run the full test suite: `php artisan test --compact`
- Run TypeScript type checking: `npm run types`
- Run ESLint: `npm run lint`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Verify all files exist and are correct per the acceptance criteria
- Confirm all acceptance criteria are met

## Acceptance Criteria

- Processing jobs are automatically dispatched when reference URLs are saved
- Article URLs are fetched and their text content is extracted
- YouTube URLs have their transcript extracted when available
- Each URL processing has a 30-second HTTP timeout
- Processing status transitions correctly: pending → processing → completed (on success) or failed (on error)
- Extracted content is stored in the `extracted_content` column of the `video_references` table
- Failed processing stores an error message in the `processing_error` column
- The `processed_at` timestamp is set when processing completes (success or failure)
- Extracted content is cached for 24 hours to avoid re-fetching the same URL
- Duplicate URLs served from cache do not make additional HTTP requests
- The status endpoint returns correct processing statuses for all references
- The status endpoint returns an `all_completed` flag
- The status endpoint returns 403 for non-owners
- The frontend displays processing status with appropriate icons (spinner, checkmark, warning)
- The frontend polls for status updates every 3 seconds
- Polling stops when all references are completed or failed
- HTML content is stripped of scripts and styles during extraction
- Extracted content is limited to 50,000 characters
- The `ProcessVideoReference` job has `$tries = 1` (no automatic retries)
- All processing tests pass
- All existing tests continue to pass (no regressions)
- PHP code passes Pint formatting
- TypeScript passes type checking
- ESLint reports no errors

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run processing tests
php artisan test tests/Feature/ReferenceProcessingTest.php --compact

# Run reference tests (regression check)
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

- The `ReferenceExtractor` service uses a pragmatic HTML parsing approach (regex + `strip_tags`) rather than a full DOM parser. This is sufficient for extracting readable text from most web pages. For production-quality extraction, a library like `readability-php` could be considered, but that is beyond the scope of this feature.
- YouTube transcript extraction is inherently fragile because it relies on parsing YouTube's page source to find caption track URLs. YouTube may change their page structure at any time. The implementation should handle this gracefully by falling back to the "No transcript available" error. A more robust approach would use the YouTube Data API with proper API keys, but that requires additional configuration beyond this feature's scope.
- The 30-second timeout is applied at the HTTP client level (`Http::timeout(30)`), not at the job level. The job timeout is set to 60 seconds to account for processing overhead after the HTTP request completes.
- The cache uses Laravel's default cache driver (configured in `config/cache.php`). In the Docker development environment, this is Redis. In tests, the array driver is used. The cache key is based on the URL's MD5 hash to ensure consistency.
- The `$tries = 1` setting means the job will not be automatically retried if it fails. This is intentional because URL extraction failures are typically permanent (page doesn't exist, no transcript available) rather than transient. The user can re-submit references to trigger a new processing attempt.
- The status polling endpoint returns JSON (not an Inertia response) because it is called via `fetch()` from the frontend, not via Inertia navigation. This is a standard AJAX pattern for real-time status updates.
- All `php artisan` commands should be run inside the Docker container. Use `make shell` to enter the container, or prefix with `docker compose exec app`.

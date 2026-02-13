# Feature: Graceful URL Processing Failure

**Epic**: E006-brand-guide-and-video-input.md
**Feature**: E006-F006
**Epic depends on**: E004-project-management.md
**Feature depends on**: E006-F005

## Task Description

Graceful URL Processing Failure handles failures when extracting content from user-provided reference URLs without blocking the video creation workflow. When a reference URL cannot be processed (timeout, inaccessible, unsupported format), the user sees a clear warning message for that specific URL but can continue creating their video with the remaining successfully-processed references or with no references at all.

**What it does**: Handles failures when extracting content from user-provided reference URLs without blocking the video creation workflow.

**Expected outcome**: If a reference URL cannot be processed (timeout, inaccessible, unsupported format), the user sees a warning message for that specific URL but can continue creating their video with the remaining references or without any references.

This feature builds on E006-F005 (Reference URL Processing), which creates the `ProcessVideoReference` job, `ReferenceExtractor` service, processing status lifecycle (pending → processing → completed/failed), the status polling endpoint, and the frontend processing status display. E006-F005 already stores errors in `processing_error` and sets `processing_status` to `failed` when extraction fails. This feature focuses on the **user experience** of those failures: presenting clear, user-friendly error messages, allowing retry of individual failed references, providing a "skip failed and continue" workflow, and ensuring the video creation pipeline never blocks on failed references.

**Dependency on E006-F005 (Reference URL Processing)**: E006-F005 creates the `ProcessVideoReference` job with error handling that sets `processing_status = 'failed'` and stores the error in `processing_error`. It also creates the status polling endpoint (`referenceStatuses`) and the frontend processing display. This feature enhances the frontend to present failures gracefully, adds a retry mechanism, and adds the "continue" flow that skips failed references.

## Objective

Implement graceful URL processing failure handling by enhancing the frontend references page to display user-friendly error messages for each failure type (timeout, HTTP error, no transcript, inaccessible), adding a retry button for individual failed references, creating a backend retry endpoint, ensuring the "Continue" button is available regardless of failures, adding user-facing error message mapping, and writing comprehensive Pest feature tests and frontend validation.

## Solution Approach

### 1. User-Friendly Error Message Mapping

The `processing_error` field from E006-F005 contains technical error messages (e.g., "Failed to fetch URL: HTTP 500", "Connection timed out", "No transcript available for this YouTube video."). Map these to user-friendly messages on the frontend:

```tsx
function getErrorMessage(processingError: string | null): string {
    if (!processingError) return 'An unknown error occurred.';

    if (
        processingError.includes('timed out') ||
        processingError.includes('timeout')
    ) {
        return 'This URL took too long to respond. The website may be slow or temporarily unavailable.';
    }
    if (processingError.includes('HTTP 4')) {
        return 'This URL could not be accessed. It may require login or no longer exist.';
    }
    if (processingError.includes('HTTP 5')) {
        return 'The website returned an error. It may be temporarily down.';
    }
    if (processingError.includes('No transcript available')) {
        return 'No transcript is available for this YouTube video. Try a video with captions enabled.';
    }
    if (processingError.includes('Could not extract YouTube video ID')) {
        return 'This YouTube URL format is not recognized. Try using a standard YouTube watch URL.';
    }
    if (processingError.includes('Failed to parse transcript')) {
        return 'The transcript for this video could not be read. Try a different video.';
    }

    return 'This URL could not be processed. Try a different URL or continue without it.';
}
```

### 2. Retry Endpoint

Add a retry endpoint to `VideoController` that re-dispatches the `ProcessVideoReference` job for a specific failed reference:

```php
public function retryReference(Video $video, VideoReference $reference): JsonResponse
{
    if ($video->user_id !== auth()->id()) {
        abort(403);
    }

    if ($reference->video_id !== $video->id) {
        abort(404);
    }

    if ($reference->processing_status !== 'failed') {
        return response()->json(['message' => 'Only failed references can be retried.'], 422);
    }

    // Clear the URL from cache so it's re-fetched
    Cache::forget('ref_content:' . md5($reference->url));

    $reference->update([
        'processing_status' => 'pending',
        'processing_error' => null,
        'extracted_content' => null,
        'processed_at' => null,
    ]);

    ProcessVideoReference::dispatch($reference);

    return response()->json(['message' => 'Processing retry started.']);
}
```

### 3. Retry Route

```php
Route::post('videos/{video}/references/{reference}/retry', [VideoController::class, 'retryReference'])
    ->name('videos.references.retry');
```

### 4. Enhanced Frontend Failure Display

Update `resources/js/pages/videos/references.tsx` to enhance the failure display created in E006-F005:

- For each **failed** reference, show:
    - A yellow/amber warning icon (not red — it's not a critical error)
    - The user-friendly error message from the mapping function
    - A "Retry" button that calls the retry endpoint and resumes polling
    - The original URL for reference

- For the overall status section:
    - A summary message when all processing is done: "X of Y references processed successfully"
    - If some failed: "Some references couldn't be processed. You can retry them or continue without them."
    - The "Continue" button is always shown once processing is complete (all are either completed or failed), regardless of how many failed
    - If ALL references failed: "None of the references could be processed. You can retry them or continue without any references."

### 5. Continue Flow

The "Continue" button navigates to the next step in the video creation flow. When clicked:

- Only completed references are used for subsequent content generation
- Failed references are ignored (they remain in the database with `processing_status = 'failed'` for record-keeping)
- If all references failed, the video continues without any reference material
- The next step page should be able to handle having zero completed references gracefully

### 6. Tests

Write tests covering:

- Retry endpoint resets a failed reference and dispatches a new job
- Retry endpoint clears the cache for the URL
- Retry endpoint returns 422 for non-failed references
- Retry endpoint returns 403 for non-owners
- Retry endpoint returns 404 for references not belonging to the video
- The "Continue" action works when all references are completed
- The "Continue" action works when some references failed
- The "Continue" action works when all references failed
- The "Continue" action works when there are no references at all

## Relevant Files

Use these files to complete the task:

- `/Users/young/dev/Itervel/app/Http/Controllers/VideoController.php` -- Must add the `retryReference` method. Already has `referenceStatuses` from E006-F005.
- `/Users/young/dev/Itervel/app/Models/VideoReference.php` -- VideoReference model with `processing_status`, `processing_error`, `extracted_content`, and `processed_at` columns.
- `/Users/young/dev/Itervel/app/Models/Video.php` -- Video model with `references()` relationship.
- `/Users/young/dev/Itervel/app/Jobs/ProcessVideoReference.php` -- The queued job from E006-F005 that processes a single reference.
- `/Users/young/dev/Itervel/routes/web.php` -- Must add the retry route.
- `/Users/young/dev/Itervel/resources/js/pages/videos/references.tsx` -- Must enhance with user-friendly error messages, retry button, summary message, and continue flow.
- `/Users/young/dev/Itervel/resources/js/types/video-reference.ts` -- VideoReference TypeScript type (should already have `processing_error` field from E006-F004).
- `/Users/young/dev/Itervel/database/factories/VideoReferenceFactory.php` -- Factory with `failed()` state from E006-F004.
- `/Users/young/dev/Itervel/database/factories/VideoFactory.php` -- Video factory from E006-F003.
- `/Users/young/dev/Itervel/database/factories/UserFactory.php` -- User factory.
- `/Users/young/dev/Itervel/tests/Feature/ReferenceProcessingTest.php` -- Processing tests from E006-F005 (reference for patterns).
- `/Users/young/dev/Itervel/tests/Pest.php` -- Pest configuration.
- `/Users/young/dev/Itervel/config/cache.php` -- Cache configuration.
- `/Users/young/dev/Itervel/bootstrap/app.php` -- App configuration.

### New Files

- `tests/Feature/ReferenceRetryTest.php` -- Pest feature tests for the retry endpoint, cache clearing, status validation, authorization, and the continue flow with various failure scenarios.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: retry-backend
    - Role: Adds the retry endpoint to VideoController, registers the retry route, handles cache clearing on retry
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: retry-frontend
    - Role: Enhances the references page with user-friendly error messages, retry buttons, summary messaging, and the continue flow
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: retry-tester
    - Role: Writes Pest feature tests for the retry endpoint, authorization, status validation, cache clearing, and the continue flow scenarios
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: retry-reviewer
    - Role: Validates the complete feature against acceptance criteria, runs all tests, checks types, linting, and formatting
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Add Retry Endpoint and Route

- **Task ID**: add-retry-endpoint
- **Depends On**: none
- **Assigned To**: retry-backend
- **Agent Type**: coder
- **Parallel**: true
- Read the existing `VideoController` at `/Users/young/dev/Itervel/app/Http/Controllers/VideoController.php`
- Read the existing `VideoReference` model at `/Users/young/dev/Itervel/app/Models/VideoReference.php`
- Read `/Users/young/dev/Itervel/routes/web.php` to understand route structure
- Edit `/Users/young/dev/Itervel/app/Http/Controllers/VideoController.php`:
    - Add `use Illuminate\Support\Facades\Cache` import
    - Add `retryReference(Video $video, VideoReference $reference): JsonResponse` method:
        - Check `$video->user_id !== auth()->id()` and abort 403
        - Check `$reference->video_id !== $video->id` and abort 404
        - Check `$reference->processing_status !== 'failed'` and return JSON 422 with message
        - Clear cache: `Cache::forget('ref_content:' . md5($reference->url))`
        - Reset the reference: update `processing_status` to `pending`, `processing_error` to `null`, `extracted_content` to `null`, `processed_at` to `null`
        - Dispatch: `ProcessVideoReference::dispatch($reference)`
        - Return JSON 200 with success message
- Edit `/Users/young/dev/Itervel/routes/web.php`:
    - Add inside the authenticated/verified middleware group:
        ```php
        Route::post('videos/{video}/references/{reference}/retry', [VideoController::class, 'retryReference'])->name('videos.references.retry');
        ```
- Run `vendor/bin/pint --dirty`
- Verify routes: `php artisan route:list --name=videos.references`

### 2. Enhance Frontend with Graceful Failure UX

- **Task ID**: enhance-failure-frontend
- **Depends On**: add-retry-endpoint
- **Assigned To**: retry-frontend
- **Agent Type**: coder
- **Parallel**: false
- Read `/Users/young/dev/Itervel/resources/js/pages/videos/references.tsx`
- Read sibling page components for UI patterns and Tailwind classes used in the project
- Run `npm run build` to generate Wayfinder routes for the new retry endpoint
- Edit `/Users/young/dev/Itervel/resources/js/pages/videos/references.tsx`:
    - Add a `getErrorMessage(processingError: string | null): string` function that maps technical errors to user-friendly messages (see Solution Approach section 1)
    - For each failed reference in the status display:
        - Show an amber/yellow warning icon (e.g., `text-amber-500` triangle exclamation)
        - Show the user-friendly error message from `getErrorMessage()`
        - Add a "Retry" button that sends a POST to the retry endpoint via `fetch()` or Wayfinder action
        - When retry is clicked: show a spinner on the button, call the retry endpoint, then resume polling
    - Add a summary section when all processing is done:
        - Count completed and failed references
        - Show "X of Y references processed successfully"
        - If some failed: "Some references couldn't be processed. You can retry them or continue without them."
        - If all failed: "None of the references could be processed. You can retry them or continue without any references."
    - Ensure the "Continue" button is visible once all references have resolved (completed or failed), regardless of failure count
    - The "Continue" button should navigate to the next step (this can be a placeholder route until the next epic implements the actual next step)
- Run `npm run build`
- Run `npm run types`
- Run `npm run lint`

### 3. Write Retry and Failure Flow Tests

- **Task ID**: write-retry-tests
- **Depends On**: add-retry-endpoint
- **Assigned To**: retry-tester
- **Agent Type**: coder
- **Parallel**: true (can run in parallel with task 2)
- Read `/Users/young/dev/Itervel/tests/Feature/ReferenceProcessingTest.php` for test patterns
- Read `/Users/young/dev/Itervel/database/factories/VideoReferenceFactory.php` for available factory states
- Create `/Users/young/dev/Itervel/tests/Feature/ReferenceRetryTest.php` using `php artisan make:test ReferenceRetryTest --pest --no-interaction`
- Write the following tests:

    ```php
    <?php

    use App\Jobs\ProcessVideoReference;
    use App\Models\Project;
    use App\Models\User;
    use App\Models\Video;
    use App\Models\VideoReference;
    use Illuminate\Support\Facades\Cache;
    use Illuminate\Support\Facades\Queue;

    // --- Retry Endpoint Tests ---

    test('failed reference can be retried', function () {
        Queue::fake();

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $video = Video::factory()->forProjectAndUser($project, $user)->create();
        $reference = VideoReference::factory()->for($video)->failed()->create();

        $response = $this->actingAs($user)->postJson(
            route('videos.references.retry', [$video, $reference])
        );

        $response->assertOk();
        $reference->refresh();
        expect($reference->processing_status)->toBe('pending');
        expect($reference->processing_error)->toBeNull();
        expect($reference->extracted_content)->toBeNull();
        expect($reference->processed_at)->toBeNull();
        Queue::assertPushed(ProcessVideoReference::class, 1);
    });

    test('retry clears cache for the URL', function () {
        Queue::fake();
        Cache::spy();

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $video = Video::factory()->forProjectAndUser($project, $user)->create();
        $reference = VideoReference::factory()->for($video)->failed()->create([
            'url' => 'https://example.com/test-article',
        ]);

        $this->actingAs($user)->postJson(
            route('videos.references.retry', [$video, $reference])
        );

        Cache::shouldHaveReceived('forget')
            ->with('ref_content:' . md5('https://example.com/test-article'))
            ->once();
    });

    test('retry returns 422 for non-failed references', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $video = Video::factory()->forProjectAndUser($project, $user)->create();
        $reference = VideoReference::factory()->for($video)->completed()->create();

        $response = $this->actingAs($user)->postJson(
            route('videos.references.retry', [$video, $reference])
        );

        $response->assertUnprocessable();
    });

    test('retry returns 422 for pending references', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $video = Video::factory()->forProjectAndUser($project, $user)->create();
        $reference = VideoReference::factory()->for($video)->create([
            'processing_status' => 'pending',
        ]);

        $response = $this->actingAs($user)->postJson(
            route('videos.references.retry', [$video, $reference])
        );

        $response->assertUnprocessable();
    });

    test('retry returns 403 for non-owners', function () {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $video = Video::factory()->forProjectAndUser($project, $owner)->create();
        $reference = VideoReference::factory()->for($video)->failed()->create();

        $response = $this->actingAs($otherUser)->postJson(
            route('videos.references.retry', [$video, $reference])
        );

        $response->assertForbidden();
    });

    test('retry returns 404 for references not belonging to video', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $video1 = Video::factory()->forProjectAndUser($project, $user)->create();
        $video2 = Video::factory()->forProjectAndUser($project, $user)->create();
        $reference = VideoReference::factory()->for($video2)->failed()->create();

        $response = $this->actingAs($user)->postJson(
            route('videos.references.retry', [$video1, $reference])
        );

        $response->assertNotFound();
    });

    test('retry redirects guests to login', function () {
        $video = Video::factory()->create();
        $reference = VideoReference::factory()->for($video)->failed()->create();

        $response = $this->postJson(
            route('videos.references.retry', [$video, $reference])
        );

        $response->assertUnauthorized();
    });

    // --- Continue Flow Tests ---

    test('status endpoint shows all_completed true when all references completed', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $video = Video::factory()->forProjectAndUser($project, $user)->create();
        VideoReference::factory()->for($video)->completed()->count(3)->create();

        $response = $this->actingAs($user)->getJson(
            route('videos.references.status', $video)
        );

        $response->assertOk();
        $response->assertJson(['all_completed' => true]);
    });

    test('status endpoint shows all_completed true when mix of completed and failed', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $video = Video::factory()->forProjectAndUser($project, $user)->create();
        VideoReference::factory()->for($video)->completed()->count(2)->create();
        VideoReference::factory()->for($video)->failed()->create();

        $response = $this->actingAs($user)->getJson(
            route('videos.references.status', $video)
        );

        $response->assertOk();
        $response->assertJson(['all_completed' => true]);
    });

    test('status endpoint shows all_completed true when all references failed', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $video = Video::factory()->forProjectAndUser($project, $user)->create();
        VideoReference::factory()->for($video)->failed()->count(3)->create();

        $response = $this->actingAs($user)->getJson(
            route('videos.references.status', $video)
        );

        $response->assertOk();
        $response->assertJson(['all_completed' => true]);
    });

    test('status endpoint shows all_completed false when retry resets a reference to pending', function () {
        Queue::fake();

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $video = Video::factory()->forProjectAndUser($project, $user)->create();
        VideoReference::factory()->for($video)->completed()->create();
        $failedRef = VideoReference::factory()->for($video)->failed()->create();

        // Retry the failed reference
        $this->actingAs($user)->postJson(
            route('videos.references.retry', [$video, $failedRef])
        );

        // Now status should show all_completed false because one is pending again
        $response = $this->actingAs($user)->getJson(
            route('videos.references.status', $video)
        );

        $response->assertJson(['all_completed' => false]);
    });
    ```

- Run the tests: `php artisan test tests/Feature/ReferenceRetryTest.php --compact`
- Fix any failing tests until all pass
- Run `vendor/bin/pint --dirty`

### 4. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: add-retry-endpoint, enhance-failure-frontend, write-retry-tests
- **Assigned To**: retry-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed below
- Verify retry tests pass: `php artisan test tests/Feature/ReferenceRetryTest.php --compact`
- Verify processing tests still pass: `php artisan test tests/Feature/ReferenceProcessingTest.php --compact`
- Verify reference tests still pass: `php artisan test tests/Feature/VideoReferenceTest.php --compact`
- Run the full test suite: `php artisan test --compact`
- Run TypeScript type checking: `npm run types`
- Run ESLint: `npm run lint`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Verify retry route exists: `php artisan route:list --name=videos.references.retry`
- Verify all files exist and are correct per the acceptance criteria
- Confirm all acceptance criteria are met

## Acceptance Criteria

- Failed references display user-friendly error messages instead of raw technical errors
- Error messages are mapped for: timeout, HTTP 4xx, HTTP 5xx, no transcript available, unrecognized YouTube URL, failed transcript parsing
- An unknown/unmapped error shows a generic "could not be processed" message
- Each failed reference has a "Retry" button
- Clicking "Retry" clears the cached content for that URL, resets the reference to pending, and dispatches a new processing job
- After retry, polling resumes to track the new processing attempt
- The retry endpoint returns 422 if the reference is not in a failed state
- The retry endpoint returns 403 for non-owners
- The retry endpoint returns 404 for references not belonging to the specified video
- A summary message shows how many references succeeded vs. failed when processing completes
- The "Continue" button is available once all references have resolved, regardless of how many failed
- The "Continue" button works when all references are completed (happy path)
- The "Continue" button works when some references failed (partial success)
- The "Continue" button works when all references failed (complete failure)
- The video creation pipeline is never blocked by failed reference processing
- All retry tests pass
- All existing processing tests continue to pass (no regressions)
- PHP code passes Pint formatting
- TypeScript passes type checking
- ESLint reports no errors

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run retry tests
php artisan test tests/Feature/ReferenceRetryTest.php --compact

# Run processing tests (regression check)
php artisan test tests/Feature/ReferenceProcessingTest.php --compact

# Run reference tests (regression check)
php artisan test tests/Feature/VideoReferenceTest.php --compact

# Run video creation tests (regression check)
php artisan test tests/Feature/VideoCreationTest.php --compact

# Run full test suite
php artisan test --compact

# Verify retry route
php artisan route:list --name=videos.references.retry

# TypeScript type checking
npm run types

# ESLint linting
npm run lint

# PHP code formatting
vendor/bin/pint --dirty
```

## Notes

- Error message mapping is done on the frontend rather than the backend to keep the backend error messages technical and useful for debugging, while presenting user-friendly text in the UI. This also avoids changing the E006-F005 job error handling.
- The retry mechanism clears the cache before re-dispatching to ensure fresh content is fetched. This handles the case where a URL was temporarily down but is now accessible.
- The retry resets all processing fields (`processing_status`, `processing_error`, `extracted_content`, `processed_at`) to give a clean slate for the new attempt.
- Only `failed` references can be retried. `pending` and `processing` references cannot be retried because they are still in progress. `completed` references don't need retry.
- The "Continue" flow is designed to be forward-compatible: the next step in the video creation pipeline (likely in a future epic) should check for completed references and use only those. Failed references remain in the database for record-keeping but are excluded from content generation.
- Warning icons use amber/yellow (`text-amber-500`) rather than red because a failed reference is a non-blocking warning, not a critical error. The user can always continue without references.
- All `php artisan` commands should be run inside the Docker container. Use `make shell` to enter the container, or prefix with `docker compose exec app`.

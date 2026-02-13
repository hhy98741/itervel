# Feature: Script Critique and Refinement

**Epic**: E011-outline-and-script-generation.md
**Feature**: E011-F004
**Epic depends on**: E009-ai-configuration-and-title-generation.md
**Feature depends on**: E011-F003

## Task Description

The AI automatically critiques the script from an audience perspective and refines it through multiple rounds. For each critique round, the AI identifies weak points (where viewers would click away), rates issues by impact, and suggests specific fixes. The AI then revises the script based on the critique. The user sees progress updates ("Refining script... Round 2 of 3"). This repeats for the configured number of rounds (0-5), maxing out at 5.

**What it does**: The AI automatically critiques the script from an audience perspective and refines it through multiple rounds.

**Expected outcome**: For each critique round, the AI identifies weak points (where viewers would click away), rates issues by impact, and suggests specific fixes. The AI then revises the script based on the critique. The user sees progress updates ("Refining script... Round 2 of 3"). This repeats for the configured number of rounds (0-5), maxing out at 5.

This feature implements an iterative refinement loop: critique → revise → critique → revise, for the configured number of rounds. The number of rounds is determined by `video.getScriptIterations()` from the AI config (E009-F001/F002). If iterations is 0, critique is skipped entirely and the video proceeds directly.

The critique uses the 'critique' AI model (`getModelForStep('critique')`) while the revision reuses the 'script' model. Each round's critique and revised script are stored for transparency.

## Objective

Create a script critique and refinement pipeline that iteratively improves the generated script through AI-powered critique rounds. Each round: the AI critiques the current script identifying weak points, rates them by viewer impact, suggests fixes, and then revises the script. Progress is shown to the user in real-time ("Round 2 of 3"). Store all critique rounds for transparency. Support 0-5 rounds as configured.

## Solution Approach

### Critique Data Structure

Each critique round produces structured feedback:

```json
{
    "round": 1,
    "total_rounds": 3,
    "critique": {
        "weak_points": [
            {
                "location": "Hook - second sentence",
                "issue": "The opening question is too generic and won't differentiate this video",
                "impact": "high",
                "suggestion": "Replace with a specific, surprising statistic or bold claim",
                "viewer_action": "Would scroll past within 3 seconds"
            },
            {
                "location": "Section 2 - transition to step 3",
                "issue": "Abrupt topic change without a bridge sentence",
                "impact": "medium",
                "suggestion": "Add a connecting phrase that previews the next point",
                "viewer_action": "Would lose focus and check comments"
            }
        ],
        "strengths": [
            "Strong storytelling in section 1",
            "Good use of rhetorical questions"
        ],
        "overall_score": 7
    },
    "revised_script": {
        /* same structure as GeneratedScript */
    }
}
```

### ScriptCritiqueService

Create `app/Services/ScriptCritiqueService.php`:

```php
class ScriptCritiqueService
{
    public function __construct(private AiClient $aiClient) {}

    public function critique(Video $video, array $currentScript): array
    {
        $modelKey = $video->getModelForStep('critique');
        $systemPrompt = $this->buildCritiqueSystemPrompt();
        $userPrompt = $this->buildCritiqueUserPrompt($video, $currentScript);
        $response = $this->aiClient->generate($modelKey, $systemPrompt, $userPrompt, 4096);
        return $this->parseCritiqueResponse($response);
    }

    public function revise(Video $video, array $currentScript, array $critique): array
    {
        $modelKey = $video->getModelForStep('script');
        $systemPrompt = $this->buildRevisionSystemPrompt();
        $userPrompt = $this->buildRevisionUserPrompt($currentScript, $critique);
        $response = $this->aiClient->generate($modelKey, $systemPrompt, $userPrompt, 8192);
        return $this->parseScriptResponse($response);
    }
}
```

The critique system prompt instructs the AI to:

- Act as a YouTube audience member who easily loses interest
- Identify 3-7 weak points in the script where a viewer would click away, skip ahead, or lose attention
- Rate each weak point by impact: `high` (would cause viewer to leave), `medium` (would lose engagement), `low` (minor quality issue)
- For each weak point, describe the issue, the likely viewer action, and suggest a specific fix
- Identify 2-3 strengths to keep
- Give an overall quality score (1-10)
- Respond in valid JSON

The revision system prompt instructs the AI to:

- Revise the script addressing all critique points
- Maintain the same section structure and delivery cues
- Keep the total word count in the 2,000-2,500 range
- Preserve the strengths identified in the critique
- Respond in the same JSON format as the original script

### RefineScriptJob

The job runs the full iteration loop:

```php
class RefineScriptJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public Video $video) {}

    public function handle(ScriptCritiqueService $service): void
    {
        $iterations = $this->video->getScriptIterations();

        if ($iterations === 0) {
            $this->video->update(['status' => 'script_refined']);
            return;
        }

        $this->video->update(['status' => 'refining_script']);

        $currentScript = $this->video->generated_script;
        $critiqueRounds = [];

        try {
            for ($round = 1; $round <= $iterations; $round++) {
                // Update progress
                $this->video->update([
                    'refinement_progress' => [
                        'current_round' => $round,
                        'total_rounds' => $iterations,
                    ],
                ]);

                // Critique
                $critique = $service->critique($this->video, $currentScript);

                // Revise
                $revisedScript = $service->revise($this->video, $currentScript, $critique);

                // Store round
                $critiqueRounds[] = [
                    'round' => $round,
                    'total_rounds' => $iterations,
                    'critique' => $critique,
                    'revised_script' => $revisedScript,
                ];

                $currentScript = $revisedScript;
            }

            $this->video->update([
                'generated_script' => $currentScript,
                'critique_rounds' => $critiqueRounds,
                'status' => 'script_refined',
                'refinement_progress' => null,
            ]);
        } catch (\Throwable $e) {
            $this->video->update([
                'status' => 'script_refinement_failed',
                'generation_error' => $e->getMessage(),
                'refinement_progress' => null,
            ]);
            throw $e;
        }
    }
}
```

### Database Changes

```php
Schema::table('videos', function (Blueprint $table) {
    $table->json('critique_rounds')->nullable()->after('generated_script');
    $table->json('refinement_progress')->nullable()->after('critique_rounds');
});
```

- `critique_rounds`: Array of all critique/revision round data for transparency
- `refinement_progress`: Ephemeral progress tracking (`{ current_round: 2, total_rounds: 3 }`) cleared after completion

### CritiqueController

```php
class CritiqueController extends Controller
{
    public function index(Video $video): Response
    {
        abort_unless($video->user_id === auth()->id(), 403);

        return Inertia::render('videos/critique', [
            'video' => $video->only([
                'id', 'status', 'generated_script', 'critique_rounds',
                'refinement_progress', 'generation_error',
            ]),
        ]);
    }

    public function refine(Video $video): RedirectResponse
    {
        abort_unless($video->user_id === auth()->id(), 403);

        RefineScriptJob::dispatch($video);

        return to_route('videos.critique', $video);
    }
}
```

### Routes

```php
Route::get('videos/{video}/critique', [CritiqueController::class, 'index'])->name('videos.critique');
Route::post('videos/{video}/refine', [CritiqueController::class, 'refine'])->name('videos.critique.refine');
```

### Frontend: Critique Page

Create `resources/js/pages/videos/critique.tsx` with states:

1. **Refining** (`status === 'refining_script'`): Shows a progress indicator "Refining script... Round {current} of {total}" with a progress bar. Polls every 5 seconds to check for progress and completion. Each completed round shows a collapsible summary of what was changed.

2. **Error** (`status === 'script_refinement_failed'`): Destructive Alert with error message and "Retry" button.

3. **Complete** (`status === 'script_refined'`): Shows the refinement results:
    - A summary: "Script refined through {N} rounds of critique"
    - Collapsible accordion for each critique round showing:
        - Round number and overall score
        - Weak points with impact badges (high=red, medium=yellow, low=blue)
        - Strengths list
        - "View revised script" toggle showing the per-round revised text
    - Final refined script preview (first 200 words)
    - "Continue" button to proceed to script review (E011-F005)
    - "Re-refine" button to run critique again

4. **Skip** (`iterations === 0`): When iterations are 0, the page shows "Script critique skipped (0 iterations configured)" with a "Continue" button to proceed.

### Zero-Iteration Handling

If `video.getScriptIterations() === 0`, the job immediately sets status to `script_refined` without any critique. The frontend detects this (no `critique_rounds`) and shows a skip message.

### Auto-Start Behavior

When the user arrives at the critique page and the video status is `script_generated` (script was just generated), the frontend can auto-trigger refinement by POSTing to the refine endpoint. Alternatively, the user can click a "Start Refinement" button. Use a `useEffect` that checks if the status is `script_generated` and auto-submits.

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Services/AiClient.php` -- AI client for API calls.
- `/Users/young/Nextcloud/dev/Itervel/app/Services/ScriptGenerationService.php` -- Script service from E011-F003. The critique revision reuses the script model and prompt structure.
- `/Users/young/Nextcloud/dev/Itervel/app/Jobs/GenerateScriptJob.php` -- Script job from E011-F003. Template for RefineScriptJob.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/Video.php` -- Video model. Must add critique/refinement columns. Uses `getScriptIterations()` from E009-F001.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/VideoFactory.php` -- Video factory. Must add critique factory states.
- `/Users/young/Nextcloud/dev/Itervel/config/ai.php` -- AI config with 'critique' step definition.
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Must add critique routes.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/videos/script.tsx` -- Script page from E011-F003. Template for critique page patterns.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/video.ts` -- Video types. Must add critique types.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/badge.tsx` -- Badge for impact levels.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/collapsible.tsx` -- Collapsible for round details.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/alert.tsx` -- Alert for error state.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/skeleton.tsx` -- Skeleton for loading.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/ScriptGenerationTest.php` -- Script tests. Template for critique test patterns.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/app/Services/ScriptCritiqueService.php` -- Service with `critique()` and `revise()` methods for iterative script refinement.
- `/Users/young/Nextcloud/dev/Itervel/app/Jobs/RefineScriptJob.php` -- Queued job that runs the full critique-revise loop for the configured number of iterations.
- `/Users/young/Nextcloud/dev/Itervel/database/migrations/YYYY_MM_DD_HHMMSS_add_critique_columns_to_videos_table.php` -- Migration adding `critique_rounds` and `refinement_progress` JSON columns.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/CritiqueController.php` -- Controller with `index` and `refine` methods.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/videos/critique.tsx` -- Critique page with refinement progress, round details, and results.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/ScriptCritiqueTest.php` -- Pest feature tests for critique and refinement.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: critique-backend-dev
    - Role: Creates ScriptCritiqueService, RefineScriptJob, migration, CritiqueController, updates Video model, and adds routes
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: critique-frontend-dev
    - Role: Creates the critique page with progress tracking, round accordion display, impact badges, and auto-start behavior
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: critique-test-dev
    - Role: Writes Pest feature tests for critique/refinement with mocked AI, multi-round loops, and zero-iteration handling
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: critique-reviewer
    - Role: Validates the complete critique and refinement implementation
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

### 1. Create Critique and Refinement Backend

- **Task ID**: create-critique-backend
- **Depends On**: none
- **Assigned To**: critique-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- Read `/Users/young/Nextcloud/dev/Itervel/app/Services/ScriptGenerationService.php` for the service pattern
- Read `/Users/young/Nextcloud/dev/Itervel/config/ai.php` for the critique step configuration
- Create migration: `docker compose exec app php artisan make:migration add_critique_columns_to_videos_table --table=videos --no-interaction`
    - `up()`: add `critique_rounds` (json, nullable) and `refinement_progress` (json, nullable) columns
    - `down()`: drop both columns
- Update Video model: add both columns to `$fillable` and `casts()` as `'array'`
- Update VideoFactory: add both as null in definition, add `withCritiqueRounds(): static` state with sample critique data
- Create `/Users/young/Nextcloud/dev/Itervel/app/Services/ScriptCritiqueService.php`:
    - `critique(Video $video, array $currentScript): array` -- uses critique model, builds critique prompt, parses weak points with impact levels
    - `revise(Video $video, array $currentScript, array $critique): array` -- uses script model, builds revision prompt incorporating critique points, returns revised script in same format
    - Critique system prompt: act as disengaged viewer, find 3-7 weak points, rate impact (high/medium/low), identify strengths, give score 1-10
    - Revision system prompt: revise addressing all critique points, maintain structure and word count, preserve strengths
- Create `docker compose exec app php artisan make:job RefineScriptJob --no-interaction`
- Edit RefineScriptJob:
    - Handle 0 iterations: immediately set status `script_refined` and return
    - For each round: update `refinement_progress`, run critique, run revision, store round data
    - After all rounds: update `generated_script` with final version, store all `critique_rounds`, set status `script_refined`, clear `refinement_progress`
    - On failure: set status `script_refinement_failed`, store error, clear progress
- Create `docker compose exec app php artisan make:controller CritiqueController --no-interaction`
- Edit CritiqueController with index and refine methods
- Add routes to web.php
- Run migration and pint

### 2. Create Critique Frontend Page

- **Task ID**: create-critique-frontend
- **Depends On**: create-critique-backend
- **Assigned To**: critique-frontend-dev
- **Agent Type**: coder
- **Parallel**: false
- Run `npm run build` to generate Wayfinder routes
- Add critique types to `resources/js/types/video.ts`:
    - `WeakPoint` with `location`, `issue`, `impact` ('high'|'medium'|'low'), `suggestion`, `viewer_action`
    - `CritiqueResult` with `weak_points: WeakPoint[]`, `strengths: string[]`, `overall_score: number`
    - `CritiqueRound` with `round`, `total_rounds`, `critique: CritiqueResult`, `revised_script: GeneratedScript`
    - `RefinementProgress` with `current_round: number`, `total_rounds: number`
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/videos/critique.tsx`:
    - Refining state: progress bar showing "Round {n} of {total}", spinner, poll every 5 seconds
    - Error state: destructive Alert with retry
    - Complete state: summary header, collapsible rounds with weak point cards (impact-colored badges: high=destructive, medium=secondary, low=outline), strengths list, score badge, "View revised script" toggle per round
    - Skip state: when no critique_rounds exist and status is script_refined, show "Critique skipped" message
    - Auto-start: `useEffect` that auto-POSTs to refine when status is `script_generated`
    - "Continue" and "Re-refine" buttons
- Run `npm run types` and `npm run lint`

### 3. Write Feature Tests

- **Task ID**: write-critique-tests
- **Depends On**: create-critique-backend
- **Assigned To**: critique-test-dev
- **Agent Type**: coder
- **Parallel**: true
- Create `docker compose exec app php artisan make:test ScriptCritiqueTest --pest --no-interaction`
- Write tests:
    - `test('critique page is displayed for video owner')` -- assert 200
    - `test('critique page returns 403 for non-owner')` -- assert 403
    - `test('refinement can be triggered')` -- assert RefineScriptJob dispatched
    - `test('refinement job runs configured number of rounds')` -- set 3 iterations, mock AiClient, run job, assert 3 critique_rounds stored
    - `test('refinement job skips when iterations is zero')` -- set 0 iterations, run job, assert status `script_refined` with no critique_rounds
    - `test('refinement job updates progress during processing')` -- mock AiClient, check that refinement_progress is updated (use partial mock or assert after each round)
    - `test('refinement job updates generated_script with final revision')` -- assert the last round's revised_script becomes the video's generated_script
    - `test('refinement job handles failure')` -- mock throw on round 2, assert failure status and error stored
    - `test('ScriptCritiqueService generates critique with weak points')` -- mock AiClient, call critique, assert weak_points array with impact levels
    - `test('ScriptCritiqueService revises script based on critique')` -- mock AiClient, call revise, assert valid script structure returned
    - `test('critique rounds are capped at 5')` -- verify getScriptIterations returns max 5 even if configured higher (if applicable from config validation)
- Run tests and full suite
- Run pint

### 4. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-critique-backend, create-critique-frontend, write-critique-tests
- **Assigned To**: critique-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands
- Verify the critique service identifies weak points with impact ratings
- Verify the revision service produces an improved script maintaining structure
- Verify the job runs the correct number of rounds
- Verify zero iterations skips critique entirely
- Verify progress updates are shown during refinement
- Verify critique rounds are stored for transparency
- Verify the final revised script replaces the generated_script
- Verify all tests pass
- Confirm all acceptance criteria are met

## Acceptance Criteria

- A `ScriptCritiqueService` with `critique()` and `revise()` methods exists
- Critique identifies 3-7 weak points with impact levels (high/medium/low), viewer action, and suggested fixes
- Critique identifies 2-3 strengths and provides an overall score (1-10)
- Revision produces an improved script maintaining the same section structure and word count range
- A `RefineScriptJob` runs the full critique-revise loop for the configured number of rounds
- Zero iterations (0) skips critique and sets status to `script_refined` immediately
- The job supports 1-5 iterations as configured via `getScriptIterations()`
- Progress is tracked in `refinement_progress` JSON during processing ("Round 2 of 3")
- All critique rounds are stored in `critique_rounds` JSON for transparency
- The final revised script replaces `generated_script`
- Video status transitions: `script_generated` → `refining_script` → `script_refined` (or `script_refinement_failed`)
- The critique page shows real-time progress with round number and progress bar
- Each critique round is viewable in a collapsible accordion with weak points, impact badges, strengths, and score
- The critique page auto-starts refinement when the user arrives from script generation
- Error state shows retry option
- Only video owners can access (403 for others)
- Tests mock the AI client
- All tests pass
- TypeScript compiles without errors
- ESLint passes

## Validation Commands

```bash
docker compose exec app php artisan test tests/Feature/ScriptCritiqueTest.php --compact
docker compose exec app php artisan test --compact
docker compose exec app php artisan route:list --name=videos.critique
npm run types
npm run lint
npm run format:check
docker compose exec app vendor/bin/pint --dirty
```

## Notes

- Each critique round makes 2 AI calls (critique + revision), so 3 rounds = 6 API calls. This is why the iteration count is capped at 5 (max 10 calls). The polling interval is 5 seconds to account for the longer processing time.
- The critique uses the 'critique' model while revision uses the 'script' model. This allows users to use a cheaper model for critique (e.g., Haiku) and a higher-quality model for revision (e.g., Opus).
- The `refinement_progress` column is ephemeral: it's set during processing and cleared on completion/failure. It exists solely for real-time progress display.
- All critique rounds are preserved in `critique_rounds` for transparency. The user can see what was changed in each round, which helps build trust in the AI refinement process.
- The `generated_script` is updated in-place with the final revision. The original pre-critique script is preserved as the `revised_script` in round 1's input (it's the `currentScript` passed to the first critique call). If users need to revert, they can regenerate the script from E011-F003.
- The auto-start behavior (auto-POSTing to refine when status is `script_generated`) provides a seamless experience. The user doesn't need to click a button -- refinement begins automatically when they reach this step.

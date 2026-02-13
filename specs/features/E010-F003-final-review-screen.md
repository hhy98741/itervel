# Feature: Final Review Screen

**Epic**: E010-video-creation-ux.md
**Feature**: E010-F003
**Epic depends on**: E006-brand-guide-and-video-input.md
**Feature depends on**: E010-F002

## Task Description

Shows a summary of all selections before the user commits to generating the video. The user sees the selected title, script preview (first 200 words), selected thumbnail, voice selection, music selection, estimated cost in credits, and a storyboard preview. They click "Generate Video" to start rendering.

**What it does**: Shows a summary of all selections before the user commits to generating the video.

**Expected outcome**: The user sees the selected title, script preview (first 200 words), selected thumbnail, voice selection, music selection, estimated cost in credits, and a storyboard preview. They click "Generate Video" to start rendering.

This feature replaces the placeholder content for the "Final Review" step (step 8) in the wizard created by E010-F002. It reads all the accumulated step data from the Video model's `step_data` JSON and presents it in a comprehensive review layout. Since the actual content for most steps hasn't been built yet (E011-E015 epics), the review screen must gracefully handle missing data by showing placeholder/empty states for sections where data isn't available yet.

The "Generate Video" button transitions the video's status from `draft` to `processing` and redirects the user to a status/download page. The actual video generation pipeline is built in later epics (E014), so this feature only handles the status transition and redirect.

## Objective

Replace the Final Review step placeholder with a comprehensive review screen that displays all video creation selections in a scannable card-based layout, shows the estimated credit cost, and provides a "Generate Video" button that transitions the video to processing status. The screen handles missing data gracefully with empty states since not all steps have real implementations yet.

## Solution Approach

### Review Screen Layout

The Final Review screen uses a card-based layout to group related information. On desktop (1200px+), cards are arranged in a responsive grid. On tablet/mobile, cards stack vertically.

```
Desktop Layout:
┌──────────────────────────────────────────────────┐
│  Final Review                                      │
│  Review your selections before generating.         │
├─────────────────────┬────────────────────────────┤
│  Title              │  Thumbnail Preview          │
│  "My Video Title"   │  [thumbnail image or        │
│                     │   placeholder]               │
├─────────────────────┴────────────────────────────┤
│  Script Preview                                    │
│  First 200 words of the script...                  │
├─────────────────────┬────────────────────────────┤
│  Voiceover          │  Music                       │
│  Selected voice     │  Selected track              │
├─────────────────────┴────────────────────────────┤
│  Storyboard Preview                                │
│  [scene cards or placeholder]                      │
├──────────────────────────────────────────────────┤
│  Estimated Cost: 5 credits                         │
│                              [Generate Video]      │
└──────────────────────────────────────────────────┘
```

### Component Architecture

**`FinalReviewStep`** - The main review component that replaces the placeholder.

Props:

```tsx
interface FinalReviewStepProps {
    video: Video;
}
```

The component reads from `video.step_data` to extract selections made in previous steps. It renders review sections using a set of sub-components:

#### Review Section Components

Each section is a self-contained card that handles its own empty state:

1. **`ReviewSectionTitle`** - Shows the selected video title or "No title selected" empty state
2. **`ReviewSectionScript`** - Shows the first 200 words of the script or "No script generated" empty state
3. **`ReviewSectionThumbnail`** - Shows the selected thumbnail image or a placeholder
4. **`ReviewSectionVoiceover`** - Shows the selected voice or "No voice selected" empty state
5. **`ReviewSectionMusic`** - Shows the selected music track or "No music selected" empty state
6. **`ReviewSectionStoryboard`** - Shows scene cards or "No storyboard available" empty state
7. **`ReviewSectionCost`** - Shows the estimated credit cost

All review sections follow the same pattern:

```tsx
function ReviewSectionTitle({ stepData }: { stepData: Video['step_data'] }) {
    const title = stepData?.['title-selection']?.title;

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-sm font-medium text-muted-foreground">
                    Title
                </CardTitle>
            </CardHeader>
            <CardContent>
                {title ? (
                    <p className="text-lg font-semibold">{title}</p>
                ) : (
                    <p className="text-sm text-muted-foreground italic">
                        No title selected
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
```

### Empty State Handling

Since most wizard steps don't have real implementations yet, the review screen must handle missing data. Each review section independently checks for its data and renders either:

- **Has data**: The actual selection/content
- **No data**: A muted italic message like "No title selected", "No script generated yet", "No thumbnail selected"

This ensures the review screen works correctly as each step is implemented in later epics without needing modifications.

### Generate Video Action

The "Generate Video" button triggers a POST request to a new controller endpoint:

```php
// In VideoCreationController
public function generate(Request $request, Video $video): RedirectResponse
{
    $this->authorize('update', $video);

    $video->update([
        'status' => 'processing',
        'current_step' => 'download',
    ]);

    // Future: Dispatch video generation job here (E014)

    return redirect()->route('videos.create.step', [
        'video' => $video,
        'step' => 'download',
    ]);
}
```

Add the route:

```php
Route::post('videos/{video}/generate', [VideoCreationController::class, 'generate'])->name('videos.generate');
```

The actual video generation (dispatching jobs, processing pipeline) is handled by E014. For now, this endpoint just updates the status and redirects to the download step.

### Credit Cost Estimation

The estimated cost is calculated based on video configuration. Since the credit system is defined in E005 but the actual cost calculation depends on E014 (rendering complexity), use a simple placeholder:

```tsx
function ReviewSectionCost({ video }: { video: Video }) {
    // Placeholder cost - will be replaced with actual calculation in E005/E014
    const estimatedCredits = 5;

    return (
        <div className="flex items-center justify-between rounded-lg border bg-muted/50 p-4">
            <div>
                <p className="text-sm font-medium">Estimated Cost</p>
                <p className="text-2xl font-bold">{estimatedCredits} credits</p>
            </div>
            <Form {...generateRoute.form()}>
                {({ processing }) => (
                    <Button size="lg" disabled={processing}>
                        {processing ? 'Generating...' : 'Generate Video'}
                    </Button>
                )}
            </Form>
        </div>
    );
}
```

### Script Preview Truncation

The script preview shows the first 200 words. Implement a simple word truncation utility:

```tsx
function truncateWords(text: string, maxWords: number): string {
    const words = text.split(/\s+/);
    if (words.length <= maxWords) return text;
    return words.slice(0, maxWords).join(' ') + '...';
}
```

This is defined locally in the review component file -- no need for a shared utility for a single use case.

### Responsive Layout

Use the `ResponsiveGrid` component from E010-F004 for the card layout:

- Desktop: 2-column grid for paired sections (title + thumbnail, voiceover + music)
- Tablet/Mobile: Single column stacked

The script preview and storyboard sections span the full width on all breakpoints.

### Testing Approach

1. PHP feature tests for the generate endpoint (status transition, authorization)
2. PHP feature test that the final-review step renders correctly
3. TypeScript type checking for the component interfaces

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/videos/create.tsx` -- The wizard page from E010-F002. The FinalReviewStep component will be integrated here via the WizardStepContent component.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/wizard-step-content.tsx` -- The step content switcher from E010-F002. Update the 'final-review' case to render FinalReviewStep instead of PlaceholderStep.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/placeholder-step.tsx` -- The placeholder component from E010-F002. FinalReviewStep replaces this for the final-review step.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/video.ts` -- Video types from E010-F001/F002. May need to extend with review-specific types.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/responsive-grid.tsx` -- Responsive grid from E010-F004 for the card layout.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/card.tsx` -- Card component for review sections.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/button.tsx` -- Button component for "Generate Video".
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/separator.tsx` -- Separator between review sections.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/badge.tsx` -- Badge for credit cost display or status indicators.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/skeleton.tsx` -- Skeleton for image placeholder states.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/lib/utils.ts` -- The `cn()` utility.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/VideoCreationController.php` -- The wizard controller from E010-F002. Add the `generate` method here.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/Video.php` -- The Video model from E010-F002.
- `/Users/young/Nextcloud/dev/Itervel/app/Policies/VideoPolicy.php` -- Video authorization policy from E010-F002.
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Routes file where the generate route will be added.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/VideoCreation/VideoCreationWizardTest.php` -- Existing wizard tests from E010-F002.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/profile.tsx` -- Reference for Form submission patterns.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/final-review-step.tsx` -- The main Final Review step component that displays all video selections in a card-based review layout.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/VideoCreation/VideoGenerationTest.php` -- Pest feature tests for the generate endpoint (status transition, authorization, redirect).

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: review-backend-dev
    - Role: Adds the generate endpoint to VideoCreationController and the generate route to web.php
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: review-frontend-dev
    - Role: Creates the FinalReviewStep component with all review sections, empty states, and "Generate Video" action. Updates WizardStepContent to use it
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: review-test-dev
    - Role: Writes Pest feature tests for the generate endpoint and the final review step rendering
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: review-reviewer
    - Role: Validates the final review implementation, reviews code quality, runs tests and validation commands, checks empty state handling
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Add Generate Endpoint to Backend

- **Task ID**: add-generate-endpoint
- **Depends On**: none
- **Assigned To**: review-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- Read `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/VideoCreationController.php` to understand the existing controller structure
- Read `/Users/young/Nextcloud/dev/Itervel/routes/web.php` to understand the existing route structure
- Add a `generate(Request $request, Video $video): RedirectResponse` method to `VideoCreationController`:
    - Call `$this->authorize('update', $video)` for authorization
    - Update the video: `$video->update(['status' => 'processing', 'current_step' => 'download'])`
    - Add a comment: `// TODO: Dispatch video generation job (E014)`
    - Return `redirect()->route('videos.create.step', ['video' => $video, 'step' => 'download'])`
- Add route to `routes/web.php` inside the existing auth+verified middleware group: `Route::post('videos/{video}/generate', [VideoCreationController::class, 'generate'])->name('videos.generate')`
- Run `docker compose exec app vendor/bin/pint --dirty` for PHP formatting

### 2. Create Final Review Step Component

- **Task ID**: create-review-component
- **Depends On**: add-generate-endpoint
- **Assigned To**: review-frontend-dev
- **Agent Type**: coder
- **Parallel**: false
- Read the following files for patterns: `/Users/young/Nextcloud/dev/Itervel/resources/js/components/wizard-step-content.tsx`, `/Users/young/Nextcloud/dev/Itervel/resources/js/components/placeholder-step.tsx`, `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/card.tsx`, `/Users/young/Nextcloud/dev/Itervel/resources/js/components/responsive-grid.tsx`, `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/profile.tsx`
- Run `npm run build` to regenerate Wayfinder actions (to pick up the new generate route)
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/components/final-review-step.tsx`:
    - Import `Card`, `CardHeader`, `CardTitle`, `CardContent` from `@/components/ui/card`
    - Import `Button` from `@/components/ui/button`
    - Import `ResponsiveGrid` from `@/components/responsive-grid`
    - Import `Separator` from `@/components/ui/separator`
    - Import `Form` from `@inertiajs/react`
    - Import the Wayfinder generate action (check generated actions path after build)
    - Import `cn` from `@/lib/utils`
    - Import `Video` type from `@/types`
    - Define a local `truncateWords(text: string, maxWords: number): string` function
    - Create individual review section components within the file (not exported, only used here):
        - `ReviewSectionTitle` -- Shows `video.step_data?.['title-selection']?.title` or "No title selected" in muted italic
        - `ReviewSectionScript` -- Shows truncated script (200 words) from `video.step_data?.['script-review']?.script` or "No script generated yet"
        - `ReviewSectionThumbnail` -- Shows placeholder with `Skeleton` component and "No thumbnail selected" since no real thumbnails exist yet
        - `ReviewSectionVoiceover` -- Shows `video.step_data?.['voiceover-selection']?.voice` or "No voice selected"
        - `ReviewSectionMusic` -- Shows `video.step_data?.['music-selection']?.track` or "No music selected"
        - `ReviewSectionStoryboard` -- Shows placeholder "No storyboard available" with dashed border empty state
    - Create the main `FinalReviewStep` component:
        - Receives `{ video }: FinalReviewStepProps`
        - Renders a heading section with "Final Review" title and "Review your selections before generating your video." description
        - Uses `ResponsiveGrid` with `columns={2}` for Title + Thumbnail pair
        - Script preview spans full width
        - Uses `ResponsiveGrid` with `columns={2}` for Voiceover + Music pair
        - Storyboard preview spans full width
        - A `Separator` before the cost/generate section
        - Cost section with estimated credits display (hardcoded 5 for now) and "Generate Video" button
        - The "Generate Video" button uses an Inertia `Form` with the generate Wayfinder action
        - Button shows "Generating..." when processing
    - Export as named export: `export function FinalReviewStep(...)`
- Update `/Users/young/Nextcloud/dev/Itervel/resources/js/components/wizard-step-content.tsx`:
    - Import `FinalReviewStep` from `@/components/final-review-step`
    - Change the 'final-review' case to render `<FinalReviewStep video={video} />` instead of `<PlaceholderStep />`
- Run `npm run types` to verify TypeScript compiles
- Run `npm run lint` to verify ESLint passes

### 3. Write Feature Tests for Generate Endpoint

- **Task ID**: write-generate-tests
- **Depends On**: add-generate-endpoint, create-review-component
- **Assigned To**: review-test-dev
- **Agent Type**: coder
- **Parallel**: false
- Read `/Users/young/Nextcloud/dev/Itervel/tests/Feature/VideoCreation/VideoCreationWizardTest.php` for test patterns
- Read `/Users/young/Nextcloud/dev/Itervel/database/factories/VideoFactory.php` for factory usage
- Create `/Users/young/Nextcloud/dev/Itervel/tests/Feature/VideoCreation/VideoGenerationTest.php` using `docker compose exec app php artisan make:test --pest VideoCreation/VideoGenerationTest`
- Write the following tests:
    - `test('user can generate a video')` -- Create a draft video for user, POST to `videos/{video}/generate`, assert redirect to download step URL, assert video status is 'processing' and current_step is 'download'
    - `test('unauthenticated user cannot generate a video')` -- POST to generate without auth, assert redirect to login
    - `test('user cannot generate another user video')` -- Create video for user A, act as user B, POST to generate, assert 403
    - `test('final review step renders correctly')` -- Create a draft video, GET the final-review step URL, assert OK and Inertia component is `videos/create`
    - `test('final review step renders with step data')` -- Create a video with step_data containing title-selection and script-review data, GET the final-review step, assert OK
    - `test('generate transitions video status from draft to processing')` -- Create a video with status 'draft', POST to generate, assert status changed to 'processing'
- Run `docker compose exec app php artisan test tests/Feature/VideoCreation/VideoGenerationTest.php --compact`
- Run `docker compose exec app php artisan test --compact` to check for regressions

### 4. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: add-generate-endpoint, create-review-component, write-generate-tests
- **Assigned To**: review-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Read the feature plan at `/Users/young/Nextcloud/dev/Itervel/specs/features/E010-F003-final-review-screen.md`
- Read all created/modified files: `final-review-step.tsx`, `wizard-step-content.tsx` (updated), `VideoCreationController.php` (updated), `web.php` (updated), `VideoGenerationTest.php`
- Run all validation commands
- Verify the FinalReviewStep component renders review sections for: title, script, thumbnail, voiceover, music, storyboard
- Verify each review section handles missing data with appropriate empty state messages
- Verify the script preview truncates to 200 words
- Verify the "Generate Video" button uses Inertia Form with the generate action
- Verify the generate endpoint transitions video status to 'processing'
- Verify authorization prevents other users from generating
- Verify the responsive layout uses ResponsiveGrid for card pairs
- Verify all tests pass
- Confirm all acceptance criteria are met
- Run PHP formatting: `docker compose exec app vendor/bin/pint --dirty`

## Acceptance Criteria

- The Final Review step replaces the placeholder with a comprehensive review layout
- The review screen shows the selected title from step data or "No title selected" empty state
- The review screen shows a script preview truncated to 200 words or "No script generated yet" empty state
- The review screen shows a thumbnail preview or placeholder empty state
- The review screen shows the voiceover selection or "No voice selected" empty state
- The review screen shows the music selection or "No music selected" empty state
- The review screen shows a storyboard section or "No storyboard available" empty state
- The review screen displays the estimated credit cost (hardcoded placeholder for now)
- A "Generate Video" button exists that POSTs to the generate endpoint
- The generate endpoint transitions the video status from 'draft' to 'processing'
- The generate endpoint redirects to the download step
- The generate endpoint is protected by the VideoPolicy (only video owner can generate)
- The review layout uses ResponsiveGrid: 2-column on desktop, stacked on mobile/tablet
- Empty states use muted, italic text for visual differentiation
- The "Generate Video" button shows "Generating..." during form submission
- All Pest feature tests pass
- TypeScript type checking passes (`npm run types`)
- ESLint passes (`npm run lint`)
- PHP formatting passes (`vendor/bin/pint --dirty`)

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run video generation tests
docker compose exec app php artisan test tests/Feature/VideoCreation/VideoGenerationTest.php --compact

# Run all video creation tests
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

- The estimated credit cost is hardcoded to 5 credits as a placeholder. The actual cost calculation will be implemented when E005 (Credits and Billing) and E014 (Video Assembly) features are built. The cost should eventually depend on video length, resolution, AI model used, etc.
- The `truncateWords` function is intentionally defined locally in the component file rather than in a shared utility. Per YAGNI, it's a single-use function. If other components need it later, it can be extracted to `lib/utils.ts`.
- The storyboard preview section exists in the layout but will always show empty state until E014 (Video Assembly) generates storyboard data. This ensures the review screen layout is complete from the start.
- The generate endpoint currently only updates the video status. The actual generation pipeline (job dispatching, processing queue, etc.) will be implemented in E014. A TODO comment marks where the job dispatch should be added.
- Each review section is a separate function component within the same file. They are not exported because they are only used within `FinalReviewStep`. This keeps the public API surface small while maintaining readability.
- The review sections read from specific keys in `video.step_data` (e.g., `step_data['title-selection'].title`). These keys must match what the corresponding step implementations (E011-E015) will write. The key naming convention follows the step ID from the wizard constants.

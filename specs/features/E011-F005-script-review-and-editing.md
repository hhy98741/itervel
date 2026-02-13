# Feature: Script Review and Editing

**Epic**: E011-outline-and-script-generation.md
**Feature**: E011-F005
**Epic depends on**: E009-ai-configuration-and-title-generation.md
**Feature depends on**: E011-F004

## Task Description

Presents the final refined script to the user for review and optional manual editing. The user sees the full script in an editable text field with word count, estimated duration, delivery cues, and a summary of how many critique rounds it went through. The user can edit the script directly before approving it.

**What it does**: Presents the final refined script to the user for review and optional manual editing.

**Expected outcome**: The user sees the full script in an editable text field with word count, estimated duration, delivery cues, and a summary of how many critique rounds it went through. The user can edit the script directly before approving it.

This feature creates the "Script Review" step (step 4 in the wizard). After script generation (E011-F003) and optional critique/refinement (E011-F004), the user reviews the final script. The script is displayed in an editable format where the user can make manual changes, see live word count and estimated duration updates, and approve the script when satisfied.

The approved script is stored as the final version and the video status transitions to `script_approved`.

## Objective

Create a script review page where the user sees the full refined script in an editable text area, with live word count, estimated duration, delivery cue legend, critique round summary, and an approve action. The user can edit the script text directly before approving. Approval saves the final script and transitions the video to `script_approved` status.

## Solution Approach

### Script Review Page

The script review page combines display and editing in a single view. The script sections are displayed as editable text areas, one per section, with metadata shown alongside.

### Frontend Component Architecture

**ScriptReviewPage** - Main page component:

```tsx
export default function ScriptReview({ video }: ScriptReviewProps) {
    const form = useForm({
        script: video.generated_script,
    });

    // Live calculations
    const totalWordCount = useMemo(
        () =>
            form.data.script.sections.reduce(
                (sum, s) => sum + countWords(s.script_text),
                0,
            ),
        [form.data.script],
    );
    const estimatedDuration = Math.round((totalWordCount / 150) * 60);
    const critiqueRoundCount = video.critique_rounds?.length ?? 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Script Review" />

            {/* Critique summary */}
            <CritiqueSummary rounds={critiqueRoundCount} />

            {/* Script stats bar */}
            <ScriptStatsBar
                wordCount={totalWordCount}
                duration={estimatedDuration}
                targetMin={2000}
                targetMax={2500}
            />

            {/* Delivery cue legend */}
            <DeliveryCueLegend />

            {/* Editable sections */}
            {form.data.script.sections.map((section, index) => (
                <ScriptSectionEditor
                    key={index}
                    section={section}
                    onChange={(updated) => updateSection(index, updated)}
                />
            ))}

            {/* Action buttons */}
            <div className="flex justify-between border-t pt-4">
                <Button variant="outline" asChild>
                    <Link href={backUrl}>Back to Critique</Link>
                </Button>
                <Button onClick={handleApprove} disabled={form.processing}>
                    Approve Script
                </Button>
            </div>
        </AppLayout>
    );
}
```

### ScriptSectionEditor Component

An editable card for each script section:

```tsx
function ScriptSectionEditor({ section, onChange }: ScriptSectionEditorProps) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle className="text-base">
                    {section.outline_title}
                </CardTitle>
                <Badge variant="secondary">
                    {countWords(section.script_text)} words
                </Badge>
            </CardHeader>
            <CardContent>
                <Textarea
                    value={section.script_text}
                    onChange={(e) =>
                        onChange({ ...section, script_text: e.target.value })
                    }
                    className="min-h-[200px] font-mono text-sm leading-relaxed"
                    placeholder="Section script text..."
                />
            </CardContent>
        </Card>
    );
}
```

### ScriptStatsBar Component

A sticky bar showing live statistics:

```tsx
function ScriptStatsBar({
    wordCount,
    duration,
    targetMin,
    targetMax,
}: ScriptStatsBarProps) {
    const isInRange = wordCount >= targetMin && wordCount <= targetMax;
    const isUnder = wordCount < targetMin;
    const isOver = wordCount > targetMax;

    return (
        <div className="sticky top-0 z-10 flex items-center justify-between rounded-lg border bg-background p-3 shadow-sm">
            <div className="flex items-center gap-4">
                <div>
                    <span className="text-sm text-muted-foreground">
                        Words:{' '}
                    </span>
                    <span
                        className={cn(
                            'font-semibold',
                            isInRange && 'text-green-600 dark:text-green-400',
                            isUnder && 'text-yellow-600 dark:text-yellow-400',
                            isOver && 'text-red-600 dark:text-red-400',
                        )}
                    >
                        {wordCount}
                    </span>
                    <span className="ml-1 text-xs text-muted-foreground">
                        / {targetMin}-{targetMax}
                    </span>
                </div>
                <Separator orientation="vertical" className="h-5" />
                <div>
                    <span className="text-sm text-muted-foreground">
                        Duration:{' '}
                    </span>
                    <span className="font-semibold">
                        {Math.floor(duration / 60)}m {duration % 60}s
                    </span>
                </div>
            </div>
        </div>
    );
}
```

The stats bar uses color coding:

- **Green**: Word count is within the 2,000-2,500 target range
- **Yellow**: Word count is below 2,000
- **Red**: Word count is above 2,500

### DeliveryCueLegend Component

A small legend explaining delivery cues:

```tsx
function DeliveryCueLegend() {
    const cues = [
        { cue: '[PAUSE]', description: 'Brief pause for emphasis' },
        { cue: '[EMPHASIS]', description: 'Stress this phrase' },
        { cue: '[SLOWER]', description: 'Slow down delivery' },
        { cue: '[FASTER]', description: 'Speed up delivery' },
        { cue: '[WHISPER]', description: 'Lower volume/intensity' },
    ];

    return (
        <Collapsible>
            <CollapsibleTrigger className="flex items-center gap-2 text-sm text-muted-foreground">
                <Info className="h-4 w-4" /> Delivery Cues
            </CollapsibleTrigger>
            <CollapsibleContent>
                <div className="mt-2 flex flex-wrap gap-3">
                    {cues.map(({ cue, description }) => (
                        <div key={cue} className="flex items-center gap-1.5">
                            <Badge variant="secondary" className="text-xs">
                                {cue}
                            </Badge>
                            <span className="text-xs text-muted-foreground">
                                {description}
                            </span>
                        </div>
                    ))}
                </div>
            </CollapsibleContent>
        </Collapsible>
    );
}
```

### CritiqueSummary Component

Shows how many refinement rounds the script went through:

```tsx
function CritiqueSummary({ rounds }: { rounds: number }) {
    if (rounds === 0) return null;

    return (
        <div className="flex items-center gap-2 rounded-lg bg-muted/50 p-3 text-sm">
            <Sparkles className="h-4 w-4 text-primary" />
            <span>
                This script was refined through{' '}
                <strong>
                    {rounds} critique {rounds === 1 ? 'round' : 'rounds'}
                </strong>
                .
            </span>
        </div>
    );
}
```

### Textarea UI Component

The project may not have a Textarea UI component yet. Check if one exists; if not, create one:

```tsx
// resources/js/components/ui/textarea.tsx
import * as React from 'react';
import { cn } from '@/lib/utils';

const Textarea = React.forwardRef<
    HTMLTextAreaElement,
    React.ComponentProps<'textarea'>
>(({ className, ...props }, ref) => (
    <textarea
        ref={ref}
        data-slot="textarea"
        className={cn(
            'flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
            className,
        )}
        {...props}
    />
));
Textarea.displayName = 'Textarea';

export { Textarea };
```

### Backend: Approve Endpoint

Add an approve endpoint to the script workflow:

```php
// In ScriptController (from E011-F003), add:
public function approve(ApproveScriptRequest $request, Video $video): RedirectResponse
{
    abort_unless($video->user_id === auth()->id(), 403);

    $video->update([
        'generated_script' => $request->validated()['script'],
        'status' => 'script_approved',
    ]);

    return to_route('videos.create.step', [
        'video' => $video,
        'step' => 'voiceover-selection',
    ]);
}
```

### Form Request: ApproveScriptRequest

```php
class ApproveScriptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->id === $this->route('video')->user_id;
    }

    public function rules(): array
    {
        return [
            'script' => ['required', 'array'],
            'script.sections' => ['required', 'array', 'min:1'],
            'script.sections.*.outline_title' => ['required', 'string', 'max:200'],
            'script.sections.*.script_text' => ['required', 'string', 'min:10'],
            'script.sections.*.word_count' => ['required', 'integer', 'min:0'],
            'script.sections.*.delivery_cues' => ['sometimes', 'array'],
            'script.sections.*.delivery_cues.*' => ['string'],
        ];
    }
}
```

### Route

```php
Route::patch('videos/{video}/script', [ScriptController::class, 'approve'])->name('videos.script.approve');
```

### Word Count Utility

Create a shared word counting function used in both the frontend and backend:

Frontend (TypeScript):

```tsx
function countWords(text: string): number {
    return text.trim().split(/\s+/).filter(Boolean).length;
}
```

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/videos/script.tsx` -- Script page from E011-F003. The review page is separate but follows similar patterns.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/videos/critique.tsx` -- Critique page from E011-F004. Users navigate here before script review.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/ScriptController.php` -- Script controller from E011-F003. Add `approve` method here.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/Video.php` -- Video model. No new columns needed (uses existing `generated_script`).
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Must add approve route.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/video.ts` -- Video types with `GeneratedScript`, `ScriptSection`, `CritiqueRound`.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/card.tsx` -- Card for section containers.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/badge.tsx` -- Badge for word counts and cues.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/separator.tsx` -- Separator in stats bar.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/collapsible.tsx` -- Collapsible for delivery cue legend.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/button.tsx` -- Button component.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/input.tsx` -- Input for text areas (if textarea doesn't exist).
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/app-layout.tsx` -- App layout.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/lib/utils.ts` -- cn() utility.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/heading.tsx` -- Heading component.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/ScriptGenerationTest.php` -- Script tests. Template for review tests.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/textarea.tsx` -- Textarea UI component (if not already existing). Standard textarea matching project UI patterns.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/videos/script-review.tsx` -- Script review and editing page with editable sections, live stats, and approve action.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/ApproveScriptRequest.php` -- Form request for validating the approved script structure.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/ScriptReviewTest.php` -- Pest feature tests for script review and approval.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: review-backend-dev
    - Role: Creates the approve endpoint, ApproveScriptRequest, adds approve route, and creates Textarea component if missing
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: review-frontend-dev
    - Role: Creates the script review page with editable sections, live word count, duration stats, delivery cue legend, and critique summary
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: review-test-dev
    - Role: Writes Pest feature tests for script approval, validation, and authorization
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: review-reviewer
    - Role: Validates the complete script review implementation
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

### 1. Create Backend Approve Endpoint and Textarea Component

- **Task ID**: create-approve-backend
- **Depends On**: none
- **Assigned To**: review-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- Read `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/ScriptController.php` for existing controller
- Check if `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/textarea.tsx` exists; if not, create it following the pattern from other UI components (e.g., `input.tsx`)
- Create `docker compose exec app php artisan make:request ApproveScriptRequest --no-interaction`
- Edit ApproveScriptRequest with validation rules for script sections structure
- Add `approve(ApproveScriptRequest $request, Video $video): RedirectResponse` method to ScriptController
- Add route: `Route::patch('videos/{video}/script', [ScriptController::class, 'approve'])->name('videos.script.approve');`
- Run `docker compose exec app vendor/bin/pint --dirty`

### 2. Create Script Review Frontend Page

- **Task ID**: create-review-frontend
- **Depends On**: create-approve-backend
- **Assigned To**: review-frontend-dev
- **Agent Type**: coder
- **Parallel**: false
- Run `npm run build` to regenerate Wayfinder routes
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/videos/script-review.tsx`:
    - Import `useForm` from Inertia for form state management
    - Display script sections in editable `Textarea` components wrapped in Cards
    - Implement `ScriptStatsBar` with sticky positioning showing live word count (color-coded) and estimated duration
    - Implement `DeliveryCueLegend` as a collapsible section explaining each cue
    - Implement `CritiqueSummary` showing refinement round count
    - Create a `countWords(text: string): number` utility function
    - Use `useMemo` for real-time word count and duration calculations as the user edits
    - "Approve Script" button submits via Inertia form to the approve route
    - "Back to Critique" link for navigation
    - Breadcrumbs: Dashboard > Video > Script Review
- Run `npm run types` and `npm run lint`

### 3. Write Feature Tests

- **Task ID**: write-review-tests
- **Depends On**: create-approve-backend
- **Assigned To**: review-test-dev
- **Agent Type**: coder
- **Parallel**: true
- Create `docker compose exec app php artisan make:test ScriptReviewTest --pest --no-interaction`
- Write tests:
    - `test('script review page is displayed for video owner')` -- GET script-review route, assert 200
    - `test('script review page returns 403 for non-owner')` -- assert 403
    - `test('user can approve a script')` -- PATCH to approve with valid script data, assert redirect, assert status `script_approved`
    - `test('approved script replaces generated script')` -- PATCH with modified text, assert `generated_script` updated
    - `test('script approval requires valid structure')` -- PATCH without sections, assert validation error
    - `test('script sections require text')` -- PATCH with empty script_text, assert validation error
    - `test('non-owner cannot approve script')` -- assert 403
    - `test('unauthenticated user cannot access script review')` -- assert redirect
- Run tests and full suite
- Run pint

### 4. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-approve-backend, create-review-frontend, write-review-tests
- **Assigned To**: review-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands
- Verify script sections are editable in text areas
- Verify live word count updates as the user types
- Verify word count is color-coded (green/yellow/red)
- Verify estimated duration updates in real-time
- Verify delivery cue legend is present and collapsible
- Verify critique summary shows round count
- Verify approval saves edited script and transitions status
- Verify Textarea component exists and follows project patterns
- Verify all tests pass
- Confirm all acceptance criteria are met

## Acceptance Criteria

- The script review page displays all script sections in editable text areas
- Word count updates live as the user edits the script text
- Word count is color-coded: green (2,000-2,500), yellow (under 2,000), red (over 2,500)
- Estimated duration updates based on word count at 150 WPM
- A sticky stats bar shows word count and duration at the top of the page
- A collapsible delivery cue legend explains each cue type
- A critique summary shows how many refinement rounds the script went through
- "Approve Script" saves the edited script and transitions status to `script_approved`
- Validation requires at least one section with non-empty text
- The approved script replaces the `generated_script` on the video
- Only video owners can access and approve (403 for others)
- A `Textarea` UI component exists following project patterns (if it didn't before)
- All tests pass
- TypeScript compiles without errors
- ESLint passes
- PHP formatting passes

## Validation Commands

```bash
docker compose exec app php artisan test tests/Feature/ScriptReviewTest.php --compact
docker compose exec app php artisan test --compact
npm run types
npm run lint
npm run format:check
docker compose exec app vendor/bin/pint --dirty
```

## Notes

- The script review page is separate from the script generation page (E011-F003). The generation page shows loading/results, while the review page provides editing. This separation keeps each page focused on one responsibility.
- The word count function uses a simple `split(/\s+/)` approach. Delivery cues (e.g., `[PAUSE]`) are counted as words. This is acceptable since the target range (2,000-2,500) is approximate and cues are a small fraction of the total.
- The `Textarea` UI component follows the same Radix/Tailwind pattern as other UI components. It uses `data-slot="textarea"` and the same focus ring styles as Input.
- The stats bar is sticky (`sticky top-0`) so the user can always see the word count and duration as they scroll through long scripts.
- The form uses Inertia's `useForm` hook to manage the script state client-side. Each section's text area updates the form data, and the word count/duration are derived via `useMemo` for performance.
- The approve endpoint saves the `script` object as-is (including recalculated word counts). The frontend should update `word_count` per section and the totals before submitting.

# Feature: Title Selection

**Epic**: E009-ai-configuration-and-title-generation.md
**Feature**: E009-F004
**Epic depends on**: E006-brand-guide-and-video-input.md
**Feature depends on**: E009-F003

## Task Description

Title Selection lets the user pick one title from the 5 AI-generated options. This is the final step in the title workflow and records the user's choice on the video so all subsequent generation steps (outline, script, thumbnails, metadata) use the selected title.

**What it does**: Lets the user pick one title from the 5 AI-generated options.

**Expected outcome**: The user selects a title via radio button and clicks "Continue." The selected title is used for all subsequent generation steps.

This feature adds selection functionality to the titles page created in E009-F003. Instead of creating a separate page, it enhances the existing titles page with radio button selection and a "Continue" button. When the user selects a title and clicks Continue, the selection is saved and the video status transitions to `title_selected`.

**Dependency on E009-F003 (Title Generation)**: Creates the titles page, the `generated_titles` JSON column, and the title display UI. This feature adds interactive selection to that page.

## Objective

Add title selection functionality to the titles page: radio button selection for the 5 generated titles, a "Continue" button that saves the selection, `selected_title_index` and `selected_title` columns on the videos table, a backend endpoint that validates and stores the selection, and a video status transition to `title_selected`. The selected title becomes the canonical title for the video used by all downstream features.

## Solution Approach

### 1. Database: Add Selection Columns

Add migration for title selection columns:

```php
Schema::table('videos', function (Blueprint $table) {
    $table->unsignedTinyInteger('selected_title_index')->nullable()->after('generated_titles');
    $table->string('selected_title')->nullable()->after('selected_title_index');
});
```

- `selected_title_index`: which of the 5 generated titles was chosen (0-4)
- `selected_title`: the actual title text (denormalized for easy access without parsing JSON)

### 2. Video Model Updates

```php
// Add to $fillable
'selected_title_index',
'selected_title',

// Helper methods
public function hasSelectedTitle(): bool
{
    return $this->selected_title !== null;
}
```

### 3. Form Request: SelectTitleRequest

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SelectTitleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $video = $this->route('video');

        return $this->user()->id === $video->user_id;
    }

    public function rules(): array
    {
        return [
            'title_index' => ['required', 'integer', 'min:0', 'max:4'],
        ];
    }

    public function messages(): array
    {
        return [
            'title_index.required' => 'Please select a title.',
            'title_index.min' => 'Invalid title selection.',
            'title_index.max' => 'Invalid title selection.',
        ];
    }
}
```

### 4. TitleController Update

Add a `select` method to the existing `TitleController`:

```php
public function select(SelectTitleRequest $request, Video $video): RedirectResponse
{
    $index = $request->validated('title_index');
    $titles = $video->generated_titles;

    if (! isset($titles[$index])) {
        abort(422, 'Selected title does not exist.');
    }

    $video->update([
        'selected_title_index' => $index,
        'selected_title' => $titles[$index]['title'],
        'status' => 'title_selected',
    ]);

    return to_route('videos.show', $video);
}
```

### 5. Route

Add to `routes/web.php`:

```php
Route::post('videos/{video}/select-title', [TitleController::class, 'select'])->name('videos.titles.select');
```

### 6. Frontend: Enhance Titles Page

Update `resources/js/pages/videos/titles.tsx` to add selection UI:

- Add `useState<number | null>` for tracking the selected radio button
- Pre-select the title with ranking 1 (index 0 in the sorted array) by default
- Wrap each title card with a radio-style selection: clicking a card selects it (add `cursor-pointer` and a visual selected state using `ring-2 ring-primary`)
- Add a hidden radio input group for form submission accessibility
- Add a "Continue" button below the title cards that submits the selection via Inertia `router.post` to the select endpoint
- The "Continue" button is disabled until a title is selected
- If the video already has a `selected_title_index`, pre-select that card
- Use `useForm` from Inertia with `{ title_index: selectedIndex }`

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/TitleController.php` -- Title controller (from E009-F003). Must add `select` method.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/Video.php` -- Video model. Must add `selected_title_index` and `selected_title` to fillable, add `hasSelectedTitle()` helper.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/VideoFactory.php` -- Video factory. Must add defaults and selection state.
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Must add title selection route.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/videos/titles.tsx` -- Titles page (from E009-F003). Must add radio selection and Continue button.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/button.tsx` -- Button component.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/card.tsx` -- Card component.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/badge.tsx` -- Badge component.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/lib/utils.ts` -- `cn()` utility.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/ai.ts` -- AI types.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/TitleGenerationTest.php` -- Existing title tests. Referenced for patterns.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/database/migrations/YYYY_MM_DD_HHMMSS_add_selected_title_to_videos_table.php` -- Migration to add `selected_title_index` and `selected_title` columns.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/SelectTitleRequest.php` -- Form Request validating title_index (required, integer, 0-4).
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/TitleSelectionTest.php` -- Pest feature tests for title selection.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: selection-backend-dev
    - Role: Creates migration, SelectTitleRequest, updates TitleController with select method, updates Video model and factory, adds route
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: selection-frontend-dev
    - Role: Enhances the titles page with radio selection UI, Continue button, and form submission
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: selection-test-dev
    - Role: Writes Pest feature tests for title selection, validation, authorization, and status transitions
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: selection-reviewer
    - Role: Validates the complete title selection implementation against acceptance criteria
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Create Migration, Update Model, Create Form Request and Controller Method

- **Task ID**: create-selection-backend
- **Depends On**: none
- **Assigned To**: selection-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- Create migration: `php artisan make:migration add_selected_title_to_videos_table --table=videos --no-interaction`
    - `up()`: add `$table->unsignedTinyInteger('selected_title_index')->nullable()->after('generated_titles');` and `$table->string('selected_title')->nullable()->after('selected_title_index');`
    - `down()`: drop both columns
- Update `/Users/young/Nextcloud/dev/Itervel/app/Models/Video.php`:
    - Add `'selected_title_index'` and `'selected_title'` to `$fillable`
    - Add `hasSelectedTitle(): bool` method returning `$this->selected_title !== null`
- Update `/Users/young/Nextcloud/dev/Itervel/database/factories/VideoFactory.php`:
    - Add `'selected_title_index' => null` and `'selected_title' => null` to `definition()`
    - Add `withSelectedTitle(int $index = 0): static` state method that requires `withGeneratedTitles()` first and sets `selected_title_index`, `selected_title` (from the generated titles array), and `status` to `'title_selected'`
- Create form request: `php artisan make:request SelectTitleRequest --no-interaction`
- Edit `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/SelectTitleRequest.php`:
    - `authorize()`: `$this->user()->id === $this->route('video')->user_id`
    - `rules()`: `['title_index' => ['required', 'integer', 'min:0', 'max:4']]`
    - `messages()`: custom error messages for required, min, and max
- Update `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/TitleController.php`:
    - Add `use App\Http\Requests\SelectTitleRequest;`
    - Add `select(SelectTitleRequest $request, Video $video): RedirectResponse` method:
        - Get validated `title_index`
        - Verify `$video->generated_titles[$index]` exists (abort 422 if not)
        - Update video with `selected_title_index`, `selected_title` (extract title text from generated_titles), and `status => 'title_selected'`
        - Redirect to `videos.show`
    - Update `index()` to also pass `selected_title_index` in the video data
- Edit `/Users/young/Nextcloud/dev/Itervel/routes/web.php`:
    - Add: `Route::post('videos/{video}/select-title', [TitleController::class, 'select'])->name('videos.titles.select');`
- Run migration: `php artisan migrate --no-interaction`
- Run `vendor/bin/pint --dirty`
- Run existing tests: `php artisan test --compact`

### 2. Enhance Titles Page with Selection UI

- **Task ID**: add-selection-ui
- **Depends On**: create-selection-backend
- **Assigned To**: selection-frontend-dev
- **Agent Type**: coder
- **Parallel**: false
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/videos/titles.tsx` to understand the current structure
- Run `npm run build` to generate Wayfinder routes for the new `select` method
- Update the titles page:
    - Add `useState<number | null>` for `selectedIndex`, initialized from `video.selected_title_index ?? 0` (default to first/best ranked)
    - Make each title card clickable: add `onClick={() => setSelectedIndex(index)}` and `cursor-pointer` class
    - Add visual selection state: when `selectedIndex === index`, add `ring-2 ring-primary` classes to the card using `cn()`
    - Add a small radio circle indicator in each card (filled when selected, empty when not)
    - Below the title cards, add a form section:
        - Use `useForm` with `{ title_index: selectedIndex }` or use `router.post` directly
        - A "Continue" `Button` (variant="default", size="lg") that submits the selection
        - The button is disabled when `selectedIndex` is null or when `processing` is true
        - The button text: "Continue with Selected Title"
    - If `video.status === 'title_selected'` and `video.selected_title_index` exists, pre-select that card and show a "Change Selection" variation of the UI
    - Keep existing functionality (loading state, error state, regenerate button)
- Run `npm run types`
- Run `npm run lint`, fix with `npm run lint:fix`
- Run `npm run build`

### 3. Write Feature Tests

- **Task ID**: write-selection-tests
- **Depends On**: create-selection-backend
- **Assigned To**: selection-test-dev
- **Agent Type**: coder
- **Parallel**: true (can run in parallel with frontend)
- Create test file: `php artisan make:test TitleSelectionTest --pest --no-interaction`
- Write tests in `/Users/young/Nextcloud/dev/Itervel/tests/Feature/TitleSelectionTest.php`:
    - `test('title can be selected')` -- create video with generated titles, POST title_index=0, assert video `selected_title` matches the first title's text, `selected_title_index` is 0, and `status` is `title_selected`
    - `test('title selection updates video status')` -- assert status changes to `title_selected`
    - `test('selecting title stores the title text')` -- verify `selected_title` matches the title at the given index
    - `test('title_index is required')` -- POST without title_index, assert validation error
    - `test('title_index must be between 0 and 4')` -- test with -1 and 5, assert validation errors
    - `test('title_index must be an integer')` -- test with string, assert validation error
    - `test('non-owner cannot select title')` -- assert 403
    - `test('guests cannot select title')` -- assert redirect to login
    - `test('selecting title with invalid index for missing generated title returns 422')` -- create video with fewer than 5 titles or no titles, try to select, assert error
    - `test('title can be re-selected')` -- select index 0, then select index 2, assert `selected_title_index` is 2 and `selected_title` updated
    - `test('hasSelectedTitle returns true after selection')` -- select a title, assert `hasSelectedTitle()` returns true
    - `test('hasSelectedTitle returns false before selection')` -- new video, assert `hasSelectedTitle()` returns false
    - `test('titles page shows selected title index')` -- create video with selection, GET titles page, assert `selected_title_index` is in video data
- Run tests: `php artisan test tests/Feature/TitleSelectionTest.php --compact`
- Run `vendor/bin/pint --dirty`
- Run full test suite: `php artisan test --compact`

### 4. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-selection-backend, add-selection-ui, write-selection-tests
- **Assigned To**: selection-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands
- Verify migration adds `selected_title_index` and `selected_title` columns
- Verify Video model has new columns in fillable and `hasSelectedTitle()` method
- Verify `SelectTitleRequest` validates title_index as required integer 0-4 with authorization
- Verify `TitleController::select()` stores the selection and transitions status
- Verify route is registered: `videos.titles.select`
- Verify titles page has clickable card selection with visual ring highlight
- Verify "Continue" button submits the selection
- Verify pre-selection works when video already has a selected title
- Confirm all acceptance criteria are met

## Acceptance Criteria

- The `videos` table has `selected_title_index` (unsigned tiny int, nullable) and `selected_title` (string, nullable) columns
- Users can click on a title card to select it (visual feedback with ring highlight)
- The top-ranked title (rank 1) is pre-selected by default
- Clicking "Continue with Selected Title" saves the selection to the video
- The selected title text is stored in `selected_title` for easy access
- The video status transitions to `title_selected` after selection
- Title selection can be changed (re-selecting a different title updates the record)
- `title_index` is validated as a required integer between 0 and 4
- Only video owners can select titles (403 for others)
- Guests are redirected to login
- The `Video` model provides `hasSelectedTitle()` helper method
- Previously selected title is highlighted when returning to the titles page
- All selection tests pass
- TypeScript compiles without errors
- ESLint passes
- PHP formatting passes

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run title selection tests
php artisan test tests/Feature/TitleSelectionTest.php --compact

# Run title generation tests (regression)
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

- The `selected_title` column is denormalized (the title text could be derived from `generated_titles[selected_title_index]['title']`). This denormalization is intentional for performance and simplicity -- downstream features can access the selected title directly without parsing JSON.
- The title selection uses a 0-based index (0-4) matching the array position in `generated_titles`. The titles are sorted by ranking in E009-F003, so index 0 is typically the highest-ranked title.
- Re-selection is supported: if a user returns to the titles page after selecting, they can choose a different title. The old selection is simply overwritten.
- The redirect after selection goes to `videos.show`. This is a temporary destination -- later epics will change this to the next step in the wizard (outline generation).
- The radio-style selection does not use an actual HTML `<input type="radio">` element but instead uses card click handlers with visual state. A hidden radio group can be added for accessibility if needed.
- All commands should be run inside the Docker container.

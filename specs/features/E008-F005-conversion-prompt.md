# Feature: Conversion Prompt

**Epic**: E008-free-tier-and-onboarding.md
**Feature**: E008-F005
**Epic depends on**: E002-user-authentication.md, E005-credits-and-billing.md
**Feature depends on**: E008-F001, E008-F004, E006-F003

## Task Description

The Conversion Prompt encourages users to purchase credits after completing their free trial video. This is the key monetization touchpoint in the onboarding funnel -- after a user has experienced the product through their free video, the system presents a compelling prompt to convert them into a paying customer.

**What it does**: Encourages users to purchase credits after completing their free trial video.

**Expected outcome**: After the user downloads their free video, they see a prompt asking "Ready for full-length videos?" along with pricing information and a button to purchase credits.

The conversion prompt appears as a modal dialog that is triggered after the user downloads their free trial video. The dialog is contextual -- it acknowledges what the user just accomplished (creating their first video) and highlights what they're missing by being on the free tier (longer videos, multiple script iterations, no watermark). It includes pricing information and a clear call-to-action button that navigates to the credit purchase page.

The prompt is shown once per session after the download action. It does not block the download -- the user receives their video file first, then sees the prompt. The user can dismiss the prompt without purchasing.

**Dependency on E008-F001 (Free Tier Video Limitations)**: The conversion prompt references the specific limitations of the free tier (duration, iterations, watermark) to contrast with paid features. The `is_free_tier` flag on the video determines whether to show the prompt.

**Dependency on E008-F004 (Guided First Video Experience)**: The guided experience leads the user through creating their first video. The conversion prompt is the natural next step after that guided creation is complete and the video is downloaded.

**Dependency on E006-F003 (Topic Input)**: The Video model and controller infrastructure. The download action that triggers the prompt will be on a video-related route.

## Objective

Implement a conversion prompt modal dialog that appears after a user downloads their free trial video. The dialog displays the headline "Ready for full-length videos?", lists the benefits of upgrading (longer duration, multiple iterations, no watermark), shows pricing information, and provides a button to navigate to the credit purchase page. The prompt is only shown for free-tier videos and can be dismissed. The backend tracks whether the prompt has been shown for a given video to avoid repeated prompts.

## Solution Approach

### 1. Database: Track Prompt Display

Add a `conversion_prompt_shown` boolean column to the `videos` table to track whether the conversion prompt has been displayed for a specific video. This prevents showing the prompt repeatedly for the same video if the user downloads multiple times.

```php
Schema::table('videos', function (Blueprint $table) {
    $table->boolean('conversion_prompt_shown')->default(false)->after('is_free_tier');
});
```

### 2. Backend: Conversion Prompt Endpoint

Create a `ConversionPromptController` with two methods:

1. `show()` -- Returns the conversion prompt data (pricing, feature comparison) as JSON or Inertia props. Called after a download to check if the prompt should be shown.
2. `dismiss()` -- Marks the prompt as shown for the video so it won't appear again.

```php
namespace App\Http\Controllers;

use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversionPromptController extends Controller
{
    public function check(Request $request, Video $video): JsonResponse
    {
        $shouldShow = $video->is_free_tier
            && ! $video->conversion_prompt_shown
            && $video->user_id === $request->user()->id;

        return response()->json([
            'showPrompt' => $shouldShow,
            'pricing' => $shouldShow ? config('video.pricing') : null,
            'limitations' => $shouldShow ? [
                'free' => config('video.free_tier'),
                'paid' => config('video.paid'),
            ] : null,
        ]);
    }

    public function dismiss(Request $request, Video $video): JsonResponse
    {
        abort_unless($video->user_id === $request->user()->id, 403);

        $video->update(['conversion_prompt_shown' => true]);

        return response()->json(['success' => true]);
    }
}
```

### 3. Configuration: Pricing Information

Add pricing configuration to `config/video.php`:

```php
'pricing' => [
    'credits' => [
        ['amount' => 5, 'price' => '$9.99', 'per_credit' => '$2.00'],
        ['amount' => 15, 'price' => '$24.99', 'per_credit' => '$1.67'],
        ['amount' => 50, 'price' => '$69.99', 'per_credit' => '$1.40'],
    ],
    'currency' => 'USD',
],
```

This provides placeholder pricing data that the conversion prompt displays. The actual purchase flow is handled by E007 (Payments and Transactions).

### 4. Routes

Add routes in `routes/web.php` within the auth middleware group:

```php
Route::get('videos/{video}/conversion-prompt', [ConversionPromptController::class, 'check'])
    ->name('videos.conversion-prompt.check');
Route::post('videos/{video}/conversion-prompt/dismiss', [ConversionPromptController::class, 'dismiss'])
    ->name('videos.conversion-prompt.dismiss');
```

### 5. Frontend: Conversion Prompt Dialog

Create a `conversion-prompt-dialog.tsx` component that renders a modal dialog with:

- Headline: "Ready for full-length videos?"
- Subheadline acknowledging the user's achievement: "Great job on your first video! Upgrade to unlock the full experience."
- A feature comparison showing what free tier includes vs. what paid includes:
    - Duration: "1 minute" vs. "Up to 10 minutes"
    - Script iterations: "1 iteration" vs. "Up to 5 iterations"
    - Watermark: "Includes watermark" vs. "No watermark"
- Pricing cards showing the credit package options
- Primary CTA button: "Purchase Credits" linking to the credit purchase page
- Secondary dismiss button: "Maybe Later"

The component uses the existing `Dialog`, `Card`, `Button`, `Badge` UI components and follows the established modal dialog patterns.

```tsx
interface ConversionPromptDialogProps {
    open: boolean;
    onDismiss: () => void;
    pricing: {
        credits: Array<{ amount: number; price: string; perCredit: string }>;
        currency: string;
    };
    limitations: {
        free: { max_duration_seconds: number; max_script_iterations: number };
        paid: { max_duration_seconds: number; max_script_iterations: number };
    };
}
```

### 6. Frontend: Integration with Download Action

The conversion prompt is triggered after a successful video download. The video download action (which will be built in a later epic for the full rendering pipeline) will call the `check` endpoint after the download completes. For now, the integration point is set up in the video show/detail page:

- After a download button click and successful download, make a GET request to the `conversion-prompt.check` endpoint
- If `showPrompt` is true, open the `ConversionPromptDialog` with the returned pricing and limitations data
- When the user dismisses the prompt ("Maybe Later"), call the `dismiss` endpoint and close the dialog
- When the user clicks "Purchase Credits", navigate to the credit purchase page (route from E007)

### 7. Video Model Updates

Add `conversion_prompt_shown` to the Video model:

```php
// Add to $fillable
'conversion_prompt_shown',

// Add to casts()
'conversion_prompt_shown' => 'boolean',

// Helper method
public function shouldShowConversionPrompt(): bool
{
    return $this->is_free_tier && ! $this->conversion_prompt_shown;
}
```

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Models/Video.php` -- Video model (created by E006-F003, modified by E008-F001). Must add `conversion_prompt_shown` to fillable, casts, and add helper method.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/VideoFactory.php` -- Video factory. Must add `conversion_prompt_shown` default.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/VideoController.php` -- Video controller (created by E006-F003). Referenced for understanding video routes and patterns.
- `/Users/young/Nextcloud/dev/Itervel/config/video.php` -- Video config (created by E008-F001). Must add `pricing` section.
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Web routes. Must add conversion prompt routes.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/dialog.tsx` -- Dialog UI component. Used by the conversion prompt modal.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/card.tsx` -- Card component. Used for pricing cards.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/button.tsx` -- Button component. Used for CTA and dismiss buttons.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/badge.tsx` -- Badge component. Used for labels on pricing cards.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/lib/utils.ts` -- Contains `cn()` utility for conditional classes.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/index.ts` -- TypeScript types.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Controller.php` -- Base controller class.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/FreeTierVideoTest.php` -- Existing free-tier tests. Referenced for test patterns.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/database/migrations/YYYY_MM_DD_HHMMSS_add_conversion_prompt_shown_to_videos_table.php` -- Migration to add `conversion_prompt_shown` boolean to videos table.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/ConversionPromptController.php` -- Controller with `check()` and `dismiss()` methods for the conversion prompt.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/conversion-prompt-dialog.tsx` -- React component rendering the conversion prompt modal with feature comparison and pricing.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/ConversionPromptTest.php` -- Pest feature tests for the conversion prompt endpoints and logic.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: conversion-backend-dev
    - Role: Creates the migration, ConversionPromptController, routes, updates Video model and factory, and adds pricing config
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: conversion-frontend-dev
    - Role: Creates the conversion-prompt-dialog component with feature comparison, pricing cards, and CTA buttons
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: conversion-test-dev
    - Role: Writes Pest feature tests for the conversion prompt check and dismiss endpoints, authorization, and prompt visibility logic
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: conversion-reviewer
    - Role: Validates the complete conversion prompt implementation against acceptance criteria, runs all tests, checks types, and verifies code quality
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Create Migration and Update Video Model

- **Task ID**: create-migration-and-model
- **Depends On**: none
- **Assigned To**: conversion-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- Create a migration: `php artisan make:migration add_conversion_prompt_shown_to_videos_table --table=videos --no-interaction`
- In the migration `up()`: `$table->boolean('conversion_prompt_shown')->default(false)->after('is_free_tier');`
- In the migration `down()`: `$table->dropColumn('conversion_prompt_shown');`
- Update `/Users/young/Nextcloud/dev/Itervel/app/Models/Video.php`:
    - Add `'conversion_prompt_shown'` to `$fillable`
    - Add `'conversion_prompt_shown' => 'boolean'` to `casts()`
    - Add `shouldShowConversionPrompt(): bool` method that returns `$this->is_free_tier && ! $this->conversion_prompt_shown`
- Update `/Users/young/Nextcloud/dev/Itervel/database/factories/VideoFactory.php`:
    - Add `'conversion_prompt_shown' => false` to `definition()`
- Update `/Users/young/Nextcloud/dev/Itervel/config/video.php`:
    - Add a `'pricing'` key with an array of credit packages: `['credits' => [['amount' => 5, 'price' => '$9.99', 'per_credit' => '$2.00'], ['amount' => 15, 'price' => '$24.99', 'per_credit' => '$1.67'], ['amount' => 50, 'price' => '$69.99', 'per_credit' => '$1.40']], 'currency' => 'USD']`
- Run migration: `php artisan migrate --no-interaction`
- Run `vendor/bin/pint --dirty`

### 2. Create ConversionPromptController and Routes

- **Task ID**: create-controller-and-routes
- **Depends On**: create-migration-and-model
- **Assigned To**: conversion-backend-dev
- **Agent Type**: coder
- **Parallel**: false
- Create the controller: `php artisan make:controller ConversionPromptController --no-interaction`
- Edit `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/ConversionPromptController.php`:
    - Add a `check(Request $request, Video $video): JsonResponse` method:
        - Verify `$video->user_id === $request->user()->id` (abort 403 if not)
        - Determine `$shouldShow = $video->shouldShowConversionPrompt()`
        - Return JSON with `showPrompt`, `pricing` (from config), and `limitations` (free_tier and paid config values)
    - Add a `dismiss(Request $request, Video $video): JsonResponse` method:
        - Verify ownership (abort 403 if not)
        - Update video: `$video->update(['conversion_prompt_shown' => true])`
        - Return JSON with `success: true`
- Update `/Users/young/Nextcloud/dev/Itervel/routes/web.php`:
    - Add `use App\Http\Controllers\ConversionPromptController;` import
    - Add routes inside the auth+verified middleware group:
        ```php
        Route::get('videos/{video}/conversion-prompt', [ConversionPromptController::class, 'check'])
            ->name('videos.conversion-prompt.check');
        Route::post('videos/{video}/conversion-prompt/dismiss', [ConversionPromptController::class, 'dismiss'])
            ->name('videos.conversion-prompt.dismiss');
        ```
- Run `vendor/bin/pint --dirty`
- Run existing tests: `php artisan test --compact`

### 3. Create Conversion Prompt Frontend Component

- **Task ID**: create-frontend-component
- **Depends On**: create-controller-and-routes
- **Assigned To**: conversion-frontend-dev
- **Agent Type**: coder
- **Parallel**: false
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/components/conversion-prompt-dialog.tsx`:
    - Import `Dialog`, `DialogContent`, `DialogHeader`, `DialogTitle`, `DialogDescription`, `DialogFooter` from `@/components/ui/dialog`
    - Import `Button` from `@/components/ui/button`
    - Import `Card`, `CardContent`, `CardHeader`, `CardTitle` from `@/components/ui/card`
    - Import `Badge` from `@/components/ui/badge`
    - Import `Check`, `X`, `Sparkles`, `ArrowRight` from `lucide-react`
    - Import `Link` from `@inertiajs/react`
    - Define `ConversionPromptDialogProps` interface:
        ```tsx
        interface ConversionPromptDialogProps {
            open: boolean;
            onDismiss: () => void;
            pricing: {
                credits: Array<{
                    amount: number;
                    price: string;
                    per_credit: string;
                }>;
                currency: string;
            };
            limitations: {
                free: {
                    max_duration_seconds: number;
                    max_script_iterations: number;
                    watermark: { enabled: boolean };
                };
                paid: {
                    max_duration_seconds: number;
                    max_script_iterations: number;
                    watermark: { enabled: boolean };
                };
            };
        }
        ```
    - Render a `Dialog` with `open` prop controlled by parent
    - Dialog content:
        - Header with `Sparkles` icon, title "Ready for full-length videos?", description "Great job on your first video! Upgrade to unlock the full experience."
        - Feature comparison section with two columns ("Free Tier" vs "Paid"):
            - Duration row: show free tier max (e.g., "1 minute") vs paid max (e.g., "Up to 10 minutes") with `Check`/`X` icons
            - Iterations row: show free tier max vs paid max
            - Watermark row: "Includes watermark" vs "No watermark"
        - Pricing cards section: render a horizontal row of `Card` components for each credit package. Each card shows the credit amount, total price, and per-credit price. Highlight the middle package with a `Badge` saying "Best Value" and a `border-primary` border
        - Footer with "Maybe Later" button (variant="ghost") calling `onDismiss`, and "Purchase Credits" button (variant="default") as a `Link` to the credit purchase page route (use a placeholder route like `/credits/purchase` since E007 builds the actual purchase flow)
    - Use Tailwind theme variables, `cn()`, and dark mode classes throughout
    - Ensure the dialog is accessible with proper ARIA labels
- Run `npm run types` to verify TypeScript
- Run `npm run lint` to check linting
- Run `npm run build` to verify build

### 4. Write Feature Tests

- **Task ID**: write-conversion-tests
- **Depends On**: create-controller-and-routes
- **Assigned To**: conversion-test-dev
- **Agent Type**: coder
- **Parallel**: true (can run in parallel with frontend)
- Create test file: `php artisan make:test ConversionPromptTest --pest --no-interaction`
- Write tests in `/Users/young/Nextcloud/dev/Itervel/tests/Feature/ConversionPromptTest.php`:
    - `test('conversion prompt check returns show for free tier video')` -- create a user and a free-tier video, GET the check endpoint, assert `showPrompt` is true and `pricing` is not null
    - `test('conversion prompt check returns no show for paid video')` -- create a non-free-tier video, assert `showPrompt` is false
    - `test('conversion prompt check returns no show after dismissal')` -- create a free-tier video with `conversion_prompt_shown` true, assert `showPrompt` is false
    - `test('conversion prompt can be dismissed')` -- POST to dismiss endpoint, assert video's `conversion_prompt_shown` is true
    - `test('dismissing conversion prompt is idempotent')` -- dismiss twice, no errors
    - `test('user cannot check conversion prompt for another user video')` -- create video owned by another user, assert 403
    - `test('user cannot dismiss conversion prompt for another user video')` -- assert 403
    - `test('guests cannot access conversion prompt endpoints')` -- assert redirect to login
    - `test('conversion prompt check returns pricing data')` -- verify the `pricing` response matches `config('video.pricing')`
    - `test('conversion prompt check returns limitation comparison')` -- verify `limitations` includes both `free` and `paid` config values
    - `test('shouldShowConversionPrompt returns true for free tier video not yet shown')` -- unit-style test on Video model method
    - `test('shouldShowConversionPrompt returns false for paid video')` -- assert false
    - `test('shouldShowConversionPrompt returns false after prompt shown')` -- assert false
- Run tests: `php artisan test tests/Feature/ConversionPromptTest.php --compact`
- Run `vendor/bin/pint --dirty`
- Run full test suite: `php artisan test --compact`

### 5. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-migration-and-model, create-controller-and-routes, create-frontend-component, write-conversion-tests
- **Assigned To**: conversion-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands
- Verify migration adds `conversion_prompt_shown` boolean column
- Verify `Video` model has `conversion_prompt_shown` in fillable, casts, and `shouldShowConversionPrompt()` method
- Verify `VideoFactory` includes `conversion_prompt_shown` default
- Verify `ConversionPromptController` has `check()` and `dismiss()` methods with proper authorization
- Verify routes exist for conversion prompt check and dismiss
- Verify `config/video.php` includes pricing data
- Verify `conversion-prompt-dialog.tsx` exists with feature comparison, pricing cards, and CTA buttons
- Verify the dialog supports dark mode and uses existing UI components
- Confirm all acceptance criteria are met

## Acceptance Criteria

- A `conversion_prompt_shown` boolean column exists on the `videos` table defaulting to `false`
- The conversion prompt check endpoint returns `showPrompt: true` only for free-tier videos that haven't been shown the prompt
- The conversion prompt check endpoint returns `showPrompt: false` for paid videos
- The conversion prompt check endpoint returns `showPrompt: false` for videos where the prompt was already shown
- Users can only check/dismiss conversion prompts for their own videos (403 for others)
- Guest users cannot access the conversion prompt endpoints (redirected to login)
- The `dismiss` endpoint marks the video's `conversion_prompt_shown` as true
- The conversion prompt dialog displays the headline "Ready for full-length videos?"
- The dialog shows a feature comparison between free tier and paid (duration, iterations, watermark)
- The dialog displays pricing information for credit packages
- The dialog has a "Purchase Credits" button that links to the credit purchase page
- The dialog has a "Maybe Later" dismiss button
- The `Video` model has a `shouldShowConversionPrompt()` helper method
- Pricing data is configurable via `config/video.php`
- All conversion prompt tests pass
- TypeScript compiles without errors (`npm run types`)
- ESLint passes (`npm run lint`)
- PHP formatting passes (`vendor/bin/pint --dirty`)
- Full test suite passes without regressions

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run conversion prompt tests
php artisan test tests/Feature/ConversionPromptTest.php --compact

# Run free-tier tests (ensure no regression)
php artisan test tests/Feature/FreeTierVideoTest.php --compact

# Run full test suite for regression check
php artisan test --compact

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

- The pricing data in `config/video.php` is placeholder data. The actual payment processing, Stripe integration, and purchase flow are handled by E007 (Payments and Transactions). The conversion prompt only displays the pricing information and links to the purchase page.
- The "Purchase Credits" button links to a route that doesn't exist yet (built in E007). Use a placeholder route path like `/credits/purchase` or the route name `credits.purchase` if it's defined. If the route doesn't exist at build time, the button should still render but may need its `href` updated when E007 is built.
- The conversion prompt is intentionally not aggressive -- it shows once per video, can be easily dismissed, and does not block the download. This respects the user's experience while still encouraging conversion.
- The `check` endpoint returns JSON rather than an Inertia response because it will be called asynchronously after a download action, not as a page navigation. This allows the frontend to fetch the prompt data without a full page reload.
- The feature comparison in the dialog dynamically reads from configuration rather than hardcoding values. If the free-tier or paid limits change in `config/video.php`, the comparison updates automatically.
- Authorization is handled inline with `abort_unless` rather than a Form Request or Policy because the logic is simple (owner check only). If the authorization becomes more complex in the future, it should be extracted into a `VideoPolicy`.
- All commands should be run inside the Docker container. Use `make shell` to enter the container, or prefix with `docker compose exec app`.

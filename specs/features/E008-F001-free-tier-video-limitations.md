# Feature: Free Tier Video Limitations

**Epic**: E008-free-tier-and-onboarding.md
**Feature**: E008-F001
**Epic depends on**: E002-user-authentication.md, E005-credits-and-billing.md
**Feature depends on**: E005-F002, E005-F003, E006-F003

## Task Description

Free Tier Video Limitations restricts the free trial video to demonstrate the product while encouraging upgrade. When a user creates a video using their free credit (granted via E005-F002), the resulting video is subject to specific limitations that differentiate it from paid videos: a watermark overlay, reduced maximum duration, and a single script iteration.

**What it does**: Restricts the free trial video to demonstrate the product while encouraging upgrade.

**Expected outcome**: The free video is limited to 1 minute maximum duration, has a maximum of 1 script iteration, and includes a watermark. The watermark reads "Made with Itervel" in the bottom right at 50% opacity.

These limitations are enforced at the application level through a configuration-driven approach. A `VideoLimits` value object encapsulates the constraints for free vs. paid videos. The `Video` model gains an `is_free_tier` boolean column that is set to `true` when the video is created using the user's free credit (the registration bonus credit from E005-F002). The limitations are applied at key points in the video creation pipeline:

1. **Duration limit**: Enforced during video configuration/rendering by capping the video length at 60 seconds for free-tier videos.
2. **Script iteration limit**: Enforced during the script generation step by allowing only 1 iteration for free-tier videos (paid videos get multiple iterations for refinement).
3. **Watermark**: Applied during video rendering by overlaying "Made with Itervel" text in the bottom-right corner at 50% opacity.

Since the video rendering pipeline (E014) does not yet exist, this feature builds the **limitation infrastructure** -- the data model, configuration, helper methods, and validation rules -- that the rendering pipeline will consume. The frontend also needs to inform users about these limitations before and during the free video creation.

**Dependency on E005-F002 (Free Credit for New Users)**: Provides the `video_credits` column, credit helper methods, and the free registration credit. The `is_free_tier` flag is determined by checking if the credit being used is the registration bonus.

**Dependency on E005-F003 (Credit Deduction on Completion)**: Provides the `CreditService` and `CreditTransaction` model. The free-tier flag can be correlated with the transaction type.

**Dependency on E006-F003 (Topic Input)**: Creates the `Video` model, migration, factory, `VideoController`, and the video creation page. This feature adds the `is_free_tier` column and limitation logic to that model.

## Objective

Add free-tier video limitation infrastructure to the application: an `is_free_tier` boolean column on the `videos` table, a `VideoLimits` configuration class that defines constraints for free vs. paid videos, helper methods on the `Video` model to check and apply limitations, frontend display of free-tier restrictions on the video creation page, and comprehensive tests. When complete, the video creation pipeline will have all the data and configuration needed to enforce duration limits, script iteration limits, and watermark application for free-tier videos.

## Solution Approach

### 1. Configuration: Video Limits

Create a configuration file `config/video.php` that defines the limits for free and paid videos:

```php
return [
    'free_tier' => [
        'max_duration_seconds' => 60,
        'max_script_iterations' => 1,
        'watermark' => [
            'enabled' => true,
            'text' => 'Made with Itervel',
            'position' => 'bottom-right',
            'opacity' => 0.5,
        ],
    ],
    'paid' => [
        'max_duration_seconds' => 600, // 10 minutes
        'max_script_iterations' => 5,
        'watermark' => [
            'enabled' => false,
            'text' => '',
            'position' => '',
            'opacity' => 0,
        ],
    ],
];
```

### 2. Database: Add `is_free_tier` Column

Add a migration to add `is_free_tier` to the `videos` table:

```php
Schema::table('videos', function (Blueprint $table) {
    $table->boolean('is_free_tier')->default(false)->after('status');
});
```

### 3. Video Model Updates

Update the `Video` model to include:

```php
// Add to $fillable
'is_free_tier',

// Add to casts()
'is_free_tier' => 'boolean',

// Helper methods
public function isFreeTier(): bool
{
    return $this->is_free_tier;
}

public function getMaxDurationSeconds(): int
{
    $tier = $this->is_free_tier ? 'free_tier' : 'paid';
    return config("video.{$tier}.max_duration_seconds");
}

public function getMaxScriptIterations(): int
{
    $tier = $this->is_free_tier ? 'free_tier' : 'paid';
    return config("video.{$tier}.max_script_iterations");
}

public function getWatermarkConfig(): array
{
    $tier = $this->is_free_tier ? 'free_tier' : 'paid';
    return config("video.{$tier}.watermark");
}

public function hasWatermark(): bool
{
    return $this->getWatermarkConfig()['enabled'];
}
```

### 4. VideoFactory Update

Update the `VideoFactory` to include the `is_free_tier` default and a state:

```php
// In definition()
'is_free_tier' => false,

// New state
public function freeTier(): static
{
    return $this->state(fn (array $attributes) => [
        'is_free_tier' => true,
    ]);
}
```

### 5. VideoController Update

When creating a video, determine if it should be flagged as free tier. The logic checks whether the credit being used is from the user's registration bonus. A simple heuristic: if the user has never completed a paid video before and is using their initial free credit, the video is free-tier.

A cleaner approach: check the `CreditTransaction` history. If the user has only received a `registration_bonus` type credit and no `purchase` type credits, the video is free-tier. Alternatively, the simplest approach: check if the user's total credit transactions of type `purchase` is zero -- meaning they've only ever had the free registration credit.

```php
// In VideoController store method
$isFreeTier = ! $user->creditTransactions()
    ->where('type', 'purchase')
    ->exists();

$video = Video::create([
    // ... existing fields
    'is_free_tier' => $isFreeTier,
]);
```

### 6. Frontend: Free Tier Indicators

Update the video creation page (from E006-F003) to display free-tier limitations when the user is creating a free-tier video. Add visual indicators:

- A banner/alert at the top of the video creation page: "Free Trial Video" with a list of limitations (1-minute max, 1 script iteration, includes watermark)
- The duration selector (when it exists in later epics) should be capped at 60 seconds
- Show the watermark preview text in a small overlay on any video preview

Create a `free-tier-banner.tsx` component:

```tsx
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Clock, Repeat, Stamp } from 'lucide-react';

interface FreeTierBannerProps {
    maxDuration: number;
    maxIterations: number;
}
```

### 7. Backend: Share Free Tier Status with Frontend

When rendering the video creation page, pass the free-tier status and limits:

```php
return Inertia::render('videos/create', [
    // ... existing props
    'isFreeTier' => $isFreeTier,
    'videoLimits' => [
        'maxDurationSeconds' => config("video.{$tier}.max_duration_seconds"),
        'maxScriptIterations' => config("video.{$tier}.max_script_iterations"),
        'hasWatermark' => config("video.{$tier}.watermark.enabled"),
    ],
]);
```

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` -- User model with credit helper methods (from E005-F002). Used to check credit history for free-tier determination.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/Video.php` -- Video model (created by E006-F003). Must add `is_free_tier` column, fillable, cast, and helper methods.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/VideoFactory.php` -- Video factory (created by E006-F003). Must add `is_free_tier` default and `freeTier()` state.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/VideoController.php` -- Video controller (created by E006-F003). Must update `store()` method to set `is_free_tier` flag.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/StoreVideoRequest.php` -- Video store request (created by E006-F003). Referenced for validation context.
- `/Users/young/Nextcloud/dev/Itervel/app/Services/CreditService.php` -- Credit service (created by E005-F003). Referenced for understanding credit transaction types.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/CreditTransaction.php` -- Credit transaction model (created by E005-F003). Used to determine if user has purchased credits.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/videos/create.tsx` -- Video creation page (created by E006-F003). Must add free-tier banner and limitation indicators.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/alert.tsx` -- Alert UI component for the free-tier banner.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/badge.tsx` -- Badge component for labeling free-tier videos.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/lib/utils.ts` -- Contains `cn()` utility for conditional classes.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/index.ts` -- TypeScript type definitions. May need Video type updates.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration for tests.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/config/video.php` -- Configuration file defining free-tier and paid video limits (duration, iterations, watermark settings).
- `/Users/young/Nextcloud/dev/Itervel/database/migrations/YYYY_MM_DD_HHMMSS_add_is_free_tier_to_videos_table.php` -- Migration to add `is_free_tier` boolean column to the `videos` table.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/free-tier-banner.tsx` -- React component displaying free-tier limitations (duration cap, iteration limit, watermark notice) on the video creation page.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/FreeTierVideoTest.php` -- Pest feature tests for free-tier video creation and limitation logic.
- `/Users/young/Nextcloud/dev/Itervel/tests/Unit/VideoLimitsTest.php` -- Pest unit tests for the Video model's limit helper methods and configuration.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: free-tier-backend-dev
    - Role: Creates the video config file, database migration, updates the Video model with free-tier helpers, updates VideoFactory, and updates VideoController to set the free-tier flag
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: free-tier-frontend-dev
    - Role: Creates the free-tier-banner component, updates the video creation page to display free-tier limitations, and updates TypeScript types
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: free-tier-test-dev
    - Role: Writes comprehensive Pest feature and unit tests for free-tier video creation, limit helpers, and configuration
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: free-tier-reviewer
    - Role: Validates the complete free-tier limitations implementation against acceptance criteria, runs all tests, checks types, and verifies code quality
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Create Video Configuration and Database Migration

- **Task ID**: create-config-and-migration
- **Depends On**: none
- **Assigned To**: free-tier-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- Create `/Users/young/Nextcloud/dev/Itervel/config/video.php` with `free_tier` and `paid` arrays containing `max_duration_seconds`, `max_script_iterations`, and `watermark` (with `enabled`, `text`, `position`, `opacity` keys). Free tier: 60s duration, 1 iteration, watermark enabled with "Made with Itervel" at bottom-right 50% opacity. Paid: 600s duration, 5 iterations, watermark disabled.
- Create a migration using `php artisan make:migration add_is_free_tier_to_videos_table --table=videos --no-interaction` inside Docker container
- In the migration `up()`: `$table->boolean('is_free_tier')->default(false)->after('status');`
- In the migration `down()`: `$table->dropColumn('is_free_tier');`
- Update `/Users/young/Nextcloud/dev/Itervel/app/Models/Video.php`:
    - Add `'is_free_tier'` to the `$fillable` array
    - Add `'is_free_tier' => 'boolean'` to the `casts()` method
    - Add `isFreeTier(): bool` method returning `$this->is_free_tier`
    - Add `getMaxDurationSeconds(): int` method that returns `config('video.free_tier.max_duration_seconds')` or `config('video.paid.max_duration_seconds')` based on `$this->is_free_tier`
    - Add `getMaxScriptIterations(): int` method with same tier-based logic
    - Add `getWatermarkConfig(): array` method returning the watermark config for the appropriate tier
    - Add `hasWatermark(): bool` method returning `$this->getWatermarkConfig()['enabled']`
- Update `/Users/young/Nextcloud/dev/Itervel/database/factories/VideoFactory.php`:
    - Add `'is_free_tier' => false` to `definition()` return array
    - Add a `freeTier()` state method returning `$this->state(fn (array $attributes) => ['is_free_tier' => true])`
- Run migration: `php artisan migrate --no-interaction`
- Run `vendor/bin/pint --dirty`

### 2. Update VideoController for Free Tier Detection

- **Task ID**: update-controller-free-tier
- **Depends On**: create-config-and-migration
- **Assigned To**: free-tier-backend-dev
- **Agent Type**: coder
- **Parallel**: false
- Update `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/VideoController.php`:
    - In the `store()` method, before creating the video, determine free-tier status by checking if the user has any `purchase` type credit transactions: `$isFreeTier = ! $user->creditTransactions()->where('type', 'purchase')->exists();`
    - Pass `'is_free_tier' => $isFreeTier` when creating the video
    - In the `create()` method, determine free-tier status and pass it along with video limits to the Inertia render: `'isFreeTier' => $isFreeTier`, `'videoLimits' => ['maxDurationSeconds' => config("video.{$tier}.max_duration_seconds"), 'maxScriptIterations' => config("video.{$tier}.max_script_iterations"), 'hasWatermark' => config("video.{$tier}.watermark.enabled")]`
- Run `vendor/bin/pint --dirty`
- Run existing video tests: `php artisan test --compact --filter=Video`

### 3. Create Free Tier Frontend Components

- **Task ID**: create-frontend-components
- **Depends On**: update-controller-free-tier
- **Assigned To**: free-tier-frontend-dev
- **Agent Type**: coder
- **Parallel**: false
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/components/free-tier-banner.tsx`:
    - Import `Alert`, `AlertDescription`, `AlertTitle` from `@/components/ui/alert`
    - Import `Badge` from `@/components/ui/badge`
    - Import icons from `lucide-react`: `Clock`, `Repeat`, `Stamp` (or `Droplets`)
    - Define `FreeTierBannerProps` interface with `maxDurationSeconds: number`, `maxIterations: number`, `hasWatermark: boolean`
    - Render an `Alert` with title "Free Trial Video" and a `Badge` showing "Free"
    - List the three limitations with icons:
        - Clock icon: "Maximum duration: {maxDurationSeconds / 60} minute"
        - Repeat icon: "Script iterations: {maxIterations}"
        - Stamp icon: "Includes 'Made with Itervel' watermark"
    - Use Tailwind theme variables and `cn()` for styling
    - Support dark mode
- Update `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/videos/create.tsx`:
    - Import the `FreeTierBanner` component
    - Accept `isFreeTier` and `videoLimits` from page props
    - Conditionally render `<FreeTierBanner>` at the top of the form when `isFreeTier` is true
    - Update TypeScript types for the page props to include `isFreeTier: boolean` and `videoLimits: { maxDurationSeconds: number; maxScriptIterations: number; hasWatermark: boolean }`
- Run `npm run types` to verify TypeScript
- Run `npm run lint` to check linting
- Run `npm run build` to verify the build succeeds

### 4. Write Tests

- **Task ID**: write-free-tier-tests
- **Depends On**: create-config-and-migration
- **Assigned To**: free-tier-test-dev
- **Agent Type**: coder
- **Parallel**: true (can run in parallel with frontend since tests only need backend)
- Create unit test file: `php artisan make:test VideoLimitsTest --pest --unit --no-interaction`
- Write unit tests in `/Users/young/Nextcloud/dev/Itervel/tests/Unit/VideoLimitsTest.php`:
    - `test('free tier video returns correct max duration')` -- create a free-tier video, assert `getMaxDurationSeconds()` returns 60
    - `test('paid video returns correct max duration')` -- create a non-free-tier video, assert `getMaxDurationSeconds()` returns 600
    - `test('free tier video returns correct max script iterations')` -- assert `getMaxScriptIterations()` returns 1
    - `test('paid video returns correct max script iterations')` -- assert returns 5
    - `test('free tier video has watermark')` -- assert `hasWatermark()` returns true
    - `test('paid video does not have watermark')` -- assert `hasWatermark()` returns false
    - `test('free tier watermark config has correct values')` -- assert text is "Made with Itervel", position is "bottom-right", opacity is 0.5
    - `test('isFreeTier returns true for free tier video')` -- assert `isFreeTier()` returns true
    - `test('isFreeTier returns false for paid video')` -- assert returns false
- Create feature test file: `php artisan make:test FreeTierVideoTest --pest --no-interaction`
- Write feature tests in `/Users/young/Nextcloud/dev/Itervel/tests/Feature/FreeTierVideoTest.php`:
    - `test('video created with free credit is marked as free tier')` -- create a user with only registration bonus credit, create a video, assert `is_free_tier` is true
    - `test('video created after purchasing credits is not free tier')` -- create a user with a purchase credit transaction, create a video, assert `is_free_tier` is false
    - `test('video creation page shows free tier banner for free users')` -- GET the video creation page as a user with no purchases, assert Inertia props include `isFreeTier: true`
    - `test('video creation page does not show free tier banner for paid users')` -- assert `isFreeTier: false` for users with purchases
    - `test('video creation page includes video limits')` -- assert `videoLimits` prop contains expected values
    - `test('free tier factory state works correctly')` -- create video with `freeTier()` state, assert `is_free_tier` is true
- Run tests: `php artisan test tests/Unit/VideoLimitsTest.php tests/Feature/FreeTierVideoTest.php --compact`
- Run `vendor/bin/pint --dirty`
- Run full test suite: `php artisan test --compact`

### 5. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-config-and-migration, update-controller-free-tier, create-frontend-components, write-free-tier-tests
- **Assigned To**: free-tier-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands
- Verify `config/video.php` exists with correct free_tier and paid configurations
- Verify migration adds `is_free_tier` boolean column with `default(false)`
- Verify `Video` model has `is_free_tier` in `$fillable`, `casts()`, and all helper methods
- Verify `VideoFactory` has `freeTier()` state
- Verify `VideoController` determines free-tier status based on credit transaction history
- Verify `free-tier-banner.tsx` component renders limitation info
- Verify video creation page conditionally shows the banner
- Confirm all acceptance criteria are met

## Acceptance Criteria

- A `config/video.php` configuration file exists with free-tier limits: 60s max duration, 1 max script iteration, watermark enabled with "Made with Itervel" at bottom-right 50% opacity
- The `videos` table has an `is_free_tier` boolean column defaulting to `false`
- Videos created by users who have only the free registration credit are automatically flagged as `is_free_tier = true`
- Videos created by users who have purchased credits are flagged as `is_free_tier = false`
- The `Video` model provides `isFreeTier()`, `getMaxDurationSeconds()`, `getMaxScriptIterations()`, `getWatermarkConfig()`, and `hasWatermark()` helper methods
- Free-tier videos return 60 for max duration, 1 for max iterations, and true for watermark
- Paid videos return 600 for max duration, 5 for max iterations, and false for watermark
- The video creation page displays a free-tier limitation banner when the user is creating a free-tier video
- The banner shows the three limitations: duration cap, iteration limit, and watermark notice
- The `VideoFactory` includes a `freeTier()` state for testing
- All unit tests for video limit helpers pass
- All feature tests for free-tier video creation pass
- TypeScript compiles without errors (`npm run types`)
- ESLint passes (`npm run lint`)
- PHP formatting passes (`vendor/bin/pint --dirty`)
- Full test suite passes without regressions

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run free-tier unit tests
php artisan test tests/Unit/VideoLimitsTest.php --compact

# Run free-tier feature tests
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

- The watermark is defined in configuration only at this stage. The actual watermark rendering (overlaying text on the video) will be implemented in the video rendering pipeline (E014). This feature provides the configuration and data that the renderer will read.
- Duration and script iteration limits are similarly configuration-only for now. The enforcement will happen in the script generation feature (E009) for iterations and the rendering pipeline (E014) for duration.
- The free-tier detection uses credit transaction history rather than a simple flag because it's more accurate. A user who received a free credit AND purchased credits should get paid-tier features even when using their remaining free credit. The presence of any `purchase` type transaction means the user is a paying customer.
- The `config/video.php` values can be adjusted without code changes, making it easy to modify limits during A/B testing or as the product evolves.
- All commands should be run inside the Docker container. Use `make shell` to enter the container, or prefix with `docker compose exec app`.

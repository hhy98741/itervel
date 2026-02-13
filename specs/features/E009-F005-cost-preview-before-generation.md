# Feature: Cost Preview Before Generation

**Epic**: E009-ai-configuration-and-title-generation.md
**Feature**: E009-F005
**Epic depends on**: E006-brand-guide-and-video-input.md
**Feature depends on**: E009-F001, E009-F002

## Task Description

Cost Preview Before Generation shows the user an estimated cost (in credits) for their video before they start the generation process. This is the final confirmation step before committing credits, giving users full transparency about what their video will cost based on their configuration choices.

**What it does**: Shows the user an estimated cost (in credits) for their video before they start the generation process.

**Expected outcome**: Based on the selected AI models, number of iterations, and video length, the user sees how many credits the video will cost before committing.

This feature creates a cost preview page in the video creation wizard that sits between the AI configuration step (E009-F001/F002) and the title generation step (E009-F003). It calculates the total credit cost based on:

- The AI model selected for each step (different models have different cost multipliers)
- The number of script iterations (more iterations = higher cost)
- A base credit cost per generation step

The cost is calculated by a `CostCalculationService` that uses the video's `ai_config` and the cost data from `config/ai.php`. The page shows a detailed breakdown of costs per step and a total, along with the user's current credit balance and whether they have sufficient credits. If insufficient, a prompt to purchase more credits is shown.

**Dependency on E009-F001 (AI Model Selection)**: Provides the `ai_config` on the video with model selections and the `config/ai.php` with cost multipliers and base costs per step.

**Dependency on E009-F002 (Script Iteration Configuration)**: Provides the iteration count in `ai_config.script_iterations` which affects the critique step cost.

## Objective

Create a cost preview page that displays a detailed cost breakdown before the user commits to video generation: a `CostCalculationService` that computes per-step and total credit costs from the video's AI configuration, a cost preview page showing the breakdown with the user's credit balance, a "Start Generation" button that checks for sufficient credits before proceeding, and a prompt to purchase credits if the balance is insufficient. When complete, users have full cost transparency before any credits are deducted.

## Solution Approach

### 1. CostCalculationService

Create `app/Services/CostCalculationService.php`:

```php
namespace App\Services;

use App\Models\Video;

class CostCalculationService
{
    public function calculate(Video $video): array
    {
        $aiConfig = $video->ai_config ?? [];
        $useRecommended = $aiConfig['use_recommended'] ?? true;
        $iterations = $aiConfig['script_iterations'] ?? config('ai.defaults.script_iterations');
        $steps = config('ai.steps');
        $models = config('ai.models');

        $breakdown = [];
        $total = 0;

        foreach ($steps as $stepKey => $step) {
            $modelKey = $useRecommended
                ? $step['default_model']
                : ($aiConfig['models'][$stepKey] ?? $step['default_model']);

            $model = $models[$modelKey] ?? $models['sonnet'];
            $baseCost = $step['base_credit_cost'];
            $multiplier = $model['cost_multiplier'];

            // Critique step cost is multiplied by iterations
            $quantity = $stepKey === 'critique' ? $iterations : 1;
            $stepCost = round($baseCost * $multiplier * $quantity, 2);

            $breakdown[] = [
                'step' => $stepKey,
                'name' => $step['name'],
                'model' => $model['name'],
                'model_key' => $modelKey,
                'base_cost' => $baseCost,
                'multiplier' => $multiplier,
                'quantity' => $quantity,
                'cost' => $stepCost,
            ];

            $total += $stepCost;
        }

        return [
            'breakdown' => $breakdown,
            'total' => round($total, 2),
            'total_credits' => (int) ceil($total), // Round up to whole credits
        ];
    }
}
```

### 2. CostPreviewController

```php
namespace App\Http\Controllers;

use App\Models\Video;
use App\Services\CostCalculationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CostPreviewController extends Controller
{
    public function show(Video $video, CostCalculationService $costService): Response
    {
        abort_unless($video->user_id === auth()->id(), 403);

        $costData = $costService->calculate($video);
        $user = auth()->user();

        return Inertia::render('videos/cost-preview', [
            'video' => $video->only(['id', 'topic', 'status', 'ai_config', 'is_free_tier']),
            'costBreakdown' => $costData['breakdown'],
            'totalCost' => $costData['total'],
            'totalCredits' => $costData['total_credits'],
            'userCredits' => $user->video_credits,
            'hasSufficientCredits' => $user->hasSufficientCredits($costData['total_credits']),
        ]);
    }

    public function confirm(Video $video, CostCalculationService $costService): RedirectResponse
    {
        abort_unless($video->user_id === auth()->id(), 403);

        $costData = $costService->calculate($video);
        $user = auth()->user();

        if (! $user->hasSufficientCredits($costData['total_credits'])) {
            return back()->withErrors([
                'credits' => 'Insufficient credits. You need ' . $costData['total_credits'] . ' credits but have ' . $user->video_credits . '.',
            ]);
        }

        // Store the estimated cost on the video for later deduction
        $video->update(['estimated_cost' => $costData['total_credits']]);

        return to_route('videos.titles.generate', $video);
    }
}
```

### 3. Database: Add Estimated Cost Column

```php
Schema::table('videos', function (Blueprint $table) {
    $table->unsignedInteger('estimated_cost')->nullable()->after('selected_title');
});
```

### 4. Routes

```php
Route::get('videos/{video}/cost-preview', [CostPreviewController::class, 'show'])->name('videos.cost-preview');
Route::post('videos/{video}/confirm-generation', [CostPreviewController::class, 'confirm'])->name('videos.confirm-generation');
```

### 5. Frontend: Cost Preview Page

Create `resources/js/pages/videos/cost-preview.tsx` with:

- A heading: "Cost Preview"
- A description: "Review the estimated credit cost for your video before starting generation."
- A cost breakdown table/card list:
    - Each row shows: step name, selected AI model name, quantity (1 for most, iteration count for critique), and credit cost
    - The critique row shows "× {iterations}" to indicate the iteration multiplier
    - Use alternating row styling or a clean table layout
- A total cost summary highlighted in a card:
    - "Estimated Total: {totalCredits} credits"
    - "Your Balance: {userCredits} credits"
    - If sufficient: green checkmark with "You have enough credits"
    - If insufficient: red warning with "You need {difference} more credits" and a "Purchase Credits" link
- A "Start Generation" button that POSTs to the confirm endpoint
    - Disabled when insufficient credits
    - Shows "Start Generation ({totalCredits} credits)" as button text
- A "Back to Configuration" link to go back and adjust settings
- Free-tier videos show "Free Trial Video" badge and always have sufficient credits (1 credit)

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Models/Video.php` -- Video model. Must add `estimated_cost` to fillable.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` -- User model. Has `video_credits`, `hasCredits()`, `hasSufficientCredits()` methods from E005-F002.
- `/Users/young/Nextcloud/dev/Itervel/config/ai.php` -- AI configuration (from E009-F001). Defines models with cost multipliers and steps with base costs.
- `/Users/young/Nextcloud/dev/Itervel/config/video.php` -- Video config (from E008-F001). Referenced for free-tier context.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/VideoConfigController.php` -- Config controller (from E009-F001). Must update redirect to go to cost-preview instead of titles.
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Must add cost preview routes.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/card.tsx` -- Card component.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/badge.tsx` -- Badge component.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/button.tsx` -- Button component.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/alert.tsx` -- Alert component for insufficient credits warning.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/separator.tsx` -- Separator component.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/heading.tsx` -- Heading component.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/app-layout.tsx` -- App layout.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/lib/utils.ts` -- `cn()` utility.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/ai.ts` -- AI types.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/app/Services/CostCalculationService.php` -- Service that calculates per-step and total credit costs from video AI configuration.
- `/Users/young/Nextcloud/dev/Itervel/database/migrations/YYYY_MM_DD_HHMMSS_add_estimated_cost_to_videos_table.php` -- Migration to add `estimated_cost` column.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/CostPreviewController.php` -- Controller with `show` and `confirm` methods.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/videos/cost-preview.tsx` -- Inertia React page showing cost breakdown, credit balance, and Start Generation button.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/CostPreviewTest.php` -- Pest feature tests for cost calculation, preview page, and generation confirmation.
- `/Users/young/Nextcloud/dev/Itervel/tests/Unit/CostCalculationTest.php` -- Pest unit tests for the CostCalculationService.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: cost-backend-dev
    - Role: Creates the CostCalculationService, CostPreviewController, migration, updates Video model, adds routes, and updates VideoConfigController redirect
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: cost-frontend-dev
    - Role: Creates the cost preview page with breakdown table, credit balance display, sufficient/insufficient states, and Start Generation button
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: cost-test-dev
    - Role: Writes Pest unit tests for CostCalculationService and feature tests for the preview page, confirmation, and insufficient credit handling
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: cost-reviewer
    - Role: Validates the complete cost preview implementation against acceptance criteria
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Create CostCalculationService, Migration, Controller, and Routes

- **Task ID**: create-cost-backend
- **Depends On**: none
- **Assigned To**: cost-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- Create `/Users/young/Nextcloud/dev/Itervel/app/Services/CostCalculationService.php`:
    - `calculate(Video $video): array` method that:
        - Reads `$video->ai_config` for model selections and iteration count
        - Iterates over `config('ai.steps')`, resolving each step's model
        - Calculates per-step cost: `base_credit_cost * model.cost_multiplier * quantity` (quantity is iteration count for critique step, 1 for others)
        - Returns array with `breakdown` (array of step cost objects), `total` (float sum), and `total_credits` (int, rounded up with `ceil()`)
    - Each breakdown item: `step`, `name`, `model`, `model_key`, `base_cost`, `multiplier`, `quantity`, `cost`
- Create migration: `php artisan make:migration add_estimated_cost_to_videos_table --table=videos --no-interaction`
    - `up()`: `$table->unsignedInteger('estimated_cost')->nullable()->after('selected_title');`
    - `down()`: `$table->dropColumn('estimated_cost');`
- Update `/Users/young/Nextcloud/dev/Itervel/app/Models/Video.php`:
    - Add `'estimated_cost'` to `$fillable`
- Update `/Users/young/Nextcloud/dev/Itervel/database/factories/VideoFactory.php`:
    - Add `'estimated_cost' => null` to `definition()`
- Create controller: `php artisan make:controller CostPreviewController --no-interaction`
- Edit `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/CostPreviewController.php`:
    - `show(Video $video, CostCalculationService $costService): Response` -- checks ownership, calculates cost, renders `videos/cost-preview` with breakdown, totals, user credits, and sufficiency flag
    - `confirm(Video $video, CostCalculationService $costService): RedirectResponse` -- checks ownership, verifies sufficient credits (return back with errors if not), stores `estimated_cost` on video, redirects to `videos.titles.generate`
- Edit `/Users/young/Nextcloud/dev/Itervel/routes/web.php`:
    - Add `use App\Http\Controllers\CostPreviewController;`
    - Add routes:
        ```php
        Route::get('videos/{video}/cost-preview', [CostPreviewController::class, 'show'])->name('videos.cost-preview');
        Route::post('videos/{video}/confirm-generation', [CostPreviewController::class, 'confirm'])->name('videos.confirm-generation');
        ```
- Update `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/VideoConfigController.php`:
    - Change redirect in `update()` from `videos.titles` to `videos.cost-preview`
- Run migration: `php artisan migrate --no-interaction`
- Run `vendor/bin/pint --dirty`
- Run existing tests: `php artisan test --compact`

### 2. Create Frontend Cost Preview Page

- **Task ID**: create-cost-page
- **Depends On**: create-cost-backend
- **Assigned To**: cost-frontend-dev
- **Agent Type**: coder
- **Parallel**: false
- Run `npm run build` to generate Wayfinder routes for CostPreviewController
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/videos/cost-preview.tsx`:
    - Import `Head`, `Link`, `router` from `@inertiajs/react`
    - Import UI components: `Card`, `CardContent`, `CardHeader`, `CardTitle`, `Badge`, `Button`, `Alert`, `AlertTitle`, `AlertDescription`, `Separator`
    - Import `Heading`, `AppLayout`
    - Import icons: `Check`, `AlertTriangle`, `ArrowLeft`, `Sparkles`, `Coins` from `lucide-react`
    - Import Wayfinder action for CostPreviewController
    - Define props type: `video`, `costBreakdown: CostBreakdownItem[]`, `totalCost: number`, `totalCredits: number`, `userCredits: number`, `hasSufficientCredits: boolean`
    - Define `CostBreakdownItem` type: `{ step: string; name: string; model: string; model_key: string; base_cost: number; multiplier: number; quantity: number; cost: number }`
    - Render:
        - Breadcrumbs: "Dashboard" > "Video" > "Cost Preview"
        - Heading: "Cost Preview" with description "Review the estimated credit cost before starting generation."
        - A `Card` containing a table/list of cost breakdown items:
            - Header row: "Step", "Model", "Qty", "Cost"
            - Each row shows: step name, model name badge, quantity (show "×{n}" for critique), cost formatted to 2 decimal places
            - Use alternating bg for rows (`even:bg-muted/50`)
            - `Separator` before total row
            - Total row: bold, "Estimated Total", "{totalCredits} credits"
        - A credit balance `Card`:
            - "Your Balance: {userCredits} credits"
            - If `hasSufficientCredits`: green `Check` icon with "You have enough credits"
            - If not: amber `AlertTriangle` icon with "You need {totalCredits - userCredits} more credits" and a `Button` linking to credits purchase page (placeholder route)
        - Footer actions:
            - "Back to Configuration" `Link` (variant="outline") to `videos.configure`
            - "Start Generation ({totalCredits} credits)" `Button` (variant="default") that POSTs to confirm-generation. Disabled when `!hasSufficientCredits` or when `processing`
    - Handle form errors (insufficient credits error from backend) with `usePage().props.errors`
    - Support dark mode
- Run `npm run types`
- Run `npm run lint`, fix with `npm run lint:fix`
- Run `npm run build`

### 3. Write Tests

- **Task ID**: write-cost-tests
- **Depends On**: create-cost-backend
- **Assigned To**: cost-test-dev
- **Agent Type**: coder
- **Parallel**: true (can run in parallel with frontend)
- Create unit test: `php artisan make:test CostCalculationTest --pest --unit --no-interaction`
- Write tests in `/Users/young/Nextcloud/dev/Itervel/tests/Unit/CostCalculationTest.php`:
    - `test('cost calculation with default config uses sonnet for all steps')` -- create video with `use_recommended: true`, assert all breakdown items have `model: 'Sonnet'` and `multiplier: 1.0`
    - `test('cost calculation with opus model has 3x multiplier')` -- set all models to opus, assert multiplier is 3.0
    - `test('cost calculation with haiku model has 0.5x multiplier')` -- set all models to haiku, assert multiplier is 0.5
    - `test('critique step cost is multiplied by iterations')` -- set 3 iterations, assert critique breakdown has `quantity: 3`
    - `test('total credits rounds up to nearest integer')` -- assert `total_credits` is `ceil($total)`
    - `test('zero iterations means no critique cost')` -- set iterations=0, assert critique cost is 0
    - `test('cost calculation handles missing ai_config gracefully')` -- video with null ai_config, assert defaults are used
    - `test('cost breakdown includes all 5 steps')` -- assert breakdown has 5 items for title, outline, script, critique, thumbnails
- Create feature test: `php artisan make:test CostPreviewTest --pest --no-interaction`
- Write tests in `/Users/young/Nextcloud/dev/Itervel/tests/Feature/CostPreviewTest.php`:
    - `test('cost preview page is displayed for video owner')` -- assert 200 and correct Inertia component
    - `test('cost preview page returns 403 for non-owner')` -- assert 403
    - `test('guests are redirected to login')` -- assert redirect
    - `test('cost preview shows breakdown and totals')` -- assert `costBreakdown`, `totalCost`, `totalCredits` props are present
    - `test('cost preview shows user credit balance')` -- assert `userCredits` prop matches user's `video_credits`
    - `test('cost preview shows sufficient credits flag')` -- user with enough credits, assert `hasSufficientCredits: true`
    - `test('cost preview shows insufficient credits flag')` -- user with 0 credits, assert `hasSufficientCredits: false`
    - `test('generation can be confirmed with sufficient credits')` -- POST confirm, assert redirect to `videos.titles.generate` and `estimated_cost` is set on video
    - `test('generation cannot be confirmed with insufficient credits')` -- user with 0 credits, POST confirm, assert back with error
    - `test('estimated cost is stored on video after confirmation')` -- assert `estimated_cost` column is populated
    - `test('free tier video always has sufficient credits')` -- free-tier video, user with 1 credit, assert sufficient
- Run tests: `php artisan test tests/Unit/CostCalculationTest.php tests/Feature/CostPreviewTest.php --compact`
- Run `vendor/bin/pint --dirty`
- Run full test suite: `php artisan test --compact`

### 4. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-cost-backend, create-cost-page, write-cost-tests
- **Assigned To**: cost-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands
- Verify `CostCalculationService` correctly calculates per-step costs with model multipliers
- Verify critique step cost is multiplied by iteration count
- Verify total credits rounds up to nearest integer
- Verify cost preview page shows breakdown table, totals, and credit balance
- Verify sufficient/insufficient credit states display correctly
- Verify "Start Generation" button is disabled when insufficient credits
- Verify confirmation redirects to title generation and stores estimated cost
- Verify configuration page now redirects to cost preview
- Confirm all acceptance criteria are met

## Acceptance Criteria

- A `CostCalculationService` calculates per-step credit costs based on the video's AI configuration
- Each step's cost is calculated as: `base_credit_cost × model.cost_multiplier × quantity`
- The critique step cost is multiplied by the iteration count
- The total credits value rounds up to the nearest whole number
- The cost preview page shows a detailed breakdown with step name, model, quantity, and cost
- The cost preview page shows the user's current credit balance
- When the user has sufficient credits, a green confirmation message is shown
- When the user has insufficient credits, a warning message with the credit deficit is shown
- The "Start Generation" button is disabled when the user lacks sufficient credits
- Clicking "Start Generation" stores the `estimated_cost` on the video and redirects to title generation
- Attempting to confirm with insufficient credits returns a validation error
- A "Back to Configuration" link allows adjusting settings
- The video configuration page (E009-F001) now redirects to cost preview instead of directly to titles
- All unit and feature tests pass
- TypeScript compiles without errors
- ESLint passes
- PHP formatting passes

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run cost calculation unit tests
php artisan test tests/Unit/CostCalculationTest.php --compact

# Run cost preview feature tests
php artisan test tests/Feature/CostPreviewTest.php --compact

# Run AI config tests (regression)
php artisan test tests/Feature/VideoConfigTest.php --compact

# Run full test suite
php artisan test --compact

# Verify routes
php artisan route:list --name=videos.cost

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

- The `total_credits` value uses `ceil()` to round up. This ensures the user is always charged at least as much as the actual cost. For example, a total of 0.8 credits rounds up to 1 credit.
- The `estimated_cost` is stored on the video at confirmation time, not at deduction time. The actual credit deduction (built in E005-F003) will use this stored value rather than recalculating, ensuring the user pays the amount they were previewed.
- The credit sufficiency check uses the `hasSufficientCredits()` method from E005-F002 on the User model. This method compares `video_credits >= amount`.
- The wizard flow after this feature: Topic → References → AI Config → **Cost Preview** → Title Generation → Title Selection. The cost preview acts as the "commit point" where the user agrees to spend credits.
- If the user goes back to reconfigure after seeing the cost preview, the preview recalculates on the next visit (it's not cached).
- The "Purchase Credits" link on the insufficient credits state is a placeholder that will link to the credit purchase page built in E007.
- All commands should be run inside the Docker container.

# Feature: Script Iteration Configuration

**Epic**: E009-ai-configuration-and-title-generation.md
**Feature**: E009-F002
**Epic depends on**: E006-brand-guide-and-video-input.md
**Feature depends on**: E009-F001

## Task Description

Script Iteration Configuration allows users to set how many AI critique-and-refinement rounds the script goes through (0-5 rounds). This enhances the AI configuration step (E009-F001) with a dedicated iteration control and informational UI.

**What it does**: Allows users to set how many AI critique-and-refinement rounds the script goes through (0-5 rounds).

**Expected outcome**: The user selects the number of iterations via a slider or dropdown. A tooltip explains what iterations do. The cost impact is shown. Free tier users are limited to 1 iteration. A warning appears at 3+ iterations about increased cost.

This feature builds on E009-F001 (AI Model Selection), which creates the video configuration page, the `ai_config` JSON column on videos, and the infrastructure for storing model selections. The `script_iterations` field is already stored in the `ai_config` JSON. This feature adds:

1. A **Slider UI component** (does not exist yet in the component library) for selecting iteration count
2. Enhanced iteration configuration UI on the video configuration page with tooltip explanations, cost impact display, and cost warnings
3. Free-tier iteration locking (capped at 1 iteration per E008-F001)
4. Visual cost impact indicators that update in real-time as the slider changes

**Dependency on E009-F001 (AI Model Selection)**: Creates the video configuration page, `ai_config` storage, and the foundational configuration infrastructure. The iteration count is part of the `ai_config` JSON structure established in F001.

## Objective

Add a Slider UI component to the component library and enhance the video configuration page with an iteration selection slider (0-5 rounds), a tooltip explaining what script iterations do, a real-time cost impact display showing additional credits per iteration, a warning badge when 3+ iterations are selected, and free-tier locking that caps iterations at 1. The iteration value is stored in the existing `ai_config.script_iterations` field.

## Solution Approach

### 1. Create Slider UI Component

Add a Slider component to the UI library following the existing Radix-based pattern. Install `@radix-ui/react-slider` and create a wrapper component:

```tsx
// resources/js/components/ui/slider.tsx
import * as React from 'react';
import * as SliderPrimitive from '@radix-ui/react-slider';
import { cn } from '@/lib/utils';

const Slider = React.forwardRef<
    React.ComponentRef<typeof SliderPrimitive.Root>,
    React.ComponentPropsWithoutRef<typeof SliderPrimitive.Root>
>(({ className, ...props }, ref) => (
    <SliderPrimitive.Root
        ref={ref}
        className={cn(
            'relative flex w-full touch-none items-center select-none',
            className,
        )}
        {...props}
    >
        <SliderPrimitive.Track className="relative h-1.5 w-full grow overflow-hidden rounded-full bg-primary/20">
            <SliderPrimitive.Range className="absolute h-full bg-primary" />
        </SliderPrimitive.Track>
        <SliderPrimitive.Thumb className="block size-4 rounded-full border border-primary/50 bg-background shadow transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50" />
    </SliderPrimitive.Root>
));
Slider.displayName = SliderPrimitive.Root.displayName;

export { Slider };
```

### 2. Iteration Configuration Section

Add a dedicated section to the video configuration page (`resources/js/pages/videos/configure.tsx`) below the model selection cards:

```tsx
// Iteration configuration section
<Card>
    <CardHeader>
        <div className="flex items-center gap-2">
            <CardTitle>Script Iterations</CardTitle>
            <Tooltip>
                <TooltipTrigger>
                    <HelpCircle className="size-4 text-muted-foreground" />
                </TooltipTrigger>
                <TooltipContent className="max-w-xs">
                    Each iteration sends the script through an AI
                    critique-and-refinement cycle. More iterations produce a
                    more polished script but increase cost and generation time.
                </TooltipContent>
            </Tooltip>
        </div>
    </CardHeader>
    <CardContent>
        <div className="space-y-4">
            <Slider
                value={[data.ai_config.script_iterations]}
                onValueChange={([val]) =>
                    setData('ai_config', {
                        ...data.ai_config,
                        script_iterations: val,
                    })
                }
                min={0}
                max={maxScriptIterations}
                step={1}
                disabled={isFreeTier}
            />
            <div className="flex justify-between text-sm text-muted-foreground">
                <span>0 (no refinement)</span>
                <span>{maxScriptIterations} (maximum)</span>
            </div>
            <div className="flex items-center gap-2">
                <span className="text-lg font-semibold">
                    {data.ai_config.script_iterations}
                </span>
                <span className="text-sm text-muted-foreground">
                    {data.ai_config.script_iterations === 1
                        ? 'iteration'
                        : 'iterations'}
                </span>
                {data.ai_config.script_iterations >= 3 && (
                    <Badge
                        variant="outline"
                        className="border-amber-500 text-amber-600"
                    >
                        Higher cost
                    </Badge>
                )}
            </div>
            {isFreeTier && (
                <p className="text-sm text-muted-foreground">
                    Free tier videos are limited to {maxScriptIterations}{' '}
                    iteration. Purchase credits to unlock more iterations.
                </p>
            )}
            <div className="text-sm text-muted-foreground">
                Cost impact: +{iterationCost.toFixed(1)} credits for{' '}
                {data.ai_config.script_iterations} iterations
            </div>
        </div>
    </CardContent>
</Card>
```

### 3. Cost Impact Calculation

The iteration cost is calculated in the component as:

```tsx
const critiqueModel = data.ai_config.use_recommended
    ? 'sonnet'
    : (data.ai_config.models?.critique ?? 'sonnet');
const critiqueCostMultiplier = aiModels[critiqueModel]?.cost_multiplier ?? 1.0;
const critiquBaseCost = aiSteps.critique.base_credit_cost;
const iterationCost =
    critiquBaseCost * critiqueCostMultiplier * data.ai_config.script_iterations;
```

### 4. Backend Validation Updates

Update the `UpdateVideoConfigRequest` (from E009-F001) to enforce free-tier iteration limits:

```php
public function rules(): array
{
    $video = $this->route('video');
    $maxIterations = $video->isFreeTier()
        ? config('video.free_tier.max_script_iterations')
        : 5;

    return [
        // ... existing rules
        'ai_config.script_iterations' => ['required', 'integer', 'min:0', "max:{$maxIterations}"],
    ];
}
```

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/videos/configure.tsx` -- Video configuration page (created by E009-F001). Must add iteration slider section.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/UpdateVideoConfigRequest.php` -- Form request (created by E009-F001). Must update to enforce free-tier iteration limits.
- `/Users/young/Nextcloud/dev/Itervel/config/ai.php` -- AI config (created by E009-F001). Referenced for step costs.
- `/Users/young/Nextcloud/dev/Itervel/config/video.php` -- Video config (from E008-F001). Referenced for free-tier max iterations.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/Video.php` -- Video model. Referenced for `isFreeTier()` and `getScriptIterations()`.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/checkbox.tsx` -- Existing Checkbox component. Radix-based pattern reference.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/tooltip.tsx` -- Existing Tooltip component. Used for iteration explanation.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/card.tsx` -- Card component. Used for iteration section wrapper.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/badge.tsx` -- Badge component. Used for cost warning.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/label.tsx` -- Label component.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/lib/utils.ts` -- `cn()` utility.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/ai.ts` -- AI TypeScript types (from E009-F001).
- `/Users/young/Nextcloud/dev/Itervel/package.json` -- NPM dependencies. Check if `@radix-ui/react-slider` is installed.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/VideoConfigTest.php` -- Existing AI config tests (from E009-F001). Must add iteration-specific tests.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/slider.tsx` -- Radix-based Slider UI component following the existing component library pattern.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/ScriptIterationConfigTest.php` -- Pest feature tests for iteration configuration, free-tier limits, and validation.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Frontend Developer
    - Name: iteration-frontend-dev
    - Role: Installs @radix-ui/react-slider, creates the Slider UI component, adds the iteration configuration section to the video configure page with tooltip, cost display, and warnings
    - Agent Type: coder
    - Resume: false

- Backend Developer
    - Name: iteration-backend-dev
    - Role: Updates the UpdateVideoConfigRequest to enforce free-tier iteration limits dynamically
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: iteration-test-dev
    - Role: Writes Pest feature tests for iteration configuration validation, free-tier limits, and boundary cases
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: iteration-reviewer
    - Role: Validates the complete iteration configuration against acceptance criteria
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Create Slider UI Component

- **Task ID**: create-slider-component
- **Depends On**: none
- **Assigned To**: iteration-frontend-dev
- **Agent Type**: coder
- **Parallel**: true
- Check if `@radix-ui/react-slider` is already in `package.json`. If not, install it: `npm install @radix-ui/react-slider`
- Read existing Radix-based UI components for pattern reference: `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/checkbox.tsx` and `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/toggle.tsx`
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/slider.tsx` following the exact Radix wrapper pattern used by other UI components. Export the `Slider` component with proper forwardRef, className merging via `cn()`, and Tailwind styling matching the project's theme variables (use `bg-primary`, `bg-primary/20`, `border-primary/50`, `bg-background`)
- Run `npm run types` to verify TypeScript
- Run `npm run build` to verify it compiles

### 2. Add Iteration Configuration to Video Configure Page

- **Task ID**: add-iteration-ui
- **Depends On**: create-slider-component
- **Assigned To**: iteration-frontend-dev
- **Agent Type**: coder
- **Parallel**: false
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/videos/configure.tsx` to understand the current page structure
- Add a "Script Iterations" section below the model selection cards:
    - Import `Slider` from `@/components/ui/slider`
    - Import `HelpCircle` from `lucide-react`
    - Import `Tooltip`, `TooltipContent`, `TooltipProvider`, `TooltipTrigger` from `@/components/ui/tooltip`
    - Add a `Card` wrapping the iteration controls
    - Card header: "Script Iterations" title with a `Tooltip`-wrapped `HelpCircle` icon that explains what iterations do ("Each iteration sends the script through an AI critique-and-refinement cycle. More iterations produce a more polished script but increase cost and generation time.")
    - Card content:
        - A `Slider` with `min={0}`, `max={maxScriptIterations}`, `step={1}`, `value={[data.ai_config.script_iterations]}`, `onValueChange` to update form data. `disabled={isFreeTier}` for free-tier users
        - Labels below the slider: "0 (no refinement)" on the left, "{maxScriptIterations} (maximum)" on the right
        - Current value display: "{N} iterations" with a "Higher cost" `Badge` (variant="outline" with amber styling) when N >= 3
        - Free-tier message: when `isFreeTier`, show text "Free tier videos are limited to 1 iteration. Purchase credits to unlock more iterations."
        - Cost impact line: "Cost impact: +{cost} credits for {N} iterations" calculated as `aiSteps.critique.base_credit_cost * critiqueModelMultiplier * iterations`
- Update the total cost summary (already in the page from F001) to include iteration cost
- Run `npm run types`
- Run `npm run lint`, fix with `npm run lint:fix`
- Run `npm run build`

### 3. Update Backend Validation for Free-Tier Limits

- **Task ID**: update-backend-validation
- **Depends On**: none
- **Assigned To**: iteration-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- Read `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/UpdateVideoConfigRequest.php`
- Update the `rules()` method to dynamically set `max` for `ai_config.script_iterations` based on the video's free-tier status:
    - Get the video: `$video = $this->route('video');`
    - Calculate max iterations: `$maxIterations = $video->isFreeTier() ? config('video.free_tier.max_script_iterations') : 5;`
    - Update rule: `'ai_config.script_iterations' => ['required', 'integer', 'min:0', "max:{$maxIterations}"]`
- Add a custom error message for the max validation: `'ai_config.script_iterations.max' => 'Free tier videos are limited to :max iteration(s).'`
- Run `vendor/bin/pint --dirty`

### 4. Write Feature Tests

- **Task ID**: write-iteration-tests
- **Depends On**: update-backend-validation
- **Assigned To**: iteration-test-dev
- **Agent Type**: coder
- **Parallel**: true (can run in parallel with frontend UI)
- Create test file: `php artisan make:test ScriptIterationConfigTest --pest --no-interaction`
- Write tests in `/Users/young/Nextcloud/dev/Itervel/tests/Feature/ScriptIterationConfigTest.php`:
    - `test('script iterations can be set to 0')` -- PUT config with iterations=0, assert saved
    - `test('script iterations can be set to 5 for paid videos')` -- non-free-tier video, iterations=5, assert saved
    - `test('script iterations above 5 is rejected')` -- assert validation error
    - `test('script iterations below 0 is rejected')` -- assert validation error
    - `test('free tier video iterations are capped at 1')` -- free-tier video, iterations=2, assert validation error
    - `test('free tier video can set iterations to 1')` -- free-tier video, iterations=1, assert saved
    - `test('free tier video can set iterations to 0')` -- free-tier video, iterations=0, assert saved
    - `test('paid video can set iterations up to 5')` -- non-free-tier video, iterations=5, assert saved
    - `test('script iterations defaults to 3 for new config')` -- verify `config('ai.defaults.script_iterations')` is 3
    - `test('getScriptIterations returns configured value')` -- set ai_config with iterations=2, assert `getScriptIterations()` returns 2
    - `test('getScriptIterations returns default when no config')` -- no ai_config, assert returns default (3)
- Run tests: `php artisan test tests/Feature/ScriptIterationConfigTest.php --compact`
- Run `vendor/bin/pint --dirty`
- Run full test suite: `php artisan test --compact`

### 5. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-slider-component, add-iteration-ui, update-backend-validation, write-iteration-tests
- **Assigned To**: iteration-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands
- Verify `slider.tsx` component exists and follows Radix UI patterns
- Verify the video configuration page has an iteration section with slider, tooltip, cost display, and warnings
- Verify free-tier videos have the slider disabled and capped at 1
- Verify backend validation enforces free-tier iteration limits
- Verify cost impact updates as iterations change
- Verify warning badge appears at 3+ iterations
- Confirm all acceptance criteria are met

## Acceptance Criteria

- A `Slider` UI component exists in `resources/js/components/ui/slider.tsx` following the Radix-based pattern
- The video configuration page has a "Script Iterations" section with a slider control
- The slider allows selecting 0-5 iterations for paid videos
- The slider is disabled and locked to 1 iteration for free-tier videos
- A tooltip icon explains what script iterations do
- The current iteration count is displayed alongside the slider
- A "Higher cost" warning badge appears when 3+ iterations are selected
- A cost impact line shows the additional credit cost for the selected iterations
- Free-tier users see a message explaining the iteration limit
- Backend validation enforces the free-tier iteration cap (rejects iterations > max for free-tier videos)
- The total cost summary on the configuration page includes iteration costs
- All iteration configuration tests pass
- TypeScript compiles without errors
- ESLint passes
- PHP formatting passes

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run iteration config tests
php artisan test tests/Feature/ScriptIterationConfigTest.php --compact

# Run AI config tests (regression)
php artisan test tests/Feature/VideoConfigTest.php --compact

# Run full test suite
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

- The `@radix-ui/react-slider` package follows the same patterns as other Radix primitives already in use (checkbox, select, toggle, tooltip). The Slider component wrapper follows the exact same pattern.
- The iteration slider is part of the same video configuration page created in E009-F001, not a separate page. It adds a section below the model selection cards.
- When `use_recommended` is checked in F001, the iteration count should still be editable (recommended settings only affect model selection, not iteration count). However, if the user is on the free tier, iterations are locked regardless.
- The cost calculation for iterations uses the critique step's model: `critique.base_credit_cost * critique_model.cost_multiplier * iterations`. If `use_recommended` is true, the critique model is Sonnet (1.0x multiplier). If custom, it uses whatever model the user selected for the critique step.
- All commands should be run inside the Docker container.

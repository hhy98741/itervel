# Feature: Guided First Video Experience

**Epic**: E008-free-tier-and-onboarding.md
**Feature**: E008-F004
**Epic depends on**: E002-user-authentication.md, E005-credits-and-billing.md
**Feature depends on**: E008-F001, E008-F003, E006-F003

## Task Description

The Guided First Video Experience walks new users through creating their first video with helpful prompts and tooltips. This is the hands-on onboarding step that follows the Welcome Tour (E008-F003) -- while the tour explains the workflow conceptually, this feature provides in-context guidance as the user actually creates their first video.

**What it does**: Walks new users through creating their first video with helpful prompts and tooltips.

**Expected outcome**: The first video creation experience includes a pre-filled example topic, tooltips at each step explaining what to do, and guidance throughout the wizard. The free trial video is limited to 1 minute.

When a user navigates to the video creation page for the first time (they have never created a video before), the system activates a guided mode. This mode provides:

1. **Pre-filled example topic**: The topic input field is pre-populated with an example topic (e.g., "5 Tips for Better Sleep - A Complete Guide") so the user can see what a good topic looks like. The user can edit or replace it before proceeding.
2. **Contextual tooltips**: Each major section/step of the video creation wizard displays a tooltip or inline help text explaining what to do and why. These tooltips use the existing Tooltip UI component from the component library.
3. **Step guidance**: A small guidance panel or inline instructions that highlight the current step and provide tips for getting the best results.
4. **Free-tier limitations display**: Since first-time users are using their free credit, the free-tier banner (from E008-F001) is shown, reinforcing the 1-minute duration limit.

The guided mode is determined by checking if the user has any existing videos. If `user.videos.count() === 0`, the guided mode activates. After the user creates their first video, subsequent visits to the video creation page show the standard (non-guided) experience.

**Dependency on E008-F001 (Free Tier Video Limitations)**: Provides the `is_free_tier` flag, video limits configuration, and the free-tier banner component that is displayed during the guided experience.

**Dependency on E008-F003 (Welcome Tour)**: The welcome tour introduces the workflow; the guided experience provides the hands-on follow-through. Users may navigate to video creation directly from the tour's "Get Started" button.

**Dependency on E006-F003 (Topic Input)**: Creates the Video model, VideoController, video creation page, and the topic input form. This feature enhances that page with guided mode features.

## Objective

Implement a guided first-video creation experience that activates when a user has never created a video before. The guided mode pre-fills an example topic, shows contextual tooltips explaining each step, displays inline guidance, and shows free-tier limitations. The backend passes a `isFirstVideo` flag to the video creation page, and the frontend uses this flag to conditionally render guidance elements. After the user creates their first video, the guided mode no longer activates.

## Solution Approach

### 1. Backend: Detect First Video

Update the `VideoController::create()` method to determine if this is the user's first video by checking their video count:

```php
public function create(): Response
{
    $user = auth()->user();
    $isFirstVideo = $user->videos()->count() === 0;

    // Free-tier detection from E008-F001
    $isFreeTier = ! $user->creditTransactions()
        ->where('type', 'purchase')
        ->exists();

    $tier = $isFreeTier ? 'free_tier' : 'paid';

    return Inertia::render('videos/create', [
        // ... existing props
        'isFirstVideo' => $isFirstVideo,
        'isFreeTier' => $isFreeTier,
        'videoLimits' => [
            'maxDurationSeconds' => config("video.{$tier}.max_duration_seconds"),
            'maxScriptIterations' => config("video.{$tier}.max_script_iterations"),
            'hasWatermark' => config("video.{$tier}.watermark.enabled"),
        ],
        'exampleTopic' => $isFirstVideo
            ? config('video.example_topic')
            : null,
    ]);
}
```

### 2. Configuration: Example Topic

Add an `example_topic` key to `config/video.php`:

```php
'example_topic' => '5 Tips for Better Sleep - A Complete Guide',
```

This keeps the example topic configurable and easy to change without modifying code.

### 3. Frontend: Guided Mode Components

Create a `guided-tooltip.tsx` component that wraps any content with a tooltip and a pulsing indicator dot. This is the primary guidance mechanism:

```tsx
interface GuidedTooltipProps {
    content: string;
    children: React.ReactNode;
    show: boolean;
    side?: 'top' | 'bottom' | 'left' | 'right';
}
```

The component uses the existing `Tooltip`, `TooltipContent`, `TooltipProvider`, `TooltipTrigger` from `@/components/ui/tooltip`. When `show` is true (guided mode active), it renders the tooltip with a small pulsing dot indicator (a `<span>` with `animate-pulse` and a colored dot) to draw the user's attention.

Create a `guided-step-panel.tsx` component that provides a collapsible inline guidance panel:

```tsx
interface GuidedStepPanelProps {
    title: string;
    description: string;
    tipItems: string[];
    show: boolean;
}
```

This renders a subtle card with a lightbulb icon, a title (e.g., "Tips for a Great Topic"), a description, and a bulleted list of tips. It uses the existing `Card` component and `cn()` for styling. It includes a dismiss button so users can hide it if they find it distracting.

### 4. Frontend: Update Video Creation Page

Update `resources/js/pages/videos/create.tsx` to:

1. Accept `isFirstVideo`, `exampleTopic` props from the page
2. Pre-fill the topic textarea with `exampleTopic` when `isFirstVideo` is true. Use it as the initial value for the form's `topic` field in the `useForm` hook:
    ```tsx
    const { data, setData, submit } = useForm({
        topic: exampleTopic ?? '',
    });
    ```
3. Wrap the topic input field with a `GuidedTooltip` component that says "Enter a topic for your video. Be specific -- include the angle or format you want (e.g., tips, tutorial, review)."
4. Show a `GuidedStepPanel` above the form with tips for writing a good topic:
    - "Be specific about your video's angle or format"
    - "Include your target audience if relevant"
    - "Keep it between 100-500 characters for best results"
    - "The example topic below is pre-filled -- feel free to use it or replace it with your own"
5. Conditionally render all guided elements only when `isFirstVideo` is true
6. The free-tier banner from E008-F001 is already conditionally shown based on `isFreeTier` -- no changes needed for that

### 5. TypeScript Types

Update the page props type for the video creation page:

```typescript
interface VideoCreateProps {
    // ... existing props
    isFirstVideo: boolean;
    isFreeTier: boolean;
    exampleTopic: string | null;
    videoLimits: {
        maxDurationSeconds: number;
        maxScriptIterations: number;
        hasWatermark: boolean;
    };
}
```

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/VideoController.php` -- Video controller (created by E006-F003, modified by E008-F001). Must update `create()` to pass `isFirstVideo` and `exampleTopic` props.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/Video.php` -- Video model (created by E006-F003, modified by E008-F001). Used to count user's videos.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` -- User model. Has `videos()` relationship from E006-F003. Used for `$user->videos()->count()`.
- `/Users/young/Nextcloud/dev/Itervel/config/video.php` -- Video config (created by E008-F001). Must add `example_topic` key.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/videos/create.tsx` -- Video creation page (created by E006-F003, modified by E008-F001). Must integrate guided mode components and pre-fill topic.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/free-tier-banner.tsx` -- Free tier banner (created by E008-F001). Already shown on the page; no changes needed.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/tooltip.tsx` -- Existing Tooltip UI component (Radix-based). Used by the guided tooltip wrapper.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/card.tsx` -- Existing Card component. Used by the guided step panel.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/button.tsx` -- Existing Button component. Used for dismiss button in guided panel.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/lib/utils.ts` -- Contains `cn()` utility for conditional classes.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/index.ts` -- TypeScript types. May need updates for page props.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/FreeTierVideoTest.php` -- Existing free-tier tests (from E008-F001). Referenced for test patterns.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/guided-tooltip.tsx` -- React component wrapping content with a tooltip and pulsing indicator dot for guided mode.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/guided-step-panel.tsx` -- React component rendering an inline guidance card with tips, using the Card UI component.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/GuidedFirstVideoTest.php` -- Pest feature tests for the guided first-video experience.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: guided-backend-dev
    - Role: Updates the VideoController to pass `isFirstVideo` and `exampleTopic` props, and adds the `example_topic` config key
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: guided-frontend-dev
    - Role: Creates the guided-tooltip and guided-step-panel components, updates the video creation page with guided mode logic, and pre-fills the example topic
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: guided-test-dev
    - Role: Writes Pest feature tests for guided mode detection, example topic pre-fill, and prop passing
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: guided-reviewer
    - Role: Validates the complete guided first-video implementation against acceptance criteria, runs all tests, checks types, and verifies code quality
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Update Backend for Guided Mode

- **Task ID**: update-backend-guided
- **Depends On**: none
- **Assigned To**: guided-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- Update `/Users/young/Nextcloud/dev/Itervel/config/video.php` to add `'example_topic' => '5 Tips for Better Sleep - A Complete Guide'` at the top level of the config array
- Update `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/VideoController.php`:
    - In the `create()` method, add `$isFirstVideo = $user->videos()->count() === 0;`
    - Pass `'isFirstVideo' => $isFirstVideo` to the Inertia render props
    - Pass `'exampleTopic' => $isFirstVideo ? config('video.example_topic') : null` to the Inertia render props
    - Ensure the existing `isFreeTier` and `videoLimits` props (from E008-F001) are still passed
- Run `vendor/bin/pint --dirty`
- Run existing tests: `php artisan test --compact --filter=Video`

### 2. Create Guided Mode Frontend Components

- **Task ID**: create-guided-components
- **Depends On**: update-backend-guided
- **Assigned To**: guided-frontend-dev
- **Agent Type**: coder
- **Parallel**: false
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/components/guided-tooltip.tsx`:
    - Import `Tooltip`, `TooltipContent`, `TooltipProvider`, `TooltipTrigger` from `@/components/ui/tooltip`
    - Define `GuidedTooltipProps` interface: `content: string`, `children: React.ReactNode`, `show: boolean`, `side?: 'top' | 'bottom' | 'left' | 'right'`
    - When `show` is false, render only `children` without any tooltip wrapper
    - When `show` is true, wrap `children` in the Tooltip components with `defaultOpen={true}` so the tooltip is visible by default
    - Add a small pulsing indicator dot using a `<span>` with classes `absolute -top-1 -right-1 size-2 rounded-full bg-primary animate-pulse` positioned relative to the trigger content
    - The tooltip content should display the `content` string with `text-sm` styling
    - Use `cn()` for conditional classes
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/components/guided-step-panel.tsx`:
    - Import `Card`, `CardContent`, `CardHeader`, `CardTitle` from `@/components/ui/card`
    - Import `Button` from `@/components/ui/button`
    - Import `Lightbulb`, `X` from `lucide-react`
    - Define `GuidedStepPanelProps` interface: `title: string`, `description: string`, `tipItems: string[]`, `show: boolean`
    - Use `useState<boolean>(true)` for `isVisible` to allow dismissing
    - When `show` is false or `isVisible` is false, render nothing (`return null`)
    - Render a `Card` with a subtle background (`bg-primary/5 border-primary/20`) containing:
        - A header with `Lightbulb` icon, the `title`, and an `X` dismiss button (variant="ghost", size="icon")
        - A description paragraph in `text-muted-foreground text-sm`
        - A bulleted list of `tipItems` with small dot markers
    - Use `cn()` for styling, support dark mode
- Update `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/videos/create.tsx`:
    - Import `GuidedTooltip` and `GuidedStepPanel`
    - Update the component props type to include `isFirstVideo: boolean` and `exampleTopic: string | null`
    - Pre-fill the topic form field: change the `useForm` initial data to use `exampleTopic ?? ''` for the `topic` field
    - Add a `GuidedStepPanel` above the topic form with title "Tips for a Great Topic", description "A well-crafted topic helps the AI generate better content. Here are some tips:", and tip items: ["Be specific about your video's angle or format (e.g., tips, tutorial, review)", "Include your target audience if relevant", "Keep it between 100-500 characters for best results", "The example topic is pre-filled -- feel free to use it or replace it with your own"]
    - Wrap the topic textarea label/field with a `GuidedTooltip` with content "Enter a topic for your video. Be specific -- include the angle or format you want." and `show={isFirstVideo}`
    - All guided elements should only render when `isFirstVideo` is true
- Run `npm run types` to verify TypeScript
- Run `npm run lint` to check linting, fix issues with `npm run lint:fix`
- Run `npm run build` to verify the build succeeds

### 3. Write Feature Tests

- **Task ID**: write-guided-tests
- **Depends On**: update-backend-guided
- **Assigned To**: guided-test-dev
- **Agent Type**: coder
- **Parallel**: true (can run in parallel with frontend since tests only need backend)
- Create the test file: `php artisan make:test GuidedFirstVideoTest --pest --no-interaction`
- Write tests in `/Users/young/Nextcloud/dev/Itervel/tests/Feature/GuidedFirstVideoTest.php`:
    - `test('video creation page shows guided mode for first-time users')` -- create a user with no videos, GET the video creation page, assert Inertia props include `isFirstVideo: true`
    - `test('video creation page does not show guided mode for users with existing videos')` -- create a user with at least one video, GET the page, assert `isFirstVideo: false`
    - `test('video creation page provides example topic for first-time users')` -- create a user with no videos, assert `exampleTopic` prop matches `config('video.example_topic')`
    - `test('video creation page does not provide example topic for returning users')` -- create a user with a video, assert `exampleTopic` is null
    - `test('guided mode deactivates after first video creation')` -- create a user, verify `isFirstVideo` is true, create a video for the user, verify `isFirstVideo` is now false on the next visit
    - `test('example topic config value is set')` -- assert `config('video.example_topic')` is not null and is a non-empty string
- Run tests: `php artisan test tests/Feature/GuidedFirstVideoTest.php --compact`
- Run `vendor/bin/pint --dirty`
- Run full test suite: `php artisan test --compact`

### 4. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: update-backend-guided, create-guided-components, write-guided-tests
- **Assigned To**: guided-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands
- Verify `config/video.php` includes `example_topic` key
- Verify `VideoController::create()` passes `isFirstVideo`, `exampleTopic`, `isFreeTier`, and `videoLimits` props
- Verify `guided-tooltip.tsx` component exists with pulsing indicator and tooltip display
- Verify `guided-step-panel.tsx` component exists with dismissible guidance card
- Verify `videos/create.tsx` integrates guided components conditionally based on `isFirstVideo`
- Verify the topic form field is pre-filled with `exampleTopic` for first-time users
- Verify guided elements do not render for returning users
- Confirm all acceptance criteria are met

## Acceptance Criteria

- The video creation page detects first-time users (no existing videos) and activates guided mode
- First-time users see a pre-filled example topic ("5 Tips for Better Sleep - A Complete Guide") in the topic input field
- First-time users see a guided step panel with tips for writing a good topic
- First-time users see contextual tooltips with pulsing indicators on the topic input field
- The guided step panel can be dismissed by the user
- Returning users (who have created at least one video) see the standard video creation page without guided elements
- The `exampleTopic` prop is null for returning users
- The example topic is editable -- users can modify or replace it before submitting
- The free-tier banner (from E008-F001) continues to display correctly alongside guided elements
- The example topic value is configurable via `config/video.php`
- All guided mode tests pass
- TypeScript compiles without errors (`npm run types`)
- ESLint passes (`npm run lint`)
- PHP formatting passes (`vendor/bin/pint --dirty`)
- Full test suite passes without regressions

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run guided mode tests
php artisan test tests/Feature/GuidedFirstVideoTest.php --compact

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

- The guided mode is purely data-driven -- it uses the video count to determine first-time status rather than a separate flag. This avoids adding another column to the users table and naturally deactivates after the first video is created.
- The example topic is stored in config rather than hardcoded in a component. This makes it easy to A/B test different example topics or localize them in the future.
- The `GuidedTooltip` component uses `defaultOpen={true}` on the Radix Tooltip so tooltips are visible by default without requiring hover. This ensures first-time users actually see the guidance.
- The `GuidedStepPanel` is dismissible because some users may find the tips obvious or distracting. The dismiss state is ephemeral (component state) -- if the user leaves and returns while still having zero videos, the panel reappears.
- The guided experience works alongside the free-tier banner from E008-F001. Both are shown simultaneously for first-time users since first-time users are always using their free credit.
- All commands should be run inside the Docker container. Use `make shell` to enter the container, or prefix with `docker compose exec app`.

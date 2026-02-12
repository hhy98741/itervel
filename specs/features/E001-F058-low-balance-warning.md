# Feature: Low Balance Warning

**Epic**: E001-ai-powered-faceless-video-creation-platform.md
**Feature**: E001-F058
**Dependencies**: E001-F054

## Task Description

Low Balance Warning alerts users when their credit balance is running low, specifically when they have only 1 credit remaining. This feature builds on top of the Credit Balance Display (E001-F054), which already provides a `CreditBalance` component in the navigation and shares `video_credits` via Inertia's shared data (`auth.user.video_credits`).

**What it does**: Alerts users when their credit balance is running low.

**Expected outcome**: When the user has only 1 credit remaining, they see a warning notification encouraging them to purchase more.

This feature enhances the existing `CreditBalance` component created in E001-F054 with warning styling and adds a dismissible warning banner that appears when `video_credits === 1`. The warning serves two purposes: (1) visual urgency on the existing credit display and (2) a prominent banner notification encouraging the user to purchase more credits.

The application currently uses a sidebar layout (default) with `AppSidebar` and `NavUser`, and an alternative header layout with `AppHeader`. Both layouts already display the credit balance via the `CreditBalance` component from E001-F054. The warning needs to appear in both layouts.

Since the credit purchase flow (E001-F060/E001-F061) is not yet built, the "purchase more" CTA will link to a placeholder route or use a `#` href that can be updated later when pricing pages are available. The feature should be designed so the link target is easily configurable.

## Objective

When an authenticated user has exactly 1 credit remaining, they should see: (1) the credit balance displayed with amber/warning styling (instead of the normal neutral styling) in both the sidebar and header navigation, and (2) a dismissible warning banner displayed below the navigation encouraging them to purchase more credits. The warning banner should be dismissible per session (using React state, not persisted to the backend) so it does not annoy users who are aware of their balance.

## Solution Approach

### 1. Enhance the `CreditBalance` Component with Warning State

The `CreditBalance` component created in E001-F054 (at `resources/js/components/credit-balance.tsx`) accepts `credits` and `compact` props. Enhance it to detect when `credits <= 1` and apply warning styling:

- When `credits <= 1` and `credits > 0`, change the icon and text color to amber/warning tones (`text-amber-500 dark:text-amber-400`)
- Add a subtle pulsing animation on the icon to draw attention when credits are low
- Update the tooltip text to include a warning message: "Only 1 credit remaining! Purchase more credits."
- The component should accept an optional `warningThreshold` prop (default: 1) for future flexibility, but for this feature the threshold is 1

The key design principle is that the `CreditBalance` component self-determines its visual state based on the `credits` value -- no external "warning mode" prop is needed. This keeps the API simple and the logic centralized.

```tsx
// Enhanced CreditBalance with warning state
const isLowBalance = credits > 0 && credits <= warningThreshold;

<Coins className={cn(
    'size-4 shrink-0',
    isLowBalance ? 'text-amber-500 dark:text-amber-400 animate-pulse' : 'text-muted-foreground',
)} />
<span className={cn(
    'text-sm font-medium tabular-nums',
    isLowBalance && 'text-amber-500 dark:text-amber-400',
)}>
    {credits}
</span>
```

### 2. Create a `LowBalanceWarning` Banner Component

Create a new `low-balance-warning.tsx` component that renders a dismissible warning banner. This banner:

- Is shown when the user's `video_credits === 1`
- Uses the existing `Alert` component (`@/components/ui/alert`) with a warning-style variant for visual consistency
- Contains an `AlertTriangle` icon from `lucide-react` (semantic warning icon)
- Shows a message like: "You have only 1 credit remaining. Purchase more credits to keep creating videos."
- Includes a CTA button/link styled as a small button using the existing `Button` component
- Has an X/close button to dismiss the banner (state managed via React `useState`, resets on page reload)
- The CTA link target is configurable via a constant (initially `#` or a future pricing route)

The banner design:

```tsx
import { AlertTriangle, X } from 'lucide-react';
import { useState } from 'react';
import { Link } from '@inertiajs/react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';

type Props = {
    credits: number;
    purchaseUrl?: string;
};

export function LowBalanceWarning({ credits, purchaseUrl = '#' }: Props) {
    const [dismissed, setDismissed] = useState(false);

    if (credits !== 1 || dismissed) {
        return null;
    }

    return (
        <Alert className="relative border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-200">
            <AlertTriangle className="text-amber-500" />
            <AlertTitle>Low Credit Balance</AlertTitle>
            <AlertDescription className="flex items-center justify-between gap-4">
                <span>
                    You have only 1 credit remaining. Purchase more to keep
                    creating videos.
                </span>
                <Button
                    size="sm"
                    variant="outline"
                    asChild
                    className="shrink-0"
                >
                    <Link href={purchaseUrl}>Buy Credits</Link>
                </Button>
            </AlertDescription>
            <button
                onClick={() => setDismissed(true)}
                className="absolute top-2 right-2 ..."
                aria-label="Dismiss warning"
            >
                <X className="size-4" />
            </button>
        </Alert>
    );
}
```

### 3. Integrate the Warning Banner into Both Layouts

The `LowBalanceWarning` banner needs to appear in the content area just below the navigation header in both layouts, so it is visible but does not interfere with the navigation itself.

**Sidebar layout** (`resources/js/layouts/app/app-sidebar-layout.tsx`):

- Add the `LowBalanceWarning` component between `AppSidebarHeader` and `{children}`, inside the `AppContent` wrapper
- Access `auth.user.video_credits` via `usePage<SharedData>()`

**Header layout** (`resources/js/layouts/app/app-header-layout.tsx`):

- Add the `LowBalanceWarning` component between `AppHeader` and `AppContent`, or as the first child inside `AppContent`
- Access `auth.user.video_credits` via `usePage<SharedData>()`

Both layouts are the template wrappers that contain the navigation and content areas. Placing the warning banner at the layout level ensures it appears on every page when conditions are met, without needing to add it to individual page components.

### 4. Testing Approach

Backend tests are not strictly needed for this feature since the logic is entirely frontend (the `video_credits` data sharing is already tested in E001-F054's tests). However, we should write a feature test that specifically validates the low-balance scenario is handled correctly at the data level:

- Test that a user with `video_credits => 1` has the correct value in shared Inertia props (ensuring the data needed for the warning is available)
- The frontend rendering and dismissal behavior will be validated via TypeScript type checks and manual/visual validation

The test is lightweight since the data pipeline is already proven by E001-F054's tests. The new test focuses specifically on the `credits === 1` edge case.

### 5. No Backend Changes Required

All the logic for this feature lives on the frontend. The `HandleInertiaRequests` middleware already shares `auth.user` (including `video_credits`) on every request. The `CreditBalance` component already receives the credits value. The warning threshold logic and banner display are purely client-side concerns.

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/credit-balance.tsx` -- The CreditBalance component created in E001-F054. Must be enhanced with warning styling when credits are low (amber colors, pulsing animation, updated tooltip text). This is the primary file to modify.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/alert.tsx` -- The Alert UI component with `Alert`, `AlertTitle`, `AlertDescription` exports and `default`/`destructive` variants. Used as the base for the warning banner. May need a new `warning` variant added, or the banner can use custom class overrides on the `default` variant.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/alert-error.tsx` -- Existing example of how the Alert component is used in the codebase. Follow this pattern for the warning banner component structure.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/button.tsx` -- The Button UI component. Used for the "Buy Credits" CTA button in the warning banner.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/tooltip.tsx` -- The Tooltip UI component. Already used by CreditBalance. The tooltip text needs updating when credits are low.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/badge.tsx` -- The Badge component. Referenced for understanding variant patterns (CVA-based variants with `default`, `secondary`, `destructive`, `outline`).
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/app/app-sidebar-layout.tsx` -- The sidebar layout template. Must be modified to include the `LowBalanceWarning` banner between the header and content area.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/app/app-header-layout.tsx` -- The header layout template. Must be modified to include the `LowBalanceWarning` banner between the header and content area.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/app-header.tsx` -- The header navigation component. Already uses `usePage<SharedData>()` and renders `CreditBalance`. Referenced for understanding how credit data flows in the header layout. No changes needed here since the CreditBalance component self-manages its warning state.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/nav-user.tsx` -- The sidebar user navigation component. Already renders `CreditBalance`. No changes needed here since the CreditBalance component self-manages its warning state.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/index.ts` -- SharedData type definition. Used for typed access to `auth.user.video_credits` in layout components.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/auth.ts` -- User type definition. After E001-F001, includes `video_credits: number`. Has `[key: string]: unknown` fallback.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/lib/utils.ts` -- Utility functions including `cn()` for conditional class merging. Used extensively in the warning styling logic.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/app-sidebar-header.tsx` -- The sidebar header component showing breadcrumbs and sidebar trigger. Referenced for understanding the layout structure where the warning banner will be placed.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/app-content.tsx` -- The content wrapper component. Referenced for understanding how content is rendered in both layouts.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Middleware/HandleInertiaRequests.php` -- The Inertia shared data middleware. Shares `auth.user` on every request. No changes needed; referenced to confirm the data flow.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` -- User factory for test setup. After E001-F001, includes `video_credits => 0` in defaults.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/DashboardTest.php` -- Existing dashboard test. Referenced as a pattern for writing the low balance test.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration. All Feature tests use `RefreshDatabase`.
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Web routes. Dashboard route is used as a test target.
- `/Users/young/Nextcloud/dev/Itervel/specs/features/E001-F054-credit-balance-display.md` -- The dependency feature spec. Details how the `CreditBalance` component was built, what it looks like, and how it integrates into both layouts. Critical reference for understanding the existing component API.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/low-balance-warning.tsx` -- A dismissible warning banner component that displays when the user has exactly 1 credit remaining. Uses the Alert UI component with amber/warning styling, includes a "Buy Credits" CTA link and an X dismiss button. Accepts `credits` (number) and optional `purchaseUrl` (string) props.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/LowBalanceWarningTest.php` -- Feature test validating that a user with 1 credit has the correct `video_credits` value in shared Inertia props, confirming the data needed for the frontend warning is available.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Frontend Developer
    - Name: low-balance-frontend-dev
    - Role: Enhances the CreditBalance component with warning styling, creates the LowBalanceWarning banner component, and integrates the banner into both layout templates
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: low-balance-test-dev
    - Role: Writes feature tests to verify the low-balance data scenario is correctly shared via Inertia props
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: low-balance-reviewer
    - Role: Validates the complete low balance warning feature against acceptance criteria, runs all tests, checks types, runs linting and formatting
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Enhance CreditBalance Component with Warning Styling

- **Task ID**: enhance-credit-balance-warning
- **Depends On**: none
- **Assigned To**: low-balance-frontend-dev
- **Agent Type**: coder
- **Parallel**: true
- Read the existing `CreditBalance` component at `/Users/young/Nextcloud/dev/Itervel/resources/js/components/credit-balance.tsx` (created by E001-F054) to understand its current implementation
- Read the E001-F054 spec at `/Users/young/Nextcloud/dev/Itervel/specs/features/E001-F054-credit-balance-display.md` for full context on the component design
- Enhance the `CreditBalance` component to detect low balance and apply warning styling:
    - Add an optional `warningThreshold` prop (default: `1`) to the component's props type
    - Compute `const isLowBalance = credits > 0 && credits <= warningThreshold;`
    - When `isLowBalance` is true:
        - Change the `Coins` icon className to use `text-amber-500 dark:text-amber-400 animate-pulse` instead of the normal `text-muted-foreground`
        - Change the credit count `span` className to use `text-amber-500 dark:text-amber-400` instead of normal text styling
        - Use the `cn()` utility from `@/lib/utils` for conditional class application
    - Update the tooltip content to show a warning message when low balance is detected:
        - Normal: `"{credits} credit(s) remaining"` (existing behavior from E001-F054)
        - Low balance: `"Only {credits} credit remaining! Purchase more credits."` (with proper pluralization)
    - Ensure the existing behavior is unchanged when credits are above the threshold
- Create the `LowBalanceWarning` banner component at `/Users/young/Nextcloud/dev/Itervel/resources/js/components/low-balance-warning.tsx`:
    - Import `AlertTriangle` and `X` from `lucide-react`
    - Import `useState` from `react`
    - Import `Link` from `@inertiajs/react`
    - Import `Alert`, `AlertTitle`, `AlertDescription` from `@/components/ui/alert`
    - Import `Button` from `@/components/ui/button`
    - Define props type: `{ credits: number; purchaseUrl?: string; }`
    - Export a named function `LowBalanceWarning`
    - Use `const [dismissed, setDismissed] = useState(false);` for dismiss state
    - Return `null` if `credits !== 1` or `dismissed` is true (early return)
    - Render an `Alert` with custom amber/warning classes: `className="relative border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-200"` on the default variant
    - Include `<AlertTriangle className="text-amber-500" />` as the icon
    - Include `<AlertTitle>Low Credit Balance</AlertTitle>`
    - Include `<AlertDescription>` with a flex layout containing:
        - Text: `"You have only 1 credit remaining. Purchase more to keep creating videos."`
        - A `<Button size="sm" variant="outline" asChild className="shrink-0 border-amber-300 text-amber-700 hover:bg-amber-100 dark:border-amber-700 dark:text-amber-300 dark:hover:bg-amber-900">` wrapping `<Link href={purchaseUrl}>Buy Credits</Link>` (default `purchaseUrl` is `'#'`)
    - Include a dismiss button positioned absolute in the top-right corner: `<button onClick={() => setDismissed(true)} className="absolute right-3 top-3 rounded-sm p-0.5 text-amber-500 opacity-70 hover:opacity-100 transition-opacity" aria-label="Dismiss low balance warning"><X className="size-4" /></button>`
- Integrate the `LowBalanceWarning` banner into the sidebar layout at `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/app/app-sidebar-layout.tsx`:
    - Import `usePage` from `@inertiajs/react`
    - Import `LowBalanceWarning` from `@/components/low-balance-warning`
    - Import `SharedData` type from `@/types`
    - Inside the component, add: `const { auth } = usePage<SharedData>().props;`
    - Add `<LowBalanceWarning credits={auth.user.video_credits} />` between `<AppSidebarHeader breadcrumbs={breadcrumbs} />` and `{children}`, wrapped in a `<div className="px-6 pt-4 md:px-4">` for consistent padding matching the header and content areas
- Integrate the `LowBalanceWarning` banner into the header layout at `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/app/app-header-layout.tsx`:
    - Import `usePage` from `@inertiajs/react`
    - Import `LowBalanceWarning` from `@/components/low-balance-warning`
    - Import `SharedData` type from `@/types`
    - Inside the component, add: `const { auth } = usePage<SharedData>().props;`
    - Add `<LowBalanceWarning credits={auth.user.video_credits} />` between `<AppHeader breadcrumbs={breadcrumbs} />` and `<AppContent>`, wrapped in a `<div className="mx-auto w-full max-w-7xl px-4 pt-4">` for consistent max-width and padding matching the header layout
- Run `npm run types` from the project root to verify TypeScript types compile correctly
- Run `npm run lint:fix` to fix any linting issues
- Run `npm run format` to format with Prettier

### 2. Write Low Balance Warning Tests

- **Task ID**: write-low-balance-tests
- **Depends On**: none
- **Assigned To**: low-balance-test-dev
- **Agent Type**: coder
- **Parallel**: true
- Create the test file using: `php artisan make:test LowBalanceWarningTest --pest --no-interaction` (run from the project root `/Users/young/Nextcloud/dev/Itervel`)
- Write the following tests in `/Users/young/Nextcloud/dev/Itervel/tests/Feature/LowBalanceWarningTest.php`:
    - `test('user with one credit has video_credits of 1 in shared data')`:

        ```php
        test('user with one credit has video_credits of 1 in shared data', function () {
            $user = User::factory()->create(['video_credits' => 1]);

            $response = $this->actingAs($user)->get(route('dashboard'));

            $response->assertOk();
            $response->assertInertia(fn ($page) => $page
                ->where('auth.user.video_credits', 1)
            );
        });
        ```

    - `test('user with more than one credit has correct video_credits in shared data')`:

        ```php
        test('user with more than one credit has correct video_credits in shared data', function () {
            $user = User::factory()->create(['video_credits' => 5]);

            $response = $this->actingAs($user)->get(route('dashboard'));

            $response->assertOk();
            $response->assertInertia(fn ($page) => $page
                ->where('auth.user.video_credits', 5)
            );
        });
        ```

    - `test('user with zero credits has video_credits of 0 in shared data')`:

        ```php
        test('user with zero credits has video_credits of 0 in shared data', function () {
            $user = User::factory()->create(['video_credits' => 0]);

            $response = $this->actingAs($user)->get(route('dashboard'));

            $response->assertOk();
            $response->assertInertia(fn ($page) => $page
                ->where('auth.user.video_credits', 0)
            );
        });
        ```

    - `test('guest cannot access dashboard to see credit warning')`:

        ```php
        test('guest cannot access dashboard to see credit warning', function () {
            $response = $this->get(route('dashboard'));

            $response->assertRedirect(route('login'));
        });
        ```

- Add the import at the top of the file: `use App\Models\User;`
- Run the tests: `php artisan test tests/Feature/LowBalanceWarningTest.php --compact` (from the project root)
- Ensure all tests pass
- Run `vendor/bin/pint --dirty` to fix any PHP formatting issues

### 3. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: enhance-credit-balance-warning, write-low-balance-tests
- **Assigned To**: low-balance-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed in the Validation Commands section below
- Verify the `CreditBalance` component at `/Users/young/Nextcloud/dev/Itervel/resources/js/components/credit-balance.tsx`:
    - Has a `warningThreshold` prop (default 1)
    - Applies amber/warning styling (`text-amber-500`, `animate-pulse`) when `credits > 0 && credits <= warningThreshold`
    - Shows a warning tooltip message when credits are low
    - Maintains normal styling when credits are above the threshold
- Verify the `LowBalanceWarning` component at `/Users/young/Nextcloud/dev/Itervel/resources/js/components/low-balance-warning.tsx`:
    - Renders an Alert with amber/warning styling when `credits === 1`
    - Returns `null` when `credits !== 1` or when dismissed
    - Has a dismiss button with proper `aria-label`
    - Has a "Buy Credits" CTA link
    - Uses the `Alert`, `AlertTitle`, `AlertDescription` components from the UI library
- Verify `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/app/app-sidebar-layout.tsx`:
    - Imports and renders `LowBalanceWarning` with `auth.user.video_credits`
    - Banner is positioned between the sidebar header and children content
- Verify `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/app/app-header-layout.tsx`:
    - Imports and renders `LowBalanceWarning` with `auth.user.video_credits`
    - Banner is positioned between the header and content area
- Verify low balance warning tests pass: `php artisan test tests/Feature/LowBalanceWarningTest.php --compact`
- Verify credit balance display tests still pass: `php artisan test tests/Feature/CreditBalanceDisplayTest.php --compact`
- Verify dashboard tests still pass: `php artisan test tests/Feature/DashboardTest.php --compact`
- Run the full test suite: `php artisan test --compact`
- Run TypeScript type checking: `npm run types`
- Run ESLint: `npm run lint`
- Run Prettier formatting check: `npm run format:check`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Confirm all acceptance criteria are met

## Acceptance Criteria

- The `CreditBalance` component applies amber/warning styling (amber text color, pulsing icon animation) when the user has exactly 1 credit remaining
- The `CreditBalance` component tooltip shows a warning message ("Only 1 credit remaining! Purchase more credits.") when credits are at the warning threshold
- The `CreditBalance` component displays normally (no warning styling) when credits are 2 or more
- The `CreditBalance` component does NOT show warning styling when credits are 0 (that is a different feature -- E001-F059 Insufficient Credits Block)
- A `LowBalanceWarning` banner component exists at `resources/js/components/low-balance-warning.tsx`
- The warning banner appears in the sidebar layout when the user has 1 credit
- The warning banner appears in the header layout when the user has 1 credit
- The warning banner does NOT appear when the user has 0, 2, or more credits
- The warning banner can be dismissed by clicking the X button
- After dismissal, the banner does not reappear until the next page load (session-based dismissal via React state)
- The warning banner includes a "Buy Credits" CTA link
- The warning banner uses amber/warning color scheme consistent with the credit balance warning styling
- All low balance warning tests pass
- All existing tests pass without regressions
- TypeScript types compile without errors
- ESLint and Prettier checks pass
- PHP code passes Pint formatting

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run low balance warning tests
php artisan test tests/Feature/LowBalanceWarningTest.php --compact

# Run credit balance display tests (dependency feature)
php artisan test tests/Feature/CreditBalanceDisplayTest.php --compact

# Run dashboard tests
php artisan test tests/Feature/DashboardTest.php --compact

# Run full test suite for regression check
php artisan test --compact

# TypeScript type checking
npm run types

# ESLint linting
npm run lint

# Prettier formatting check
npm run format:check

# PHP code formatting
vendor/bin/pint --dirty
```

## Notes

- **Warning threshold**: The feature specifies "1 credit remaining" as the trigger. The `CreditBalance` component uses `warningThreshold` (default 1) for flexibility, but the `LowBalanceWarning` banner checks `credits === 1` strictly. If the threshold needs to change in the future, both the component and banner should be updated.
- **Zero credits are NOT included**: When `credits === 0`, the warning styling should NOT apply. Zero credits is handled by E001-F059 (Insufficient Credits Block), which blocks video creation entirely. The low balance warning is specifically for the "running low" state, not the "empty" state.
- **Purchase URL placeholder**: Since the credit purchase flow (E001-F060 Credit Package Purchase, E001-F061 Stripe Checkout) has not been built yet, the "Buy Credits" link uses `'#'` as a placeholder. When those features are built, the `purchaseUrl` prop should be updated to point to the pricing/checkout page. The prop-based approach makes this a simple change.
- **Session-based dismissal**: The banner dismissal uses React's `useState`, which resets on full page reload. This is intentional -- if the user navigates via Inertia (SPA-style navigation), the banner stays dismissed because the layout component persists. If they hard-refresh, the banner reappears. This provides a good balance between persistence and reminder.
- **No backend changes**: All logic is frontend-only. The `video_credits` data is already shared via Inertia from the `HandleInertiaRequests` middleware (confirmed in E001-F054).
- **Amber color scheme**: The amber color palette (`amber-50`, `amber-200`, `amber-500`, `amber-800`, `amber-950`) is used for the warning state because it semantically represents "caution/warning" without being as severe as red/destructive. This follows common UI conventions.
- **Dark mode support**: All warning styles include dark mode variants (`dark:text-amber-400`, `dark:border-amber-800`, `dark:bg-amber-950/50`) to ensure the warning is visible and aesthetically appropriate in both light and dark themes.
- **Accessibility**: The dismiss button includes `aria-label="Dismiss low balance warning"` for screen readers. The Alert component already has `role="alert"` which announces the warning to assistive technologies.
- **All commands should be run inside the Docker container**. Use `make shell` to enter the container, or prefix with `docker compose exec app`.

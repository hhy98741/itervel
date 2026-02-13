# Feature: Credit Balance Display

**Epic**: E005-credits-and-billing.md
**Feature**: E005-F001
**Epic depends on**: E002-user-authentication.md
**Feature depends on**: E002-F003

## Task Description

Credit Balance Display shows the user's current credit balance prominently in the application navigation so they always know how many credits they have remaining. This is a key UX feature for the credit-based business model -- users need constant visibility into their credit balance to make informed decisions about creating videos.

**What it does**: Shows the user's current credit balance prominently in the application header.

**Expected outcome**: The user always sees how many credits they have remaining in the navigation bar.

This feature depends on E001-F001 (User Registration) which adds the `video_credits` column to the users table and the `video_credits` property to the TypeScript `User` type, and E001-F003 (User Login) which configures authentication and Inertia shared data. After those features are built:

- The `users` table has a `video_credits` unsigned integer column (default 0)
- The `User` model has `video_credits` in its `$fillable` array
- The `User` TypeScript type includes `video_credits: number`
- The `HandleInertiaRequests` middleware shares `auth.user` (the full User model) on every request
- The `UserFactory` includes `video_credits` in its default state (default 0)

Since the `HandleInertiaRequests` middleware already shares the full `auth.user` object via `$request->user()`, and E001-F001 adds `video_credits` to both the model and TypeScript type, the credit balance is already available on the frontend through `usePage<SharedData>().props.auth.user.video_credits`. No backend changes are needed for data availability.

The application uses two layout modes:

1. **Sidebar layout** (default, configured in `app-layout.tsx`): Uses `AppSidebar` with `NavUser` in the sidebar footer
2. **Header layout** (alternative): Uses `AppHeader` with navigation and user avatar in a top bar

The credit balance should be displayed in both layouts to ensure visibility regardless of which layout is active.

## Objective

Display the user's credit balance in both the sidebar and header navigation layouts so the credit count is always visible to authenticated users. The display should be clean, non-intrusive, and follow existing component patterns. No backend changes are required since the data is already shared via Inertia.

## Solution Approach

### 1. Create a Reusable `CreditBalance` Component

Create a new React component `credit-balance.tsx` in the `resources/js/components/` directory. This component will:

- Accept `credits` (number) as a prop
- Display a coin/credit icon alongside the formatted credit count
- Use the existing `Tooltip` component to show "Video Credits" on hover
- Use Tailwind CSS for styling, following existing component patterns
- Support a `compact` prop variant for the collapsed sidebar state

The component design:

```tsx
import { Coins } from 'lucide-react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

type Props = {
    credits: number;
    compact?: boolean;
};

export function CreditBalance({ credits, compact = false }: Props) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <div className="flex items-center gap-1.5 ...">
                    <Coins className="size-4 ..." />
                    {!compact && <span className="...">{credits}</span>}
                </div>
            </TooltipTrigger>
            <TooltipContent>
                <p>
                    {credits} {credits === 1 ? 'credit' : 'credits'} remaining
                </p>
            </TooltipContent>
        </Tooltip>
    );
}
```

The `Coins` icon from `lucide-react` is a good semantic choice for credits. The icon library is already used extensively throughout the codebase (e.g., `BookOpen`, `Folder`, `LayoutGrid`, `Menu`, `Search`, `Settings`, `LogOut` in the existing components).

### 2. Add Credit Balance to the Header Layout (`AppHeader`)

In `resources/js/components/app-header.tsx`, add the `CreditBalance` component to the right side of the header, positioned between the right nav items (Repository/Documentation links) and the user avatar dropdown. This placement ensures the credit balance is visible without disrupting the existing layout flow.

The credit balance will be placed in the `ml-auto flex items-center space-x-2` div, before the user avatar dropdown. It should be visible on both mobile and desktop.

The component already accesses `auth.user` via `usePage<SharedData>()`, so `auth.user.video_credits` is directly available.

### 3. Add Credit Balance to the Sidebar Layout (`NavUser`)

In `resources/js/components/nav-user.tsx`, add the credit balance display within the `SidebarMenuButton` area, next to the user info. The sidebar has a collapsed state (`collapsible="icon"`) where only icons are shown, so the component needs to handle this gracefully.

The credit balance should appear as a small badge or inline element next to the user's name/email in the sidebar footer. When the sidebar is collapsed, only the credit icon should be visible via tooltip.

The component already accesses `auth.user` via `usePage<SharedData>()`.

### 4. Add Credit Balance to the Mobile Navigation

The `AppHeader` component already includes a mobile Sheet menu. The credit balance should also be visible in the mobile view. Since the header layout already shows the credit balance in the top bar (visible on all screen sizes), the mobile sheet does not need a separate credit display.

### 5. Testing Approach

Write a feature test that verifies the `video_credits` value is included in the shared Inertia props for authenticated users. This validates that the data pipeline works correctly. The frontend rendering of the component will be validated visually and via TypeScript type checking.

The test will:

- Create a user with a specific `video_credits` value
- Make an authenticated request to the dashboard
- Assert the Inertia response includes the `video_credits` property on the user object

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Http/Middleware/HandleInertiaRequests.php` -- The Inertia shared data middleware. Shares `auth.user` (the full User model serialization) on every request. No changes needed since `video_credits` is already part of the User model after E001-F001, but referenced to confirm the data flow.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` -- The User Eloquent model. After E001-F001, includes `video_credits` in `$fillable`. No changes needed for this feature.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/app-header.tsx` -- The application header component used in the header layout. Must be modified to include the credit balance display between the right nav items and user avatar dropdown.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/nav-user.tsx` -- The sidebar user navigation component. Must be modified to show the credit balance in the sidebar footer area.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/user-info.tsx` -- The user info display component showing avatar, name, and email. Referenced for understanding the user data display pattern. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/user-menu-content.tsx` -- The dropdown menu content for user actions. Referenced for understanding how user data flows. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/tooltip.tsx` -- The Tooltip UI component. Will be used to show "X credits remaining" on hover over the credit balance.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/badge.tsx` -- The Badge UI component. May be used for styling the credit count display.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/sidebar.tsx` -- The sidebar UI primitives (`useSidebar` hook provides the `state` for collapsed detection). Referenced for understanding sidebar state.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/auth.ts` -- TypeScript types for `User` and `Auth`. After E001-F001, includes `video_credits: number` on the `User` type. Referenced for type safety.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/index.ts` -- SharedData type definition. Referenced for understanding how shared props are typed.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/app-layout.tsx` -- The main app layout which delegates to the sidebar layout. Referenced for understanding which layout is active.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/app/app-sidebar-layout.tsx` -- The sidebar layout template. Shows how `AppSidebar` is rendered. Referenced for layout context.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/app/app-header-layout.tsx` -- The header layout template. Shows how `AppHeader` is rendered. Referenced for layout context.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/app-sidebar.tsx` -- The sidebar component containing `NavUser`. Referenced for understanding sidebar structure.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/lib/utils.ts` -- Utility functions including `cn()` for class merging. Will be used for conditional styling.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` -- User factory. After E001-F001, includes `video_credits => 0` in defaults. Referenced for test setup.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/DashboardTest.php` -- Existing dashboard test. Referenced as a pattern for testing authenticated Inertia responses.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration. All Feature tests automatically use `RefreshDatabase`.
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Web routes. The dashboard route renders the `dashboard` Inertia page. Referenced for test route targets.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/credit-balance.tsx` -- A reusable React component that displays the user's credit balance with a coin icon and tooltip. Accepts `credits` (number) and optional `compact` (boolean) props.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/CreditBalanceDisplayTest.php` -- Feature test that verifies the `video_credits` field is shared via Inertia on authenticated pages.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Frontend Developer
    - Name: credit-balance-frontend-dev
    - Role: Creates the CreditBalance component, integrates it into the AppHeader and NavUser components, ensures proper styling and responsive behavior
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: credit-balance-test-dev
    - Role: Writes feature tests to verify credit balance data is available in Inertia shared props for authenticated users
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: credit-balance-reviewer
    - Role: Validates the complete credit balance display feature against acceptance criteria, runs all tests, checks types, runs linting and formatting
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Create CreditBalance Component and Integrate into Navigation

- **Task ID**: create-credit-balance-component
- **Depends On**: none
- **Assigned To**: credit-balance-frontend-dev
- **Agent Type**: coder
- **Parallel**: true
- Create the file `/Users/young/Nextcloud/dev/Itervel/resources/js/components/credit-balance.tsx` with the following implementation:
    - Import `Coins` from `lucide-react` (the icon library already used throughout the app)
    - Import `Tooltip`, `TooltipContent`, `TooltipTrigger` from `@/components/ui/tooltip`
    - Define a `Props` type with `credits: number` and `compact?: boolean`
    - Export a named function `CreditBalance` that renders:
        - A `Tooltip` wrapper for accessibility
        - Inside the trigger: a `div` with `flex items-center gap-1.5` containing a `Coins` icon (`size-4`) and a `span` with the credit count (hidden when `compact` is true)
        - Style the credit count with `text-sm font-medium tabular-nums` for consistent number width
        - Style the icon with `text-muted-foreground` for subtle appearance, `shrink-0` to prevent icon compression
        - The `TooltipContent` should show `"{credits} credit(s) remaining"` with proper pluralization
    - The component should be clean and minimal, following the patterns in sibling components like `user-info.tsx`
- Integrate into `/Users/young/Nextcloud/dev/Itervel/resources/js/components/app-header.tsx`:
    - Import `CreditBalance` from `@/components/credit-balance`
    - Add the credit balance display in the `ml-auto flex items-center space-x-2` div (line 179), before the search button and right nav items
    - Place it as: `<CreditBalance credits={auth.user.video_credits} />`
    - The `auth.user` object is already available from `usePage<SharedData>()` on line 68-69
    - Wrap it in a container that is visible on all screen sizes: no responsive hiding needed since the header bar is always visible
- Integrate into `/Users/young/Nextcloud/dev/Itervel/resources/js/components/nav-user.tsx`:
    - Import `CreditBalance` from `@/components/credit-balance`
    - Import `useSidebar` from `@/components/ui/sidebar` (already imported)
    - Add the credit balance above the `NavUser` dropdown in the sidebar. Place a `CreditBalance` component inside a `SidebarMenuItem` within the existing `SidebarMenu`, before the dropdown `SidebarMenuItem`. Use `state === 'collapsed'` from the existing `useSidebar()` hook (already called on line 21) to pass `compact={state === 'collapsed'}` to show only the icon when the sidebar is collapsed.
    - Structure in the `SidebarMenu`:
        ```tsx
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton
                    size="sm"
                    className="cursor-default hover:bg-transparent"
                >
                    <CreditBalance
                        credits={auth.user.video_credits}
                        compact={state === 'collapsed'}
                    />
                </SidebarMenuButton>
            </SidebarMenuItem>
            <SidebarMenuItem>
                {/* existing DropdownMenu for user */}
            </SidebarMenuItem>
        </SidebarMenu>
        ```
- Run `npm run types` to verify TypeScript types compile correctly
- Run `npm run lint:fix` to fix any linting issues
- Run `npm run format` to format with Prettier

### 2. Write Credit Balance Display Tests

- **Task ID**: write-credit-balance-tests
- **Depends On**: none
- **Assigned To**: credit-balance-test-dev
- **Agent Type**: coder
- **Parallel**: true
- Create the test file using: `php artisan make:test CreditBalanceDisplayTest --pest --no-interaction`
- Write the following tests in `/Users/young/Nextcloud/dev/Itervel/tests/Feature/CreditBalanceDisplayTest.php`:
    - `test('authenticated user sees video credits in shared data')` -- Create a user with `video_credits => 5`, act as that user, GET the dashboard, assert the Inertia response includes the user with video_credits:

        ```php
        test('authenticated user sees video credits in shared data', function () {
            $user = User::factory()->create(['video_credits' => 5]);

            $response = $this->actingAs($user)->get(route('dashboard'));

            $response->assertOk();
            $response->assertInertia(fn ($page) => $page
                ->where('auth.user.video_credits', 5)
            );
        });
        ```

    - `test('video credits update is reflected in shared data')` -- Create a user with 10 credits, modify credits to 7, GET the dashboard, assert the Inertia response shows 7:

        ```php
        test('video credits update is reflected in shared data', function () {
            $user = User::factory()->create(['video_credits' => 10]);
            $user->update(['video_credits' => 7]);

            $response = $this->actingAs($user)->get(route('dashboard'));

            $response->assertOk();
            $response->assertInertia(fn ($page) => $page
                ->where('auth.user.video_credits', 7)
            );
        });
        ```

    - `test('user with zero credits sees zero in shared data')` -- Create a user with 0 credits, GET the dashboard, assert 0:

        ```php
        test('user with zero credits sees zero in shared data', function () {
            $user = User::factory()->create(['video_credits' => 0]);

            $response = $this->actingAs($user)->get(route('dashboard'));

            $response->assertOk();
            $response->assertInertia(fn ($page) => $page
                ->where('auth.user.video_credits', 0)
            );
        });
        ```

    - `test('guest does not see credit balance data')` -- GET the dashboard as a guest, assert redirect to login (no credit data exposed to unauthenticated users):

        ```php
        test('guest does not see credit balance data', function () {
            $response = $this->get(route('dashboard'));

            $response->assertRedirect(route('login'));
        });
        ```

- Run the tests: `php artisan test tests/Feature/CreditBalanceDisplayTest.php --compact`
- Ensure all tests pass
- Run `vendor/bin/pint --dirty` to fix any PHP formatting issues

### 3. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-credit-balance-component, write-credit-balance-tests
- **Assigned To**: credit-balance-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed below
- Verify credit balance tests pass: `php artisan test tests/Feature/CreditBalanceDisplayTest.php --compact`
- Verify dashboard tests still pass: `php artisan test tests/Feature/DashboardTest.php --compact`
- Run the full test suite to ensure no regressions: `php artisan test --compact`
- Run TypeScript type checking: `npm run types`
- Run ESLint: `npm run lint`
- Run Prettier formatting check: `npm run format:check`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Verify the file `/Users/young/Nextcloud/dev/Itervel/resources/js/components/credit-balance.tsx` exists and exports a `CreditBalance` component
- Verify `/Users/young/Nextcloud/dev/Itervel/resources/js/components/app-header.tsx` imports and renders `CreditBalance` with `auth.user.video_credits`
- Verify `/Users/young/Nextcloud/dev/Itervel/resources/js/components/nav-user.tsx` imports and renders `CreditBalance` with `auth.user.video_credits` and handles the collapsed sidebar state
- Verify the credit balance is positioned in the header before the user avatar dropdown
- Verify the credit balance in the sidebar handles the collapsed state (shows only icon when collapsed)
- Verify the `CreditBalance` component has a tooltip showing the credit count with proper pluralization
- Confirm all acceptance criteria are met

## Acceptance Criteria

- A `CreditBalance` reusable component exists at `resources/js/components/credit-balance.tsx`
- The credit balance is visible in the application header layout (`app-header.tsx`) for authenticated users
- The credit balance is visible in the sidebar layout (`nav-user.tsx`) for authenticated users
- The credit balance displays a coin icon (`Coins` from lucide-react) alongside the numeric credit count
- Hovering over the credit balance shows a tooltip with "{N} credit(s) remaining" (with proper pluralization)
- The sidebar credit balance handles the collapsed state by showing only the icon
- The `video_credits` value is included in the Inertia shared data for authenticated users (verified by test)
- Users with 0 credits see "0" displayed
- The credit balance updates reflect the actual database value on each page load
- All credit balance display tests pass
- All existing tests pass without regressions
- TypeScript types compile without errors
- ESLint and Prettier checks pass
- PHP code passes Pint formatting

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run credit balance display tests
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

- No backend changes are required for this feature. The `HandleInertiaRequests` middleware already shares `$request->user()` as `auth.user`, which includes all model attributes (including `video_credits` after E001-F001). The `video_credits` attribute is not in the `$hidden` array, so it is serialized to the frontend automatically.
- The `video_credits` TypeScript type is added to the `User` type in E001-F001. If for any reason that dependency is not yet built, the `User` type has `[key: string]: unknown` which allows accessing `video_credits` but without type safety. The implementation should assume the typed property exists.
- The `CreditBalance` component is intentionally kept simple and reusable. It will be used again in features like E001-F058 (Low Balance Warning) which may enhance it with warning styling when credits are low, and E001-F059 (Insufficient Credits Block) which may reference the credit count. Keeping it as a standalone component enables this future extension.
- The `Coins` icon from `lucide-react` is chosen because it semantically represents credits/currency. Other options considered: `CircleDollarSign` (too dollar-specific), `Wallet` (implies payment, not balance), `CreditCard` (implies payment method). `Coins` best represents a balance of credits.
- The credit balance does not need real-time updates (WebSocket/polling) for this feature. It refreshes on each Inertia page navigation, which is sufficient since credit deductions happen during video rendering (a page-transition event). Real-time updates could be added later if needed.
- All commands should be run inside the Docker container. Use `make shell` to enter the container, or prefix with `docker compose exec app`.

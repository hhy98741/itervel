# Feature: Responsive Layout

**Epic**: E010-video-creation-ux.md
**Feature**: E010-F004
**Dependencies**: none

## Task Description

The Responsive Layout feature adapts the Itervel application layout for desktop, tablet, and mobile screens to ensure a seamless user experience across all device sizes. This is a foundational cross-cutting concern that establishes responsive layout patterns, utility hooks, and component infrastructure that all future features will build upon.

**What it does**: Adapts the application layout for desktop, tablet, and mobile screens.

**Expected outcome**: On desktop (1200px+), all features are available with side-by-side layouts. On tablet (768-1199px), layouts stack vertically with full functionality. On mobile (under 768px), users can review outputs, download files, and monitor progress. Full video creation is optimized for desktop.

The application currently uses the Laravel React Starter Kit's layout system, which includes a `SidebarProvider` with mobile detection via `useIsMobile()` (breakpoint at 768px using `matchMedia`), an `AppSidebar` that collapses to a `Sheet` on mobile, and an `AppHeader` variant with a mobile hamburger menu. The existing responsive infrastructure provides a solid foundation but needs to be extended with:

1. A richer breakpoint system matching the three-tier specification (mobile < 768px, tablet 768-1199px, desktop 1200px+)
2. New responsive utility hooks for tablet and desktop detection
3. A responsive container component for consistent content width management
4. Responsive grid layout components for side-by-side (desktop) vs stacked (tablet/mobile) content
5. A mobile-aware banner/notice system to inform mobile users that full video creation is desktop-optimized
6. Updates to the existing layout components to use the extended breakpoint system

## Objective

Establish a comprehensive responsive layout infrastructure with three breakpoint tiers (mobile, tablet, desktop), responsive utility hooks, a responsive container component, and responsive content grid components. The existing sidebar layout already handles mobile/desktop sidebar collapsing, so this feature extends the system to support the specific layout behaviors described: side-by-side on desktop, vertically stacked on tablet, and a review/download-focused experience on mobile with a desktop-optimization notice for video creation workflows.

## Solution Approach

### Breakpoint Strategy

The application uses Tailwind CSS v4, which provides the following default breakpoints:

- `sm`: 640px
- `md`: 768px
- `lg`: 1024px
- `xl`: 1280px
- `2xl`: 1536px

Map the feature's three tiers to Tailwind breakpoints:

- **Mobile**: < 768px (default, no prefix)
- **Tablet**: 768px-1199px (`md:` prefix, up to but not including `xl:`)
- **Desktop**: 1200px+ (custom breakpoint or `xl:` at 1280px)

Since Tailwind v4 uses CSS-based configuration via `@theme`, add a custom breakpoint at 1200px to match the spec exactly. Add a `--breakpoint-desktop: 1200px` in `resources/css/app.css` under the `@theme` block, which allows using `desktop:` as a Tailwind prefix. Alternatively, use `min-[1200px]:` as an arbitrary breakpoint value -- this is cleaner and avoids modifying global theme config for one feature.

**Decision**: Use Tailwind's arbitrary value syntax `min-[1200px]:` for the exact 1200px breakpoint, combined with the existing `md:` (768px) breakpoint for tablet. This avoids unnecessary theme configuration and follows the YAGNI principle. Create a TypeScript constant for this value in the hooks for JavaScript-side matching.

### Responsive Hooks Architecture

Extend the existing `useIsMobile` hook pattern to create a suite of responsive hooks:

```tsx
// Breakpoint constants
const MOBILE_BREAKPOINT = 768;
const DESKTOP_BREAKPOINT = 1200;

// Hook: useBreakpoint() - returns 'mobile' | 'tablet' | 'desktop'
// Hook: useIsTablet() - true when 768px <= width < 1200px
// Hook: useIsDesktop() - true when width >= 1200px
```

All hooks follow the existing `useSyncExternalStore` pattern from `use-mobile.tsx` for SSR safety and consistent reactivity.

### Responsive Container Component

Create a `ResponsiveContainer` component that wraps content with consistent max-width and padding rules per breakpoint:

- Mobile: full-width with `px-4` padding
- Tablet: `max-w-3xl` centered with `px-6` padding
- Desktop: `max-w-7xl` centered with `px-8` padding

This mirrors the existing `AppContent` component pattern but adds explicit breakpoint-driven behavior.

### Responsive Content Grid Component

Create a `ResponsiveGrid` component that handles the side-by-side (desktop) vs stacked (tablet/mobile) layout pattern:

- Desktop: `grid-cols-2` or configurable column count for side-by-side
- Tablet: `grid-cols-1` stacked vertically
- Mobile: `grid-cols-1` stacked vertically

This component uses standard Tailwind responsive classes and accepts a `columns` prop for the desktop column count.

### Mobile Desktop-Optimization Notice

Create a `DesktopOptimizedNotice` component that renders a dismissible banner on mobile devices, informing users that full video creation is optimized for desktop. This component:

- Uses `useIsMobile()` to conditionally render
- Shows a subtle, non-blocking info banner
- Is dismissible (persisted to `sessionStorage`)
- Provides a "Continue Anyway" option alongside a "Switch to Desktop" suggestion

### Layout Component Updates

Update the existing layout infrastructure minimally:

- The `AppShell` component is already well-structured with `SidebarProvider` for mobile
- The `AppSidebarLayout` and `AppHeaderLayout` already handle basic responsive behavior
- Add the `ResponsiveContainer` as an optional wrapper within `AppContent` for pages that need the three-tier responsive behavior
- The settings layout (`SettingsLayout`) already uses `lg:flex-row` / `lg:w-48` patterns -- these serve as examples of the existing approach

### No Backend Changes

This feature is purely frontend. No controllers, middleware, routes, or models need to change.

### Testing Approach

Since this is a CSS/hook-based feature, testing focuses on:

1. PHP feature tests verifying pages render successfully (Inertia rendering)
2. TypeScript type checking to ensure hook interfaces are correct
3. The hooks themselves are tested by ensuring the build passes and types are correct -- unit testing `matchMedia` behavior is not practical without a browser environment, and the existing `useIsMobile` hook already establishes this pattern without tests

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/resources/js/hooks/use-mobile.tsx` -- The existing mobile detection hook using `useSyncExternalStore` and `matchMedia`. This is the pattern to follow for new responsive hooks. Uses `MOBILE_BREAKPOINT = 768`.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/hooks/use-mobile-navigation.ts` -- Existing hook for mobile navigation cleanup. Shows the hook file naming pattern.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/sidebar.tsx` -- The comprehensive sidebar UI component that already uses `useIsMobile()` for mobile detection and renders a `Sheet` on mobile vs a fixed sidebar on desktop. The `SidebarProvider` context is the primary consumer of mobile state. Shows the existing responsive pattern.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/app-shell.tsx` -- The top-level shell that wraps either a header layout or sidebar layout with `SidebarProvider`. Entry point for layout variant selection.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/app-content.tsx` -- The content area component that renders as `<main>` for header variant or `SidebarInset` for sidebar variant. This is where responsive container behavior could be integrated.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/app-header.tsx` -- The header layout navigation that already has mobile menu (Sheet-based hamburger at `lg:hidden`) and desktop nav (`hidden lg:flex`). Shows existing responsive class patterns.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/app-sidebar-header.tsx` -- Sidebar header with responsive height transition using `group-has-data-[collapsible=icon]`.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/app-layout.tsx` -- The main app layout entry point that delegates to `app-sidebar-layout`.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/app/app-sidebar-layout.tsx` -- The sidebar layout composition that combines `AppShell`, `AppSidebar`, `AppContent`, and `AppSidebarHeader`.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/app/app-header-layout.tsx` -- The alternative header-only layout composition.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/settings/layout.tsx` -- Settings layout with existing responsive pattern: `flex-col lg:flex-row lg:space-x-12` and `w-full lg:w-48`. Good reference for tablet/desktop stacking behavior.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/auth/auth-split-layout.tsx` -- Auth split layout using `lg:grid-cols-2` for side-by-side on desktop. Another example of the responsive grid pattern.
- `/Users/young/Nextcloud/dev/Itervel/resources/css/app.css` -- Tailwind CSS v4 configuration with theme variables. Where custom breakpoints could be added if needed.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/lib/utils.ts` -- The `cn()` utility for conditional Tailwind class merging.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/index.ts` -- Shared types including `SharedData` and re-exports from `ui.ts`, `navigation.ts`, `auth.ts`.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/ui.ts` -- UI type definitions including `AppLayoutProps` and `AuthLayoutProps`.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/dashboard.tsx` -- The dashboard page that already uses responsive grid: `md:grid-cols-3`. This page should be updated to demonstrate the new responsive layout components.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/alert.tsx` -- Existing Alert component that can be used for the desktop-optimization notice banner.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/DashboardTest.php` -- Existing dashboard test showing the Pest test pattern for authenticated page rendering.
- `/Users/young/Nextcloud/dev/Itervel/vite.config.ts` -- Vite config with React, Tailwind, and Wayfinder plugins. No changes needed but reference for build setup.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/resources/js/hooks/use-breakpoint.ts` -- Central responsive breakpoint hook that exports `useBreakpoint()`, `useIsTablet()`, and `useIsDesktop()` functions. Follows the `useSyncExternalStore` pattern from `use-mobile.tsx`.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/responsive-container.tsx` -- A responsive wrapper component that applies consistent max-width and padding based on the current breakpoint tier.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/responsive-grid.tsx` -- A responsive grid component that renders side-by-side columns on desktop and stacks vertically on tablet/mobile.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/desktop-optimized-notice.tsx` -- A dismissible banner component that notifies mobile users that full video creation is optimized for desktop.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/ResponsiveLayoutTest.php` -- Pest feature tests verifying pages render correctly and the responsive layout components are available.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Frontend Developer (Hooks & Utilities)
    - Name: responsive-hooks-dev
    - Role: Creates the responsive breakpoint hooks (`use-breakpoint.ts`) following the existing `use-mobile.tsx` pattern with `useSyncExternalStore`, and adds the custom breakpoint constant to the CSS theme if needed
    - Agent Type: coder
    - Resume: false

- Frontend Developer (Components)
    - Name: responsive-components-dev
    - Role: Creates the responsive layout components (`responsive-container.tsx`, `responsive-grid.tsx`, `desktop-optimized-notice.tsx`) and updates the dashboard page to demonstrate the new responsive patterns
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: responsive-test-dev
    - Role: Writes Pest feature tests verifying page rendering and creates the test file for the responsive layout feature
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: responsive-reviewer
    - Role: Validates the complete responsive layout implementation against acceptance criteria, runs tests, type checks, linting, and verifies correct responsive class usage across all new files
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

### 1. Create Responsive Breakpoint Hooks

- **Task ID**: create-breakpoint-hooks
- **Depends On**: none
- **Assigned To**: responsive-hooks-dev
- **Agent Type**: coder
- **Parallel**: true
- Read the existing `/Users/young/Nextcloud/dev/Itervel/resources/js/hooks/use-mobile.tsx` file to understand the `useSyncExternalStore` + `matchMedia` pattern
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/hooks/use-breakpoint.ts` with the following exports:
    - `MOBILE_BREAKPOINT = 768` constant (matches existing value in `use-mobile.tsx`)
    - `DESKTOP_BREAKPOINT = 1200` constant (matches the feature spec)
    - `type Breakpoint = 'mobile' | 'tablet' | 'desktop'` type export
    - `useBreakpoint(): Breakpoint` -- Returns the current breakpoint tier based on window width. Uses two `matchMedia` queries: one for `(max-width: 767px)` (mobile) and one for `(min-width: 1200px)` (desktop). If neither matches, it's tablet. Uses `useSyncExternalStore` for SSR safety. Server snapshot returns `'desktop'` (desktop-first for SSR)
    - `useIsTablet(): boolean` -- Returns `true` when breakpoint is `'tablet'`. Implemented using a single `matchMedia` query: `(min-width: 768px) and (max-width: 1199px)`. Uses `useSyncExternalStore` pattern
    - `useIsDesktop(): boolean` -- Returns `true` when breakpoint is `'desktop'`. Uses `matchMedia` query: `(min-width: 1200px)`. Uses `useSyncExternalStore` pattern
- Each hook should follow the exact same structure as `use-mobile.tsx`: module-level `matchMedia` instantiation, `subscribe` function, `getSnapshot` function, `getServerSnapshot` function, then the hook function using `useSyncExternalStore`
- Ensure the file uses named exports (not default) since it exports multiple hooks
- Run `npm run types` to verify TypeScript compiles correctly

### 2. Create Responsive Layout Components

- **Task ID**: create-responsive-components
- **Depends On**: create-breakpoint-hooks
- **Assigned To**: responsive-components-dev
- **Agent Type**: coder
- **Parallel**: false
- Read the existing components for patterns: `/Users/young/Nextcloud/dev/Itervel/resources/js/components/app-content.tsx`, `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/card.tsx`, `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/alert.tsx`
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/components/responsive-container.tsx`:
    - A wrapper component that provides consistent responsive max-width and padding
    - Props: `children: ReactNode`, `className?: string`, `as?: React.ElementType` (defaults to `'div'`)
    - Apply classes: `w-full px-4 md:px-6 md:max-w-3xl min-[1200px]:px-8 min-[1200px]:max-w-7xl mx-auto`
    - Use `cn()` from `@/lib/utils` to merge with any custom `className`
    - Export as a named export: `export function ResponsiveContainer(...)`
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/components/responsive-grid.tsx`:
    - A grid component that renders side-by-side on desktop and stacked on tablet/mobile
    - Props: `children: ReactNode`, `className?: string`, `columns?: 2 | 3` (defaults to `2`), `gap?: string` (defaults to `'gap-6'`)
    - Apply classes: `grid grid-cols-1 min-[1200px]:grid-cols-{columns}` with the configurable gap
    - Use `cn()` for class merging
    - Export as a named export: `export function ResponsiveGrid(...)`
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/components/desktop-optimized-notice.tsx`:
    - A dismissible info banner for mobile users
    - Uses `useIsMobile()` from `@/hooks/use-mobile` to conditionally render (only shows on mobile)
    - Uses `useState` initialized from `sessionStorage.getItem('desktop-notice-dismissed')` to track dismissal
    - When dismissed, sets `sessionStorage.setItem('desktop-notice-dismissed', 'true')` and hides
    - Renders using the existing `Alert` component from `@/components/ui/alert` with an info variant or a custom styled div with `bg-muted` background
    - Contains text: "For the best video creation experience, we recommend using a desktop browser." with an "X" close button
    - Also shows secondary text: "You can still review outputs, download files, and monitor progress on mobile."
    - Uses `Monitor` icon from `lucide-react` for the alert icon and `X` icon for dismiss
    - Export as a named export: `export function DesktopOptimizedNotice(...)`
- Update `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/dashboard.tsx` to demonstrate the responsive layout:
    - Import `ResponsiveGrid` from `@/components/responsive-grid`
    - Import `DesktopOptimizedNotice` from `@/components/desktop-optimized-notice`
    - Add `<DesktopOptimizedNotice />` at the top of the dashboard content (inside the existing flex container, before the grid)
    - The existing dashboard grid already uses `md:grid-cols-3` which is a good responsive pattern -- keep it. The `ResponsiveGrid` component is available for future feature pages that need the desktop side-by-side vs tablet/mobile stacked pattern
- Run `npm run types` to verify TypeScript compiles correctly

### 3. Write Feature Tests

- **Task ID**: write-responsive-tests
- **Depends On**: create-responsive-components
- **Assigned To**: responsive-test-dev
- **Agent Type**: coder
- **Parallel**: false
- Read the existing test file `/Users/young/Nextcloud/dev/Itervel/tests/Feature/DashboardTest.php` for the Pest test pattern
- Create `/Users/young/Nextcloud/dev/Itervel/tests/Feature/ResponsiveLayoutTest.php` using `php artisan make:test --pest ResponsiveLayoutTest` (run inside Docker container: prefix commands with `docker compose exec app`)
- Write the following tests:
    - `test('dashboard page renders successfully for authenticated user')` -- `$user = User::factory()->create(); $this->actingAs($user)->get(route('dashboard'))->assertOk();`
    - `test('dashboard page returns inertia dashboard component')` -- Verify the Inertia component name is `dashboard` using `assertInertia(fn ($page) => $page->component('dashboard'))`
    - `test('settings profile page renders with responsive layout')` -- `$user = User::factory()->create(); $this->actingAs($user)->get(route('profile.edit'))->assertOk();`
    - `test('settings password page renders with responsive layout')` -- `$user = User::factory()->create(); $this->actingAs($user)->get(route('user-password.edit'))->assertOk();`
    - `test('unauthenticated user is redirected from dashboard')` -- `$this->get(route('dashboard'))->assertRedirect(route('login'));`
- Use `User::factory()->create()` for all authenticated tests
- Run the tests with `docker compose exec app php artisan test tests/Feature/ResponsiveLayoutTest.php --compact`
- Also run `docker compose exec app php artisan test --compact` to verify no regressions

### 4. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-breakpoint-hooks, create-responsive-components, write-responsive-tests
- **Assigned To**: responsive-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed in the Validation Commands section below
- Verify the new hook file `/Users/young/Nextcloud/dev/Itervel/resources/js/hooks/use-breakpoint.ts` exists and exports `useBreakpoint`, `useIsTablet`, `useIsDesktop`, `MOBILE_BREAKPOINT`, `DESKTOP_BREAKPOINT`, and the `Breakpoint` type
- Verify the hook follows the `useSyncExternalStore` pattern from `use-mobile.tsx`
- Verify `/Users/young/Nextcloud/dev/Itervel/resources/js/components/responsive-container.tsx` exists and uses `cn()`, supports `className` prop, and applies correct responsive classes with `min-[1200px]:` for the desktop breakpoint
- Verify `/Users/young/Nextcloud/dev/Itervel/resources/js/components/responsive-grid.tsx` exists and uses `min-[1200px]:grid-cols-{n}` for desktop side-by-side layout
- Verify `/Users/young/Nextcloud/dev/Itervel/resources/js/components/desktop-optimized-notice.tsx` exists, uses `useIsMobile()`, supports dismissal via `sessionStorage`, and renders appropriate messaging
- Verify `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/dashboard.tsx` imports and renders `DesktopOptimizedNotice`
- Verify all new files follow the project's kebab-case naming convention
- Verify no existing functionality is broken by the changes
- Confirm all acceptance criteria are met
- Run PHP formatting: `docker compose exec app vendor/bin/pint --dirty`

## Acceptance Criteria

- A `useBreakpoint()` hook exists that returns `'mobile'`, `'tablet'`, or `'desktop'` based on the current viewport width
- A `useIsTablet()` hook returns `true` when viewport is between 768px and 1199px
- A `useIsDesktop()` hook returns `true` when viewport is 1200px or wider
- All hooks use `useSyncExternalStore` for SSR safety, following the existing `useIsMobile()` pattern
- Breakpoint constants `MOBILE_BREAKPOINT` (768) and `DESKTOP_BREAKPOINT` (1200) are exported for reuse
- A `ResponsiveContainer` component provides consistent responsive max-width and padding across breakpoints
- A `ResponsiveGrid` component renders side-by-side columns on desktop (1200px+) and stacks vertically on tablet/mobile
- A `DesktopOptimizedNotice` component shows a dismissible banner on mobile informing users that video creation is desktop-optimized
- The dismissal state of `DesktopOptimizedNotice` persists within the session via `sessionStorage`
- The dashboard page renders the `DesktopOptimizedNotice` component
- All new components use the `cn()` utility from `@/lib/utils`
- All new components support dark mode via existing Tailwind theme variables
- TypeScript type checking passes (`npm run types`)
- ESLint passes (`npm run lint`)
- All Pest feature tests pass, including the new `ResponsiveLayoutTest.php`
- No regressions in the existing test suite

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run responsive layout tests
docker compose exec app php artisan test tests/Feature/ResponsiveLayoutTest.php --compact

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

- The existing `useIsMobile()` hook in `use-mobile.tsx` is kept as-is for backward compatibility since it is used by the sidebar component. The new `useBreakpoint()` hook complements it rather than replacing it.
- The `min-[1200px]:` Tailwind arbitrary value syntax is preferred over adding a custom theme breakpoint because it keeps the change localized and avoids modifying the global CSS theme for a single feature. If many features end up needing 1200px as a breakpoint, it can be promoted to a theme-level `--breakpoint-desktop` in `app.css` later.
- The server-side snapshot for `useBreakpoint()` returns `'desktop'` because the application is primarily designed for desktop use and this provides the widest layout during SSR/hydration. This matches the existing `useIsMobile()` pattern which returns `false` (not mobile) for SSR.
- The `DesktopOptimizedNotice` is designed to be addable to any page that contains video creation workflow steps. For now it is placed on the dashboard as a demonstration. Other feature implementations (e.g., the video creation wizard from E001-F070) should add it to their own pages as appropriate.
- All commands should be run inside the Docker container. Use `docker compose exec app` prefix or `make shell` to enter the container. Frontend commands (`npm run types`, `npm run lint`) can be run from the host if Node.js is available, or inside the container.
- The `ResponsiveGrid` component uses `min-[1200px]:` instead of `lg:` (1024px) to precisely match the spec's 1200px desktop breakpoint. This is a deliberate choice to match the feature requirement that side-by-side layouts appear at 1200px+, not at 1024px.

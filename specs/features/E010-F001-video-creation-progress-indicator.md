# Feature: Video Creation Progress Indicator

**Epic**: E010-video-creation-ux.md
**Feature**: E010-F001
**Epic depends on**: E006-brand-guide-and-video-input.md
**Feature depends on**: E010-F004

## Task Description

Shows the overall progress of the video creation workflow across all steps. The user sees a visual indicator showing which steps are complete (checkmark), which is currently active (spinner with progress), and which are upcoming. Estimated time remaining is displayed.

**What it does**: Shows the overall progress of the video creation workflow across all steps.

**Expected outcome**: The user sees a visual indicator showing which steps are complete (checkmark), which is currently active (spinner with progress), and which are upcoming. Estimated time remaining is displayed.

This feature creates a reusable progress indicator component for the video creation wizard. The wizard has 9 steps: Input, Title Selection, Outline Review, Script Review, Voiceover Selection, Thumbnail Selection, Music Selection, Final Review, and Download. The progress indicator is a visual sidebar or horizontal bar that shows the user where they are in the workflow. It needs to handle three states per step (complete, active, upcoming), show a spinner on the active step, and display an estimated time remaining based on the current and remaining steps.

The progress indicator is a purely frontend component. It receives its state from props (current step, step statuses, estimated time) and renders accordingly. It will be consumed by the wizard navigation component (E010-F002) which manages the step state.

This feature depends on E010-F004 (Responsive Layout) because the progress indicator must adapt its presentation across breakpoints: a vertical sidebar on desktop, and a compact horizontal bar on tablet/mobile.

## Objective

Create a reusable `VideoProgressIndicator` component that visually communicates the user's position in the 9-step video creation workflow. The component shows completed steps with checkmarks, the active step with a spinner, upcoming steps as muted, and an estimated time remaining. The component is responsive: vertical on desktop, horizontal/compact on tablet and mobile.

## Solution Approach

### Data Model

Define a TypeScript type for the step data the component receives:

```tsx
type StepStatus = 'complete' | 'active' | 'upcoming';

type WizardStep = {
    id: string;
    label: string;
    status: StepStatus;
};
```

The 9 steps and their IDs:

| Step | ID                    | Label               |
| ---- | --------------------- | ------------------- |
| 1    | `input`               | Input               |
| 2    | `title-selection`     | Title Selection     |
| 3    | `outline-review`      | Outline Review      |
| 4    | `script-review`       | Script Review       |
| 5    | `voiceover-selection` | Voiceover Selection |
| 6    | `thumbnail-selection` | Thumbnail Selection |
| 7    | `music-selection`     | Music Selection     |
| 8    | `final-review`        | Final Review        |
| 9    | `download`            | Download            |

Define these step definitions as a constant array in a shared types/constants file so the wizard (E010-F002) and progress indicator use the same source of truth.

### Component Architecture

**`VideoProgressIndicator`** - The main component.

Props:

```tsx
interface VideoProgressIndicatorProps {
    steps: WizardStep[];
    currentStepIndex: number;
    estimatedTimeRemaining?: string; // e.g., "~3 min remaining"
    className?: string;
}
```

The component renders:

- **Desktop (1200px+)**: A vertical list of steps in a sidebar-style column. Each step shows a circle indicator (checkmark for complete, spinner for active, numbered circle for upcoming), the step label, and a connecting line between steps.
- **Tablet/Mobile (< 1200px)**: A compact horizontal progress bar showing the current step number, step label, a progress fraction (e.g., "3 of 9"), and estimated time. A thin progress bar fills proportionally.

Use `useIsDesktop()` from `@/hooks/use-breakpoint` (created in E010-F004) to switch between layouts.

### Visual Design

**Desktop vertical layout:**

```
  [✓] Input
   |
  [✓] Title Selection
   |
  [◉] Outline Review        ← spinner, highlighted
   |
  [ ] Script Review          ← muted
   |
  [ ] Voiceover Selection
   |
  [ ] Thumbnail Selection
   |
  [ ] Music Selection
   |
  [ ] Final Review
   |
  [ ] Download

  ~5 min remaining
```

**Mobile/Tablet compact layout:**

```
┌─────────────────────────────────────────┐
│  Step 3 of 9 · Outline Review · ~5 min │
│  ████████░░░░░░░░░░░░░░░░░░░░░░░░░░░░░ │
└─────────────────────────────────────────┘
```

### Styling

- Use existing UI primitives: `Spinner` from `@/components/ui/spinner` for the active step
- Use `Check` icon from `lucide-react` for completed steps
- Use `cn()` from `@/lib/utils` for conditional class merging
- Color scheme:
    - Complete: `text-primary` with filled circle background
    - Active: `text-primary` with spinner animation, slightly larger/emphasized
    - Upcoming: `text-muted-foreground` with empty circle border
    - Connecting lines: `border-primary` for completed connections, `border-muted` for upcoming
- Estimated time: `text-sm text-muted-foreground` at the bottom (desktop) or inline (mobile)
- Dark mode: Use theme CSS variables (`text-primary`, `text-muted-foreground`, `bg-muted`, etc.) which automatically adapt

### Step Constants File

Create a shared constants file for the wizard step definitions:

```tsx
// resources/js/constants/video-wizard-steps.ts
export const VIDEO_WIZARD_STEPS = [
    { id: 'input', label: 'Input' },
    { id: 'title-selection', label: 'Title Selection' },
    { id: 'outline-review', label: 'Outline Review' },
    { id: 'script-review', label: 'Script Review' },
    { id: 'voiceover-selection', label: 'Voiceover Selection' },
    { id: 'thumbnail-selection', label: 'Thumbnail Selection' },
    { id: 'music-selection', label: 'Music Selection' },
    { id: 'final-review', label: 'Final Review' },
    { id: 'download', label: 'Download' },
] as const;

export type VideoWizardStepId = (typeof VIDEO_WIZARD_STEPS)[number]['id'];
```

### No Backend Changes

This feature is purely frontend. No controllers, routes, models, or migrations are needed. The progress indicator is a presentational component that receives all its data via props.

### Testing Approach

Since this is a React component with no backend dependencies:

1. TypeScript type checking ensures the interfaces are correct
2. ESLint/Prettier ensure code quality
3. No PHP feature tests needed for this component alone (the wizard feature E010-F002 will test the full page rendering)

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/resources/js/hooks/use-breakpoint.ts` -- The responsive breakpoint hooks created in E010-F004. Use `useIsDesktop()` to switch between vertical and compact layouts.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/hooks/use-mobile.tsx` -- The existing mobile detection hook. Reference for pattern but use `use-breakpoint.ts` hooks instead for the three-tier system.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/spinner.tsx` -- The existing Spinner component to use for the active step indicator.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/badge.tsx` -- May be useful for step number badges or status indicators.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/separator.tsx` -- Reference for the connecting line styling between steps.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/lib/utils.ts` -- The `cn()` utility for conditional Tailwind class merging.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/index.ts` -- Shared types. The new wizard types can be added here or in a dedicated file.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/app-content.tsx` -- Reference for component structure patterns.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/heading.tsx` -- Reference for component export patterns and structure.
- `/Users/young/Nextcloud/dev/Itervel/specs/features/E010-F004-responsive-layout.md` -- The responsive layout feature plan. This feature depends on the hooks and components created there.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/resources/js/constants/video-wizard-steps.ts` -- Shared constants defining the 9 video wizard steps with their IDs and labels. Used by both the progress indicator and the wizard navigation (E010-F002).
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/video.ts` -- TypeScript types for the video creation workflow: `StepStatus`, `WizardStep`, `VideoWizardStepId`, and `VideoProgressIndicatorProps`.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/video-progress-indicator.tsx` -- The main progress indicator component with desktop vertical and mobile/tablet compact layouts.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Frontend Developer (Types & Constants)
    - Name: types-constants-dev
    - Role: Creates the shared video wizard step constants and TypeScript type definitions used by the progress indicator and wizard
    - Agent Type: coder
    - Resume: false

- Frontend Developer (Component)
    - Name: progress-indicator-dev
    - Role: Creates the VideoProgressIndicator component with desktop vertical and mobile/tablet compact responsive layouts
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: progress-indicator-reviewer
    - Role: Validates the progress indicator implementation, checks TypeScript types, linting, responsive behavior, and ensures the component follows existing codebase patterns
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Create Video Wizard Types and Constants

- **Task ID**: create-wizard-types
- **Depends On**: none
- **Assigned To**: types-constants-dev
- **Agent Type**: coder
- **Parallel**: true
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/types/index.ts` and `/Users/young/Nextcloud/dev/Itervel/resources/js/types/auth.ts` to understand the existing type export pattern
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/hooks/use-breakpoint.ts` to verify it exists (dependency from E010-F004)
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/constants/video-wizard-steps.ts` with the `VIDEO_WIZARD_STEPS` array constant and `VideoWizardStepId` type. First create the `constants/` directory. The array should define 9 steps in order with `id` (kebab-case string) and `label` (display string) properties. Use `as const` for type narrowing
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/types/video.ts` with `StepStatus` (`'complete' | 'active' | 'upcoming'`), `WizardStep` (with `id`, `label`, `status` fields), and `VideoProgressIndicatorProps` (with `steps: WizardStep[]`, `currentStepIndex: number`, `estimatedTimeRemaining?: string`, `className?: string`)
- Update `/Users/young/Nextcloud/dev/Itervel/resources/js/types/index.ts` to re-export from `./video` (follow the pattern of existing re-exports from `./auth`, `./navigation`, `./ui`)
- Run `npm run types` to verify TypeScript compiles correctly

### 2. Create Video Progress Indicator Component

- **Task ID**: create-progress-indicator
- **Depends On**: create-wizard-types
- **Assigned To**: progress-indicator-dev
- **Agent Type**: coder
- **Parallel**: false
- Read the following files to understand patterns: `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/spinner.tsx`, `/Users/young/Nextcloud/dev/Itervel/resources/js/components/heading.tsx`, `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/badge.tsx`, `/Users/young/Nextcloud/dev/Itervel/resources/js/hooks/use-breakpoint.ts`
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/components/video-progress-indicator.tsx`:
    - Import `useIsDesktop` from `@/hooks/use-breakpoint`
    - Import `Spinner` from `@/components/ui/spinner`
    - Import `Check` from `lucide-react`
    - Import `cn` from `@/lib/utils`
    - Import `VideoProgressIndicatorProps` from `@/types`
    - Create the component with two rendering modes:
        - **Desktop (vertical)**: A `<nav>` element with `<ol>` list. Each step renders as a `<li>` with: a circle indicator (32px, rounded-full), step label text, and a vertical connecting line (2px border) between steps. Complete steps show a `Check` icon in a filled primary circle. Active step shows `Spinner` in a primary-outlined circle with the label in bold. Upcoming steps show the step number in a muted bordered circle with muted text. Estimated time appears below the last step as `text-sm text-muted-foreground`
        - **Tablet/Mobile (compact)**: A `<div>` with: a top row showing "Step {n} of {total} · {label} · {estimatedTime}" in `text-sm`, and a bottom row with a thin progress bar (`h-1 rounded-full bg-muted` container with a `bg-primary` fill at `width: {(currentStepIndex + 1) / total * 100}%` with transition)
    - Use `data-slot="video-progress-indicator"` on the root element
    - Export as named export: `export function VideoProgressIndicator(...)`
- Run `npm run types` to verify TypeScript compiles
- Run `npm run lint` to verify ESLint passes

### 3. Validate Progress Indicator Implementation

- **Task ID**: validate-all
- **Depends On**: create-wizard-types, create-progress-indicator
- **Assigned To**: progress-indicator-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Read the feature plan at `/Users/young/Nextcloud/dev/Itervel/specs/features/E010-F001-video-creation-progress-indicator.md`
- Read all created/modified files: `resources/js/constants/video-wizard-steps.ts`, `resources/js/types/video.ts`, `resources/js/types/index.ts`, `resources/js/components/video-progress-indicator.tsx`
- Run all validation commands
- Verify the component uses `useIsDesktop()` for responsive switching
- Verify step states (complete/active/upcoming) render distinct visuals
- Verify the estimated time remaining is displayed
- Verify `cn()` is used for class merging and `className` prop is supported
- Verify all acceptance criteria are met

## Acceptance Criteria

- A `VIDEO_WIZARD_STEPS` constant array defines the 9 steps with IDs and labels
- A `VideoWizardStepId` type is exported for type-safe step references
- `StepStatus`, `WizardStep`, and `VideoProgressIndicatorProps` types are defined and exported
- The `VideoProgressIndicator` component renders a vertical step list on desktop (1200px+)
- The `VideoProgressIndicator` component renders a compact horizontal bar on tablet/mobile (< 1200px)
- Completed steps show a checkmark icon in a filled circle
- The active step shows a spinner animation with emphasized label text
- Upcoming steps show the step number in a muted circle with muted text
- Vertical connecting lines between steps use primary color for completed connections and muted for upcoming
- Estimated time remaining is displayed (at the bottom on desktop, inline on mobile/tablet)
- The component uses `data-slot="video-progress-indicator"` on the root element
- All new files follow kebab-case naming convention
- TypeScript type checking passes (`npm run types`)
- ESLint passes (`npm run lint`)
- The component supports dark mode via existing theme CSS variables

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# TypeScript type checking
npm run types

# ESLint linting
npm run lint

# Prettier formatting check
npm run format:check
```

## Notes

- This component is purely presentational -- it does not manage step state itself. The parent wizard component (E010-F002) will manage the current step, compute step statuses, and pass them as props.
- The estimated time remaining is a string prop, not computed by this component. The wizard or a higher-level component will calculate it based on the remaining steps and known processing times.
- The `constants/` directory is new and follows the pattern of keeping shared constants separate from types and components.
- The vertical layout uses `<nav>` and `<ol>` for accessibility and semantic HTML. Screen readers will understand the list structure and current position.
- The compact mobile layout prioritizes showing "where am I" information without taking up much vertical space, since mobile users may be monitoring progress rather than actively creating.

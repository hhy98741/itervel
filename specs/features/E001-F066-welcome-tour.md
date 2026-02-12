# Feature: Welcome Tour

**Epic**: E001-ai-powered-faceless-video-creation-platform.md
**Feature**: E001-F066
**Dependencies**: E001-F003

## Task Description

The Welcome Tour introduces new users to the Itervel platform through a brief guided tour shown after their first login. It educates users about the 4-step video creation workflow before they start using the application.

**What it does**: Introduces new users to the platform through a brief guided tour after their first login.

**Expected outcome**: The user sees 4 slides explaining the workflow: (1) enter topic and references, (2) AI generates and refines content, (3) review and customize at each step, (4) download complete video package.

The tour is implemented as a full-screen modal dialog that appears on the dashboard after a user's first login. The system tracks whether the user has completed the tour via a `has_completed_welcome_tour` boolean column on the `users` table. When the user logs in and this column is `false`, the dashboard page receives a `showWelcomeTour` prop set to `true`, and the frontend renders a multi-step dialog component. The user navigates through 4 slides using "Next" / "Previous" buttons, and on the final slide clicks "Get Started" to dismiss the tour. Dismissing the tour (either by completing it or clicking "Skip") sends a POST request to a dedicated endpoint that sets `has_completed_welcome_tour` to `true`, ensuring the tour is never shown again.

**Dependency on E001-F003 (User Login)**: That feature configures authentication, session management, and rate limiting. The welcome tour only appears for authenticated users on the dashboard, so login must be functional. The tour relies on the authenticated session to identify the user and update their tour completion status.

## Objective

Implement a 4-slide welcome tour modal dialog that appears on the dashboard after a user's first login, explaining the Itervel video creation workflow. Track tour completion per-user so the tour is only shown once. Provide "Skip" and navigation controls so users can dismiss or step through the tour at their own pace.

## Solution Approach

### 1. Database: Add tour completion tracking

Add a `has_completed_welcome_tour` boolean column to the `users` table via a new migration. Default to `false` so all existing and new users will see the tour on their next dashboard visit.

```php
// Migration
Schema::table('users', function (Blueprint $table) {
    $table->boolean('has_completed_welcome_tour')->default(false)->after('password');
});
```

Update the `User` model to include `has_completed_welcome_tour` in `$fillable` and in the `casts()` method as a boolean. Update the `UserFactory` to include `'has_completed_welcome_tour' => false` in the default state, and add a `withCompletedWelcomeTour()` factory state for tests.

### 2. Backend: Controller and Route

Create a `WelcomeTourController` with a single `complete` method that marks the authenticated user's tour as completed. This is an invokable-style action endpoint:

```php
namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WelcomeTourController extends Controller
{
    public function complete(Request $request): RedirectResponse
    {
        $request->user()->update(['has_completed_welcome_tour' => true]);

        return back();
    }
}
```

Add the route in `routes/web.php` within the auth middleware group:

```php
Route::post('welcome-tour/complete', [WelcomeTourController::class, 'complete'])
    ->middleware(['auth', 'verified'])
    ->name('welcome-tour.complete');
```

### 3. Backend: Pass tour state to dashboard

Update the dashboard route in `routes/web.php` to pass the `showWelcomeTour` prop:

```php
Route::get('dashboard', function () {
    return Inertia::render('dashboard', [
        'showWelcomeTour' => ! auth()->user()->has_completed_welcome_tour,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');
```

### 4. Frontend: Welcome Tour Dialog Component

Create a `welcome-tour.tsx` component in `resources/js/components/` that renders a multi-step dialog using the existing `Dialog`, `DialogContent`, `DialogHeader`, `DialogTitle`, `DialogDescription`, `DialogFooter` components from `@/components/ui/dialog` and `Button` from `@/components/ui/button`.

The component follows the multi-step modal pattern established by `two-factor-setup-modal.tsx`:

- Uses `useState` to track the current slide index (0-3)
- Renders different content for each slide based on the index
- Provides "Previous" and "Next" buttons in the footer, with "Get Started" on the final slide
- Provides a "Skip" button that dismisses the tour immediately
- On completion (either "Get Started" or "Skip"), sends a POST request to the `welcome-tour.complete` route using Inertia's `router.post()` or the Wayfinder-generated action
- The dialog is controlled (open/close managed by parent via props), similar to the `TwoFactorSetupModal` pattern

Slide content (static text with icons from `lucide-react`):

1. **Step 1: Enter Your Topic** -- "Start by entering your video topic and any reference materials. The AI uses these to understand exactly what you want to create." (Icon: `Lightbulb` or `PenLine`)
2. **Step 2: AI Generates Content** -- "Our AI generates and iteratively refines your script, voiceover, visuals, and more -- producing high-quality content through multiple rounds of improvement." (Icon: `Sparkles` or `Wand2`)
3. **Step 3: Review & Customize** -- "Review each component of your video and make adjustments. You're in control at every step of the process." (Icon: `SlidersHorizontal` or `Eye`)
4. **Step 4: Download Your Video** -- "Download your complete, upload-ready video package including the video file, thumbnail, title, description, and tags." (Icon: `Download` or `Package`)

Each slide should include a step indicator (e.g., "Step 1 of 4" or dot indicators) so the user knows their progress.

```tsx
// Component signature
interface WelcomeTourProps {
    open: boolean;
    onComplete: () => void;
}
```

### 5. Frontend: Integrate into Dashboard

Update `resources/js/pages/dashboard.tsx` to:

1. Accept a `showWelcomeTour` prop from the Inertia page
2. Render the `WelcomeTour` dialog when `showWelcomeTour` is true
3. On tour completion, call the Wayfinder-generated route to POST to `welcome-tour.complete`

```tsx
import { usePage } from '@inertiajs/react';
// ...

export default function Dashboard() {
    const { showWelcomeTour } = usePage<{ showWelcomeTour: boolean }>().props;
    // ... render WelcomeTour component
}
```

### 6. Type Safety

Add the `has_completed_welcome_tour` field to the `User` type in `resources/js/types/auth.ts`:

```typescript
export type User = {
    // ... existing fields
    has_completed_welcome_tour: boolean;
};
```

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` -- User model. Must add `has_completed_welcome_tour` to `$fillable` and `casts()`.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` -- User factory. Must add default state for `has_completed_welcome_tour` and a `withCompletedWelcomeTour()` state.
- `/Users/young/Nextcloud/dev/Itervel/database/migrations/0001_01_01_000000_create_users_table.php` -- Existing users migration. Referenced for understanding the schema. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Web routes. Must add the `welcome-tour.complete` route and update the dashboard route to pass `showWelcomeTour` prop.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/dashboard.tsx` -- Dashboard page. Must integrate the welcome tour dialog.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/auth.ts` -- TypeScript auth types. Must add `has_completed_welcome_tour` to `User` type.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/index.ts` -- TypeScript type re-exports. Referenced for understanding the SharedData pattern.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/dialog.tsx` -- Dialog UI component (Radix-based). Used by the welcome tour modal. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/button.tsx` -- Button UI component. Used in the tour for navigation buttons. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/two-factor-setup-modal.tsx` -- Reference pattern for multi-step dialog implementation. Follow this component's patterns for the welcome tour dialog (step management, Dialog usage, button layout).
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Middleware/HandleInertiaRequests.php` -- Inertia middleware for shared data. Referenced for understanding how shared props work. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Controller.php` -- Base controller class. The new controller extends this.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/app-layout.tsx` -- App layout. Used by the dashboard page. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/lib/utils.ts` -- Utility functions (`cn()` helper). Used for styling. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/DashboardTest.php` -- Existing dashboard tests. Referenced for test patterns. The welcome tour tests go in a separate test file.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration. All Feature tests use `RefreshDatabase` automatically.
- `/Users/young/Nextcloud/dev/Itervel/app/Providers/FortifyServiceProvider.php` -- Fortify service provider. Referenced for understanding auth configuration after E001-F003.
- `/Users/young/Nextcloud/dev/Itervel/bootstrap/app.php` -- Application bootstrap. Referenced for middleware configuration. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/package.json` -- NPM dependencies. `lucide-react` is already installed for icons. No changes needed.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/database/migrations/YYYY_MM_DD_HHMMSS_add_has_completed_welcome_tour_to_users_table.php` -- Migration to add the `has_completed_welcome_tour` boolean column to the `users` table.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/WelcomeTourController.php` -- Controller with a `complete()` method that marks the user's tour as done.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/welcome-tour.tsx` -- React component implementing the 4-slide welcome tour dialog.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/WelcomeTourTest.php` -- Pest feature tests for the welcome tour.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: tour-backend-dev
    - Role: Creates the database migration, updates the User model and factory, creates the WelcomeTourController, updates routes, and updates the dashboard route to pass the showWelcomeTour prop
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: tour-frontend-dev
    - Role: Creates the welcome-tour.tsx component with 4-slide dialog, updates the dashboard page to integrate the tour, and updates TypeScript types
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: tour-test-dev
    - Role: Writes comprehensive Pest feature tests for the welcome tour completion endpoint, dashboard prop passing, and tour visibility logic
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: tour-reviewer
    - Role: Validates the complete welcome tour feature against acceptance criteria, runs all tests, checks types, runs linting and formatting
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Create Database Migration and Update User Model

- **Task ID**: create-migration-and-model
- **Depends On**: none
- **Assigned To**: tour-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- Create a new migration using `php artisan make:migration add_has_completed_welcome_tour_to_users_table --table=users --no-interaction` inside the Docker container (use `make shell` or `docker compose exec app`)
- In the migration `up()` method, add: `$table->boolean('has_completed_welcome_tour')->default(false)->after('password');`
- In the migration `down()` method, add: `$table->dropColumn('has_completed_welcome_tour');`
- Update `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php`:
    - Add `'has_completed_welcome_tour'` to the `$fillable` array
    - Add `'has_completed_welcome_tour' => 'boolean'` to the `casts()` method return array
- Update `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php`:
    - Add `'has_completed_welcome_tour' => false` to the `definition()` method return array
    - Add a new `withCompletedWelcomeTour()` factory state method:
        ```php
        public function withCompletedWelcomeTour(): static
        {
            return $this->state(fn (array $attributes) => [
                'has_completed_welcome_tour' => true,
            ]);
        }
        ```
- Run the migration: `php artisan migrate --no-interaction`
- Run `vendor/bin/pint --dirty` to fix any formatting issues
- Run existing tests to verify nothing is broken: `php artisan test --compact`

### 2. Create WelcomeTourController and Routes

- **Task ID**: create-controller-and-routes
- **Depends On**: create-migration-and-model
- **Assigned To**: tour-backend-dev
- **Agent Type**: coder
- **Parallel**: false
- Create the controller: `php artisan make:controller WelcomeTourController --no-interaction`
- Edit `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/WelcomeTourController.php` to add a `complete()` method:

    ```php
    <?php

    namespace App\Http\Controllers;

    use Illuminate\Http\RedirectResponse;
    use Illuminate\Http\Request;

    class WelcomeTourController extends Controller
    {
        /**
         * Mark the welcome tour as completed for the authenticated user.
         */
        public function complete(Request $request): RedirectResponse
        {
            $request->user()->update(['has_completed_welcome_tour' => true]);

            return back();
        }
    }
    ```

- Update `/Users/young/Nextcloud/dev/Itervel/routes/web.php` to:
    1. Add `use App\Http\Controllers\WelcomeTourController;` import at the top
    2. Add the tour completion route inside the existing `auth` + `verified` middleware scope. Add it right after the dashboard route:
        ```php
        Route::post('welcome-tour/complete', [WelcomeTourController::class, 'complete'])
            ->name('welcome-tour.complete');
        ```
    3. Update the dashboard route closure to pass the `showWelcomeTour` prop:
        ```php
        Route::get('dashboard', function () {
            return Inertia::render('dashboard', [
                'showWelcomeTour' => ! auth()->user()->has_completed_welcome_tour,
            ]);
        })->middleware(['auth', 'verified'])->name('dashboard');
        ```
        Note: The `auth()->user()` call is safe here because the `auth` middleware ensures the user is authenticated.
- Run `vendor/bin/pint --dirty` to fix any formatting issues
- Run `php artisan test tests/Feature/DashboardTest.php --compact` to verify the dashboard still works

### 3. Create Welcome Tour Frontend Component

- **Task ID**: create-tour-component
- **Depends On**: create-controller-and-routes
- **Assigned To**: tour-frontend-dev
- **Agent Type**: coder
- **Parallel**: false
- Update `/Users/young/Nextcloud/dev/Itervel/resources/js/types/auth.ts` to add `has_completed_welcome_tour: boolean;` to the `User` type (before the `[key: string]: unknown;` line)
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/components/welcome-tour.tsx` with the following implementation:
    - Import `useState` from React
    - Import `router` from `@inertiajs/react` for making the POST request to complete the tour
    - Import `Dialog`, `DialogContent`, `DialogHeader`, `DialogTitle`, `DialogDescription`, `DialogFooter` from `@/components/ui/dialog`
    - Import `Button` from `@/components/ui/button`
    - Import icons from `lucide-react`: `Lightbulb`, `Sparkles`, `SlidersHorizontal`, `Download` (or similar appropriate icons)
    - Import the Wayfinder-generated route for the complete endpoint (it will be auto-generated at `@/actions/App/Http/Controllers/WelcomeTourController` after running Vite)
    - Define the slide data as a constant array of objects with `title`, `description`, and `icon` fields:
        - Slide 1: title "Enter Your Topic", description "Start by entering your video topic and any reference materials. The AI uses these to understand exactly what you want to create.", icon `Lightbulb`
        - Slide 2: title "AI Generates Content", description "Our AI generates and iteratively refines your script, voiceover, visuals, and more -- producing high-quality content through multiple rounds of improvement.", icon `Sparkles`
        - Slide 3: title "Review & Customize", description "Review each component of your video and make adjustments. You're in control at every step of the process.", icon `SlidersHorizontal`
        - Slide 4: title "Download Your Video", description "Download your complete, upload-ready video package including the video file, thumbnail, title, description, and tags.", icon `Download`
    - Component props: `open: boolean`
    - Use `useState<number>(0)` for `currentStep`
    - The `handleComplete` function should use `router.post()` with the Wayfinder-generated route for `welcome-tour.complete`. If the Wayfinder action is not available, use the named route: `router.post(route('welcome-tour.complete'))`. The simplest approach is to use `router.post('/welcome-tour/complete')` with `preserveState: false`.
    - The dialog should NOT have a close button (override the default DialogContent close button by using a custom wrapper, or set `onInteractOutside` to prevent closing). The user must either click "Skip" or complete the tour.
    - Render a step indicator showing dots or "Step X of 4"
    - Render the current slide's icon (large, centered), title, and description
    - Render navigation buttons in the footer:
        - Left side: "Skip" text button (variant="ghost") on all slides -- calls `handleComplete`
        - Right side: "Previous" button (variant="outline") if not on first slide, "Next" button (variant="default") if not on last slide, "Get Started" button (variant="default") on last slide -- "Get Started" calls `handleComplete`
    - Follow the component patterns from `two-factor-setup-modal.tsx` (Dialog usage, step management)
    - Follow the existing Tailwind CSS patterns (use theme variables like `text-foreground`, `text-muted-foreground`, `bg-primary`, etc.)
    - Use the `cn()` utility from `@/lib/utils` for conditional class names
- Update `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/dashboard.tsx`:
    - Import the `WelcomeTour` component
    - Accept `showWelcomeTour` from page props using `usePage()` hook (add it to the page's props type)
    - Render `<WelcomeTour open={showWelcomeTour} />` inside the `AppLayout`
    - Example:

        ```tsx
        import { Head, usePage } from '@inertiajs/react';
        import WelcomeTour from '@/components/welcome-tour';
        // ...

        export default function Dashboard() {
            const { showWelcomeTour } = usePage<{ showWelcomeTour: boolean }>()
                .props;

            return (
                <AppLayout breadcrumbs={breadcrumbs}>
                    <Head title="Dashboard" />
                    {showWelcomeTour && <WelcomeTour open={showWelcomeTour} />}
                    {/* existing content */}
                </AppLayout>
            );
        }
        ```

- Run `npm run types` to verify TypeScript compilation
- Run `npm run lint` to check for linting issues, fix any with `npm run lint:fix`
- Run `npm run build` to generate Wayfinder routes and verify the build succeeds

### 4. Write Feature Tests

- **Task ID**: write-tour-tests
- **Depends On**: create-controller-and-routes
- **Assigned To**: tour-test-dev
- **Agent Type**: coder
- **Parallel**: true (can run in parallel with create-tour-component since tests only need the backend)
- Create the test file using: `php artisan make:test WelcomeTourTest --pest --no-interaction`
- Edit `/Users/young/Nextcloud/dev/Itervel/tests/Feature/WelcomeTourTest.php` with the following tests:

    ```php
    <?php

    use App\Models\User;

    test('dashboard shows welcome tour for new users', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('showWelcomeTour', true)
        );
    });

    test('dashboard does not show welcome tour for users who completed it', function () {
        $user = User::factory()->withCompletedWelcomeTour()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('showWelcomeTour', false)
        );
    });

    test('welcome tour can be completed', function () {
        $user = User::factory()->create();

        expect($user->has_completed_welcome_tour)->toBeFalse();

        $response = $this->actingAs($user)->post(route('welcome-tour.complete'));

        $response->assertRedirect();
        $user->refresh();
        expect($user->has_completed_welcome_tour)->toBeTrue();
    });

    test('completing welcome tour is idempotent', function () {
        $user = User::factory()->withCompletedWelcomeTour()->create();

        $response = $this->actingAs($user)->post(route('welcome-tour.complete'));

        $response->assertRedirect();
        $user->refresh();
        expect($user->has_completed_welcome_tour)->toBeTrue();
    });

    test('guests cannot complete welcome tour', function () {
        $response = $this->post(route('welcome-tour.complete'));

        $response->assertRedirect(route('login'));
    });

    test('new users have has_completed_welcome_tour set to false by default', function () {
        $user = User::factory()->create();

        expect($user->has_completed_welcome_tour)->toBeFalse();
    });

    test('welcome tour completed state persists across sessions', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('welcome-tour.complete'));

        // Simulate a new session by making a fresh request
        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('showWelcomeTour', false)
        );
    });
    ```

- Run the tests: `php artisan test tests/Feature/WelcomeTourTest.php --compact`
- Ensure all tests pass. Debug and fix any failures.
- Run `vendor/bin/pint --dirty` to fix any formatting issues in the test file
- Run the full test suite to ensure no regressions: `php artisan test --compact`

### 5. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-migration-and-model, create-controller-and-routes, create-tour-component, write-tour-tests
- **Assigned To**: tour-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed below
- Verify welcome tour tests pass: `php artisan test tests/Feature/WelcomeTourTest.php --compact`
- Verify dashboard tests still pass: `php artisan test tests/Feature/DashboardTest.php --compact`
- Run the full test suite for regression check: `php artisan test --compact`
- Run TypeScript type checking: `npm run types`
- Run ESLint: `npm run lint`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Run Prettier: `npm run format:check`
- Verify the migration file exists and adds `has_completed_welcome_tour` boolean column with `default(false)`
- Verify `User` model has `has_completed_welcome_tour` in `$fillable` and `casts()` returns it as `'boolean'`
- Verify `UserFactory` has the default state and `withCompletedWelcomeTour()` state
- Verify `WelcomeTourController` exists with a `complete()` method that updates the user and redirects back
- Verify `routes/web.php` has the `welcome-tour.complete` route with `auth` and `verified` middleware
- Verify the dashboard route passes `showWelcomeTour` prop based on the user's `has_completed_welcome_tour` value
- Verify `welcome-tour.tsx` component exists with 4 slides, navigation buttons, skip functionality, and step indicators
- Verify `dashboard.tsx` integrates the welcome tour component
- Verify `auth.ts` type includes `has_completed_welcome_tour`
- Confirm all acceptance criteria are met

## Acceptance Criteria

- New users see a welcome tour modal dialog on their first dashboard visit
- Users who have completed the tour do not see it again on subsequent visits
- The tour contains exactly 4 slides explaining the workflow:
    1. "Enter Your Topic" -- entering topic and references
    2. "AI Generates Content" -- AI generates and refines content
    3. "Review & Customize" -- review and customize at each step
    4. "Download Your Video" -- download complete video package
- Users can navigate forward and backward through slides using "Next" and "Previous" buttons
- The final slide has a "Get Started" button that completes the tour
- A "Skip" button is available on all slides to dismiss the tour immediately
- Completing or skipping the tour persists the completion state (tour never shown again)
- The completion endpoint is protected by `auth` and `verified` middleware
- Guests cannot access the tour completion endpoint (redirected to login)
- The `has_completed_welcome_tour` column exists on the `users` table with a default of `false`
- Step indicators show the user's progress through the tour (e.g., "Step 1 of 4" or dot indicators)
- All tests pass (welcome tour tests and existing test suite)
- TypeScript compiles without errors
- PHP code passes Pint formatting
- ESLint passes without errors

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run welcome tour tests
php artisan test tests/Feature/WelcomeTourTest.php --compact

# Run dashboard tests
php artisan test tests/Feature/DashboardTest.php --compact

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

- The welcome tour is a purely frontend-driven experience after the initial prop is passed. The only backend interaction is the single POST to mark completion. This keeps the implementation simple and avoids unnecessary API calls during slide navigation.
- The tour uses the existing Radix Dialog component (`@/components/ui/dialog`), which provides proper accessibility (focus trapping, ESC key handling, screen reader announcements). However, the ESC key and overlay click behaviors should be disabled for the tour to ensure the user explicitly dismisses it via "Skip" or "Get Started".
- The `lucide-react` package is already installed in the project, so no new dependencies are needed for icons.
- The Wayfinder route for the `WelcomeTourController::complete` method will be auto-generated when Vite runs (via the `@laravel/vite-plugin-wayfinder` plugin). After creating the controller and route, run `npm run build` to generate the TypeScript route functions. If the Wayfinder import is not available during development, fall back to using `router.post('/welcome-tour/complete')` directly.
- The tour completion is intentionally idempotent -- calling the endpoint multiple times simply sets the same value. This avoids edge cases with double-clicks or retries.
- After E001-F003 (User Login) is built, authentication will be fully configured with session management, rate limiting, and remember-me functionality. The welcome tour relies only on the basic `auth` middleware and user model, so it is compatible with the E001-F003 changes.
- All commands should be run inside the Docker container. Use `make shell` to enter the container, or prefix with `docker compose exec app`.
- The `has_completed_welcome_tour` column is placed `after('password')` in the migration to keep user-preference columns grouped together in the schema.

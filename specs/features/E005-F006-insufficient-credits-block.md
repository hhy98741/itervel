# Feature: Insufficient Credits Block

**Epic**: E005-credits-and-billing.md
**Feature**: E005-F006
**Epic depends on**: E002-user-authentication.md
**Feature depends on**: E005-F001

## Task Description

Insufficient Credits Block prevents users from starting the video generation workflow when they have 0 credits. This is a critical guardrail for the credit-based business model -- without it, users could initiate expensive AI processing and video rendering pipelines that cannot be paid for.

**What it does**: Prevents video generation when the user has no credits.

**Expected outcome**: If the user tries to create a video with 0 credits, the system blocks the action and directs them to purchase credits.

This feature operates at two levels:

1. **Backend enforcement (middleware)**: A custom Laravel middleware `EnsureSufficientCredits` that protects video creation routes. If a user with 0 credits attempts to access any video creation route, the middleware returns a redirect (for Inertia requests) or a 403 JSON response (for API requests) with a message directing them to purchase credits. This is the authoritative enforcement layer.

2. **Frontend prevention (UI)**: The "Create Video" button/CTA (wherever it appears in the application) is visually disabled when `auth.user.video_credits === 0`, and clicking it shows an informative dialog explaining that the user needs credits to create videos, with a CTA to purchase credits. This provides a good UX by preventing the user from even attempting the action.

This feature depends on E001-F054 (Credit Balance Display), which ensures that `video_credits` is available in the Inertia shared data (`auth.user.video_credits`) and displayed in the navigation. It also relies on E001-F055 (Free Credit for New Users), which adds the `hasCredits()` and `hasSufficientCredits()` helper methods to the User model -- these methods are used by the middleware for clean credit checking.

Since the video creation wizard (E001-F070 Step-by-Step Wizard Navigation) and the "Create Video" button/page are not yet built, this feature focuses on building the **reusable infrastructure** that will enforce the credit check:

- A middleware that can be applied to any route group
- A reusable React component/hook for frontend credit gating
- Tests that verify the middleware behavior

The video creation routes do not exist yet, so the tests will use a temporary test route to validate the middleware behavior. When video creation routes are built later, they simply need to apply the `ensure.credits` middleware alias.

## Objective

Create a backend middleware and frontend component that together prevent users with 0 credits from initiating video creation. The middleware provides server-side enforcement returning appropriate responses for Inertia and API requests. The frontend provides a reusable `InsufficientCreditsDialog` component and a `useCreditsGate` hook that downstream features can use to gate UI actions. When complete, any route can be protected by adding the `ensure.credits` middleware, and any UI element can check credits using the shared hook/component.

## Solution Approach

### 1. Backend: `EnsureSufficientCredits` Middleware

Create a custom middleware at `app/Http/Middleware/EnsureSufficientCredits.php` that:

- Checks if the authenticated user has credits via `$request->user()->hasCredits()` (the method added by E001-F055)
- If the user has credits, the request proceeds normally
- If the user has 0 credits and the request is an Inertia request, redirect back with an error flash message
- If the user has 0 credits and the request is a standard/API request, return a 403 JSON response
- Falls back gracefully if the user is not authenticated (lets other middleware handle that)

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSufficientCredits
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->hasCredits()) {
            return $next($request);
        }

        if ($request->header('X-Inertia')) {
            return redirect()->back()->with('error', 'You have no credits remaining. Please purchase credits to create videos.');
        }

        return response()->json([
            'message' => 'Insufficient credits. Please purchase credits to create videos.',
        ], 403);
    }
}
```

Register the middleware alias in `bootstrap/app.php` so it can be applied to routes:

```php
->withMiddleware(function (Middleware $middleware): void {
    // ... existing middleware config ...
    $middleware->alias([
        'ensure.credits' => \App\Http\Middleware\EnsureSufficientCredits::class,
    ]);
})
```

This approach follows the existing middleware patterns in the codebase (`HandleAppearance`, `HandleInertiaRequests`). The middleware is registered as an alias rather than a global middleware because it should only apply to specific route groups (video creation routes), not every request.

### 2. Frontend: `InsufficientCreditsDialog` Component

Create a reusable dialog component that can wrap any action button to gate it behind a credit check. When the user has 0 credits and clicks the wrapped action, the dialog opens instead of performing the action.

The component uses the existing `Dialog` UI component and follows the patterns established in the codebase (e.g., how the delete user dialog works in `delete-user.tsx`).

```tsx
import { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import type { SharedData } from '@/types';

type Props = {
    children: (props: {
        disabled: boolean;
        onClick: () => void;
    }) => React.ReactNode;
    purchaseUrl?: string;
};

export function InsufficientCreditsDialog({
    children,
    purchaseUrl = '#',
}: Props) {
    const { auth } = usePage<SharedData>().props;
    const [open, setOpen] = useState(false);
    const hasCredits = (auth.user.video_credits as number) > 0;

    return (
        <>
            {children({
                disabled: !hasCredits,
                onClick: () => {
                    if (!hasCredits) {
                        setOpen(true);
                    }
                },
            })}
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <AlertCircle className="size-5 text-destructive" />
                            No Credits Remaining
                        </DialogTitle>
                        <DialogDescription>
                            You need at least 1 credit to create a video.
                            Purchase credits to get started.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button asChild>
                            <Link href={purchaseUrl}>Purchase Credits</Link>
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
```

### 3. Frontend: `useCreditsGate` Hook

Create a simple hook that encapsulates the credit check logic for reuse across components:

```tsx
import { usePage } from '@inertiajs/react';
import type { SharedData } from '@/types';

export function useCreditsGate() {
    const { auth } = usePage<SharedData>().props;
    const credits = auth.user.video_credits as number;

    return {
        credits,
        hasCredits: credits > 0,
        hasSufficientCredits: (amount: number = 1) => credits >= amount,
    };
}
```

This hook provides a clean API for any component that needs to check credit availability without directly accessing `usePage` and casting types. Downstream features (video creation wizard, final review screen) can use this hook to conditionally render or disable elements.

### 4. Testing Approach

Since no video creation routes exist yet, the tests will:

1. **Create a temporary test route** within the test file that applies the `ensure.credits` middleware, then test the middleware behavior against that route
2. **Test the middleware directly** -- verify it blocks requests for users with 0 credits and allows requests for users with credits
3. **Test both Inertia and standard request responses** -- verify the middleware returns the correct response type based on the request headers

The test approach follows the pattern used in the existing authentication tests where routes are tested via HTTP assertions.

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` -- The User Eloquent model. After E001-F055, will have `hasCredits()` and `hasSufficientCredits()` methods. The middleware uses `hasCredits()` to check credit availability. Currently has `video_credits` in `$fillable` (after E001-F001).
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Middleware/HandleAppearance.php` -- Existing custom middleware. Follow this pattern for the new `EnsureSufficientCredits` middleware structure (namespace, use statements, handle method signature, return type).
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Middleware/HandleInertiaRequests.php` -- The Inertia shared data middleware. Shows how `$request->user()` is used and how shared data flows. The `auth.user.video_credits` value is available on every Inertia page because of this middleware.
- `/Users/young/Nextcloud/dev/Itervel/bootstrap/app.php` -- Application bootstrap with middleware configuration. The `ensure.credits` middleware alias must be registered here using `$middleware->alias()` inside the existing `withMiddleware` callback.
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Main web routes. Referenced for understanding route middleware patterns (`['auth', 'verified']` on the dashboard route). No changes needed for this feature, but when video creation routes are added later, they will use `['auth', 'verified', 'ensure.credits']`.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/dialog.tsx` -- The Dialog UI component (`Dialog`, `DialogContent`, `DialogDescription`, `DialogFooter`, `DialogHeader`, `DialogTitle`, `DialogTrigger`). Used as the base for the `InsufficientCreditsDialog` component.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/button.tsx` -- The Button UI component with variants (`default`, `destructive`, `outline`, `secondary`, `ghost`, `link`) and sizes (`default`, `sm`, `lg`, `icon`). Used in the dialog footer for Cancel and Purchase buttons.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/delete-user.tsx` -- Existing example of a dialog-based confirmation component. Follow this pattern for understanding how dialogs are structured and how form actions are gated behind confirmation dialogs.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/alert-error.tsx` -- Existing error alert component. Referenced for understanding how error states are communicated in the frontend.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/auth.ts` -- TypeScript `User` type. After E001-F001, includes `video_credits: number`. Has `[key: string]: unknown` fallback. The hook and component access `video_credits` from the user object.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/index.ts` -- `SharedData` type definition. Used for typed access to `auth.user` via `usePage<SharedData>()`.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/lib/utils.ts` -- Utility functions including `cn()` for class merging. May be used for conditional styling in the dialog component.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` -- User factory for creating test users. After E001-F001, includes `video_credits => 0` in defaults. Used in tests to create users with specific credit values.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/DashboardTest.php` -- Existing feature test. Referenced for understanding test patterns (Pest, actingAs, route assertions).
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Auth/RegistrationTest.php` -- Existing registration test. Referenced for understanding test patterns with POST requests and route assertions.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration. All Feature tests use `RefreshDatabase` automatically.
- `/Users/young/Nextcloud/dev/Itervel/specs/features/E001-F054-credit-balance-display.md` -- The dependency feature spec. Details how `video_credits` is shared via Inertia and how the `CreditBalance` component works. Referenced for understanding the data flow.
- `/Users/young/Nextcloud/dev/Itervel/specs/features/E001-F055-free-credit-for-new-users.md` -- The sibling feature spec. Details the `hasCredits()`, `hasSufficientCredits()`, `deductCredits()`, and `addCredits()` methods on the User model. The middleware uses `hasCredits()`.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/app/Http/Middleware/EnsureSufficientCredits.php` -- Custom middleware that blocks requests from users with 0 credits. Returns a redirect with error flash for Inertia requests, or a 403 JSON response for standard/API requests. Uses the User model's `hasCredits()` method.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/insufficient-credits-dialog.tsx` -- A reusable React dialog component that opens when a user with 0 credits attempts a credit-gated action. Uses the render props pattern to provide `disabled` and `onClick` props to the child element. Displays a dialog explaining the need for credits with a CTA to purchase.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/hooks/use-credits-gate.ts` -- A React hook that encapsulates credit checking logic. Returns `credits`, `hasCredits`, and `hasSufficientCredits()` for use in any component that needs to gate actions behind credit availability.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Credits/InsufficientCreditsTest.php` -- Pest feature tests for the `EnsureSufficientCredits` middleware. Tests that users with 0 credits are blocked, users with credits are allowed, and the correct response type (Inertia redirect vs JSON 403) is returned.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: credits-block-backend-dev
    - Role: Creates the EnsureSufficientCredits middleware, registers the middleware alias in bootstrap/app.php, and verifies that E001-F055's hasCredits() method is available on the User model
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: credits-block-frontend-dev
    - Role: Creates the InsufficientCreditsDialog component and useCreditsGate hook, ensures proper TypeScript types and follows existing component patterns
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: credits-block-test-dev
    - Role: Writes feature tests for the EnsureSufficientCredits middleware covering all scenarios (0 credits blocked, positive credits allowed, Inertia vs JSON responses, unauthenticated users)
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: credits-block-reviewer
    - Role: Validates the complete insufficient credits block feature against acceptance criteria, runs all tests, checks types, runs linting and formatting
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Create EnsureSufficientCredits Middleware and Register Alias

- **Task ID**: create-middleware
- **Depends On**: none
- **Assigned To**: credits-block-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- Verify that E001-F055's changes are in place on the User model at `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php`: confirm that the `hasCredits()` method exists. If it does not exist, add it as specified in E001-F055's plan (returns `$this->video_credits > 0`). Also verify `video_credits` is in the model's `$fillable` array (from E001-F001). If not present, add `'video_credits'` to the `$fillable` array.
- Read the existing middleware at `/Users/young/Nextcloud/dev/Itervel/app/Http/Middleware/HandleAppearance.php` to understand the middleware pattern used in this project (namespace, imports, handle method signature)
- Create the middleware file `/Users/young/Nextcloud/dev/Itervel/app/Http/Middleware/EnsureSufficientCredits.php` with the following implementation:
    - Namespace: `App\Http\Middleware`
    - Import `Closure`, `Illuminate\Http\Request`, `Symfony\Component\HttpFoundation\Response`
    - `handle(Request $request, Closure $next): Response` method that:
        1. Gets the authenticated user via `$request->user()`
        2. If the user is null (not authenticated) OR the user has credits (`$user->hasCredits()`), call `$next($request)` and return the response -- let other middleware handle auth
        3. If the user has no credits and the request has the `X-Inertia` header (checking via `$request->header('X-Inertia')`), return `redirect()->back()->with('error', 'You have no credits remaining. Please purchase credits to create videos.')`
        4. Otherwise (standard/API request with no credits), return `response()->json(['message' => 'Insufficient credits. Please purchase credits to create videos.'], 403)`
    - Add a PHPDoc block for the class and the handle method following the pattern in `HandleAppearance.php`
- Read the current `/Users/young/Nextcloud/dev/Itervel/bootstrap/app.php` to understand the existing middleware configuration
- Modify `/Users/young/Nextcloud/dev/Itervel/bootstrap/app.php` to register the middleware alias. Inside the existing `->withMiddleware(function (Middleware $middleware): void {` callback, after the `$middleware->web(append: [...])` call, add:
    ```php
    $middleware->alias([
        'ensure.credits' => \App\Http\Middleware\EnsureSufficientCredits::class,
    ]);
    ```
- Run `vendor/bin/pint --dirty` to format the PHP files

### 2. Create Frontend Credits Gate Hook and Dialog Component

- **Task ID**: create-frontend-components
- **Depends On**: none
- **Assigned To**: credits-block-frontend-dev
- **Agent Type**: coder
- **Parallel**: true
- Read the existing dialog component at `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/dialog.tsx` to understand the Dialog primitive API (Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle)
- Read the existing delete user component at `/Users/young/Nextcloud/dev/Itervel/resources/js/components/delete-user.tsx` to understand how confirmation dialogs are structured in this codebase
- Read the types at `/Users/young/Nextcloud/dev/Itervel/resources/js/types/auth.ts` and `/Users/young/Nextcloud/dev/Itervel/resources/js/types/index.ts` to understand the User type and SharedData type
- Create the `useCreditsGate` hook at `/Users/young/Nextcloud/dev/Itervel/resources/js/hooks/use-credits-gate.ts`:
    - Import `usePage` from `@inertiajs/react`
    - Import `SharedData` type from `@/types`
    - Export a named function `useCreditsGate` that:
        1. Calls `usePage<SharedData>()` to get the page props
        2. Extracts `auth.user.video_credits` and casts it: `const credits = (auth.user.video_credits ?? 0) as number;`
        3. Returns an object: `{ credits, hasCredits: credits > 0, hasSufficientCredits: (amount: number = 1) => credits >= amount }`
    - The hook should be concise (under 15 lines) and follow the pattern of existing hooks like `use-mobile.ts` and `use-initials.ts`
- Create the `InsufficientCreditsDialog` component at `/Users/young/Nextcloud/dev/Itervel/resources/js/components/insufficient-credits-dialog.tsx`:
    - Import `useState` from `react`
    - Import `Link` from `@inertiajs/react`
    - Import `AlertCircle` from `lucide-react` (semantic "blocked" icon, already used in the codebase via `AlertCircleIcon` in alert-error.tsx)
    - Import `Dialog`, `DialogContent`, `DialogDescription`, `DialogFooter`, `DialogHeader`, `DialogTitle` from `@/components/ui/dialog`
    - Import `Button` from `@/components/ui/button`
    - Import `useCreditsGate` from `@/hooks/use-credits-gate`
    - Define the props type:
        ```tsx
        type Props = {
            children: (props: {
                disabled: boolean;
                onClick: () => void;
            }) => React.ReactNode;
            purchaseUrl?: string;
        };
        ```
    - Export a named function `InsufficientCreditsDialog` that:
        1. Uses `const { hasCredits } = useCreditsGate();`
        2. Manages dialog open state: `const [open, setOpen] = useState(false);`
        3. Renders the children via the render props pattern: `children({ disabled: !hasCredits, onClick: () => { if (!hasCredits) setOpen(true); } })`
        4. Renders a `Dialog` with `open={open}` and `onOpenChange={setOpen}` containing:
            - `DialogHeader` with `DialogTitle` showing an `AlertCircle` icon (size-5, text-destructive) and "No Credits Remaining"
            - `DialogDescription` with text: "You need at least 1 credit to create a video. Purchase credits to get started."
            - `DialogFooter` with two buttons:
                - `Button variant="outline"` with `onClick={() => setOpen(false)}` text "Cancel"
                - `Button asChild` wrapping `Link href={purchaseUrl}` text "Purchase Credits" (default `purchaseUrl` is `'#'`)
    - The component follows the dialog pattern from `delete-user.tsx` but uses the render props pattern for flexibility (the parent component controls the trigger element)
- Run `npm run types` to verify TypeScript types compile correctly
- Run `npm run lint:fix` to fix any linting issues
- Run `npm run format` to format with Prettier

### 3. Write Insufficient Credits Middleware Tests

- **Task ID**: write-credits-block-tests
- **Depends On**: create-middleware
- **Assigned To**: credits-block-test-dev
- **Agent Type**: coder
- **Parallel**: false
- Create the test file using: `php artisan make:test Credits/InsufficientCreditsTest --pest --no-interaction` (run from the project root `/Users/young/Nextcloud/dev/Itervel`)
- Read the existing test patterns at `/Users/young/Nextcloud/dev/Itervel/tests/Feature/DashboardTest.php` and `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Auth/RegistrationTest.php` for reference
- Write the following tests in `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Credits/InsufficientCreditsTest.php`. Each test defines a temporary route with the `ensure.credits` middleware using `Route::middleware(['auth', 'ensure.credits'])->get(...)`:
    - At the top of the file, add these imports:
        ```php
        use App\Models\User;
        use Illuminate\Support\Facades\Route;
        ```
    - Add a `beforeEach` hook that registers a temporary test route:
        ```php
        beforeEach(function () {
            Route::middleware(['web', 'auth', 'ensure.credits'])->get('/test-credits-route', function () {
                return response()->json(['message' => 'Access granted']);
            })->name('test.credits');
        });
        ```
    - `test('user with credits can access credit-protected route')`:

        ```php
        test('user with credits can access credit-protected route', function () {
            $user = User::factory()->create(['video_credits' => 5]);

            $response = $this->actingAs($user)->get('/test-credits-route');

            $response->assertOk();
            $response->assertJson(['message' => 'Access granted']);
        });
        ```

    - `test('user with 1 credit can access credit-protected route')`:

        ```php
        test('user with 1 credit can access credit-protected route', function () {
            $user = User::factory()->create(['video_credits' => 1]);

            $response = $this->actingAs($user)->get('/test-credits-route');

            $response->assertOk();
            $response->assertJson(['message' => 'Access granted']);
        });
        ```

    - `test('user with zero credits is blocked from credit-protected route')`:

        ```php
        test('user with zero credits is blocked from credit-protected route', function () {
            $user = User::factory()->create(['video_credits' => 0]);

            $response = $this->actingAs($user)->get('/test-credits-route');

            $response->assertForbidden();
            $response->assertJson(['message' => 'Insufficient credits. Please purchase credits to create videos.']);
        });
        ```

    - `test('user with zero credits gets redirect for inertia requests')`:

        ```php
        test('user with zero credits gets redirect for inertia requests', function () {
            $user = User::factory()->create(['video_credits' => 0]);

            $response = $this->actingAs($user)
                ->get('/test-credits-route', [
                    'X-Inertia' => 'true',
                    'X-Inertia-Version' => '1.0',
                ]);

            $response->assertRedirect();
            $response->assertSessionHas('error', 'You have no credits remaining. Please purchase credits to create videos.');
        });
        ```

    - `test('guest is not blocked by credits middleware')`:

        ```php
        test('guest is not blocked by credits middleware', function () {
            $response = $this->get('/test-credits-route');

            $response->assertRedirect(route('login'));
        });
        ```

    - `test('credits middleware passes through when user is not authenticated')`:
      This tests that the middleware itself does not block unauthenticated users (the `auth` middleware handles that separately). To isolate the `ensure.credits` middleware behavior:

        ```php
        test('credits middleware passes through when user is not authenticated', function () {
            Route::middleware(['web', 'ensure.credits'])->get('/test-credits-no-auth', function () {
                return response()->json(['message' => 'Passed through']);
            });

            $response = $this->get('/test-credits-no-auth');

            $response->assertOk();
            $response->assertJson(['message' => 'Passed through']);
        });
        ```

- Run the tests: `php artisan test tests/Feature/Credits/InsufficientCreditsTest.php --compact`
- Ensure all tests pass
- Run `vendor/bin/pint --dirty` to fix any PHP formatting issues

### 4. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-middleware, create-frontend-components, write-credits-block-tests
- **Assigned To**: credits-block-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed in the Validation Commands section below
- Verify the middleware file exists at `/Users/young/Nextcloud/dev/Itervel/app/Http/Middleware/EnsureSufficientCredits.php`:
    - Has the correct namespace `App\Http\Middleware`
    - Has a `handle` method with the correct signature
    - Checks `$request->user()->hasCredits()` to determine access
    - Returns redirect with error session for Inertia requests (checking `X-Inertia` header)
    - Returns 403 JSON response for non-Inertia requests
    - Passes through when user is null (not authenticated)
- Verify `/Users/young/Nextcloud/dev/Itervel/bootstrap/app.php` registers the middleware alias:
    - Contains `$middleware->alias(['ensure.credits' => \App\Http\Middleware\EnsureSufficientCredits::class])`
    - The existing middleware configuration (encryptCookies, web append) is unchanged
- Verify the frontend hook at `/Users/young/Nextcloud/dev/Itervel/resources/js/hooks/use-credits-gate.ts`:
    - Exports a `useCreditsGate` function
    - Returns `credits`, `hasCredits`, and `hasSufficientCredits` properties
    - Uses `usePage<SharedData>()` for typed access to user data
- Verify the frontend component at `/Users/young/Nextcloud/dev/Itervel/resources/js/components/insufficient-credits-dialog.tsx`:
    - Exports an `InsufficientCreditsDialog` function
    - Uses the render props pattern with `children` prop
    - Shows a dialog with title, description, and Purchase Credits CTA
    - Uses `useCreditsGate` hook for credit checking
    - Has Cancel and Purchase Credits buttons in the dialog footer
- Verify insufficient credits tests pass: `php artisan test tests/Feature/Credits/InsufficientCreditsTest.php --compact`
- If credit tests from E001-F055 exist, verify they still pass: `php artisan test tests/Feature/Credits --compact`
- Verify dashboard tests still pass: `php artisan test tests/Feature/DashboardTest.php --compact`
- Run the full test suite for regression check: `php artisan test --compact`
- Run TypeScript type checking: `npm run types`
- Run ESLint: `npm run lint`
- Run Prettier formatting check: `npm run format:check`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Confirm all acceptance criteria are met

## Acceptance Criteria

- A middleware `EnsureSufficientCredits` exists at `app/Http/Middleware/EnsureSufficientCredits.php`
- The middleware is registered as the `ensure.credits` alias in `bootstrap/app.php`
- The middleware blocks users with 0 credits from accessing protected routes
- The middleware allows users with 1 or more credits to access protected routes
- The middleware returns a redirect with an error session flash for Inertia requests when credits are insufficient
- The middleware returns a 403 JSON response with a message for non-Inertia requests when credits are insufficient
- The middleware passes through when the user is not authenticated (delegating auth enforcement to other middleware)
- A `useCreditsGate` hook exists at `resources/js/hooks/use-credits-gate.ts` that returns `credits`, `hasCredits`, and `hasSufficientCredits()`
- An `InsufficientCreditsDialog` component exists at `resources/js/components/insufficient-credits-dialog.tsx`
- The dialog component uses the render props pattern for flexible trigger element composition
- The dialog displays a clear message about needing credits and a CTA to purchase credits
- All insufficient credits middleware tests pass
- All existing tests pass without regressions
- TypeScript types compile without errors
- ESLint and Prettier checks pass
- PHP code passes Pint formatting

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run insufficient credits tests
php artisan test tests/Feature/Credits/InsufficientCreditsTest.php --compact

# Run all credit-related tests (if E001-F055 tests exist)
php artisan test tests/Feature/Credits --compact

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

- **No video creation routes exist yet**: The video creation wizard (E001-F070) and related features have not been built. This feature builds the reusable middleware and frontend infrastructure. When video creation routes are added, they simply need `['auth', 'verified', 'ensure.credits']` middleware to be protected. The tests use temporary routes to validate the middleware in isolation.
- **Dependency on E001-F055 for `hasCredits()` method**: The middleware calls `$request->user()->hasCredits()`. This method is added by E001-F055 (Free Credit for New Users). If E001-F055 has not been built yet when this feature is implemented, the backend developer task (Step 1) includes a fallback: verify the method exists and add it if necessary. The method is simple (`return $this->video_credits > 0;`).
- **Dependency on E001-F054 for `video_credits` in Inertia shared data**: The frontend components access `auth.user.video_credits` from Inertia shared props. This is available because E001-F054 (Credit Balance Display) ensures `video_credits` is part of the User model serialization shared via `HandleInertiaRequests`. If E001-F054 is not yet built, the `video_credits` field is still available as long as E001-F001 (User Registration) has added it to the User model's `$fillable` and it is not in `$hidden`.
- **Purchase URL placeholder**: The `InsufficientCreditsDialog` component and the middleware error message reference purchasing credits. Since E001-F060 (Credit Package Purchase) and E001-F061 (Stripe Checkout) have not been built, the dialog uses `'#'` as the default `purchaseUrl`. This can be easily updated when the pricing page exists. The `purchaseUrl` prop makes this a single-line change.
- **Render props pattern**: The `InsufficientCreditsDialog` uses a render props pattern (`children: (props) => ReactNode`) rather than wrapping a specific element. This gives downstream features maximum flexibility in how they compose the credit gate with their UI. For example:
    ```tsx
    <InsufficientCreditsDialog>
        {({ disabled, onClick }) => (
            <Button
                disabled={disabled}
                onClick={disabled ? onClick : handleCreateVideo}
            >
                Create Video
            </Button>
        )}
    </InsufficientCreditsDialog>
    ```
- **Middleware vs authorization gate**: A middleware was chosen over a Laravel Gate/Policy because the credit check is a business rule rather than a permission/ownership check. Middleware is the appropriate layer for "can this user perform this class of action" checks, while Gates/Policies are better for "can this user act on this specific resource." The middleware also provides a single point of enforcement that cannot be bypassed by individual controllers.
- **Inertia request detection**: The middleware checks for the `X-Inertia` header to determine the response type. This is the standard way to detect Inertia requests in Laravel middleware. When an Inertia request is blocked, a redirect with session flash is used because Inertia handles redirects automatically and can display the flash message to the user.
- **All commands should be run inside the Docker container**. Use `make shell` to enter the container, or prefix with `docker compose exec app`.

# Feature: User Profile Viewing

**Epic**: E003-user-profile-and-account-management.md
**Feature**: E003-F001
**Dependencies**: E002-F003

## Task Description

User Profile Viewing provides authenticated users with a dedicated page to view their account information at a glance. This is distinct from the existing settings/profile page (which is an editable form for updating name and email). The profile viewing page is a read-only overview that displays the user's name, email, email verification status, join date (formatted), and video credit balance.

**What it does**: Lets users view their account information such as email, join date, and credit balance.

**Expected outcome**: The user sees a profile page displaying their account details at a glance.

This feature depends on E001-F003 (User Login), which itself depends on E001-F001 (User Registration). After those features are built:

- The User model will implement `MustVerifyEmail` and have a `video_credits` column (from E001-F001).
- The User TypeScript type will include `video_credits: number` (from E001-F001).
- Authentication, session management, rate limiting, and remember-me are configured (from E001-F003).

The existing codebase has a settings area with a sidebar navigation (`/settings/profile`, `/settings/password`, `/settings/two-factor`, `/settings/appearance`). The current profile settings page (`settings/profile`) is an editable form for updating name and email -- it is NOT a profile viewing page. This feature creates a new, separate profile overview page that lives within the existing settings layout at `/settings/account`, providing a clean read-only view of the user's account details.

The page will be accessible via a new sidebar navigation item "Account" at the top of the settings sidebar. It will display the user's avatar (initials fallback), name, email, email verification status (verified badge or unverified warning), join date (formatted as a human-readable date), and video credit balance.

## Objective

Create a read-only account overview page within the settings area that displays the authenticated user's key information: name, email, verification status, join date, and video credit balance. The page will follow existing settings page patterns and be accessible from the settings sidebar navigation.

## Solution Approach

### 1. Backend: Create AccountController

Create a new controller `App\Http\Controllers\Settings\AccountController` with a single `show` method that renders the `settings/account` Inertia page. The controller passes the user's `created_at` timestamp and `video_credits` as explicit page props (the user's core data like name, email, and email_verified_at are already available via shared Inertia data in `HandleInertiaRequests`).

```php
namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/account', [
            'joinedAt' => $user->created_at->toDateString(),
            'videoCredits' => $user->video_credits,
        ]);
    }
}
```

The `created_at` is passed explicitly as a formatted string (`joinedAt`) rather than relying on the shared user data to keep date formatting server-side and to provide a clean, dedicated prop. The `video_credits` is passed explicitly for the same reason -- even though the shared auth user data includes it, making it an explicit prop is cleaner for the page component and makes the data contract explicit.

### 2. Backend: Add Route

Add a new route in `routes/settings.php` within the `auth` middleware group:

```php
Route::get('settings/account', [AccountController::class, 'show'])->name('account.show');
```

This should be placed in the `['auth']` group (alongside the profile edit route), not in the `['auth', 'verified']` group, so that users who have not yet verified their email can still view their account information. This is intentional -- the account overview is a read-only page that does not require email verification.

### 3. Frontend: Create Account Page Component

Create `resources/js/pages/settings/account.tsx` following the exact same pattern as the existing settings pages (`settings/profile.tsx`, `settings/password.tsx`). The page will:

- Use `AppLayout` with breadcrumbs
- Use `SettingsLayout` for the settings sidebar
- Display the user's information in a clean, read-only layout using the `Heading` component for section titles
- Use the `UserInfo` component (existing) to display the avatar and name
- Show each detail (email, verification status, join date, credits) as labeled rows using a definition-list style layout
- Use the `Badge` component to show email verification status (green for verified, yellow/warning for unverified)

```tsx
// Simplified structure
export default function Account({ joinedAt, videoCredits }: AccountProps) {
    const { auth } = usePage<SharedData>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Account overview" />
            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Account overview"
                        description="Your account information at a glance"
                    />
                    {/* Account details rows */}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
```

### 4. Frontend: Update Settings Sidebar Navigation

Update the `sidebarNavItems` array in `resources/js/layouts/settings/layout.tsx` to add an "Account" item at the top of the list, linking to the new account route. Import the Wayfinder-generated route function.

### 5. Tests

Create a new test file `tests/Feature/Settings/AccountTest.php` with Pest tests covering:

- The account page renders for authenticated users
- The account page displays the user's information (using `assertInertia`)
- Guests are redirected to login
- The page includes correct `joinedAt` and `videoCredits` props

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/ProfileController.php` -- Sibling controller in the Settings namespace. Follow its patterns for controller structure, imports, and Inertia rendering. The `edit()` method is the closest analog to our `show()` method.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Controller.php` -- Base controller class that all controllers extend.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` -- The User model. After E001-F001, it will have `video_credits` in `$fillable` and implement `MustVerifyEmail`. The `created_at` timestamp is used for the join date.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Middleware/HandleInertiaRequests.php` -- Shares `auth.user` data with all Inertia pages. The shared user data includes name, email, email_verified_at, created_at, updated_at, and (after E001-F001) video_credits.
- `/Users/young/Nextcloud/dev/Itervel/routes/settings.php` -- Settings route definitions. The new account route must be added here following existing patterns.
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Main web routes. Includes `settings.php`. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/profile.tsx` -- Existing settings page. Follow its exact pattern for layout structure, breadcrumbs, AppLayout + SettingsLayout wrapping, imports, and component organization.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/password.tsx` -- Another settings page. Confirms the consistent pattern used across all settings pages.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/dashboard.tsx` -- Page component example showing breadcrumbs and AppLayout usage.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/settings/layout.tsx` -- Settings sidebar layout with navigation items. Must be updated to add the "Account" nav item. Follow existing patterns for the `sidebarNavItems` array and Wayfinder route imports.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/app-layout.tsx` -- Main app layout wrapper. Used by all authenticated pages.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/heading.tsx` -- Heading component with `variant` prop. Used in settings pages for section headers.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/user-info.tsx` -- Displays user avatar and name. Can be reused on the account page for the user's avatar/name display.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/badge.tsx` -- Badge component with variants (default, secondary, destructive, outline). Used for showing email verification status.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/separator.tsx` -- Separator component. May be used between account detail sections.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/index.ts` -- Re-exports all types and defines SharedData. Referenced for understanding the shared data structure.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/auth.ts` -- User type definition. After E001-F001, includes `video_credits: number`. Contains `created_at` and `email_verified_at`.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/navigation.ts` -- BreadcrumbItem and NavItem types used in page components and settings layout.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` -- User factory with states for verified/unverified users. Used in tests. After E001-F001, includes `video_credits`.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/ProfileUpdateTest.php` -- Existing settings test. Follow its patterns for test structure, actingAs usage, and assertions.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/DashboardTest.php` -- Simple test showing guest redirect and authenticated access patterns.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration. Feature tests use RefreshDatabase automatically.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/nav-user.tsx` -- Shows how the user menu links to settings. Referenced for understanding navigation flow.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/user-menu-content.tsx` -- User dropdown menu. Referenced for context.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/AccountController.php` -- New controller with a `show()` method that renders the `settings/account` Inertia page with `joinedAt` and `videoCredits` props.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/account.tsx` -- New Inertia page component displaying the user's account overview (name, email, verification status, join date, credit balance) in a read-only layout.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/AccountTest.php` -- Pest feature tests for the account overview page.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: profile-backend-dev
    - Role: Creates the AccountController, adds the route in settings.php, and generates Wayfinder routes by running the build
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: profile-frontend-dev
    - Role: Creates the account page component, updates the settings sidebar layout to include the Account nav item
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: profile-test-dev
    - Role: Writes comprehensive Pest feature tests for the account overview page
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: profile-reviewer
    - Role: Validates the complete feature against acceptance criteria, runs all tests, checks types, runs linting and formatting
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Create AccountController and Route

- **Task ID**: create-backend-controller-route
- **Depends On**: none
- **Assigned To**: profile-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- Read `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/ProfileController.php` to understand the controller pattern
- Read `/Users/young/Nextcloud/dev/Itervel/routes/settings.php` to understand the route structure
- Create the controller using artisan: `php artisan make:controller Settings/AccountController --no-interaction`
- Edit the generated `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/AccountController.php`:
    - Add necessary imports: `use Illuminate\Http\Request;`, `use Inertia\Inertia;`, `use Inertia\Response;`
    - Add a single `show` method that takes `Request $request` and returns `Response`
    - In the `show` method, get the authenticated user via `$request->user()`
    - Return `Inertia::render('settings/account', ['joinedAt' => $user->created_at->toDateString(), 'videoCredits' => $user->video_credits])` -- note: after E001-F001, the User model will have `video_credits`. If that column does not exist yet, use `$user->video_credits ?? 0` as a safe fallback
- Edit `/Users/young/Nextcloud/dev/Itervel/routes/settings.php`:
    - Add `use App\Http\Controllers\Settings\AccountController;` to the imports at the top
    - Add the route inside the `Route::middleware(['auth'])->group(function () {` block (the first middleware group, NOT the `['auth', 'verified']` group):
        ```php
        Route::get('settings/account', [AccountController::class, 'show'])->name('account.show');
        ```
    - Place it right after the `Route::redirect('settings', '/settings/account');` line -- also update the existing redirect from `/settings/profile` to `/settings/account` so the default settings page is the account overview
- Run `vendor/bin/pint --dirty` to fix any formatting issues
- Run `npm run build` to regenerate Wayfinder routes (so the `@/routes/account` route functions are available for the frontend)
- Verify the controller works by checking the route list: `php artisan route:list --path=settings/account`

### 2. Create Account Page Component and Update Settings Layout

- **Task ID**: create-frontend-page
- **Depends On**: create-backend-controller-route
- **Assigned To**: profile-frontend-dev
- **Agent Type**: coder
- **Parallel**: false
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/profile.tsx` to understand the exact page component pattern
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/settings/layout.tsx` to understand the settings sidebar navigation
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/components/user-info.tsx` and `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/badge.tsx` for reusable components
- Check what Wayfinder route was generated by reading `/Users/young/Nextcloud/dev/Itervel/resources/js/routes/account/index.ts` (or similar path -- look in the `resources/js/routes/` directory for the generated account route)
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/account.tsx` following the exact pattern of `settings/profile.tsx`:
    - Import: `{ Head, usePage } from '@inertiajs/react'`
    - Import: `Heading from '@/components/heading'`
    - Import: `{ Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'`
    - Import: `{ Badge } from '@/components/ui/badge'`
    - Import: `AppLayout from '@/layouts/app-layout'`
    - Import: `SettingsLayout from '@/layouts/settings/layout'`
    - Import: `type { BreadcrumbItem, SharedData } from '@/types'`
    - Import: `{ useInitials } from '@/hooks/use-initials'`
    - Import the Wayfinder route function for the account page (e.g., `import { show } from '@/routes/account'`)
    - Define the `AccountProps` interface: `{ joinedAt: string; videoCredits: number; }`
    - Define `breadcrumbs` array with a single item: `{ title: 'Account overview', href: show().url }`
    - Export default function `Account` with destructured props
    - Access `auth.user` via `usePage<SharedData>().props`
    - Render within `AppLayout > SettingsLayout`:
        - A `Heading` with `variant="small"`, `title="Account overview"`, `description="Your account information at a glance"`
        - A container div with the user's avatar (large, using Avatar/AvatarFallback/AvatarImage from the existing component) and their name prominently displayed
        - A definition list (`dl`) style layout with labeled rows for:
            - **Email**: Display `auth.user.email`
            - **Email status**: Show a `Badge` -- green ("Verified") if `auth.user.email_verified_at` is not null, otherwise yellow/outline ("Unverified")
            - **Member since**: Display the `joinedAt` prop formatted as a readable date (e.g., using `new Date(joinedAt).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })`)
            - **Video credits**: Display the `videoCredits` prop as a number
        - Use consistent styling: each row should have a `dt` (label) and `dd` (value) pair with Tailwind classes for layout (e.g., `grid grid-cols-1 sm:grid-cols-3 gap-1` per row, label with `text-sm font-medium text-muted-foreground`, value with `text-sm`)
- Update `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/settings/layout.tsx`:
    - Import the Wayfinder route function for the account page (e.g., `import { show as showAccount } from '@/routes/account'`)
    - Add a new nav item at the TOP of the `sidebarNavItems` array:
        ```tsx
        {
            title: 'Account',
            href: showAccount(),
            icon: null,
        },
        ```
    - The final order should be: Account, Profile, Password, Two-Factor Auth, Appearance
- Run `npm run types` to verify TypeScript types compile correctly
- Run `npm run lint:fix` to fix any linting issues
- Run `npm run format` to ensure Prettier formatting

### 3. Write Account Page Tests

- **Task ID**: write-account-tests
- **Depends On**: create-backend-controller-route
- **Assigned To**: profile-test-dev
- **Agent Type**: coder
- **Parallel**: true
- Read `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/ProfileUpdateTest.php` for test patterns in the settings area
- Read `/Users/young/Nextcloud/dev/Itervel/tests/Feature/DashboardTest.php` for simple page access test patterns
- Create the test file using artisan: `php artisan make:test Settings/AccountTest --pest --no-interaction`
- Edit `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/AccountTest.php` with the following tests:
    - `test('account page is displayed for authenticated users')` -- Create a user, act as that user, GET `route('account.show')`, assert 200 OK

        ```php
        test('account page is displayed for authenticated users', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->get(route('account.show'));

            $response->assertOk();
        });
        ```

    - `test('account page displays user information')` -- Create a user with specific attributes, GET the account page, use `assertInertia` to verify the page component is `settings/account` and the props include `joinedAt` and `videoCredits`

        ```php
        test('account page displays user information', function () {
            $user = User::factory()->create([
                'video_credits' => 5,
            ]);

            $response = $this->actingAs($user)->get(route('account.show'));

            $response->assertInertia(fn ($page) => $page
                ->component('settings/account')
                ->where('joinedAt', $user->created_at->toDateString())
                ->where('videoCredits', 5)
            );
        });
        ```

    - `test('guests are redirected to login from account page')` -- GET the account page without authentication, assert redirect to login

        ```php
        test('guests are redirected to login from account page', function () {
            $response = $this->get(route('account.show'));

            $response->assertRedirect(route('login'));
        });
        ```

    - `test('account page shows zero credits for new users without credits')` -- Create a user with `video_credits => 0`, verify the prop is 0

        ```php
        test('account page shows zero credits for new users without credits', function () {
            $user = User::factory()->create([
                'video_credits' => 0,
            ]);

            $response = $this->actingAs($user)->get(route('account.show'));

            $response->assertInertia(fn ($page) => $page
                ->component('settings/account')
                ->where('videoCredits', 0)
            );
        });
        ```

    - `test('account page is accessible to unverified users')` -- Create an unverified user, GET the account page, assert 200 OK (since we use the `auth` middleware, not `verified`)

        ```php
        test('account page is accessible to unverified users', function () {
            $user = User::factory()->unverified()->create();

            $response = $this->actingAs($user)->get(route('account.show'));

            $response->assertOk();
        });
        ```

- Note: These tests assume E001-F001 has been built (the User model has `video_credits` and the factory includes it). If the `video_credits` column does not exist yet at test time, the tests creating users with `video_credits` will need adjustment. However, since E001-F005 depends on E001-F003 which depends on E001-F001, the column should exist.
- Run the tests: `php artisan test tests/Feature/Settings/AccountTest.php --compact`
- Ensure all tests pass
- Run `vendor/bin/pint --dirty` to fix any formatting issues

### 4. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-backend-controller-route, create-frontend-page, write-account-tests
- **Assigned To**: profile-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed below
- Verify account tests pass: `php artisan test tests/Feature/Settings/AccountTest.php --compact`
- Verify all settings tests pass: `php artisan test tests/Feature/Settings --compact`
- Verify all auth tests still pass: `php artisan test tests/Feature/Auth --compact`
- Run the full test suite to ensure no regressions: `php artisan test --compact`
- Run TypeScript type checking: `npm run types`
- Run ESLint: `npm run lint`
- Run Prettier formatting check: `npm run format:check` (or `npm run format`)
- Run PHP formatting: `vendor/bin/pint --dirty`
- Verify the AccountController exists at `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/AccountController.php` with a `show()` method returning an Inertia response with `joinedAt` and `videoCredits` props
- Verify the route exists: `php artisan route:list --path=settings/account`
- Verify the route name is `account.show`
- Verify the account page component exists at `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/account.tsx`
- Verify the settings sidebar layout includes the "Account" nav item at the top
- Verify the settings redirect has been updated from `/settings/profile` to `/settings/account`
- Verify the account page displays: user name, email, email verification status (with Badge), join date, and video credits
- Confirm all acceptance criteria are met

## Acceptance Criteria

- The account overview page renders successfully at `/settings/account` for authenticated users
- The account page displays the user's name prominently
- The account page displays the user's email address
- The account page displays the user's email verification status using a badge (green "Verified" or "Unverified" indicator)
- The account page displays the user's join date in a human-readable format
- The account page displays the user's video credit balance
- The settings sidebar includes an "Account" navigation item at the top of the list
- Navigating to `/settings` redirects to `/settings/account` (the new default settings page)
- The account page is accessible to unverified users (uses `auth` middleware, not `verified`)
- Guests are redirected to the login page when trying to access the account page
- The page follows the same layout pattern as other settings pages (AppLayout + SettingsLayout)
- The breadcrumb shows "Account overview"
- All account tests pass
- All existing tests pass without regressions
- TypeScript types compile without errors
- ESLint and Prettier checks pass
- PHP code passes Pint formatting

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run account page tests
php artisan test tests/Feature/Settings/AccountTest.php --compact

# Run all settings tests
php artisan test tests/Feature/Settings --compact

# Run all auth tests for regression check
php artisan test tests/Feature/Auth --compact

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

# Verify route exists
php artisan route:list --path=settings/account
```

## Notes

- This feature creates a NEW page (`/settings/account`) that is separate from the existing profile settings page (`/settings/profile`). The existing profile page is an editable form for updating name and email. The new account page is a read-only overview of account details including credit balance and join date.
- The `/settings` redirect is updated from `/settings/profile` to `/settings/account` so the account overview becomes the default settings landing page. This makes sense because the account overview is the most general settings page and provides a natural starting point.
- The `joinedAt` prop uses `$user->created_at->toDateString()` which returns a `Y-m-d` format string. The frontend formats this for display using `toLocaleDateString()` for locale-appropriate rendering.
- The `videoCredits` prop is passed explicitly from the controller rather than relying on shared Inertia data. This makes the page's data requirements explicit and testable via `assertInertia`.
- After E001-F001, the User model will have a `video_credits` attribute. If this feature is somehow executed before E001-F001, the `video_credits` access will return `null`. The controller should use `$user->video_credits ?? 0` as a safe fallback, and the frontend should handle `0` gracefully.
- The route is placed in the `['auth']` middleware group (not `['auth', 'verified']`) intentionally. Users should be able to view their account information even if they have not verified their email. This is consistent with how the profile edit page works (also in the `['auth']` group).
- The Wayfinder route for `account.show` will be auto-generated when `npm run build` is run. The generated file will be at `resources/js/routes/account/index.ts` (based on the route name `account.show`). The import in the frontend will be `import { show } from '@/routes/account'`.
- All commands should be run inside the Docker container. Use `make shell` to enter the container, or prefix with `docker compose exec app`.

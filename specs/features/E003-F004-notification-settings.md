# Feature: Notification Settings

**Epic**: E003-user-profile-and-account-management.md
**Feature**: E003-F004
**Dependencies**: E003-F001

## Task Description

Notification Settings allows authenticated users to toggle email notifications on or off from within the settings area. This is a simple but important user preference that controls whether the system sends email notifications to the user (e.g., video rendering complete, file retention warnings, payment receipts, etc.). The preference is stored as a boolean column on the `users` table and respected by the application's notification system.

**What it does**: Allows users to toggle email notifications on or off.

**Expected outcome**: The user can enable or disable email notifications from a settings page, and the system respects their preference.

This feature depends on E001-F005 (User Profile Viewing), which creates the account settings page at `/settings/account` and updates the settings sidebar navigation. After E001-F005 is built:

- The settings sidebar will include an "Account" nav item at the top of the list.
- The `/settings` redirect will point to `/settings/account`.
- The `AccountController` will exist in `App\Http\Controllers\Settings`.
- The User model will have `video_credits` and implement `MustVerifyEmail` (from earlier dependencies E001-F001, E001-F003).

The notification settings page will live at `/settings/notifications` within the existing settings layout, accessible from the sidebar navigation. It will present a single toggle (checkbox) for enabling or disabling email notifications. The page follows the same settings page pattern used throughout the application: an Inertia page component wrapped in `AppLayout > SettingsLayout`, using `Heading` for the section header. The form uses Inertia's `<Form>` component with a Wayfinder-generated controller action for submission, and the checkbox component from the existing UI library.

The User model already uses the `Notifiable` trait (from `Illuminate\Notifications\Notifiable`), which provides the infrastructure for sending notifications. This feature adds a `email_notifications_enabled` boolean column to the `users` table (defaulting to `true` for new users and existing users) that can be checked before sending email notifications.

## Objective

Create a notification settings page at `/settings/notifications` where authenticated users can toggle email notifications on or off. The preference is persisted to the database and will be respected by the notification system when sending emails. The page follows the existing settings page patterns and integrates into the settings sidebar navigation.

## Solution Approach

### 1. Database: Add Notification Preference Column to Users Table

Create a migration to add a boolean column to the `users` table:

```php
$table->boolean('email_notifications_enabled')->default(true);
```

Using `default(true)` means:

- All existing users gain the column with notifications enabled (opt-out model, which is the standard approach).
- New users start with notifications enabled.
- The column is NOT nullable -- it always has a definitive `true` or `false` value, simplifying both backend and frontend logic.

### 2. Model: Update User Model

Add the new column to the `$fillable` array and add a boolean cast:

```php
protected $fillable = [
    'name', 'email', 'password',
    'email_notifications_enabled',
];

protected function casts(): array
{
    return [
        // existing casts...
        'email_notifications_enabled' => 'boolean',
    ];
}
```

### 3. Backend: Create NotificationController

Create `App\Http\Controllers\Settings\NotificationController` with `edit` and `update` methods following the exact pattern of `ProfileController` and `PasswordController`:

```php
class NotificationController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/notifications', [
            'emailNotificationsEnabled' => $request->user()->email_notifications_enabled,
        ]);
    }

    public function update(NotificationUpdateRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return to_route('notifications.edit');
    }
}
```

The `emailNotificationsEnabled` prop is passed explicitly from the controller rather than relying on shared Inertia data. This makes the page's data requirements explicit and testable via `assertInertia`.

### 4. Backend: Create NotificationUpdateRequest

Create `App\Http\Requests\Settings\NotificationUpdateRequest` following the pattern of existing form requests in the project:

```php
class NotificationUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email_notifications_enabled' => ['required', 'boolean'],
        ];
    }
}
```

### 5. Backend: Add Routes

Add routes in `routes/settings.php` within the `['auth']` middleware group (not `['auth', 'verified']`). Notification preferences are a basic account setting -- users should be able to manage their notification preferences even if they have not yet verified their email. This is consistent with the profile edit page and the account overview page being in the `['auth']` group.

```php
Route::get('settings/notifications', [NotificationController::class, 'edit'])->name('notifications.edit');
Route::patch('settings/notifications', [NotificationController::class, 'update'])->name('notifications.update');
```

### 6. Frontend: Create Notifications Page Component

Create `resources/js/pages/settings/notifications.tsx` following the exact pattern of the existing settings pages (`settings/profile.tsx`, `settings/password.tsx`). The page uses the Inertia `<Form>` component with a hidden input for the checkbox value (since `<Form>` works with native form elements).

The page will display:

- A `Heading` with `variant="small"` explaining the notification settings
- A `Checkbox` component (from the existing `@/components/ui/checkbox`) with a label for toggling email notifications
- A description explaining what the toggle controls
- A save button with the standard success transition pattern

Since the existing `Checkbox` component is Radix-based and does not use a native `<input>`, the form should use `useForm` from Inertia for full control over form data, similar to the approach recommended in E001-F007 for Select components.

```tsx
const { data, setData, patch, processing, recentlySuccessful, errors } =
    useForm({
        email_notifications_enabled: emailNotificationsEnabled,
    });

const handleSubmit = (e: FormEvent) => {
    e.preventDefault();
    patch(NotificationController.update());
};
```

### 7. Frontend: Update Settings Sidebar

Add a "Notifications" nav item in `resources/js/layouts/settings/layout.tsx`. Place it after "Account" (from E001-F005) and after "Preferences" (from E001-F007, if present) but before "Profile". The intended order is: Account, Preferences, Notifications, Profile, Password, Two-Factor Auth, Appearance. If sibling features have not been built yet, place it logically after the first item and before "Profile".

### 8. TypeScript Types

The notification preference is passed as an explicit page prop (not via the shared user type), so no changes to the `User` type in `resources/js/types/auth.ts` are needed. The page component defines its own props interface.

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/ProfileController.php` -- Sibling controller. Follow its exact pattern for controller structure, Inertia rendering, and the `edit`/`update` method signatures.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/PasswordController.php` -- Another sibling controller with `edit`/`update` pattern. Confirms the consistent structure.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Controller.php` -- Base controller class to extend.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` -- The User model. Must be updated to add `email_notifications_enabled` to `$fillable` and `casts()`. Already uses the `Notifiable` trait from `Illuminate\Notifications\Notifiable`.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/Settings/ProfileUpdateRequest.php` -- Sibling form request. Follow its pattern for the new `NotificationUpdateRequest`.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/Settings/PasswordUpdateRequest.php` -- Another sibling form request. Confirms the consistent validation pattern using array syntax.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Middleware/HandleInertiaRequests.php` -- Shares auth data with all Inertia pages. No changes needed -- the notification preference is passed as an explicit page prop from the controller.
- `/Users/young/Nextcloud/dev/Itervel/routes/settings.php` -- Settings route definitions. Must add new notification routes here in the `['auth']` middleware group.
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Main web routes file. Includes settings.php. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/profile.tsx` -- Primary reference for the frontend page pattern. Follow its exact structure: imports, breadcrumbs, `AppLayout > SettingsLayout` wrapping, `<h1 className="sr-only">` for accessibility.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/password.tsx` -- Another form-based settings page. Confirms the consistent pattern with `InputError` and `Transition` for success feedback.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/two-factor.tsx` -- Settings page that uses the `Checkbox`/toggle pattern and `Badge` component. Good reference for non-form-heavy settings pages.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/settings/layout.tsx` -- Settings sidebar layout. Must be updated to add the "Notifications" nav item. Follow existing patterns for the `sidebarNavItems` array and Wayfinder route imports.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/app-layout.tsx` -- Main app layout wrapper. Used by all authenticated pages.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/heading.tsx` -- Heading component with `variant` prop. Used for section headers on settings pages.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/input-error.tsx` -- InputError component for displaying validation errors below form fields.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/checkbox.tsx` -- Radix Checkbox component. Used for the email notifications toggle. Note: this is a Radix component, not a native checkbox, so `useForm` is needed for form data management.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/label.tsx` -- Label component. Used for the checkbox label.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/button.tsx` -- Button component. Used for the save button.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/index.ts` -- SharedData type definition. Referenced for understanding shared data structure.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/navigation.ts` -- BreadcrumbItem and NavItem types. Used in the page component and settings layout.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/lib/utils.ts` -- Utility functions including `cn()` for conditional class names.
- `/Users/young/Nextcloud/dev/Itervel/database/migrations/0001_01_01_000000_create_users_table.php` -- Original users migration. Reference for understanding the existing schema.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` -- User factory. Must be updated to include a default value for the new `email_notifications_enabled` column.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/ProfileUpdateTest.php` -- Existing settings test file. Follow its patterns for test structure, `actingAs` usage, assertions, and form submission testing.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/DashboardTest.php` -- Simple test showing guest redirect and authenticated access patterns.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration. Feature tests automatically use `RefreshDatabase`.
- `/Users/young/Nextcloud/dev/Itervel/bootstrap/app.php` -- Application configuration. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/routes/profile/index.ts` -- Example of Wayfinder-generated route file. Reference for understanding how route functions are structured and imported.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/actions/App/Http/Controllers/Settings/ProfileController.ts` -- Example of Wayfinder-generated action file. Reference for understanding how controller actions are structured and imported.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/database/migrations/YYYY_MM_DD_HHMMSS_add_email_notifications_enabled_to_users_table.php` -- Migration adding `email_notifications_enabled` boolean column (default `true`) to the `users` table.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/NotificationController.php` -- New controller with `edit()` and `update()` methods for rendering and updating the notification settings page.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/Settings/NotificationUpdateRequest.php` -- Form request class with validation rule for the `email_notifications_enabled` boolean field.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/notifications.tsx` -- New Inertia page component with a checkbox toggle for enabling/disabling email notifications.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/NotificationSettingsTest.php` -- Pest feature tests covering page access, toggle submission, validation, and authorization.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: notifications-backend-dev
    - Role: Creates the migration, updates the User model and factory, creates the NotificationController and NotificationUpdateRequest, adds routes, and regenerates Wayfinder routes
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: notifications-frontend-dev
    - Role: Creates the notifications page component and updates the settings sidebar layout to include the Notifications nav item
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: notifications-test-dev
    - Role: Writes comprehensive Pest feature tests for the notification settings page covering access, toggle submission, validation, and authorization
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: notifications-reviewer
    - Role: Validates the complete feature against acceptance criteria, runs all tests, checks types, runs linting and formatting
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Create Migration, Update Model, Factory, Controller, FormRequest, and Routes

- **Task ID**: create-backend-foundation
- **Depends On**: none
- **Assigned To**: notifications-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- Read `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` to understand the current model structure
- Read `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` to understand the factory structure
- Read `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/ProfileController.php` to understand the controller pattern
- Read `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/Settings/PasswordUpdateRequest.php` to understand the form request pattern
- Read `/Users/young/Nextcloud/dev/Itervel/routes/settings.php` to understand the route structure
- **Step 1: Create the migration** using artisan:
    ```bash
    php artisan make:migration add_email_notifications_enabled_to_users_table --table=users --no-interaction
    ```
    Edit the generated migration file to add the column in `up()`:
    ```php
    $table->boolean('email_notifications_enabled')->default(true);
    ```
    And in `down()`:
    ```php
    $table->dropColumn('email_notifications_enabled');
    ```
- **Step 2: Run the migration**:
    ```bash
    php artisan migrate --no-interaction
    ```
- **Step 3: Update the User model** at `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php`:
    - Add `'email_notifications_enabled'` to the `$fillable` array
    - Add the boolean cast in the `casts()` method:
        ```php
        'email_notifications_enabled' => 'boolean',
        ```
- **Step 4: Update the UserFactory** at `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php`:
    - Add `email_notifications_enabled` to the `definition()` method with default value `true`:
        ```php
        'email_notifications_enabled' => true,
        ```
- **Step 5: Create the FormRequest** using artisan:
    ```bash
    php artisan make:request Settings/NotificationUpdateRequest --no-interaction
    ```
    Edit `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/Settings/NotificationUpdateRequest.php`:
    - Set `authorize()` to return `true`
    - Add validation rules in `rules()`:
        ```php
        return [
            'email_notifications_enabled' => ['required', 'boolean'],
        ];
        ```
- **Step 6: Create the controller** using artisan:

    ```bash
    php artisan make:controller Settings/NotificationController --no-interaction
    ```

    Edit `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/NotificationController.php`:
    - Add imports: `use App\Http\Requests\Settings\NotificationUpdateRequest;`, `use Illuminate\Http\RedirectResponse;`, `use Illuminate\Http\Request;`, `use Inertia\Inertia;`, `use Inertia\Response;`
    - Add PHPDoc block `/** Show the user's notification settings page. */` before the `edit` method
    - Add `edit(Request $request): Response` method that renders `'settings/notifications'` with one prop:
        ```php
        return Inertia::render('settings/notifications', [
            'emailNotificationsEnabled' => $request->user()->email_notifications_enabled,
        ]);
        ```
    - Add PHPDoc block `/** Update the user's notification settings. */` before the `update` method
    - Add `update(NotificationUpdateRequest $request): RedirectResponse` method:

        ```php
        $request->user()->update($request->validated());

        return to_route('notifications.edit');
        ```

- **Step 7: Add routes** in `/Users/young/Nextcloud/dev/Itervel/routes/settings.php`:
    - Add `use App\Http\Controllers\Settings\NotificationController;` to the imports at the top of the file
    - Add two routes inside the `Route::middleware(['auth'])->group(function () {` block (the first middleware group, NOT the `['auth', 'verified']` group), after the existing profile routes:
        ```php
        Route::get('settings/notifications', [NotificationController::class, 'edit'])->name('notifications.edit');
        Route::patch('settings/notifications', [NotificationController::class, 'update'])->name('notifications.update');
        ```
- **Step 8: Run formatting and build**:
    ```bash
    vendor/bin/pint --dirty
    npm run build
    ```
- **Step 9: Verify** the routes are registered:
    ```bash
    php artisan route:list --path=settings/notifications
    ```

### 2. Create Notifications Page Component and Update Settings Layout

- **Task ID**: create-frontend-page
- **Depends On**: create-backend-foundation
- **Assigned To**: notifications-frontend-dev
- **Agent Type**: coder
- **Parallel**: false
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/profile.tsx` to understand the exact page component pattern (form with Wayfinder action, Transition for success, AppLayout + SettingsLayout)
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/password.tsx` to confirm the consistent form pattern
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/settings/layout.tsx` to understand the sidebar navigation structure
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/checkbox.tsx` to understand the Checkbox component API
- Check what Wayfinder routes were generated by looking in `/Users/young/Nextcloud/dev/Itervel/resources/js/routes/` for `notifications/` directory and in `/Users/young/Nextcloud/dev/Itervel/resources/js/actions/App/Http/Controllers/Settings/` for `NotificationController` actions
- **Step 1: Create the notifications page** at `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/notifications.tsx`:
    - Import pattern (follow profile.tsx structure, adapted for this page):
        ```tsx
        import { Transition } from '@headlessui/react';
        import { Head, useForm } from '@inertiajs/react';
        import { type FormEvent } from 'react';
        import Heading from '@/components/heading';
        import InputError from '@/components/input-error';
        import { Button } from '@/components/ui/button';
        import { Checkbox } from '@/components/ui/checkbox';
        import { Label } from '@/components/ui/label';
        import AppLayout from '@/layouts/app-layout';
        import SettingsLayout from '@/layouts/settings/layout';
        import type { BreadcrumbItem } from '@/types';
        import NotificationController from '@/actions/App/Http/Controllers/Settings/NotificationController';
        import { edit } from '@/routes/notifications';
        ```
    - Define props type:
        ```tsx
        type NotificationProps = {
            emailNotificationsEnabled: boolean;
        };
        ```
    - Define breadcrumbs:
        ```tsx
        const breadcrumbs: BreadcrumbItem[] = [
            { title: 'Notification settings', href: edit().url },
        ];
        ```
    - Export default function `Notifications` with destructured props
    - Use `useForm` hook (NOT the `<Form>` component) because the Radix Checkbox does not use a native `<input>`, so form data must be managed programmatically:

        ```tsx
        const { data, setData, patch, processing, recentlySuccessful, errors } =
            useForm({
                email_notifications_enabled: emailNotificationsEnabled,
            });

        const handleSubmit = (e: FormEvent) => {
            e.preventDefault();
            patch(NotificationController.update(), {
                preserveScroll: true,
            });
        };
        ```

    - Wrap in `AppLayout > SettingsLayout` like all other settings pages
    - Add `<Head title="Notification settings" />`
    - Add `<h1 className="sr-only">Notification Settings</h1>` for accessibility (matches other settings pages)
    - Use `Heading` with `variant="small"`, `title="Email notifications"`, `description="Manage your email notification preferences"`
    - Use a regular `<form onSubmit={handleSubmit}>` element with `className="space-y-6"`:
        - A `div` containing the Checkbox and Label, styled as a flex row:
            ```tsx
            <div className="flex items-center space-x-3">
                <Checkbox
                    id="email_notifications_enabled"
                    checked={data.email_notifications_enabled}
                    onCheckedChange={(checked) =>
                        setData('email_notifications_enabled', checked === true)
                    }
                />
                <Label
                    htmlFor="email_notifications_enabled"
                    className="cursor-pointer"
                >
                    Enable email notifications
                </Label>
            </div>
            ```
        - A description paragraph below the checkbox:
            ```tsx
            <p className="text-sm text-muted-foreground">
                When enabled, you will receive email notifications about video
                rendering completion, file retention warnings, and other
                important updates.
            </p>
            ```
        - An `InputError` for the `email_notifications_enabled` field
        - The save button and success transition, following the exact pattern from profile.tsx:
            ```tsx
            <div className="flex items-center gap-4">
                <Button
                    disabled={processing}
                    data-test="update-notifications-button"
                >
                    Save
                </Button>
                <Transition
                    show={recentlySuccessful}
                    enter="transition ease-in-out"
                    enterFrom="opacity-0"
                    leave="transition ease-in-out"
                    leaveTo="opacity-0"
                >
                    <p className="text-sm text-neutral-600">Saved</p>
                </Transition>
            </div>
            ```

- **Step 2: Update the settings sidebar layout** at `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/settings/layout.tsx`:
    - Add import for the notifications route: `import { edit as editNotifications } from '@/routes/notifications';`
    - Add a new nav item to the `sidebarNavItems` array. Place it before "Profile" (and after "Account" and "Preferences" if they exist from E001-F005 and E001-F007). If those sibling features are not present yet, place it before "Profile" as the first item. The final intended order is: Account, Preferences, Notifications, Profile, Password, Two-Factor Auth, Appearance.
        ```tsx
        {
            title: 'Notifications',
            href: editNotifications(),
            icon: null,
        },
        ```
- **Step 3: Run frontend validation**:
    ```bash
    npm run types
    npm run lint:fix
    npm run format
    ```

### 3. Write Notification Settings Feature Tests

- **Task ID**: write-notification-tests
- **Depends On**: create-backend-foundation
- **Assigned To**: notifications-test-dev
- **Agent Type**: coder
- **Parallel**: true
- Read `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/ProfileUpdateTest.php` for test patterns in the settings area
- Read `/Users/young/Nextcloud/dev/Itervel/tests/Feature/DashboardTest.php` for simple page access/redirect test patterns
- Create the test file using artisan:
    ```bash
    php artisan make:test Settings/NotificationSettingsTest --pest --no-interaction
    ```
- Edit `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/NotificationSettingsTest.php` with the following tests:

    **Access Tests:**
    - `test('notification settings page is displayed for authenticated users')`:

        ```php
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('notifications.edit'));

        $response->assertOk();
        ```

    - `test('guests are redirected to login from notification settings page')`:

        ```php
        $response = $this->get(route('notifications.edit'));

        $response->assertRedirect(route('login'));
        ```

    - `test('notification settings page is accessible to unverified users')`:

        ```php
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get(route('notifications.edit'));

        $response->assertOk();
        ```

    **Display Tests:**
    - `test('notification settings page displays current preference')`:

        ```php
        $user = User::factory()->create([
            'email_notifications_enabled' => true,
        ]);

        $response = $this->actingAs($user)->get(route('notifications.edit'));

        $response->assertInertia(fn ($page) => $page
            ->component('settings/notifications')
            ->where('emailNotificationsEnabled', true)
        );
        ```

    - `test('notification settings page displays disabled preference')`:

        ```php
        $user = User::factory()->create([
            'email_notifications_enabled' => false,
        ]);

        $response = $this->actingAs($user)->get(route('notifications.edit'));

        $response->assertInertia(fn ($page) => $page
            ->component('settings/notifications')
            ->where('emailNotificationsEnabled', false)
        );
        ```

    **Update Tests:**
    - `test('email notifications can be disabled')`:

        ```php
        $user = User::factory()->create([
            'email_notifications_enabled' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('notifications.update'), [
                'email_notifications_enabled' => false,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('notifications.edit'));

        $user->refresh();
        expect($user->email_notifications_enabled)->toBeFalse();
        ```

    - `test('email notifications can be enabled')`:

        ```php
        $user = User::factory()->create([
            'email_notifications_enabled' => false,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('notifications.update'), [
                'email_notifications_enabled' => true,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('notifications.edit'));

        $user->refresh();
        expect($user->email_notifications_enabled)->toBeTrue();
        ```

    - `test('email notifications preference is required')`:

        ```php
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('notifications.update'), []);

        $response->assertSessionHasErrors('email_notifications_enabled');
        ```

    - `test('email notifications preference must be boolean')`:

        ```php
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('notifications.update'), [
                'email_notifications_enabled' => 'invalid',
            ]);

        $response->assertSessionHasErrors('email_notifications_enabled');
        ```

    - `test('guests cannot update notification settings')`:

        ```php
        $response = $this->patch(route('notifications.update'), [
            'email_notifications_enabled' => false,
        ]);

        $response->assertRedirect(route('login'));
        ```

    - `test('new users have email notifications enabled by default')`:

        ```php
        $user = User::factory()->create();

        expect($user->email_notifications_enabled)->toBeTrue();
        ```

- Run the tests:
    ```bash
    php artisan test tests/Feature/Settings/NotificationSettingsTest.php --compact
    ```
- Ensure all tests pass
- Run formatting:
    ```bash
    vendor/bin/pint --dirty
    ```

### 4. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-backend-foundation, create-frontend-page, write-notification-tests
- **Assigned To**: notifications-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed below
- Verify notification tests pass: `php artisan test tests/Feature/Settings/NotificationSettingsTest.php --compact`
- Verify all settings tests pass: `php artisan test tests/Feature/Settings --compact`
- Verify all auth tests still pass: `php artisan test tests/Feature/Auth --compact`
- Run the full test suite to ensure no regressions: `php artisan test --compact`
- Run TypeScript type checking: `npm run types`
- Run ESLint: `npm run lint`
- Run Prettier formatting check: `npm run format`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Verify the migration exists and adds `email_notifications_enabled` boolean column (default `true`) to the `users` table
- Verify the User model has `email_notifications_enabled` in `$fillable` and `casts()`
- Verify the UserFactory includes `email_notifications_enabled` with default value `true`
- Verify the NotificationController exists at `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/NotificationController.php` with `edit()` and `update()` methods
- Verify the NotificationUpdateRequest exists at `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/Settings/NotificationUpdateRequest.php` with correct validation rules
- Verify the routes exist: `php artisan route:list --path=settings/notifications`
- Verify the route names are `notifications.edit` and `notifications.update`
- Verify the routes are in the `['auth']` middleware group (NOT `['auth', 'verified']`)
- Verify the notifications page component exists at `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/notifications.tsx`
- Verify the settings sidebar layout includes the "Notifications" nav item
- Verify the notifications page has a checkbox toggle for email notifications
- Verify the form submits via PATCH to the update route
- Verify the success feedback ("Saved") transition works
- Confirm all acceptance criteria are met

## Acceptance Criteria

- A database migration adds `email_notifications_enabled` boolean column (default `true`) to the `users` table
- The notification settings page renders successfully at `/settings/notifications` for authenticated users
- The notification settings page displays the user's current email notification preference as a checkbox toggle
- Users can disable email notifications by unchecking the checkbox and saving
- Users can enable email notifications by checking the checkbox and saving
- The preference change persists to the database after saving
- New users have email notifications enabled by default
- Validation rejects non-boolean values and shows appropriate error messages
- The form shows a "Saved" success message after successful update
- The settings sidebar includes a "Notifications" navigation item
- The notification settings page is accessible to unverified users (uses `auth` middleware, not `verified`)
- Guests are redirected to the login page when trying to access the notification settings page
- The page follows the same layout pattern as other settings pages (AppLayout + SettingsLayout)
- The breadcrumb shows "Notification settings"
- All notification settings tests pass
- All existing tests pass without regressions
- TypeScript types compile without errors
- ESLint and Prettier checks pass
- PHP code passes Pint formatting

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run notification settings tests
php artisan test tests/Feature/Settings/NotificationSettingsTest.php --compact

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

# Verify routes exist
php artisan route:list --path=settings/notifications
```

## Notes

- The notification preference uses an opt-out model (`default(true)`), meaning users start with notifications enabled and must explicitly disable them. This is the standard approach for email notifications and is more user-friendly than requiring users to opt in.
- The column is NOT nullable. Every user always has a definitive `true` or `false` value, eliminating the need for null checks in both backend and frontend code. Using `default(true)` in the migration means all existing users gain the column with notifications enabled.
- The route is placed in the `['auth']` middleware group (not `['auth', 'verified']`) intentionally. Users should be able to manage their notification preferences even if they have not verified their email. This is consistent with the profile edit page and the account overview page.
- The `useForm` hook is used instead of the `<Form>` component because the Radix Checkbox does not render a native `<input type="checkbox">` element. The `<Form>` component relies on native form elements for data collection, so `useForm` with programmatic `setData` in `onCheckedChange` provides full control over form state.
- The `email_notifications_enabled` preference will be consumed by future features that send email notifications (e.g., E001-F053 File Retention Policy warnings, E001-F089 Shotstack Completion Webhook notifications, E001-F061 Stripe Checkout receipts). Those features should check `$user->email_notifications_enabled` before dispatching email notifications. This feature only handles storing and editing the preference.
- The Wayfinder routes for `notifications.edit` and `notifications.update` will be auto-generated when `npm run build` is run. The generated route file will be at `resources/js/routes/notifications/index.ts` (based on the route name prefix `notifications`). The action file will be at `resources/js/actions/App/Http/Controllers/Settings/NotificationController.ts`.
- All commands should be run inside the Docker container. Use `make shell` to enter the container, or prefix with `docker compose exec app`.

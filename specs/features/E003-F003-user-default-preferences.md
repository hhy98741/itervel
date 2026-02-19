# Feature: User Default Preferences

**Epic**: E003-user-profile-and-account-management.md
**Feature**: E003-F003
**Epic depends on**: E002-user-authentication.md
**Feature depends on**: E003-F001

## Task Description

User Default Preferences lets authenticated users configure default settings for video creation, such as default video length, speaking pace, and number of script iterations. These defaults are automatically applied when the user creates a new video, reducing repetitive configuration.

**What it does**: Lets users set default preferences for video creation, such as default video length, speaking pace, and number of script iterations.

**Expected outcome**: The user configures their preferred defaults (e.g., 12-minute video, 165 words per minute, 2 script iterations) and these are automatically applied when creating new videos.

This feature depends on E001-F005 (User Profile Viewing), which creates the account settings page at `/settings/account` and updates the settings sidebar navigation. After E001-F005 is built:

- The settings sidebar will include an "Account" nav item at the top of the list.
- The `/settings` redirect will point to `/settings/account`.
- The `AccountController` will exist in `App\Http\Controllers\Settings`.
- The User model will have `video_credits` and implement `MustVerifyEmail` (from earlier dependencies E001-F001, E001-F003).

The preferences page will live at `/settings/preferences` within the existing settings layout, accessible from the sidebar navigation. It will present a form with three preference fields: default video length (in minutes), speaking pace (words per minute), and number of script iterations (0-5). These preferences are stored as columns on the `users` table and exposed via the authenticated user's shared Inertia data.

The design follows the existing settings page patterns exactly: an Inertia page component wrapped in `AppLayout > SettingsLayout`, using `Heading` for the section header, and Inertia's `<Form>` component with a Wayfinder-generated controller action for submission. The form uses existing UI components (`Select`, `Input`, `Label`) and provides success feedback using the same `Transition` pattern seen in the profile and password settings pages.

## Objective

Create a preferences settings page at `/settings/preferences` where authenticated users can configure default video creation settings (video length, speaking pace, script iterations). These preferences are persisted to the database and will be automatically applied when creating new videos in future features.

## Solution Approach

### 1. Database: Add Preference Columns to Users Table

Create a migration to add three nullable columns to the `users` table with sensible defaults:

```php
$table->unsignedSmallInteger('default_video_length')->default(10); // minutes (1-20)
$table->unsignedSmallInteger('default_speaking_pace')->default(150); // words per minute (100-200)
$table->unsignedTinyInteger('default_script_iterations')->default(2); // iterations (0-5)
```

Using database-level defaults means new users automatically get reasonable values without requiring explicit preference setup. The columns are NOT nullable -- they always have a value.

### 2. Model: Update User Model

Add the three new columns to the `$fillable` array and add integer casts:

```php
protected $fillable = [
    'name', 'email', 'password',
    'default_video_length', 'default_speaking_pace', 'default_script_iterations',
];

protected function casts(): array
{
    return [
        // existing casts...
        'default_video_length' => 'integer',
        'default_speaking_pace' => 'integer',
        'default_script_iterations' => 'integer',
    ];
}
```

### 3. Backend: Create PreferencesController

Create `App\Http\Controllers\Settings\PreferencesController` with `edit` and `update` methods following the exact pattern of `ProfileController`:

```php
class PreferencesController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/preferences', [
            'defaultVideoLength' => $request->user()->default_video_length,
            'defaultSpeakingPace' => $request->user()->default_speaking_pace,
            'defaultScriptIterations' => $request->user()->default_script_iterations,
        ]);
    }

    public function update(PreferencesUpdateRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return to_route('preferences.edit');
    }
}
```

### 4. Backend: Create PreferencesUpdateRequest

Create `App\Http\Requests\Settings\PreferencesUpdateRequest` following the pattern of `ProfileUpdateRequest`:

```php
class PreferencesUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'default_video_length' => ['required', 'integer', 'min:1', 'max:20'],
            'default_speaking_pace' => ['required', 'integer', 'min:100', 'max:200'],
            'default_script_iterations' => ['required', 'integer', 'min:0', 'max:5'],
        ];
    }

    public function messages(): array
    {
        return [
            'default_video_length.min' => 'Video length must be at least 1 minute.',
            'default_video_length.max' => 'Video length cannot exceed 20 minutes.',
            'default_speaking_pace.min' => 'Speaking pace must be at least 100 words per minute.',
            'default_speaking_pace.max' => 'Speaking pace cannot exceed 200 words per minute.',
            'default_script_iterations.min' => 'Script iterations must be at least 0.',
            'default_script_iterations.max' => 'Script iterations cannot exceed 5.',
        ];
    }
}
```

### 5. Backend: Add Routes

Add routes in `routes/settings.php` within the `['auth', 'verified']` middleware group (preferences require verified email since they relate to video creation functionality):

```php
Route::get('settings/preferences', [PreferencesController::class, 'edit'])->name('preferences.edit');
Route::patch('settings/preferences', [PreferencesController::class, 'update'])->name('preferences.update');
```

### 6. Frontend: Create Preferences Page Component

Create `resources/js/pages/settings/preferences.tsx` following the exact pattern of `settings/profile.tsx` and `settings/password.tsx`:

- Use `AppLayout > SettingsLayout` wrapping
- Use `Heading` with `variant="small"`
- Use Inertia `<Form>` with Wayfinder-generated action `PreferencesController.update.form()`
- Use `Select` components for video length (predefined options: 1-20 minutes) and script iterations (0-5)
- Use `Input` with `type="number"` for speaking pace (100-200 range)
- Show validation errors with `InputError`
- Show save success with `Transition` pattern (same as profile/password pages)
- Each field has a brief description below the label explaining what the setting controls

### 7. Frontend: Update Settings Sidebar

Add a "Preferences" nav item in `resources/js/layouts/settings/layout.tsx` after "Account" (from E001-F005) and before "Profile". Import the Wayfinder route and add the entry to `sidebarNavItems`.

### 8. TypeScript Types

The preference values are passed as explicit page props (not via the shared user type), so no changes to the `User` type in `resources/js/types/auth.ts` are needed. The page component defines its own props interface.

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/ProfileController.php` -- Sibling controller. Follow its exact pattern for controller structure, Inertia rendering, and the `edit`/`update` method signatures.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Controller.php` -- Base controller class to extend.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` -- The User model. Must be updated to add new preference columns to `$fillable` and `casts()`. After E001-F001/F005, it will have `video_credits`, `MustVerifyEmail`, etc.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/Settings/ProfileUpdateRequest.php` -- Sibling form request. Follow its pattern for the new `PreferencesUpdateRequest`.
- `/Users/young/Nextcloud/dev/Itervel/app/Concerns/ProfileValidationRules.php` -- Example of validation rule organization using traits. Reference for understanding the validation pattern. No changes needed here, but the pattern of array-syntax rules should be followed.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Middleware/HandleInertiaRequests.php` -- Shares auth data with all Inertia pages. No changes needed -- preferences are passed as explicit page props from the controller.
- `/Users/young/Nextcloud/dev/Itervel/routes/settings.php` -- Settings route definitions. Must add new preference routes here.
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Main web routes file. Includes settings.php. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/profile.tsx` -- Primary reference for the frontend page pattern. Follow its exact structure: imports, breadcrumbs, `<Form>` with Wayfinder action, success `Transition`, `AppLayout > SettingsLayout` wrapping.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/password.tsx` -- Another form-based settings page. Confirms the consistent pattern with `<Form>`, `InputError`, and `Transition`.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/settings/layout.tsx` -- Settings sidebar layout. Must be updated to add the "Preferences" nav item. Follow existing patterns for the `sidebarNavItems` array and Wayfinder route imports.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/heading.tsx` -- Heading component with `variant` prop. Used for section headers on settings pages.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/input-error.tsx` -- InputError component for displaying validation errors below form fields.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/input.tsx` -- Input component. Used for the speaking pace number input.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/label.tsx` -- Label component. Used for form field labels.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/select.tsx` -- Select component (Radix-based). Used for video length and script iterations dropdowns.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/button.tsx` -- Button component. Used for the save button.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/index.ts` -- SharedData type. Referenced for `usePage<SharedData>()` usage (though not needed for this page since preferences are explicit props).
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/navigation.ts` -- BreadcrumbItem and NavItem types. Used in the page component and settings layout.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/lib/utils.ts` -- Utility functions including `cn()` for conditional class names.
- `/Users/young/Nextcloud/dev/Itervel/database/migrations/0001_01_01_000000_create_users_table.php` -- Original users migration. Reference for understanding the existing schema.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` -- User factory. Must be updated to include default values for the new preference columns.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/ProfileUpdateTest.php` -- Existing settings test file. Follow its patterns for test structure, `actingAs` usage, assertions, and form submission testing.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/DashboardTest.php` -- Simple test showing guest redirect and authenticated access patterns.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration. Feature tests automatically use `RefreshDatabase`.
- `/Users/young/Nextcloud/dev/Itervel/bootstrap/app.php` -- Application configuration. No changes needed.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/database/migrations/YYYY_MM_DD_HHMMSS_add_preference_columns_to_users_table.php` -- Migration adding `default_video_length`, `default_speaking_pace`, and `default_script_iterations` columns to the `users` table with database-level defaults.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/PreferencesController.php` -- New controller with `edit()` and `update()` methods for rendering and updating the preferences page.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/Settings/PreferencesUpdateRequest.php` -- Form request class with validation rules for the three preference fields.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/preferences.tsx` -- New Inertia page component with a form for editing video creation defaults (video length, speaking pace, script iterations).
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/PreferencesTest.php` -- Pest feature tests covering page access, form submission, validation, and authorization.
- `tests/Browser/Settings/PreferencesTest.php` -- Pest browser tests: smoke test for the preferences page, dark mode spot check, and preferences form submission flow test using `data-test` selectors.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: preferences-backend-dev
    - Role: Creates the migration, updates the User model and factory, creates the PreferencesController and PreferencesUpdateRequest, adds routes, and regenerates Wayfinder routes
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: preferences-frontend-dev
    - Role: Creates the preferences page component and updates the settings sidebar layout to include the Preferences nav item
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: preferences-test-dev
    - Role: Writes comprehensive Pest feature tests for the preferences page covering access, submission, validation, and authorization
    - Agent Type: coder
    - Resume: false

- Browser Test Developer
    - Name: preferences-browser-test-dev
    - Role: Writes Pest browser tests (smoke test, dark mode check, form submission flow) for the preferences settings page
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: preferences-reviewer
    - Role: Validates the complete feature against acceptance criteria, runs all tests, checks types, runs linting and formatting
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Create Migration, Update Model, Factory, Controller, FormRequest, and Routes

- **Task ID**: create-backend-foundation
- **Depends On**: none
- **Assigned To**: preferences-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- Read `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` to understand the current model structure
- Read `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` to understand the factory structure
- Read `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/ProfileController.php` to understand the controller pattern
- Read `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/Settings/ProfileUpdateRequest.php` to understand the form request pattern
- Read `/Users/young/Nextcloud/dev/Itervel/routes/settings.php` to understand the route structure
- **Step 1: Create the migration** using artisan:
    ```bash
    php artisan make:migration add_preference_columns_to_users_table --table=users --no-interaction
    ```
    Edit the generated migration file to add three columns in `up()`:
    ```php
    $table->unsignedSmallInteger('default_video_length')->default(10);
    $table->unsignedSmallInteger('default_speaking_pace')->default(150);
    $table->unsignedTinyInteger('default_script_iterations')->default(2);
    ```
    And in `down()`:
    ```php
    $table->dropColumn(['default_video_length', 'default_speaking_pace', 'default_script_iterations']);
    ```
- **Step 2: Run the migration**:
    ```bash
    php artisan migrate --no-interaction
    ```
- **Step 3: Update the User model** at `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php`:
    - Add `'default_video_length'`, `'default_speaking_pace'`, `'default_script_iterations'` to the `$fillable` array
    - Add integer casts in the `casts()` method:
        ```php
        'default_video_length' => 'integer',
        'default_speaking_pace' => 'integer',
        'default_script_iterations' => 'integer',
        ```
- **Step 4: Update the UserFactory** at `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php`:
    - Add the three preference columns to the `definition()` method with default values matching the migration:
        ```php
        'default_video_length' => 10,
        'default_speaking_pace' => 150,
        'default_script_iterations' => 2,
        ```
- **Step 5: Create the FormRequest** using artisan:
    ```bash
    php artisan make:request Settings/PreferencesUpdateRequest --no-interaction
    ```
    Edit `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/Settings/PreferencesUpdateRequest.php`:
    - Set `authorize()` to return `true`
    - Add validation rules in `rules()`:
        ```php
        return [
            'default_video_length' => ['required', 'integer', 'min:1', 'max:20'],
            'default_speaking_pace' => ['required', 'integer', 'min:100', 'max:200'],
            'default_script_iterations' => ['required', 'integer', 'min:0', 'max:5'],
        ];
        ```
    - Add a `messages()` method with user-friendly error messages for min/max constraints
- **Step 6: Create the controller** using artisan:
    ```bash
    php artisan make:controller Settings/PreferencesController --no-interaction
    ```
    Edit `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/PreferencesController.php`:
    - Add imports: `use App\Http\Requests\Settings\PreferencesUpdateRequest;`, `use Illuminate\Http\RedirectResponse;`, `use Illuminate\Http\Request;`, `use Inertia\Inertia;`, `use Inertia\Response;`
    - Add `edit(Request $request): Response` method that renders `'settings/preferences'` with three props:
        ```php
        return Inertia::render('settings/preferences', [
            'defaultVideoLength' => $request->user()->default_video_length,
            'defaultSpeakingPace' => $request->user()->default_speaking_pace,
            'defaultScriptIterations' => $request->user()->default_script_iterations,
        ]);
        ```
    - Add `update(PreferencesUpdateRequest $request): RedirectResponse` method:
        ```php
        $request->user()->update($request->validated());
        return to_route('preferences.edit');
        ```
- **Step 7: Add routes** in `/Users/young/Nextcloud/dev/Itervel/routes/settings.php`:
    - Add `use App\Http\Controllers\Settings\PreferencesController;` to the imports at the top
    - Add two routes inside the `Route::middleware(['auth', 'verified'])->group(function () {` block:
        ```php
        Route::get('settings/preferences', [PreferencesController::class, 'edit'])->name('preferences.edit');
        Route::patch('settings/preferences', [PreferencesController::class, 'update'])->name('preferences.update');
        ```
- **Step 8: Run formatting and build**:
    ```bash
    vendor/bin/pint --dirty
    npm run build
    ```
- **Step 9: Verify** the routes are registered:
    ```bash
    php artisan route:list --path=settings/preferences
    ```

### 2. Create Preferences Page Component and Update Settings Layout

- **Task ID**: create-frontend-page
- **Depends On**: create-backend-foundation
- **Assigned To**: preferences-frontend-dev
- **Agent Type**: coder
- **Parallel**: false
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/profile.tsx` to understand the exact page component pattern (form with Wayfinder action, Transition for success, AppLayout + SettingsLayout)
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/password.tsx` to confirm the consistent form pattern
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/settings/layout.tsx` to understand the sidebar navigation structure
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/select.tsx` to understand the Select component API
- Check what Wayfinder routes were generated by looking in `/Users/young/Nextcloud/dev/Itervel/resources/js/routes/` for `preferences/` directory and in `/Users/young/Nextcloud/dev/Itervel/resources/js/actions/App/Http/Controllers/Settings/` for `PreferencesController` actions
- **Step 1: Create the preferences page** at `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/preferences.tsx`:
    - Import pattern (follow profile.tsx exactly):
        ```tsx
        import { Transition } from '@headlessui/react';
        import { Form, Head } from '@inertiajs/react';
        import Heading from '@/components/heading';
        import InputError from '@/components/input-error';
        import { Button } from '@/components/ui/button';
        import { Input } from '@/components/ui/input';
        import { Label } from '@/components/ui/label';
        import {
            Select,
            SelectContent,
            SelectItem,
            SelectTrigger,
            SelectValue,
        } from '@/components/ui/select';
        import AppLayout from '@/layouts/app-layout';
        import SettingsLayout from '@/layouts/settings/layout';
        import type { BreadcrumbItem } from '@/types';
        import PreferencesController from '@/actions/App/Http/Controllers/Settings/PreferencesController';
        import { edit } from '@/routes/preferences';
        ```
    - Define `PreferencesProps` interface:
        ```tsx
        type PreferencesProps = {
            defaultVideoLength: number;
            defaultSpeakingPace: number;
            defaultScriptIterations: number;
        };
        ```
    - Define breadcrumbs:
        ```tsx
        const breadcrumbs: BreadcrumbItem[] = [
            { title: 'Preferences', href: edit().url },
        ];
        ```
    - Export default function `Preferences` with destructured props
    - Wrap in `AppLayout > SettingsLayout` like all other settings pages
    - Add `<Head title="Preferences" />`
    - Add `<h1 className="sr-only">Preferences</h1>` for accessibility (matches other settings pages)
    - Use `Heading` with `variant="small"`, `title="Video creation defaults"`, `description="Set your preferred defaults for new video creation"`
    - Use Inertia `<Form>` with `PreferencesController.update.form()` and `options={{ preserveScroll: true }}`
    - Inside the form render function `{({ processing, recentlySuccessful, errors }) => ( ... )}`:
        - **Default Video Length** field: Use `Select` component with name `default_video_length`. Options from 1-20 minutes. Default value from `defaultVideoLength.toString()`. Include a hidden input with `name="default_video_length"` to submit the value (or use the Select's native form submission). Add a description below: "The default duration for new videos (1-20 minutes)."
        - **Speaking Pace** field: Use `Input` with `type="number"`, `name="default_speaking_pace"`, `min={100}`, `max={200}`, `defaultValue={defaultSpeakingPace}`. Add a description below: "Words per minute for voiceover pacing (100-200 WPM)."
        - **Script Iterations** field: Use `Select` component with name `default_script_iterations`. Options from 0-5. Default value from `defaultScriptIterations.toString()`. Add a description below: "Number of AI critique-and-refinement rounds (0-5)."
        - Add `data-test` attributes to interactive elements for browser testing:
            - `data-test="video-length-select"` on the video length Select trigger
            - `data-test="speaking-pace-input"` on the speaking pace Input
            - `data-test="script-iterations-select"` on the script iterations Select trigger
            - `data-test="update-preferences-button"` on the save button (already specified above)
        - **Save button and success transition**: Follow the exact pattern from profile.tsx:
            ```tsx
            <div className="flex items-center gap-4">
                <Button
                    disabled={processing}
                    data-test="update-preferences-button"
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
    - IMPORTANT NOTE about Select + Form: The Radix Select component does not use a native `<select>` element, so it may not automatically submit values with the form. To work around this, use a hidden `<input>` for each select field that is updated when the select value changes. Use React `useState` to track each select value and bind the hidden input's `value` to the state. Alternatively, check if the Inertia `<Form>` component handles this automatically with the `name` prop. Test this behavior. If not, use `useForm` instead of `<Form>` to have full control over form data.
    - ALTERNATIVE APPROACH: If the Radix Select does not integrate cleanly with Inertia's `<Form>`, switch to using `useForm` hook instead:

        ```tsx
        import { useForm, Head } from '@inertiajs/react';
        import PreferencesController from '@/actions/App/Http/Controllers/Settings/PreferencesController';

        const { data, setData, patch, processing, recentlySuccessful, errors } =
            useForm({
                default_video_length: defaultVideoLength,
                default_speaking_pace: defaultSpeakingPace,
                default_script_iterations: defaultScriptIterations,
            });

        const handleSubmit = (e: FormEvent) => {
            e.preventDefault();
            patch(PreferencesController.update());
        };
        ```

        Then use a regular `<form onSubmit={handleSubmit}>` and bind Select `onValueChange` to `setData`. This approach gives full control over form state and is the safer choice for Select components.

- **Step 2: Update the settings sidebar layout** at `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/settings/layout.tsx`:
    - Add import for the preferences route: `import { edit as editPreferences } from '@/routes/preferences';`
    - Add a new nav item to the `sidebarNavItems` array. Place it after "Account" (added by E001-F005) and before "Profile". If "Account" is not present yet (E001-F005 not yet built), place it after the existing "Profile" item instead. The final intended order is: Account, Preferences, Profile, Password, Two-Factor Auth, Appearance.
        ```tsx
        {
            title: 'Preferences',
            href: editPreferences(),
            icon: null,
        },
        ```
- **Step 3: Run frontend validation**:
    ```bash
    npm run types
    npm run lint:fix
    npm run format
    ```

### 3. Write Preferences Feature Tests

- **Task ID**: write-preferences-tests
- **Depends On**: create-backend-foundation
- **Assigned To**: preferences-test-dev
- **Agent Type**: coder
- **Parallel**: true
- Read `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/ProfileUpdateTest.php` for test patterns
- Read `/Users/young/Nextcloud/dev/Itervel/tests/Feature/DashboardTest.php` for simple access/redirect test patterns
- Create the test file using artisan:
    ```bash
    php artisan make:test Settings/PreferencesTest --pest --no-interaction
    ```
- Edit `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/PreferencesTest.php` with the following tests:

    **Access Tests:**
    - `test('preferences page is displayed for authenticated verified users')`:
        ```php
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get(route('preferences.edit'));
        $response->assertOk();
        ```
    - `test('guests are redirected to login from preferences page')`:
        ```php
        $response = $this->get(route('preferences.edit'));
        $response->assertRedirect(route('login'));
        ```
    - `test('unverified users are redirected from preferences page')`:
        ```php
        $user = User::factory()->unverified()->create();
        $response = $this->actingAs($user)->get(route('preferences.edit'));
        $response->assertRedirect(route('verification.notice'));
        ```

    **Display Tests:**
    - `test('preferences page displays current user preferences')`:
        ```php
        $user = User::factory()->create([
            'default_video_length' => 12,
            'default_speaking_pace' => 165,
            'default_script_iterations' => 3,
        ]);
        $response = $this->actingAs($user)->get(route('preferences.edit'));
        $response->assertInertia(fn ($page) => $page
            ->component('settings/preferences')
            ->where('defaultVideoLength', 12)
            ->where('defaultSpeakingPace', 165)
            ->where('defaultScriptIterations', 3)
        );
        ```
    - `test('preferences page displays default values for new users')`:
        ```php
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get(route('preferences.edit'));
        $response->assertInertia(fn ($page) => $page
            ->component('settings/preferences')
            ->where('defaultVideoLength', 10)
            ->where('defaultSpeakingPace', 150)
            ->where('defaultScriptIterations', 2)
        );
        ```

    **Update Tests:**
    - `test('preferences can be updated')`:
        ```php
        $user = User::factory()->create();
        $response = $this->actingAs($user)->patch(route('preferences.update'), [
            'default_video_length' => 15,
            'default_speaking_pace' => 170,
            'default_script_iterations' => 4,
        ]);
        $response->assertSessionHasNoErrors()->assertRedirect(route('preferences.edit'));
        $user->refresh();
        expect($user->default_video_length)->toBe(15);
        expect($user->default_speaking_pace)->toBe(170);
        expect($user->default_script_iterations)->toBe(4);
        ```
    - `test('preferences update rejects invalid video length')`:
        ```php
        $user = User::factory()->create();
        $response = $this->actingAs($user)->patch(route('preferences.update'), [
            'default_video_length' => 25,
            'default_speaking_pace' => 150,
            'default_script_iterations' => 2,
        ]);
        $response->assertSessionHasErrors('default_video_length');
        ```
    - `test('preferences update rejects video length below minimum')`:
        ```php
        $user = User::factory()->create();
        $response = $this->actingAs($user)->patch(route('preferences.update'), [
            'default_video_length' => 0,
            'default_speaking_pace' => 150,
            'default_script_iterations' => 2,
        ]);
        $response->assertSessionHasErrors('default_video_length');
        ```
    - `test('preferences update rejects invalid speaking pace')`:
        ```php
        $user = User::factory()->create();
        $response = $this->actingAs($user)->patch(route('preferences.update'), [
            'default_video_length' => 10,
            'default_speaking_pace' => 250,
            'default_script_iterations' => 2,
        ]);
        $response->assertSessionHasErrors('default_speaking_pace');
        ```
    - `test('preferences update rejects speaking pace below minimum')`:
        ```php
        $user = User::factory()->create();
        $response = $this->actingAs($user)->patch(route('preferences.update'), [
            'default_video_length' => 10,
            'default_speaking_pace' => 50,
            'default_script_iterations' => 2,
        ]);
        $response->assertSessionHasErrors('default_speaking_pace');
        ```
    - `test('preferences update rejects invalid script iterations')`:
        ```php
        $user = User::factory()->create();
        $response = $this->actingAs($user)->patch(route('preferences.update'), [
            'default_video_length' => 10,
            'default_speaking_pace' => 150,
            'default_script_iterations' => 6,
        ]);
        $response->assertSessionHasErrors('default_script_iterations');
        ```
    - `test('preferences update rejects negative script iterations')`:
        ```php
        $user = User::factory()->create();
        $response = $this->actingAs($user)->patch(route('preferences.update'), [
            'default_video_length' => 10,
            'default_speaking_pace' => 150,
            'default_script_iterations' => -1,
        ]);
        $response->assertSessionHasErrors('default_script_iterations');
        ```
    - `test('preferences update accepts boundary values')`:
        ```php
        $user = User::factory()->create();
        $response = $this->actingAs($user)->patch(route('preferences.update'), [
            'default_video_length' => 1,
            'default_speaking_pace' => 100,
            'default_script_iterations' => 0,
        ]);
        $response->assertSessionHasNoErrors()->assertRedirect(route('preferences.edit'));
        $user->refresh();
        expect($user->default_video_length)->toBe(1);
        expect($user->default_speaking_pace)->toBe(100);
        expect($user->default_script_iterations)->toBe(0);
        ```
    - `test('preferences update accepts maximum boundary values')`:
        ```php
        $user = User::factory()->create();
        $response = $this->actingAs($user)->patch(route('preferences.update'), [
            'default_video_length' => 20,
            'default_speaking_pace' => 200,
            'default_script_iterations' => 5,
        ]);
        $response->assertSessionHasNoErrors()->assertRedirect(route('preferences.edit'));
        $user->refresh();
        expect($user->default_video_length)->toBe(20);
        expect($user->default_speaking_pace)->toBe(200);
        expect($user->default_script_iterations)->toBe(5);
        ```
    - `test('guests cannot update preferences')`:
        ```php
        $response = $this->patch(route('preferences.update'), [
            'default_video_length' => 10,
            'default_speaking_pace' => 150,
            'default_script_iterations' => 2,
        ]);
        $response->assertRedirect(route('login'));
        ```

- Run the tests:
    ```bash
    php artisan test tests/Feature/Settings/PreferencesTest.php --compact
    ```
- Ensure all tests pass
- Run formatting:
    ```bash
    vendor/bin/pint --dirty
    ```

### 4. Write Browser Tests

- **Task ID**: write-browser-tests
- **Depends On**: create-frontend-page, write-preferences-tests
- **Assigned To**: preferences-browser-test-dev
- **Agent Type**: coder
- **Parallel**: false
- Create `tests/Browser/Settings/PreferencesTest.php`
- Write a smoke test for the preferences page:
    - Create and authenticate a verified user
    - Visit `/settings/preferences`
    - Assert the page loads successfully with no JavaScript errors
- Write a dark mode spot check:
    - Visit the preferences page, switch to dark color scheme using `colorScheme('dark')`, assert no JavaScript errors
- Write a preferences form submission flow test:
    - Create and authenticate a verified user
    - Visit the preferences page
    - Change the speaking pace value using `[data-test="speaking-pace-input"]`
    - Click the save button using `[data-test="update-preferences-button"]`
    - Assert the "Saved" confirmation message appears
    - Assert no JavaScript errors
- Use `data-test` selectors for all element interactions
- Ensure all browser tests use `assertNoJavaScriptErrors()`
- Run browser tests: `php artisan test tests/Browser/Settings/PreferencesTest.php --compact`

### 5. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-backend-foundation, create-frontend-page, write-preferences-tests, write-browser-tests
- **Assigned To**: preferences-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed below
- Verify preferences tests pass: `php artisan test tests/Feature/Settings/PreferencesTest.php --compact`
- Verify all settings tests pass: `php artisan test tests/Feature/Settings --compact`
- Run the full test suite to ensure no regressions: `php artisan test --compact`
- Run TypeScript type checking: `npm run types`
- Run ESLint: `npm run lint`
- Run Prettier formatting check: `npm run format`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Verify the migration exists and adds three columns (`default_video_length`, `default_speaking_pace`, `default_script_iterations`) to the `users` table
- Verify the User model has the new columns in `$fillable` and `casts()`
- Verify the UserFactory includes default values for the preference columns
- Verify the PreferencesController exists at `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/PreferencesController.php` with `edit()` and `update()` methods
- Verify the PreferencesUpdateRequest exists at `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/Settings/PreferencesUpdateRequest.php` with correct validation rules
- Verify the routes exist: `php artisan route:list --path=settings/preferences`
- Verify the route names are `preferences.edit` and `preferences.update`
- Verify the preferences page component exists at `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/preferences.tsx`
- Verify the settings sidebar layout includes the "Preferences" nav item
- Verify the preferences page has form fields for all three settings: video length, speaking pace, script iterations
- Verify the form submits via PATCH to the update route
- Verify validation error messages display correctly for each field
- Verify the success feedback ("Saved") transition works
- Run browser tests: `php artisan test tests/Browser/Settings/PreferencesTest.php --compact`
- Verify `data-test` attributes exist on interactive elements in the preferences page component
- Confirm all acceptance criteria are met

## Acceptance Criteria

- A database migration adds `default_video_length` (default 10), `default_speaking_pace` (default 150), and `default_script_iterations` (default 2) columns to the `users` table
- The preferences page renders successfully at `/settings/preferences` for authenticated, verified users
- The preferences page displays the user's current preference values in the form fields
- New users see sensible default values (10 min, 150 WPM, 2 iterations) without needing to configure anything
- Users can update their default video length (1-20 minutes) and the change persists
- Users can update their default speaking pace (100-200 WPM) and the change persists
- Users can update their default script iterations (0-5) and the change persists
- Validation rejects values outside the allowed ranges and shows clear error messages
- The form shows a "Saved" success message after successful update
- The settings sidebar includes a "Preferences" navigation item
- Guests are redirected to the login page when trying to access the preferences page
- Unverified users are redirected to the email verification notice
- The page follows the same layout pattern as other settings pages (AppLayout + SettingsLayout)
- The breadcrumb shows "Preferences"
- All preferences tests pass
- All existing tests pass without regressions
- TypeScript types compile without errors
- ESLint and Prettier checks pass
- PHP code passes Pint formatting
- All new pages have smoke tests (no JavaScript errors)
- Dark mode spot check passes for the preferences page
- Preferences form submission flow passes browser test using `data-test` selectors
- Interactive frontend elements have `data-test` attributes
- All browser tests pass

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run preferences page tests
php artisan test tests/Feature/Settings/PreferencesTest.php --compact

# Run all settings tests
php artisan test tests/Feature/Settings --compact

# Run full test suite for regression check
php artisan test --compact

# TypeScript type checking
npm run types

# ESLint linting
npm run lint

# Prettier formatting check
npm run format

# PHP code formatting
vendor/bin/pint --dirty

# Verify routes exist
php artisan route:list --path=settings/preferences

# Run browser tests
php artisan test tests/Browser/Settings/PreferencesTest.php --compact
```

## Notes

- The three preference fields are stored directly on the `users` table rather than in a separate `user_preferences` table. This is intentional for simplicity -- there are only three fields, they are tightly coupled to the user, and YAGNI principles apply. If preferences grow significantly in the future, they can be extracted to a separate table.
- Database-level defaults (`default(10)`, `default(150)`, `default(2)`) mean existing users gain sensible defaults automatically when the migration runs. No data backfill is needed.
- The columns are NOT nullable. Every user always has a preference value, simplifying both backend and frontend code (no null checks needed).
- The preferences page uses `['auth', 'verified']` middleware (unlike the account overview page which uses `['auth']`) because preferences relate to video creation, which requires email verification.
- The `useForm` approach is recommended over the `<Form>` component for this page because of the Radix Select components. The `<Form>` component relies on native form elements for data collection, but Radix Select does not render a native `<select>`. Using `useForm` with `setData` in `onValueChange` callbacks gives full control.
- Speaking pace uses an `Input` with `type="number"` for fine-grained control (100-200 range with many possible values), while video length (1-20) and script iterations (0-5) use `Select` dropdowns since they have fewer discrete options.
- The preference values will be consumed by future video creation features (E001-F017 Topic Input, E001-F021 Script Iteration Configuration, etc.) which will read the user's defaults to pre-fill configuration forms. This feature only handles storing and editing the defaults.
- All commands should be run inside the Docker container. Use `make shell` to enter the container, or prefix with `docker compose exec app`.

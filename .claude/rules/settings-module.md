---
load: conditional
paths:
    - app/Http/Controllers/Settings/**
    - app/Http/Requests/Settings/**
    - resources/js/pages/settings/**
    - resources/js/layouts/settings/**
    - routes/settings.php
    - tests/**/Settings/**
keywords:
    - settings
    - profile
    - password
    - appearance
    - preferences
description: Settings module architecture for multi-page feature modules
---

# Settings Module Architecture

The settings section demonstrates a pattern for building multi-page feature modules with shared layout and navigation.

## Route Structure

Settings routes are defined in `routes/settings.php` and included from `routes/web.php`:

```php
// routes/web.php
require __DIR__.'/settings.php';

// routes/settings.php
Route::middleware(['auth', 'verified'])->prefix('settings')->group(function () {
    Route::redirect('/', '/settings/profile');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('appearance', fn () => Inertia::render('settings/appearance'))->name('appearance');

    // Two-factor authentication routes...
});
```

## Controller Pattern

Each settings concern has its own controller:

```
app/Http/Controllers/Settings/
├── ProfileController.php
├── PasswordController.php
└── TwoFactorAuthenticationController.php
```

Controllers handle `edit` (show form) and `update` (process form) actions:

```php
class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        // Handle update...
        return to_route('profile.edit');
    }
}
```

## Frontend Page Structure

Settings pages are in `resources/js/pages/settings/`:

```
pages/settings/
├── profile.tsx
├── password.tsx
├── appearance.tsx
├── two-factor-authentication.tsx
└── delete-user.tsx
```

Each page uses a consistent layout with breadcrumbs:

```tsx
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

interface ProfileProps {
    mustVerifyEmail: boolean;
    status?: string;
}

export default function Profile({ mustVerifyEmail, status }: ProfileProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Settings', href: '/settings/profile' },
        { title: 'Profile' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <SettingsLayout>
                <ProfileForm
                    mustVerifyEmail={mustVerifyEmail}
                    status={status}
                />
            </SettingsLayout>
        </AppLayout>
    );
}
```

## Settings Layout

The `SettingsLayout` component provides consistent navigation:

```tsx
// layouts/settings/layout.tsx
export default function SettingsLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    return (
        <div className="flex flex-col gap-8 lg:flex-row">
            <aside className="lg:w-64">
                <SettingsNav />
            </aside>
            <main className="flex-1">{children}</main>
        </div>
    );
}
```

## Form Components

Settings forms use Inertia's `<Form>` component with Wayfinder actions:

```tsx
import { Form } from '@inertiajs/react';
import * as ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';

export function ProfileForm({ user }: ProfileFormProps) {
    return (
        <Form {...ProfileController.update.form()}>
            <Input name="name" defaultValue={user.name} />
            <Input name="email" defaultValue={user.email} />
            <Button type="submit">Save</Button>
        </Form>
    );
}
```

## Adding a New Settings Section

1. **Create Controller**:

    ```bash
    php artisan make:controller Settings/NotificationsController
    ```

2. **Create Form Request** (if needed):

    ```bash
    php artisan make:request Settings/NotificationsUpdateRequest
    ```

3. **Add Routes** in `routes/settings.php`:

    ```php
    Route::get('notifications', [NotificationsController::class, 'edit'])->name('notifications.edit');
    Route::patch('notifications', [NotificationsController::class, 'update'])->name('notifications.update');
    ```

4. **Create Page** at `resources/js/pages/settings/notifications.tsx`

5. **Update Navigation** in settings nav component

6. **Write Tests** in `tests/Feature/Settings/NotificationsTest.php`

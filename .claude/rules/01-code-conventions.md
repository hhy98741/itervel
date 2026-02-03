---
load: always
description: Code conventions for PHP, TypeScript, React, and Tailwind CSS
---

# Code Conventions

## Best Practices

### Clean Code Principles

- **Single Responsibility**: Each function should do one thing and do it well.
- **Keep functions short**: Max 25 lines; extract logic into helper functions.
- **Avoid deep nesting**: Use early returns to reduce indentation.
- **Write self-documenting code**: Use clear names and structure; comments should explain _why_, not _what_.

### DRY (Don’t Repeat Yourself)

- Extract repeated logic into reusable functions or utilities.
- If you copy-paste code more than twice, refactor it into a shared module.
- Use configuration files or constants instead of hardcoding values.

### YAGNI (You Aren’t Gonna Need It)

- Implement functionality only when it is actually needed, not when anticipated.
- Avoid adding features or code that may seem useful in the future but aren't required now.
- This reduces complexity, prevents over-engineering, and keeps the codebase lean.

### KISS (Keep It Simple, Stupid)

- Favor simple, clear solutions over complex ones.
- Write code that is easy to understand, maintain, and debug.
- Avoid unnecessary abstractions or clever tricks—simplicity improves reliability and collaboration.

### Code Refactoring

- Improve the internal structure of existing code without changing its external behavior.
- Regularly simplify logic, eliminate duplication, enhance naming, and break down large functions to increase readability and maintainability.

### Testing

- **Write unit tests** for all core logic using Pest.
- Test edge cases and error conditions.
- Run tests before committing.

## PHP Conventions

### Namespaces

Follow PSR-4 autoloading:

```php
namespace App\Http\Controllers\Settings;  // app/Http/Controllers/Settings/
namespace App\Concerns;                    // app/Concerns/
namespace App\Actions\Fortify;             // app/Actions/Fortify/
```

### Class Organization

1. Traits (`use` statements)
2. Properties (constants, static, instance)
3. Constructor
4. Public methods
5. Protected methods
6. Private methods

### Method Signatures

Always include return types and parameter types:

```php
public function update(ProfileUpdateRequest $request): RedirectResponse
{
    // ...
}

protected function profileRules(): array
{
    // ...
}
```

### Validation Rules

Use array syntax for validation rules:

```php
// Correct
'email' => ['required', 'string', 'email', Rule::unique('users')->ignore($id)]

// Avoid pipe syntax
'email' => 'required|string|email|unique:users'
```

## TypeScript/React Conventions

### File Naming

- Components: `kebab-case.tsx` (e.g., `app-layout.tsx`, `two-factor-form.tsx`)
- Hooks: `use-*.ts` (e.g., `use-appearance.ts`, `use-mobile.ts`)
- Types: `*.d.ts` or in `types/` folder
- Pages: Match Laravel route structure (e.g., `settings/profile.tsx`)

### Component Exports

Use named function exports for page components:

```tsx
// pages/dashboard.tsx
export default function Dashboard({ breadcrumbs }: DashboardProps) {
    return <AppLayout breadcrumbs={breadcrumbs}>{/* ... */}</AppLayout>;
}
```

### Interface Naming

Props interfaces use `ComponentNameProps` pattern:

```tsx
interface DashboardProps {
    breadcrumbs: BreadcrumbItem[];
}

interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: 'default' | 'destructive';
}
```

### Imports Order

1. React and framework imports
2. Third-party libraries
3. Internal aliases (`@/components/`, `@/hooks/`, etc.)
4. Relative imports
5. Type imports (using `type` keyword)

```tsx
import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';
import { type User } from '@/types';
```

### Form Handling with Wayfinder

Use Wayfinder-generated actions with Inertia forms:

```tsx
import { Form } from '@inertiajs/react';
import * as ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';

<Form {...ProfileController.update.form()}>{/* form fields */}</Form>;
```

Or with `useForm`:

```tsx
const { data, setData, submit } = useForm({ name: '', email: '' });

const handleSubmit = (e: FormEvent) => {
    e.preventDefault();
    submit(ProfileController.update());
};
```

## Tailwind CSS Conventions

### Class Organization

Order classes logically:

1. Layout (display, position)
2. Sizing (width, height)
3. Spacing (margin, padding)
4. Typography (font, text)
5. Colors (background, text color)
6. Effects (shadow, transition)
7. State variants (hover, focus, dark)

```tsx
<div className="flex w-full items-center justify-between bg-white p-4 text-sm text-gray-600 shadow-sm hover:bg-gray-50 dark:bg-gray-800" />
```

### Using `cn()` Utility

For conditional classes:

```tsx
import { cn } from '@/lib/utils';

<button
    className={cn(
        'rounded-md px-4 py-2',
        variant === 'primary' && 'bg-blue-500 text-white',
        variant === 'secondary' && 'bg-gray-200 text-gray-800',
        disabled && 'cursor-not-allowed opacity-50',
        className,
    )}
/>;
```

## Route Naming

Use dot notation for nested routes:

```php
Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
Route::delete('profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
```

## Testing Conventions

### Test Naming

Use descriptive test names:

```php
test('profile page is displayed', function () { });
test('profile information can be updated', function () { });
test('email verification status is unchanged when email is unchanged', function () { });
```

### Test Structure

Follow Arrange-Act-Assert pattern:

```php
test('user can update profile', function () {
    // Arrange
    $user = User::factory()->create();

    // Act
    $response = $this->actingAs($user)
        ->patch('/settings/profile', [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);

    // Assert
    $response->assertRedirect('/settings/profile');
    $user->refresh();
    expect($user->name)->toBe('New Name');
});
```

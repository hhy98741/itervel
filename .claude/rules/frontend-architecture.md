---
load: conditional
paths:
    - resources/js/**
    - '*.tsx'
    - '*.ts'
keywords:
    - react
    - typescript
    - frontend
    - component
    - hook
    - layout
    - inertia
    - page
description: React component patterns, hooks, layouts, and TypeScript types
---

# Frontend Architecture

## Component Patterns

### UI Components (`components/ui/`)

Base UI components follow a consistent pattern:

```tsx
import { cn } from '@/lib/utils';

interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: 'default' | 'destructive' | 'outline' | 'ghost';
    size?: 'default' | 'sm' | 'lg';
}

function Button({
    className,
    variant = 'default',
    size = 'default',
    ...props
}: ButtonProps) {
    return (
        <button
            className={cn(buttonVariants({ variant, size }), className)}
            {...props}
        />
    );
}
```

Key conventions:

- Extend native HTML element attributes
- Use `cn()` utility for class merging
- Provide sensible defaults for variants
- Export as named function (not arrow function default export)

### Feature Components (`components/`)

Feature components compose UI components:

```tsx
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export function SearchForm({ onSubmit }: SearchFormProps) {
    // Component logic
}
```

### Page Components (`pages/`)

Inertia pages receive props from Laravel controllers:

```tsx
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

interface DashboardProps {
    breadcrumbs: BreadcrumbItem[];
}

export default function Dashboard({ breadcrumbs }: DashboardProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>{/* Page content */}</AppLayout>
    );
}
```

## Layout System

### AppLayout

Used for authenticated pages. Supports variants:

```tsx
<AppLayout breadcrumbs={breadcrumbs} variant="sidebar">
    {children}
</AppLayout>
```

Variants: `sidebar` (default), `header`

### AuthLayout

Used for public authentication pages:

```tsx
<AuthLayout title="Login" description="Enter your credentials">
    {children}
</AuthLayout>
```

## Custom Hooks

### `useAppearance()`

Theme management hook:

```tsx
const { appearance, updateAppearance } = useAppearance();
// appearance: 'light' | 'dark' | 'system'
updateAppearance('dark');
```

### `useMobile()`

Responsive design detection:

```tsx
const isMobile = useMobile();
```

### `useCurrentUrl()`

Get current Inertia URL:

```tsx
const currentUrl = useCurrentUrl();
```

### `useInitials()`

Extract user initials:

```tsx
const initials = useInitials(user);
```

### `useClipboard()`

Copy to clipboard functionality:

```tsx
const { copy, copied } = useClipboard();
await copy(text);
```

### `useTwoFactorAuth()`

Two-factor authentication state:

```tsx
const { isEnabled, isConfirmed, qrCode, recoveryCodes } = useTwoFactorAuth();
```

## Type Definitions

### SharedData

Props shared across all Inertia pages (from `HandleInertiaRequests` middleware):

```tsx
interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: { user: User };
    appearance: 'light' | 'dark' | 'system';
    sidebarOpen: boolean;
}
```

Access in components:

```tsx
import { usePage } from '@inertiajs/react';
import { type SharedData } from '@/types';

const { auth } = usePage<SharedData>().props;
```

## Path Aliases

Import paths use the `@/` alias pointing to `resources/js/`:

```tsx
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';
import { type User } from '@/types';
```

## Class Name Utility

Use `cn()` from `@/lib/utils` for conditional classes:

```tsx
import { cn } from '@/lib/utils';

<div className={cn('base-class', isActive && 'active-class', className)} />;
```

The `cn()` function combines `clsx` and `tailwind-merge` for proper Tailwind class handling.

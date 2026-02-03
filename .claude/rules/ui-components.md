---
load: conditional
paths:
    - resources/js/components/ui/**
    - resources/js/components/*.tsx
keywords:
    - component
    - button
    - input
    - dialog
    - modal
    - form
    - ui
    - radix
    - shadcn
description: Radix UI-based component library with Tailwind CSS styling
---

# UI Components

This project uses a component library inspired by shadcn/ui, built with Radix UI primitives and styled with Tailwind CSS.

## Component Location

UI components live in `resources/js/components/ui/`:

```
components/ui/
├── alert-dialog.tsx
├── button.tsx
├── checkbox.tsx
├── dialog.tsx
├── dropdown-menu.tsx
├── input.tsx
├── input-otp.tsx
├── label.tsx
├── navigation-menu.tsx
├── scroll-area.tsx
├── select.tsx
├── separator.tsx
├── sheet.tsx
├── sidebar.tsx
├── skeleton.tsx
├── switch.tsx
├── textarea.tsx
├── tooltip.tsx
└── ...
```

## Component Patterns

### Basic Component Structure

```tsx
import * as React from 'react';
import { cn } from '@/lib/utils';

interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: 'default' | 'destructive' | 'outline' | 'ghost';
    size?: 'default' | 'sm' | 'lg' | 'icon';
}

const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
    ({ className, variant = 'default', size = 'default', ...props }, ref) => {
        return (
            <button
                className={cn(buttonVariants({ variant, size }), className)}
                ref={ref}
                {...props}
            />
        );
    },
);
Button.displayName = 'Button';

export { Button };
```

### Using Radix UI Primitives

```tsx
import * as DialogPrimitive from '@radix-ui/react-dialog';
import { cn } from '@/lib/utils';

const Dialog = DialogPrimitive.Root;
const DialogTrigger = DialogPrimitive.Trigger;

const DialogContent = React.forwardRef<
    React.ElementRef<typeof DialogPrimitive.Content>,
    React.ComponentPropsWithoutRef<typeof DialogPrimitive.Content>
>(({ className, children, ...props }, ref) => (
    <DialogPrimitive.Portal>
        <DialogPrimitive.Overlay className="fixed inset-0 bg-black/50" />
        <DialogPrimitive.Content
            ref={ref}
            className={cn(
                'fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 rounded-lg bg-white p-6',
                className,
            )}
            {...props}
        >
            {children}
        </DialogPrimitive.Content>
    </DialogPrimitive.Portal>
));

export { Dialog, DialogTrigger, DialogContent };
```

## Available Components

### Form Components

| Component  | Usage                   |
| ---------- | ----------------------- |
| `Input`    | Text input field        |
| `Textarea` | Multi-line text input   |
| `Select`   | Dropdown select         |
| `Checkbox` | Checkbox input          |
| `Switch`   | Toggle switch           |
| `Label`    | Form label              |
| `InputOtp` | OTP input for 2FA codes |

### Layout Components

| Component        | Usage                      |
| ---------------- | -------------------------- |
| `Separator`      | Visual divider             |
| `ScrollArea`     | Custom scrollbar container |
| `Sidebar`        | Application sidebar        |
| `NavigationMenu` | Navigation with dropdowns  |

### Overlay Components

| Component      | Usage               |
| -------------- | ------------------- |
| `Dialog`       | Modal dialog        |
| `AlertDialog`  | Confirmation dialog |
| `Sheet`        | Slide-out panel     |
| `DropdownMenu` | Contextual menu     |
| `Tooltip`      | Hover tooltip       |

### Feedback Components

| Component  | Usage               |
| ---------- | ------------------- |
| `Skeleton` | Loading placeholder |

## Usage Examples

### Button Variants

```tsx
import { Button } from '@/components/ui/button';

<Button>Default</Button>
<Button variant="destructive">Delete</Button>
<Button variant="outline">Cancel</Button>
<Button variant="ghost">Link</Button>
<Button size="sm">Small</Button>
<Button size="lg">Large</Button>
<Button size="icon"><Icon /></Button>
```

### Dialog

```tsx
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

<Dialog>
    <DialogTrigger asChild>
        <Button>Open Dialog</Button>
    </DialogTrigger>
    <DialogContent>
        <DialogHeader>
            <DialogTitle>Dialog Title</DialogTitle>
            <DialogDescription>Dialog description text.</DialogDescription>
        </DialogHeader>
        {/* Dialog content */}
    </DialogContent>
</Dialog>;
```

### Form with Label and Input

```tsx
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';

<div className="space-y-2">
    <Label htmlFor="email">Email</Label>
    <Input
        id="email"
        type="email"
        name="email"
        placeholder="email@example.com"
    />
</div>;
```

### OTP Input

```tsx
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';

<InputOTP maxLength={6}>
    <InputOTPGroup>
        <InputOTPSlot index={0} />
        <InputOTPSlot index={1} />
        <InputOTPSlot index={2} />
        <InputOTPSlot index={3} />
        <InputOTPSlot index={4} />
        <InputOTPSlot index={5} />
    </InputOTPGroup>
</InputOTP>;
```

## Adding New Components

1. Create file in `components/ui/` following naming convention
2. Use Radix UI primitives when possible for accessibility
3. Style with Tailwind classes
4. Use `cn()` utility for class merging
5. Export named components (not default export)
6. Add TypeScript props interface extending native element attributes

## Icons

This project uses Lucide React for icons:

```tsx
import { Settings, User, LogOut } from 'lucide-react';

<Settings className="h-4 w-4" />
<User className="h-5 w-5 text-gray-500" />
```

Find icons at: https://lucide.dev/icons

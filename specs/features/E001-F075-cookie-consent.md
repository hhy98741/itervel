# Feature: Cookie Consent

**Epic**: E001-ai-powered-faceless-video-creation-platform.md
**Feature**: E001-F075
**Dependencies**: none

## Task Description

The Cookie Consent feature displays a GDPR-compliant cookie consent banner to visitors. EU regulations require websites to inform users about cookie usage and obtain consent before setting non-essential cookies.

**What it does**: Displays a cookie consent banner for EU users.

**Expected outcome**: Visitors from the EU see a banner informing them about cookie usage and can accept or configure their preferences.

This feature is implemented as a client-side React component that renders globally across all pages. The consent state is persisted via a browser cookie (which is itself exempt from the consent requirement since it is strictly necessary for remembering the user's preference). The banner appears at the bottom of the viewport on first visit and disappears once the user makes a choice. A preferences dialog allows granular control over cookie categories (essential, analytics, marketing). The backend receives the consent state via a cookie that the `HandleInertiaRequests` middleware can share with all pages so components can conditionally load tracking scripts.

The existing codebase already uses a cookie-based pattern for user preferences -- the `appearance` cookie (managed via `use-appearance.tsx` and read by `HandleAppearance` middleware) and the `sidebar_state` cookie (read in `HandleInertiaRequests`). The cookie consent feature follows the same pattern: a client-side cookie set via `document.cookie`, excluded from encryption in `bootstrap/app.php`, and shared via Inertia for SSR awareness.

## Objective

Implement a cookie consent banner component that appears on every page for users who have not yet made a consent choice. The banner must offer "Accept All", "Reject All", and "Manage Preferences" options. A preferences dialog allows toggling individual cookie categories (essential cookies are always on and cannot be disabled). The user's choice is persisted in a `cookie_consent` cookie and shared to all Inertia pages via the `HandleInertiaRequests` middleware so that other features (analytics, marketing pixels) can check consent status before loading.

## Solution Approach

### Architecture Overview

This is primarily a frontend feature with minimal backend changes. The approach is:

1. **React component** (`cookie-consent-banner.tsx`) -- renders a fixed-position banner at the bottom of the screen
2. **React component** (`cookie-consent-dialog.tsx`) -- a modal dialog for managing individual category preferences
3. **React hook** (`use-cookie-consent.ts`) -- encapsulates all consent state logic, cookie reading/writing, and provides a reactive API
4. **Middleware update** -- add `cookie_consent` to the unencrypted cookies list and share the consent value via Inertia
5. **App entry point update** -- render the banner globally so it appears on all pages (both authenticated and guest pages)

### Cookie Structure

The consent is stored as a JSON-encoded cookie named `cookie_consent`:

```json
{
    "essential": true,
    "analytics": false,
    "marketing": false,
    "timestamp": "2026-02-11T12:00:00.000Z"
}
```

- `essential` is always `true` and cannot be toggled off
- `analytics` controls performance/analytics cookies (e.g., Google Analytics)
- `marketing` controls advertising/marketing cookies
- `timestamp` records when consent was given (for audit purposes)
- Cookie max-age: 365 days (standard for consent cookies)
- Cookie path: `/`
- SameSite: `Lax`

### Frontend Implementation

**Hook: `use-cookie-consent.ts`**
Follows the same pattern as `use-appearance.tsx` -- uses `useSyncExternalStore` for reactive state management and `document.cookie` for persistence. Provides:

- `consentState: CookieConsentState | null` -- null means no consent has been given yet (show banner)
- `hasConsented: boolean` -- whether any consent decision has been made
- `acceptAll(): void` -- sets all categories to true
- `rejectAll(): void` -- sets only essential to true, all others false
- `updatePreferences(prefs: Partial<CookieConsentPreferences>): void` -- granular update
- `resetConsent(): void` -- clears consent cookie (re-shows banner)

**Banner Component: `cookie-consent-banner.tsx`**

- Fixed position at bottom of viewport (`fixed bottom-0 inset-x-0 z-50`)
- Shown only when `hasConsented` is false
- Contains: brief text explaining cookie usage, three buttons ("Accept All", "Reject All", "Manage Preferences")
- Slides up with a CSS animation on mount
- Uses existing UI components: `Button` (primary for Accept, outline for Reject and Manage), `Card` for the banner container
- Responsive: stacks buttons vertically on mobile, horizontally on desktop

**Preferences Dialog: `cookie-consent-dialog.tsx`**

- Uses the existing `Dialog`, `DialogContent`, `DialogHeader`, `DialogTitle`, `DialogDescription`, `DialogFooter` from `@/components/ui/dialog`
- Lists cookie categories with descriptions and toggle controls
- Essential cookies toggle is always on and disabled
- Uses the existing `Checkbox` component from `@/components/ui/checkbox` for toggles (following existing pattern since there is no Switch component)
- "Save Preferences" button applies selections
- Categories:
    - **Essential** (always on, disabled) -- "Required for the website to function. Cannot be disabled."
    - **Analytics** -- "Help us understand how visitors interact with our website."
    - **Marketing** -- "Used to deliver relevant advertisements and track campaign effectiveness."

**Global Rendering:**
The banner component is rendered in `resources/js/app.tsx` inside the `setup()` function, wrapping the `<App>` component. This ensures it appears on every page regardless of layout. The banner renders outside of Inertia's page component tree but within React's StrictMode.

```tsx
root.render(
    <StrictMode>
        <App {...props} />
        <CookieConsentBanner />
    </StrictMode>,
);
```

### Backend Implementation

**`bootstrap/app.php`** -- Add `cookie_consent` to the `encryptCookies(except:)` list so the cookie is readable client-side:

```php
$middleware->encryptCookies(except: ['appearance', 'sidebar_state', 'cookie_consent']);
```

**`HandleInertiaRequests.php`** -- Share consent state to all pages so server-rendered components or scripts can check consent:

```php
'cookieConsent' => $request->cookie('cookie_consent')
    ? json_decode($request->cookie('cookie_consent'), true)
    : null,
```

**Types update** -- Add `cookieConsent` to the `SharedData` TypeScript type so it is available in `usePage<SharedData>().props`.

### No GeoIP Detection

The feature description mentions "EU users" but implementing server-side GeoIP detection adds significant complexity and an external dependency. The standard GDPR-compliant approach used by most websites is to show the cookie consent banner to ALL visitors globally. This is simpler, more privacy-friendly, and avoids false negatives (EU users on VPNs, travelers, etc.). The banner is non-intrusive and does not block page content, so showing it to all visitors is acceptable.

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/resources/js/app.tsx` -- App entry point where the cookie consent banner will be rendered globally alongside the Inertia `<App>` component.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/hooks/use-appearance.tsx` -- Reference implementation for the cookie-based reactive state pattern using `useSyncExternalStore`, `document.cookie`, and module-level state. The `use-cookie-consent.ts` hook should follow the same structural pattern.
- `/Users/young/Nextcloud/dev/Itervel/bootstrap/app.php` -- Middleware configuration where `cookie_consent` must be added to the `encryptCookies(except:)` list to allow client-side cookie access.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Middleware/HandleInertiaRequests.php` -- Shares data to all Inertia pages. Must be updated to share `cookieConsent` parsed from the `cookie_consent` cookie.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/index.ts` -- `SharedData` type definition. Must be extended with `cookieConsent` property.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/button.tsx` -- Existing Button component with variants (`default`, `outline`, `secondary`, `ghost`). Reuse for banner action buttons.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/dialog.tsx` -- Existing Dialog components (Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter). Reuse for the preferences dialog.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/checkbox.tsx` -- Existing Checkbox component. Reuse for category toggles in the preferences dialog.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/card.tsx` -- Existing Card component. Can be used for the banner container styling.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/separator.tsx` -- Existing Separator component for visual dividers between categories.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/label.tsx` -- Existing Label component for checkbox labels.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/lib/utils.ts` -- Contains the `cn()` utility for conditional class merging.
- `/Users/young/Nextcloud/dev/Itervel/resources/css/app.css` -- Tailwind CSS configuration with theme variables for light and dark mode.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest test configuration. Feature tests use `RefreshDatabase`.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/ExampleTest.php` -- Existing home route test. Reference for test patterns.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/resources/js/hooks/use-cookie-consent.ts` -- Custom React hook encapsulating cookie consent state management. Uses `useSyncExternalStore` for reactive state, reads/writes the `cookie_consent` cookie, and exports `CookieConsentState` type, `useCookieConsent()` hook, and `initializeCookieConsent()` function.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/cookie-consent-banner.tsx` -- The main cookie consent banner component. Fixed-position bottom bar with accept/reject/manage buttons. Only renders when no consent cookie exists.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/cookie-consent-dialog.tsx` -- Preferences dialog component using the existing Dialog UI components. Lists cookie categories with checkboxes and descriptions.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/CookieConsentTest.php` -- Pest feature tests for the cookie consent backend behavior (middleware sharing, cookie parsing).

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: consent-backend-dev
    - Role: Updates bootstrap/app.php middleware config, HandleInertiaRequests middleware, and SharedData TypeScript types to support the cookie consent cookie
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: consent-frontend-dev
    - Role: Creates the use-cookie-consent hook, cookie-consent-banner component, cookie-consent-dialog component, and integrates the banner into app.tsx
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: consent-test-dev
    - Role: Writes Pest feature tests for cookie consent backend behavior and validates the complete implementation
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: consent-reviewer
    - Role: Validates the complete cookie consent implementation against acceptance criteria, runs all tests, type checks, linting, and formatting
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

### 1. Update Backend to Support Cookie Consent

- **Task ID**: update-backend-consent
- **Depends On**: none
- **Assigned To**: consent-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- In `/Users/young/Nextcloud/dev/Itervel/bootstrap/app.php`, add `'cookie_consent'` to the `encryptCookies(except:)` array so the line reads: `$middleware->encryptCookies(except: ['appearance', 'sidebar_state', 'cookie_consent']);`
- In `/Users/young/Nextcloud/dev/Itervel/app/Http/Middleware/HandleInertiaRequests.php`, add a `cookieConsent` key to the `share()` method's return array. Parse the `cookie_consent` cookie as JSON: `'cookieConsent' => $request->cookie('cookie_consent') ? json_decode($request->cookie('cookie_consent'), true) : null,`
- In `/Users/young/Nextcloud/dev/Itervel/resources/js/types/index.ts`, add `cookieConsent` to the `SharedData` type. Define a `CookieConsentPreferences` type inline or in a separate type file: `cookieConsent: { essential: boolean; analytics: boolean; marketing: boolean; timestamp: string } | null;`. Update the `SharedData` type to include this field (replace the `[key: string]: unknown` index signature approach or add the explicit property above it).
- Run `vendor/bin/pint --dirty` to format PHP changes
- Run `npm run types` to verify TypeScript compiles

### 2. Create Cookie Consent Hook

- **Task ID**: create-consent-hook
- **Depends On**: update-backend-consent
- **Assigned To**: consent-frontend-dev
- **Agent Type**: coder
- **Parallel**: false
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/hooks/use-cookie-consent.ts`
- Follow the exact same pattern as `/Users/young/Nextcloud/dev/Itervel/resources/js/hooks/use-appearance.tsx` -- module-level state, `useSyncExternalStore`, listeners set, subscribe/notify functions
- Define TypeScript types at the top of the file:

    ```typescript
    export type CookieConsentPreferences = {
        essential: boolean;
        analytics: boolean;
        marketing: boolean;
    };

    export type CookieConsentState = CookieConsentPreferences & {
        timestamp: string;
    };

    export type UseCookieConsentReturn = {
        readonly consentState: CookieConsentState | null;
        readonly hasConsented: boolean;
        readonly acceptAll: () => void;
        readonly rejectAll: () => void;
        readonly updatePreferences: (
            prefs: Partial<CookieConsentPreferences>,
        ) => void;
        readonly resetConsent: () => void;
    };
    ```

- Implement `setCookie(name, value, days)` helper (same as in use-appearance.tsx) to write the `cookie_consent` cookie with `path=/;max-age=...;SameSite=Lax`
- Implement `getStoredConsent(): CookieConsentState | null` -- reads the `cookie_consent` cookie from `document.cookie`, parses JSON, returns null if not found or invalid
- Implement `saveConsent(state: CookieConsentState): void` -- serializes to JSON, writes cookie, updates module-level state, calls notify
- Implement `acceptAll()` -- calls `saveConsent` with all categories true and current timestamp
- Implement `rejectAll()` -- calls `saveConsent` with only essential true, analytics and marketing false
- Implement `updatePreferences(prefs)` -- merges partial preferences with current state (defaulting to reject for unset categories), saves
- Implement `resetConsent()` -- deletes the cookie by setting max-age=0, sets module state to null, notifies
- Export `initializeCookieConsent()` function that reads the stored consent on app startup (called from app.tsx)
- Export `useCookieConsent()` hook that returns `UseCookieConsentReturn`
- The cookie name must be `cookie_consent` to match the backend configuration

### 3. Create Cookie Consent Banner and Dialog Components

- **Task ID**: create-consent-components
- **Depends On**: create-consent-hook
- **Assigned To**: consent-frontend-dev
- **Agent Type**: coder
- **Parallel**: false
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/components/cookie-consent-banner.tsx`:
    - Import `useCookieConsent` from `@/hooks/use-cookie-consent`
    - Import `Button` from `@/components/ui/button`
    - Import `useState` from React for controlling the preferences dialog visibility
    - The component renders only when `hasConsented` is false (return `null` otherwise)
    - Render a `<div>` with classes: `fixed bottom-0 inset-x-0 z-50 p-4 sm:p-6` with a subtle slide-up animation
    - Inside, render a container div with `mx-auto max-w-4xl rounded-lg border bg-background p-4 shadow-lg sm:p-6` styling
    - Content: a `<p>` with text explaining cookie usage: "We use cookies to enhance your browsing experience, analyze site traffic, and personalize content. You can choose which cookies you allow."
    - Buttons row with `flex flex-col gap-2 sm:flex-row sm:justify-end` layout:
        - "Manage Preferences" -- `Button` variant `outline`, opens the `CookieConsentDialog`
        - "Reject All" -- `Button` variant `outline`, calls `rejectAll()`
        - "Accept All" -- `Button` variant `default`, calls `acceptAll()`
    - Render `<CookieConsentDialog open={dialogOpen} onOpenChange={setDialogOpen} />` conditionally
    - Support dark mode through Tailwind theme variables (`bg-background`, `text-foreground`, `border-border`)
    - Export as default named function: `export default function CookieConsentBanner()`

- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/components/cookie-consent-dialog.tsx`:
    - Import Dialog components from `@/components/ui/dialog`
    - Import `Checkbox` from `@/components/ui/checkbox`
    - Import `Label` from `@/components/ui/label`
    - Import `Button` from `@/components/ui/button`
    - Import `Separator` from `@/components/ui/separator`
    - Import `useCookieConsent` from `@/hooks/use-cookie-consent`
    - Props: `{ open: boolean; onOpenChange: (open: boolean) => void }`
    - Use local state for checkbox values (initialized from current consent state or defaults)
    - Render three category rows, each with a Checkbox, label, and description:
        - **Essential Cookies** -- checkbox always checked, disabled. Description: "Required for the website to function properly. These cookies ensure basic functionalities and security features."
        - **Analytics Cookies** -- toggleable. Description: "Help us understand how visitors interact with our website by collecting and reporting information anonymously."
        - **Marketing Cookies** -- toggleable. Description: "Used to deliver relevant advertisements and track campaign effectiveness across websites."
    - Separate categories with `<Separator />` components
    - Footer with "Cancel" (outline, closes dialog) and "Save Preferences" (default, calls `updatePreferences` with current selections and closes dialog)
    - Export as named function: `export function CookieConsentDialog({ open, onOpenChange }: CookieConsentDialogProps)`

### 4. Integrate Banner into App Entry Point

- **Task ID**: integrate-banner-app
- **Depends On**: create-consent-components
- **Assigned To**: consent-frontend-dev
- **Agent Type**: coder
- **Parallel**: false
- Update `/Users/young/Nextcloud/dev/Itervel/resources/js/app.tsx`:
    - Import `CookieConsentBanner` from `@/components/cookie-consent-banner`
    - Import `initializeCookieConsent` from `@/hooks/use-cookie-consent`
    - In the `setup()` function, add `<CookieConsentBanner />` after the `<App {...props} />` component inside `<StrictMode>`:
        ```tsx
        root.render(
            <StrictMode>
                <App {...props} />
                <CookieConsentBanner />
            </StrictMode>,
        );
        ```
    - Call `initializeCookieConsent()` alongside the existing `initializeTheme()` call at the bottom of the file
- Run `npm run types` to verify TypeScript compiles
- Run `npm run lint` to check for linting issues
- Run `npm run format` to ensure Prettier formatting

### 5. Write Feature Tests

- **Task ID**: write-consent-tests
- **Depends On**: integrate-banner-app
- **Assigned To**: consent-test-dev
- **Agent Type**: coder
- **Parallel**: false
- Create `/Users/young/Nextcloud/dev/Itervel/tests/Feature/CookieConsentTest.php` using `php artisan make:test CookieConsentTest --pest --no-interaction`
- Write the following Pest tests:
    - `test('cookie consent is null when no consent cookie is set')` -- GET `/`, assert Inertia page has `cookieConsent` prop set to null
    - `test('cookie consent preferences are shared when cookie is set')` -- Set a `cookie_consent` cookie with JSON `{"essential":true,"analytics":true,"marketing":false,"timestamp":"2026-01-01T00:00:00.000Z"}`, GET `/`, assert Inertia page has `cookieConsent` prop with matching values
    - `test('cookie consent is null when cookie contains invalid json')` -- Set `cookie_consent` cookie to `invalid-json`, GET `/`, assert `cookieConsent` prop is null (graceful handling)
    - `test('home page loads successfully without consent cookie')` -- GET `/`, assert 200 status
    - `test('dashboard page shares cookie consent when authenticated')` -- Create user, set consent cookie, acting as user GET `/dashboard`, assert Inertia page includes `cookieConsent` prop
- Use `$this->withUnencryptedCookies(['cookie_consent' => '...'])` to set the unencrypted cookie in tests (since it is in the `except` list)
- Follow existing test patterns from `/Users/young/Nextcloud/dev/Itervel/tests/Feature/ExampleTest.php` and tests in the `tests/Feature/Auth/` directory
- Use `$response->assertInertia(fn ($page) => $page->where('cookieConsent', ...))` for Inertia prop assertions
- Run `php artisan test tests/Feature/CookieConsentTest.php --compact` to verify all tests pass
- Run `vendor/bin/pint --dirty` to format the test file

### 6. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: update-backend-consent, create-consent-hook, create-consent-components, integrate-banner-app, write-consent-tests
- **Assigned To**: consent-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed below
- Verify the cookie consent test file passes: `php artisan test tests/Feature/CookieConsentTest.php --compact`
- Run the full test suite to ensure no regressions: `php artisan test --compact`
- Run TypeScript type checking: `npm run types`
- Run ESLint: `npm run lint`
- Run Prettier formatting check: `npm run format:check`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Verify the new files exist:
    - `resources/js/hooks/use-cookie-consent.ts`
    - `resources/js/components/cookie-consent-banner.tsx`
    - `resources/js/components/cookie-consent-dialog.tsx`
    - `tests/Feature/CookieConsentTest.php`
- Verify `bootstrap/app.php` includes `cookie_consent` in the `encryptCookies(except:)` list
- Verify `HandleInertiaRequests.php` shares `cookieConsent` in the `share()` method
- Verify `resources/js/types/index.ts` includes the `cookieConsent` property in `SharedData`
- Verify `resources/js/app.tsx` renders `<CookieConsentBanner />` and calls `initializeCookieConsent()`
- Verify the banner component uses existing UI components (Button, Dialog, Checkbox) rather than reimplementing them
- Verify dark mode support through Tailwind theme variables
- Verify responsive design with appropriate breakpoint classes
- Confirm all acceptance criteria are met

## Acceptance Criteria

- The cookie consent banner appears on every page when no `cookie_consent` cookie exists
- The banner does NOT appear when a valid `cookie_consent` cookie already exists
- Clicking "Accept All" sets all cookie categories to true and hides the banner
- Clicking "Reject All" sets only essential cookies to true (analytics and marketing false) and hides the banner
- Clicking "Manage Preferences" opens a dialog with individual category toggles
- The Essential cookies checkbox is always checked and disabled (cannot be toggled off)
- Analytics and Marketing categories can be individually toggled in the preferences dialog
- Clicking "Save Preferences" in the dialog saves the selections, sets the cookie, and hides the banner
- The consent state is persisted in a `cookie_consent` cookie with a 365-day max-age
- The `cookie_consent` cookie is excluded from Laravel's cookie encryption
- The `HandleInertiaRequests` middleware shares `cookieConsent` data to all Inertia pages
- The `SharedData` TypeScript type includes the `cookieConsent` property
- The banner renders correctly in both light and dark mode
- The banner is responsive (stacked buttons on mobile, inline on desktop)
- All Pest feature tests pass
- TypeScript type checking passes (`npm run types`)
- ESLint passes (`npm run lint`)
- The existing test suite has no regressions (`php artisan test --compact`)
- PHP formatting passes (`vendor/bin/pint --dirty`)

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run cookie consent tests
php artisan test tests/Feature/CookieConsentTest.php --compact

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

- The cookie consent banner is shown to ALL visitors, not just EU users. This is the standard industry approach because implementing GeoIP detection adds complexity, an external dependency, and can produce false negatives (EU citizens using VPNs, traveling, etc.). Showing the banner globally is the safer and simpler compliance strategy.
- The `cookie_consent` cookie itself is classified as a "strictly necessary" cookie under GDPR because it records the user's consent preference. It does not require prior consent to be set.
- The consent state includes a `timestamp` field for audit trail purposes -- GDPR requires being able to demonstrate when consent was obtained.
- The `initializeCookieConsent()` function is called alongside `initializeTheme()` in `app.tsx` to ensure the consent state is loaded from the cookie on initial page load before any React component renders.
- No new npm dependencies are needed. The implementation uses existing Radix UI Dialog, Checkbox, and Button components.
- The banner uses `z-50` to match the z-index of the existing Dialog overlay, ensuring it appears above page content but below any open dialogs.
- Future features that need to check consent (e.g., analytics integration, marketing pixels) can use either the `useCookieConsent()` hook on the client side or the `cookieConsent` shared prop from the server side.
- The `HandleInertiaRequests` middleware uses `json_decode` with a fallback to `null` for invalid JSON to handle edge cases gracefully (corrupted cookie, manual tampering).
- All commands should be run inside the Docker container. Use `make shell` to enter the container, or prefix with `docker compose exec app`.

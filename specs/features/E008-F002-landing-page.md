# Feature: Landing Page

**Epic**: E008-free-tier-and-onboarding.md
**Feature**: E008-F002
**Dependencies**: none

## Task Description

The Landing Page is the public-facing entry point to the Itervel platform. It replaces the current default Laravel welcome page (`resources/js/pages/welcome.tsx`) with a product-focused marketing page that communicates Itervel's value proposition to potential users and drives them toward registration.

**What it does**: Presents the product to potential users with a compelling value proposition and call to action.

**Expected outcome**: Visitors see a hero section ("Create YouTube Videos That Actually Perform"), feature highlights, social proof, and a clear call to action ("Create Your First Video Free") that leads to registration.

The current welcome page is the stock Laravel starter kit welcome page with Laravel ecosystem links and branding. This feature completely replaces it with a custom landing page that reflects the Itervel product. The route (`/`, named `home`) already exists in `routes/web.php` and renders the `welcome` Inertia page, passing `canRegister` as a prop. The implementation must preserve this routing structure while transforming the frontend page content.

## Objective

Replace the default Laravel welcome page with a compelling, responsive product landing page for Itervel that includes: a navigation header with login/register links, a hero section with the headline "Create YouTube Videos That Actually Perform" and primary CTA button "Create Your First Video Free", feature highlight cards showcasing key platform capabilities, a social proof section, and a final CTA section. The page must support dark mode, be fully responsive, and link the CTA to the registration page.

## Solution Approach

### Architecture

The landing page is a single Inertia page component that replaces the existing `resources/js/pages/welcome.tsx`. No new backend route is needed since the `home` route already exists at `/` in `routes/web.php` and passes the `canRegister` prop via Fortify features detection.

The approach uses **no new layout file** -- the landing page is self-contained (like the current welcome page) since it has a unique full-width design that doesn't fit the app-layout (sidebar) or auth-layout (centered card) patterns.

### Frontend Structure

Break the landing page into well-organized sections within the page component, extracting reusable section components into a `resources/js/components/landing/` directory to keep the main page file manageable:

1. **`resources/js/components/landing/landing-header.tsx`** -- Sticky navigation bar with logo, nav links, and login/register buttons
2. **`resources/js/components/landing/hero-section.tsx`** -- Hero with headline, subheadline, CTA button, and decorative visual
3. **`resources/js/components/landing/features-section.tsx`** -- Grid of feature highlight cards
4. **`resources/js/components/landing/social-proof-section.tsx`** -- Testimonials/stats section
5. **`resources/js/components/landing/cta-section.tsx`** -- Bottom call-to-action banner
6. **`resources/js/components/landing/landing-footer.tsx`** -- Simple footer with copyright and links

### Styling Approach

- Use the existing Tailwind CSS v4 theme variables (e.g., `bg-background`, `text-foreground`, `bg-primary`, etc.) for consistent theming
- Support dark mode using the existing `dark:` variant setup (`.dark` class strategy)
- Use the existing `cn()` utility from `@/lib/utils` for conditional classes
- Reuse the existing `Button` component from `@/components/ui/button` for CTA buttons
- Reuse the existing `Card`, `CardHeader`, `CardTitle`, `CardDescription`, `CardContent` components for feature cards
- Use `lucide-react` icons (already installed) for feature highlight icons
- Responsive: mobile-first with `sm:`, `md:`, `lg:` breakpoints

### Navigation & CTAs

- Use Wayfinder-generated routes: `login()`, `register()`, `dashboard()`, `home()` from `@/routes`
- Use Inertia `<Link>` component for all internal navigation (SPA transitions)
- Primary CTA "Create Your First Video Free" links to `register()` route
- If user is already authenticated (`auth.user` exists), show "Go to Dashboard" linking to `dashboard()` instead of login/register
- Preserve the existing `canRegister` prop to conditionally show registration links

### Key Content Sections

**Hero Section:**

- Headline: "Create YouTube Videos That Actually Perform"
- Subheadline: Brief description of what Itervel does (AI-powered faceless video creation)
- Primary CTA: "Create Your First Video Free" button (links to `/register`)
- Secondary CTA: "See How It Works" (scrolls to features section)

**Feature Highlights (4-6 cards):**

- AI Script Generation with Iterative Refinement
- Professional Voiceover Generation
- Automated Video Assembly
- Smart Thumbnail Creation
- YouTube-Optimized Metadata
- Complete Upload-Ready Package

**Social Proof Section:**

- Platform statistics (e.g., placeholder stats like "Videos Created", "Hours Saved", "Creators Served")
- Static placeholder data since this is a new product -- use realistic-looking numbers that can be replaced with real data later

**Final CTA:**

- Reinforcing message and repeated primary CTA button

### Backend Changes

The only backend change needed is to update the `canRegister` prop name or add an `appName` prop if needed. The current route in `routes/web.php` already passes `canRegister` which is sufficient. No controller, model, or middleware changes are required.

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Contains the `home` route (`/`) that renders the welcome page via Inertia. This route passes `canRegister` prop. The route itself does not need to change.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/welcome.tsx` -- The current welcome page that will be completely replaced with the new landing page content. This is the primary file to modify.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/button.tsx` -- Existing Button component with variants (default, destructive, outline, secondary, ghost, link) and sizes (default, sm, lg, icon). Reuse for CTAs.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/card.tsx` -- Existing Card components (Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter). Reuse for feature highlight cards.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/badge.tsx` -- Existing Badge component. Can be used for labels like "Free" or "AI-Powered" in the hero.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/separator.tsx` -- Existing Separator for visual dividers between sections.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/app-logo.tsx` -- Existing app logo component showing the Laravel icon + "Laravel Starter Kit" text. Will need to be updated or a new logo variant created for the landing page header.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/app-logo-icon.tsx` -- The SVG logo icon component. Reuse in the landing header.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/text-link.tsx` -- Styled Inertia Link component. Reuse for footer links.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/lib/utils.ts` -- Contains the `cn()` utility for class merging.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/routes/index.ts` -- Wayfinder-generated routes (`home`, `login`, `register`, `dashboard`). Import from `@/routes`.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/index.ts` -- SharedData type definition with `auth` and `name` properties.
- `/Users/young/Nextcloud/dev/Itervel/resources/css/app.css` -- Tailwind CSS v4 configuration with theme variables for both light and dark modes.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Middleware/HandleInertiaRequests.php` -- Shares `name` (app name), `auth.user`, and `sidebarOpen` props to all Inertia pages.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/ExampleTest.php` -- Existing test for the home route. Must be updated to validate the new landing page content.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/landing/landing-header.tsx` -- Sticky navigation header for the landing page with logo, nav links, and auth buttons. Responsive with mobile hamburger menu.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/landing/hero-section.tsx` -- Hero section with headline, subheadline, CTA buttons, and optional decorative visual element.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/landing/features-section.tsx` -- Grid of feature highlight cards using the existing Card UI component.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/landing/social-proof-section.tsx` -- Statistics/social proof section with placeholder metrics.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/landing/cta-section.tsx` -- Final call-to-action banner section.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/landing/landing-footer.tsx` -- Simple footer with copyright, links, and branding.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/LandingPageTest.php` -- Comprehensive Pest test file for the landing page.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Frontend Developer (Landing Components)
    - Name: landing-components-dev
    - Role: Creates the landing page section components (header, hero, features, social proof, CTA, footer) in the `resources/js/components/landing/` directory
    - Agent Type: coder
    - Resume: false

- Frontend Developer (Page Integration)
    - Name: landing-page-dev
    - Role: Replaces the existing `welcome.tsx` page content with the new landing page, integrating all section components and wiring up props, auth state, and routing
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: landing-test-dev
    - Role: Writes comprehensive Pest feature tests for the landing page covering guest access, authenticated user redirection, registration link visibility, and Inertia rendering
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: landing-reviewer
    - Role: Validates the complete landing page implementation against acceptance criteria, runs tests, checks types, runs linting, and verifies dark mode and responsive design patterns
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

### 1. Create Landing Page Section Components

- **Task ID**: create-landing-components
- **Depends On**: none
- **Assigned To**: landing-components-dev
- **Agent Type**: coder
- **Parallel**: true
- Create the directory `resources/js/components/landing/`
- Create `landing-header.tsx`: A responsive sticky navigation header component that accepts `auth` (user object or null) and `canRegister` (boolean) as props. Include the `AppLogoIcon` from `@/components/app-logo-icon` with "Itervel" text branding. Show "Log in" and "Register" links for guests using `<Link>` from `@inertiajs/react` with `login()` and `register()` routes from `@/routes`. Show "Dashboard" link for authenticated users using `dashboard()`. On mobile, use a hamburger menu (can use a simple state toggle with Tailwind responsive classes). Use Tailwind theme variables for styling (`bg-background`, `text-foreground`, `border-border`). Add a `sticky top-0 z-50` container with a subtle `border-b` and `backdrop-blur` effect.
- Create `hero-section.tsx`: The main hero section component. Contains the headline `<h1>` with text "Create YouTube Videos That Actually Perform", a subheadline paragraph: "Transform any topic into a complete, upload-ready YouTube video with AI-powered script writing, voiceover generation, and automated video assembly.", a primary CTA `<Button>` component (size `lg`) wrapping a `<Link>` to `register()` with text "Create Your First Video Free", and a secondary text link "See How It Works" with `href="#features"` for smooth scroll. Accept `canRegister` and `isAuthenticated` props -- if authenticated, show "Go to Dashboard" linking to `dashboard()` instead. Use a `Badge` component to display a small label above the headline like "AI-Powered Video Creation". Center the content with generous vertical padding (`py-20 lg:py-32`).
- Create `features-section.tsx`: A section with an `id="features"` anchor. Section heading "Everything You Need to Create Stunning Videos". Render a responsive grid (`grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6`) of 6 feature cards using the existing `Card`, `CardHeader`, `CardTitle`, `CardDescription`, `CardContent` components. Each card should have a `lucide-react` icon, title, and description. Features to highlight: (1) Sparkles icon - "AI Script Generation" / "Iterative AI refinement produces engaging, well-structured scripts tailored to your audience", (2) Mic icon - "Professional Voiceover" / "Natural-sounding AI voiceovers with customizable pace and tone", (3) Video icon - "Automated Video Assembly" / "Complete video production with stock footage, transitions, and background music", (4) Image icon - "Smart Thumbnails" / "AI-generated, click-optimized thumbnails that drive views", (5) FileText icon - "YouTube-Ready Metadata" / "Optimized titles, descriptions, tags, and chapters for maximum discoverability", (6) Package icon - "Upload-Ready Package" / "Download everything you need -- video, thumbnail, and metadata -- in one click".
- Create `social-proof-section.tsx`: A section with placeholder statistics displayed in a horizontal row (`flex flex-col md:flex-row`). Three to four stat blocks, each showing a large number and a label: "10,000+" / "Videos Created", "50,000+" / "Hours Saved", "2,500+" / "Creators Served". Use `text-4xl font-bold` for numbers and `text-muted-foreground text-sm` for labels. Add a subtle background differentiation (`bg-muted/50`).
- Create `cta-section.tsx`: A final CTA banner section. Contains a heading "Ready to Create Your First Video?", a supporting paragraph, and a `<Button>` (size `lg`) wrapping `<Link>` to `register()` with text "Get Started Free". Accept `canRegister` and `isAuthenticated` props for conditional rendering (show "Go to Dashboard" if authenticated). Center-aligned with generous padding.
- Create `landing-footer.tsx`: A simple footer with the app name "Itervel", copyright year (use `new Date().getFullYear()`), and minimal links. Use `text-muted-foreground text-sm` styling. Add a `Separator` at the top. Keep it clean and minimal.
- All components must: use `cn()` from `@/lib/utils` for conditional classes, support dark mode via `dark:` variants, follow the existing kebab-case file naming convention, use named function default exports matching the component pattern in the codebase.

### 2. Integrate Components into Welcome Page

- **Task ID**: integrate-welcome-page
- **Depends On**: create-landing-components
- **Assigned To**: landing-page-dev
- **Agent Type**: coder
- **Parallel**: false
- Completely replace the content of `resources/js/pages/welcome.tsx` with the new landing page implementation
- Keep the existing component signature: `export default function Welcome({ canRegister = true }: { canRegister?: boolean })`
- Keep the `usePage<SharedData>().props` pattern to access `auth` data
- Keep the `<Head title="Welcome">` component (update title to "Itervel - AI-Powered YouTube Video Creation" or similar)
- Remove the `<link>` tags for `fonts.bunny.net` (the Instrument Sans font is already configured in `app.css` via Tailwind theme)
- Import and compose all landing section components in order: `LandingHeader`, `HeroSection`, `FeaturesSection`, `SocialProofSection`, `CtaSection`, `LandingFooter`
- Pass the relevant props to each component: `auth.user` for authentication state, `canRegister` for registration visibility
- Wrap everything in a `<div className="min-h-screen bg-background text-foreground">` container
- Ensure smooth scroll behavior works for the "See How It Works" link targeting `#features` (add `scroll-smooth` to the outer container or use `scroll-behavior: smooth` in CSS)
- Verify the page renders correctly by running `npm run types` to check TypeScript types

### 3. Write Feature Tests

- **Task ID**: write-landing-tests
- **Depends On**: integrate-welcome-page
- **Assigned To**: landing-test-dev
- **Agent Type**: coder
- **Parallel**: false
- Create `/Users/young/Nextcloud/dev/Itervel/tests/Feature/LandingPageTest.php` using Pest format
- Update or replace the existing `/Users/young/Nextcloud/dev/Itervel/tests/Feature/ExampleTest.php` test to validate the new landing page content
- Write the following tests:
    - `test('landing page returns successful response')` -- GET `/` returns 200
    - `test('landing page renders welcome component')` -- Assert Inertia component is `welcome`
    - `test('landing page displays hero headline')` -- Assert response contains "Create YouTube Videos That Actually Perform"
    - `test('landing page shows registration link when registration is enabled')` -- Assert response renders with `canRegister` prop set to true (Fortify registration is enabled in config)
    - `test('landing page shows login link for guests')` -- Assert response contains `/login` href
    - `test('landing page shows dashboard link for authenticated users')` -- Acting as a user, assert response contains `/dashboard` href
    - `test('authenticated users see dashboard link instead of register')` -- Acting as a user, verify the CTA behavior
- Use the existing test patterns from `tests/Feature/DashboardTest.php` and `tests/Feature/ExampleTest.php`
- Use `User::factory()->create()` for authenticated user tests
- Use `$this->get(route('home'))` for route access
- Use `assertOk()`, `assertInertia()` for response assertions
- Run tests with `php artisan test tests/Feature/LandingPageTest.php --compact` inside the Docker container (`make shell` then run, or use `docker compose exec app php artisan test`)

### 4. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-landing-components, integrate-welcome-page, write-landing-tests
- **Assigned To**: landing-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed below
- Verify the landing page test file passes: `php artisan test tests/Feature/LandingPageTest.php --compact`
- Verify the existing example test still passes: `php artisan test tests/Feature/ExampleTest.php --compact`
- Run the full test suite to ensure no regressions: `php artisan test --compact`
- Run TypeScript type checking: `npm run types`
- Run ESLint: `npm run lint`
- Run Prettier check: `npm run format:check`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Verify all new component files exist in `resources/js/components/landing/`
- Verify the welcome.tsx page imports and uses all section components
- Verify dark mode classes are present throughout the components
- Verify responsive breakpoint classes (`sm:`, `md:`, `lg:`) are used appropriately
- Verify all Inertia `<Link>` components use Wayfinder route functions (`login()`, `register()`, `dashboard()`) imported from `@/routes`
- Verify the existing `Button` and `Card` UI components are reused (not reimplemented)
- Confirm acceptance criteria are fully met

## Acceptance Criteria

- The landing page loads successfully at `/` (route name `home`) with HTTP 200 status
- Guests see a navigation header with "Log in" and "Register" links
- Guests see the hero section with headline "Create YouTube Videos That Actually Perform"
- Guests see the primary CTA button "Create Your First Video Free" that links to `/register`
- Guests see a feature highlights section with at least 4 feature cards
- Guests see a social proof/statistics section
- Guests see a final CTA section encouraging registration
- Authenticated users see "Dashboard" link instead of "Log in"/"Register" in the header
- Authenticated users see "Go to Dashboard" CTA instead of "Create Your First Video Free"
- The registration link/CTA is hidden when `canRegister` is false
- The page is responsive across mobile, tablet, and desktop breakpoints
- The page supports dark mode using the existing Tailwind dark mode strategy
- All landing page feature tests pass
- TypeScript type checking passes (`npm run types`)
- ESLint passes (`npm run lint`)
- The existing test suite has no regressions

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run landing page tests
php artisan test tests/Feature/LandingPageTest.php --compact

# Run example test (should still pass)
php artisan test tests/Feature/ExampleTest.php --compact

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

- The current `welcome.tsx` is approximately 500+ lines of Laravel boilerplate SVGs and links. The replacement will be significantly cleaner and product-focused.
- The `fonts.bunny.net` link in the current welcome page for Instrument Sans is redundant since the font is already configured in `app.css` via the Tailwind theme. Remove it.
- The `AppLogoIcon` currently renders the Laravel logo SVG. For the landing page, it should be reused as-is for now (it can be updated to a custom Itervel logo in a separate feature).
- The `AppLogo` component text currently says "Laravel Starter Kit". The landing page header should display "Itervel" as the brand name instead, using a new inline rendering rather than modifying the shared `AppLogo` component (which is used in the sidebar for authenticated pages).
- Social proof numbers are placeholder values since this is a new product. They should be easy to update later (defined as constants or an array at the top of the component).
- The `scroll-smooth` class or CSS property should be applied for the "See How It Works" anchor link to work with smooth scrolling to the `#features` section.
- All commands should be run inside the Docker container. Use `make shell` to enter the container, or prefix with `docker compose exec app`.

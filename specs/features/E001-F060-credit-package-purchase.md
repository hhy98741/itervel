# Feature: Credit Package Purchase

**Epic**: E001-ai-powered-faceless-video-creation-platform.md
**Feature**: E001-F060
**Dependencies**: E001-F054

## Task Description

Credit Package Purchase allows users to browse and select from three credit packages at different price points, each offering volume discounts for purchasing more credits at once. This is the core monetization feature of the platform, enabling users to buy additional video creation credits beyond their initial free credit.

**What it does**: Offers credit packages at different price points with volume discounts.

**Expected outcome**: The user can purchase credits in three packages: 5 credits for $20 (standard), 15 credits for $50 (17% discount), or 35 credits for $100 (29% discount).

This feature focuses on the package definition, pricing display, and package selection flow. It does NOT handle the actual payment processing -- that is delegated to E001-F061 (Stripe Checkout), which will redirect users to Stripe's hosted checkout page after they select a package. This feature creates:

1. A configuration file (`config/credits.php`) that defines the three credit packages with their names, credit amounts, prices, and discount percentages. Using config rather than a database table keeps the package definitions simple, version-controlled, and easy to modify.
2. A `CreditPackage` value object class that encapsulates package data with helper methods (price per credit, formatted price, discount label).
3. A `CreditPackageController` that serves the pricing page via Inertia and provides package data to the frontend.
4. A pricing page (`resources/js/pages/credits/pricing.tsx`) that displays the three packages as styled cards with clear pricing, discount badges, and "Buy Now" buttons.
5. Routes for the pricing page accessible to authenticated, verified users.

The feature depends on E001-F054 (Credit Balance Display), which establishes:

- The `video_credits` column on the users table
- The `video_credits` property on the TypeScript `User` type
- The `CreditBalance` component in the navigation
- The `HandleInertiaRequests` middleware sharing `auth.user.video_credits`

The `addCredits()` method on the User model (from E001-F055) will be used by E001-F061/E001-F062 after successful payment, not by this feature directly.

## Objective

Create a credit pricing page that displays three purchase packages (5 credits/$20, 15 credits/$50, 35 credits/$100) with clear pricing, discount indicators, and "Buy Now" call-to-action buttons. The page should be accessible from the main navigation sidebar. The package data should be centralized in a configuration file so it can be shared between the frontend display and the backend checkout flow (E001-F061). When complete, users can browse the pricing page and see all available credit packages. The "Buy Now" buttons will be wired to the checkout flow in E001-F061.

## Solution Approach

### 1. Credit Package Configuration (`config/credits.php`)

Define the three credit packages in a Laravel configuration file. This approach is preferred over a database table because:

- The packages are fixed business rules, not user-generated data
- Config files are version-controlled and deployable
- No database queries needed to display pricing
- Easy to modify pricing without migrations

```php
<?php

return [
    'packages' => [
        [
            'id' => 'starter',
            'name' => 'Starter',
            'credits' => 5,
            'price' => 2000, // Price in cents to avoid floating-point issues
            'discount' => 0,
        ],
        [
            'id' => 'popular',
            'name' => 'Popular',
            'credits' => 15,
            'price' => 5000,
            'discount' => 17,
        ],
        [
            'id' => 'best-value',
            'name' => 'Best Value',
            'credits' => 35,
            'price' => 10000,
            'discount' => 29,
        ],
    ],

    'currency' => 'usd',
];
```

Prices are stored in cents (integer) to match Stripe's convention and avoid floating-point arithmetic issues. The `id` field provides a stable identifier for each package that will be used in the checkout flow (E001-F061) to look up the selected package.

### 2. CreditPackage Value Object

Create a simple value object class `App\ValueObjects\CreditPackage` that wraps the raw config array and provides computed properties. This keeps the pricing logic centralized and testable.

```php
<?php

namespace App\ValueObjects;

class CreditPackage
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly int $credits,
        public readonly int $price,
        public readonly int $discount,
    ) {}

    public static function fromConfig(array $config): self
    {
        return new self(
            id: $config['id'],
            name: $config['name'],
            credits: $config['credits'],
            price: $config['price'],
            discount: $config['discount'],
        );
    }

    public static function all(): array
    {
        return array_map(
            fn (array $package) => self::fromConfig($package),
            config('credits.packages'),
        );
    }

    public static function findById(string $id): ?self
    {
        $packages = config('credits.packages');

        $package = collect($packages)->firstWhere('id', $id);

        return $package ? self::fromConfig($package) : null;
    }

    /** Price formatted in dollars (e.g., "$20") */
    public function formattedPrice(): string
    {
        return '$' . number_format($this->price / 100, 0);
    }

    /** Price per credit in dollars (e.g., "$4.00") */
    public function pricePerCredit(): string
    {
        return '$' . number_format($this->price / 100 / $this->credits, 2);
    }

    /** Convert to array for Inertia serialization */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'credits' => $this->credits,
            'price' => $this->price,
            'discount' => $this->discount,
            'formatted_price' => $this->formattedPrice(),
            'price_per_credit' => $this->pricePerCredit(),
        ];
    }
}
```

The `findById()` method will be used by E001-F061 (Stripe Checkout) to look up the package when initiating a checkout session. The `toArray()` method provides all the data the frontend needs to render the pricing cards.

### 3. CreditPackageController

Create a controller `App\Http\Controllers\CreditPackageController` with an `index` method that renders the pricing page via Inertia, passing the package data.

```php
<?php

namespace App\Http\Controllers;

use App\ValueObjects\CreditPackage;
use Inertia\Inertia;
use Inertia\Response;

class CreditPackageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('credits/pricing', [
            'packages' => array_map(
                fn (CreditPackage $package) => $package->toArray(),
                CreditPackage::all(),
            ),
        ]);
    }
}
```

The controller is intentionally minimal. It passes the package data to the Inertia page. The actual purchase/checkout logic will be added in E001-F061.

### 4. Route Registration

Add a route for the pricing page in `routes/web.php` within the authenticated middleware group. The route should be accessible to all authenticated, verified users.

```php
Route::get('credits', [CreditPackageController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('credits.index');
```

This route should be added to `routes/web.php` before the `require __DIR__.'/settings.php';` line, within the existing middleware pattern.

### 5. Frontend Pricing Page (`resources/js/pages/credits/pricing.tsx`)

Create a pricing page that displays the three credit packages as cards. The page follows the existing Inertia page patterns (see `dashboard.tsx`, `settings/profile.tsx`):

- Uses `AppLayout` with breadcrumbs
- Displays a `Heading` component with title and description
- Uses `Card` components from the UI library for each package
- Each card shows: package name, credit amount, price, discount badge (if applicable), price per credit, and a "Buy Now" button
- The "Popular" package should be visually highlighted as recommended
- The "Buy Now" buttons initially link to `#` (placeholder) -- E001-F061 will wire them to the Stripe checkout flow using Wayfinder actions

The layout uses a responsive grid: 1 column on mobile, 3 columns on desktop, with the middle card (Popular) slightly elevated or bordered to draw attention.

TypeScript type for the package data:

```tsx
type CreditPackageData = {
    id: string;
    name: string;
    credits: number;
    price: number;
    discount: number;
    formatted_price: string;
    price_per_credit: string;
};
```

### 6. Navigation Integration

Add a "Credits" or "Buy Credits" link to the sidebar navigation in `resources/js/components/app-sidebar.tsx` so users can easily navigate to the pricing page. Use the `Coins` icon from `lucide-react` (consistent with the `CreditBalance` component from E001-F054). The Wayfinder-generated route function will be used for the href.

### 7. Testing Strategy

Write feature tests that verify:

- The pricing page is accessible to authenticated, verified users
- The pricing page returns the correct package data via Inertia
- Guests are redirected to the login page
- Unverified users are redirected
- The CreditPackage value object computes values correctly

Write unit tests for the CreditPackage value object:

- `fromConfig()` creates a valid instance
- `all()` returns all three packages
- `findById()` finds the correct package
- `findById()` returns null for invalid IDs
- `formattedPrice()` returns correct format
- `pricePerCredit()` returns correct format
- `toArray()` contains all required keys

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Controller.php` -- Base controller class. The new `CreditPackageController` will extend this.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/ProfileController.php` -- Reference controller showing the pattern for Inertia page rendering with `Inertia::render()`.
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Main web routes file. The credits route will be added here within the authenticated middleware group.
- `/Users/young/Nextcloud/dev/Itervel/routes/settings.php` -- Reference for route registration patterns with middleware groups.
- `/Users/young/Nextcloud/dev/Itervel/bootstrap/app.php` -- Application bootstrap. Referenced for understanding middleware configuration.
- `/Users/young/Nextcloud/dev/Itervel/config/services.php` -- Reference for config file patterns. The new `config/credits.php` will follow this structure.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` -- User model. After E001-F054/F055, includes `video_credits` in fillable and credit helper methods. Referenced for understanding the credit system.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Middleware/HandleInertiaRequests.php` -- Inertia shared data middleware. Shares `auth.user` with `video_credits`. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/dashboard.tsx` -- Reference for Inertia page component patterns (AppLayout, breadcrumbs, Head).
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/profile.tsx` -- Reference for Inertia page with form handling and Wayfinder action imports.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/card.tsx` -- Card UI component (Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter). Will be used for package cards.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/button.tsx` -- Button UI component with variants (default, destructive, outline, secondary, ghost, link) and sizes. Will be used for "Buy Now" buttons.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/badge.tsx` -- Badge UI component with variants (default, secondary, destructive, outline). Will be used for discount badges.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/heading.tsx` -- Heading component with title, description, and variant props. Will be used on the pricing page.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/app-sidebar.tsx` -- Sidebar navigation component. Will be modified to add a "Buy Credits" navigation item.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/app-header.tsx` -- Header navigation component. Referenced for understanding how navigation items are rendered in the header layout.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/app-layout.tsx` -- Main app layout. The pricing page will use this layout.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/lib/utils.ts` -- Utility functions including `cn()` for class merging.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/index.ts` -- SharedData type definition. Referenced for type imports.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/auth.ts` -- User type with `video_credits`. Referenced for understanding the user data shape.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/navigation.ts` -- NavItem and BreadcrumbItem types. Referenced for navigation integration.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/DashboardTest.php` -- Reference for testing authenticated Inertia page access patterns.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/ProfileUpdateTest.php` -- Reference for testing Inertia responses with `assertInertia()`.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration. Feature tests use `RefreshDatabase` automatically.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` -- User factory for test setup.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/config/credits.php` -- Configuration file defining the three credit packages (starter: 5/$20, popular: 15/$50, best-value: 35/$100) with prices in cents, credit amounts, and discount percentages.
- `/Users/young/Nextcloud/dev/Itervel/app/ValueObjects/CreditPackage.php` -- Value object class encapsulating credit package data with static factory methods (`fromConfig`, `all`, `findById`) and computed properties (`formattedPrice`, `pricePerCredit`, `toArray`).
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/CreditPackageController.php` -- Controller with `index` method that renders the `credits/pricing` Inertia page with all package data.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/credits/pricing.tsx` -- Inertia page component displaying the three credit packages as styled cards with pricing, discount badges, and "Buy Now" buttons within the AppLayout.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Credits/CreditPackagePurchaseTest.php` -- Feature tests for the pricing page (auth access, Inertia response data, guest redirect, unverified redirect).
- `/Users/young/Nextcloud/dev/Itervel/tests/Unit/ValueObjects/CreditPackageTest.php` -- Unit tests for the CreditPackage value object (fromConfig, all, findById, formattedPrice, pricePerCredit, toArray).

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: credit-package-backend-dev
    - Role: Creates the credit package configuration, CreditPackage value object, CreditPackageController, and route registration. Handles all PHP backend work.
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: credit-package-frontend-dev
    - Role: Creates the pricing page component, adds the navigation link to the sidebar, and ensures proper styling and responsive layout.
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: credit-package-test-dev
    - Role: Writes feature tests for the pricing page controller and unit tests for the CreditPackage value object.
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: credit-package-reviewer
    - Role: Validates the complete credit package purchase feature against acceptance criteria, runs all tests, checks types, runs linting and formatting.
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Create Credit Package Configuration and Value Object

- **Task ID**: create-credit-package-backend
- **Depends On**: none
- **Assigned To**: credit-package-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- Create the configuration file `/Users/young/Nextcloud/dev/Itervel/config/credits.php` with the following content:
    - A `packages` array containing three packages:
        - `['id' => 'starter', 'name' => 'Starter', 'credits' => 5, 'price' => 2000, 'discount' => 0]`
        - `['id' => 'popular', 'name' => 'Popular', 'credits' => 15, 'price' => 5000, 'discount' => 17]`
        - `['id' => 'best-value', 'name' => 'Best Value', 'credits' => 35, 'price' => 10000, 'discount' => 29]`
    - A `currency` key set to `'usd'`
    - Prices in cents (integer) to match Stripe convention
- Create the directory `app/ValueObjects/` if it does not exist
- Create `/Users/young/Nextcloud/dev/Itervel/app/ValueObjects/CreditPackage.php` as a value object with:
    - Constructor using PHP 8 property promotion with `readonly` properties: `string $id`, `string $name`, `int $credits`, `int $price`, `int $discount`
    - Static method `fromConfig(array $config): self` that creates an instance from a config array
    - Static method `all(): array` that returns an array of `CreditPackage` instances from `config('credits.packages')`
    - Static method `findById(string $id): ?self` that finds a package by its ID from the config, returns null if not found. Use `collect()->firstWhere()`.
    - Method `formattedPrice(): string` that returns the price formatted as dollars (e.g., `"$20"`). Use `number_format($this->price / 100, 0)`.
    - Method `pricePerCredit(): string` that returns price per credit (e.g., `"$4.00"`). Use `number_format($this->price / 100 / $this->credits, 2)`.
    - Method `toArray(): array` that returns `['id', 'name', 'credits', 'price', 'discount', 'formatted_price', 'price_per_credit']` with computed values.
    - Add PHPDoc blocks for all methods with `@return` type hints
- Create the controller using: `php artisan make:class App/Http/Controllers/CreditPackageController --no-interaction`
    - If the artisan command does not support this, manually create `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/CreditPackageController.php`
    - The controller extends `App\Http\Controllers\Controller`
    - Has one method `index(): Response` (using `Inertia\Response`)
    - The `index` method renders `credits/pricing` via `Inertia::render()` with:
        - `'packages'` prop: `array_map(fn (CreditPackage $package) => $package->toArray(), CreditPackage::all())`
    - Import `App\ValueObjects\CreditPackage` and `Inertia\Inertia`
- Add the route to `/Users/young/Nextcloud/dev/Itervel/routes/web.php`:
    - Add `use App\Http\Controllers\CreditPackageController;` at the top
    - Add the following route before the `require __DIR__.'/settings.php';` line:
        ```php
        Route::get('credits', [CreditPackageController::class, 'index'])
            ->middleware(['auth', 'verified'])
            ->name('credits.index');
        ```
- Run `vendor/bin/pint --dirty` to format all changed PHP files
- Verify the route is registered: `php artisan route:list --name=credits`

### 2. Create Pricing Page Frontend

- **Task ID**: create-pricing-page-frontend
- **Depends On**: create-credit-package-backend
- **Assigned To**: credit-package-frontend-dev
- **Agent Type**: coder
- **Parallel**: false
- Create the directory `resources/js/pages/credits/` if it does not exist
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/credits/pricing.tsx` with the following structure:
    - Import `Head` from `@inertiajs/react`
    - Import `AppLayout` from `@/layouts/app-layout`
    - Import `Heading` from `@/components/heading`
    - Import `{ Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter }` from `@/components/ui/card`
    - Import `{ Button }` from `@/components/ui/button`
    - Import `{ Badge }` from `@/components/ui/badge`
    - Import `{ cn }` from `@/lib/utils`
    - Import `type { BreadcrumbItem }` from `@/types`
    - Import the Wayfinder route `import { index } from '@/routes/credits'` (this will be auto-generated after running `npm run build`)
    - Define a `CreditPackageData` type:
        ```tsx
        type CreditPackageData = {
            id: string;
            name: string;
            credits: number;
            price: number;
            discount: number;
            formatted_price: string;
            price_per_credit: string;
        };
        ```
    - Define props interface:
        ```tsx
        interface PricingProps {
            packages: CreditPackageData[];
        }
        ```
    - Set up breadcrumbs:
        ```tsx
        const breadcrumbs: BreadcrumbItem[] = [
            { title: 'Buy Credits', href: index().url },
        ];
        ```
    - Export default function `Pricing({ packages }: PricingProps)` that renders:
        - `AppLayout` with breadcrumbs
        - `Head` with title "Buy Credits"
        - A container div with `px-4 py-6` padding (matching settings layout pattern)
        - `Heading` with title "Buy Credits" and description "Purchase credit packages to create more videos"
        - A responsive grid `grid gap-6 md:grid-cols-3 max-w-4xl` containing three cards
        - Each card renders with:
            - `Card` component with conditional styling: the "Popular" package (`id === 'popular'`) gets a highlighted border (`border-primary ring-2 ring-primary/20`) and a "Most Popular" badge
            - `CardHeader` with the package name as `CardTitle` and "{credits} video credits" as `CardDescription`
            - `CardContent` showing:
                - The formatted price in large text (`text-4xl font-bold`)
                - The price per credit in smaller muted text (`text-sm text-muted-foreground`)
                - A discount `Badge` (only when `discount > 0`) showing "Save {discount}%"
            - `CardFooter` with a `Button` (full width, `w-full`):
                - Default variant for starter and best-value
                - `default` variant for the popular package (all packages use default buttons)
                - The button text should be "Buy Now"
                - For now, the button `onClick` should be a placeholder (no-op or `#` link). Add a `data-package-id={package.id}` attribute for E001-F061 to wire up.
                - Note: The "Buy Now" button will be replaced with an Inertia form or link to the checkout controller in E001-F061. For now, use a disabled-looking `Button` with a comment indicating E001-F061 will wire this up: `{/* E001-F061 will wire this to Stripe checkout */}`
    - The card for the "Best Value" package should show a "Best Value" badge in the header
    - Ensure responsive layout: single column on small screens, 3-column grid on medium+
- Modify `/Users/young/Nextcloud/dev/Itervel/resources/js/components/app-sidebar.tsx`:
    - Import `Coins` from `lucide-react`
    - Import the Wayfinder credits route: `import { index as creditsIndex } from '@/routes/credits'`
    - Add a new nav item to the `mainNavItems` array after "Dashboard":
        ```tsx
        {
            title: 'Buy Credits',
            href: creditsIndex(),
            icon: Coins,
        },
        ```
- Run `npm run build` to generate Wayfinder route functions (this creates `resources/js/routes/credits.ts`)
- Run `npm run types` to verify TypeScript types compile correctly
- Run `npm run lint:fix` to fix any linting issues
- Run `npm run format` to format with Prettier

### 3. Write Feature and Unit Tests

- **Task ID**: write-credit-package-tests
- **Depends On**: create-credit-package-backend
- **Assigned To**: credit-package-test-dev
- **Agent Type**: coder
- **Parallel**: true
- Create the feature test using: `php artisan make:test Credits/CreditPackagePurchaseTest --pest --no-interaction`
- Write the following tests in `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Credits/CreditPackagePurchaseTest.php`:
    - `test('pricing page is displayed for authenticated verified users')`:
        ```php
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get(route('credits.index'));
        $response->assertOk();
        ```
    - `test('pricing page returns all three credit packages')`:
        ```php
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get(route('credits.index'));
        $response->assertInertia(fn ($page) => $page
            ->component('credits/pricing')
            ->has('packages', 3)
            ->has('packages.0', fn ($package) => $package
                ->where('id', 'starter')
                ->where('name', 'Starter')
                ->where('credits', 5)
                ->where('price', 2000)
                ->where('discount', 0)
                ->where('formatted_price', '$20')
                ->where('price_per_credit', '$4.00')
            )
            ->has('packages.1', fn ($package) => $package
                ->where('id', 'popular')
                ->where('credits', 15)
                ->where('price', 5000)
                ->where('discount', 17)
                ->etc()
            )
            ->has('packages.2', fn ($package) => $package
                ->where('id', 'best-value')
                ->where('credits', 35)
                ->where('price', 10000)
                ->where('discount', 29)
                ->etc()
            )
        );
        ```
    - `test('guests are redirected to login from pricing page')`:
        ```php
        $response = $this->get(route('credits.index'));
        $response->assertRedirect(route('login'));
        ```
    - `test('unverified users are redirected from pricing page')`:
        ```php
        $user = User::factory()->unverified()->create();
        $response = $this->actingAs($user)->get(route('credits.index'));
        $response->assertRedirect(route('verification.notice'));
        ```
- Create the unit test directory `tests/Unit/ValueObjects/` if it does not exist
- Create the unit test using: `php artisan make:test ValueObjects/CreditPackageTest --pest --unit --no-interaction`
- Write the following tests in `/Users/young/Nextcloud/dev/Itervel/tests/Unit/ValueObjects/CreditPackageTest.php`:
    - Add `use App\ValueObjects\CreditPackage;` at the top
    - `test('fromConfig creates a valid credit package instance')`:
        ```php
        $package = CreditPackage::fromConfig([
            'id' => 'test',
            'name' => 'Test',
            'credits' => 10,
            'price' => 3000,
            'discount' => 15,
        ]);
        expect($package->id)->toBe('test');
        expect($package->name)->toBe('Test');
        expect($package->credits)->toBe(10);
        expect($package->price)->toBe(3000);
        expect($package->discount)->toBe(15);
        ```
    - `test('all returns all configured credit packages')`:
        ```php
        $packages = CreditPackage::all();
        expect($packages)->toHaveCount(3);
        expect($packages[0])->toBeInstanceOf(CreditPackage::class);
        expect($packages[0]->id)->toBe('starter');
        expect($packages[1]->id)->toBe('popular');
        expect($packages[2]->id)->toBe('best-value');
        ```
    - `test('findById returns the correct package')`:
        ```php
        $package = CreditPackage::findById('popular');
        expect($package)->not->toBeNull();
        expect($package->id)->toBe('popular');
        expect($package->credits)->toBe(15);
        expect($package->price)->toBe(5000);
        ```
    - `test('findById returns null for nonexistent package')`:
        ```php
        $package = CreditPackage::findById('nonexistent');
        expect($package)->toBeNull();
        ```
    - `test('formattedPrice returns correct dollar format')`:
        ```php
        $package = CreditPackage::fromConfig([
            'id' => 'test', 'name' => 'Test', 'credits' => 5, 'price' => 2000, 'discount' => 0,
        ]);
        expect($package->formattedPrice())->toBe('$20');
        ```
    - `test('formattedPrice formats large amounts correctly')`:
        ```php
        $package = CreditPackage::fromConfig([
            'id' => 'test', 'name' => 'Test', 'credits' => 35, 'price' => 10000, 'discount' => 0,
        ]);
        expect($package->formattedPrice())->toBe('$100');
        ```
    - `test('pricePerCredit calculates correctly for starter package')`:
        ```php
        $package = CreditPackage::fromConfig([
            'id' => 'starter', 'name' => 'Starter', 'credits' => 5, 'price' => 2000, 'discount' => 0,
        ]);
        expect($package->pricePerCredit())->toBe('$4.00');
        ```
    - `test('pricePerCredit calculates correctly for popular package')`:
        ```php
        $package = CreditPackage::fromConfig([
            'id' => 'popular', 'name' => 'Popular', 'credits' => 15, 'price' => 5000, 'discount' => 17,
        ]);
        expect($package->pricePerCredit())->toBe('$3.33');
        ```
    - `test('pricePerCredit calculates correctly for best value package')`:
        ```php
        $package = CreditPackage::fromConfig([
            'id' => 'best-value', 'name' => 'Best Value', 'credits' => 35, 'price' => 10000, 'discount' => 29,
        ]);
        expect($package->pricePerCredit())->toBe('$2.86');
        ```
    - `test('toArray contains all required keys')`:
        ```php
        $package = CreditPackage::fromConfig([
            'id' => 'starter', 'name' => 'Starter', 'credits' => 5, 'price' => 2000, 'discount' => 0,
        ]);
        $array = $package->toArray();
        expect($array)->toHaveKeys(['id', 'name', 'credits', 'price', 'discount', 'formatted_price', 'price_per_credit']);
        expect($array['id'])->toBe('starter');
        expect($array['formatted_price'])->toBe('$20');
        expect($array['price_per_credit'])->toBe('$4.00');
        ```
- Run the tests: `php artisan test tests/Feature/Credits/CreditPackagePurchaseTest.php --compact`
- Run the unit tests: `php artisan test tests/Unit/ValueObjects/CreditPackageTest.php --compact`
- Ensure all tests pass
- Run `vendor/bin/pint --dirty` to format any PHP files

### 4. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-credit-package-backend, create-pricing-page-frontend, write-credit-package-tests
- **Assigned To**: credit-package-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed below
- Verify the config file `/Users/young/Nextcloud/dev/Itervel/config/credits.php` exists and contains three packages with correct prices in cents
- Verify the value object `/Users/young/Nextcloud/dev/Itervel/app/ValueObjects/CreditPackage.php` exists with all required methods: `fromConfig`, `all`, `findById`, `formattedPrice`, `pricePerCredit`, `toArray`
- Verify the controller `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/CreditPackageController.php` exists with an `index` method
- Verify the route `credits.index` exists: `php artisan route:list --name=credits`
- Verify the frontend page `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/credits/pricing.tsx` exists and renders three package cards
- Verify the sidebar navigation in `/Users/young/Nextcloud/dev/Itervel/resources/js/components/app-sidebar.tsx` includes a "Buy Credits" link
- Run feature tests: `php artisan test tests/Feature/Credits/CreditPackagePurchaseTest.php --compact`
- Run unit tests: `php artisan test tests/Unit/ValueObjects/CreditPackageTest.php --compact`
- Run full test suite for regression check: `php artisan test --compact`
- Run TypeScript type checking: `npm run types`
- Run ESLint: `npm run lint`
- Run Prettier formatting check: `npm run format:check`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Verify the package data matches the expected values:
    - Starter: 5 credits, $20 (2000 cents), 0% discount, $4.00/credit
    - Popular: 15 credits, $50 (5000 cents), 17% discount, $3.33/credit
    - Best Value: 35 credits, $100 (10000 cents), 29% discount, $2.86/credit
- Confirm all acceptance criteria are met

## Acceptance Criteria

- A configuration file `config/credits.php` exists with three credit packages defined: starter (5/$20), popular (15/$50), best-value (35/$100)
- A `CreditPackage` value object exists at `app/ValueObjects/CreditPackage.php` with static factory methods and computed properties
- The `CreditPackage::all()` method returns all three packages from config
- The `CreditPackage::findById()` method can look up individual packages by their string ID
- Prices are stored in cents (integers) to avoid floating-point issues
- A `CreditPackageController` exists with an `index` method that renders the pricing page via Inertia
- The pricing page is accessible at the `credits.index` route (`GET /credits`) for authenticated, verified users
- Guests are redirected to the login page when accessing the pricing page
- Unverified users are redirected to the email verification notice
- The pricing page displays three cards showing package name, credit amount, price, price per credit, and discount percentage
- The "Popular" package card is visually highlighted as the recommended option
- Discount badges appear on packages with a discount (Popular: 17%, Best Value: 29%)
- A "Buy Credits" navigation link with a Coins icon appears in the sidebar
- The "Buy Now" buttons are present on each card (placeholder, to be wired in E001-F061)
- All feature tests pass for the pricing page controller
- All unit tests pass for the CreditPackage value object
- All existing tests pass without regressions
- TypeScript types compile without errors
- ESLint and Prettier checks pass
- PHP code passes Pint formatting

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Verify route is registered
php artisan route:list --name=credits

# Run credit package feature tests
php artisan test tests/Feature/Credits/CreditPackagePurchaseTest.php --compact

# Run credit package unit tests
php artisan test tests/Unit/ValueObjects/CreditPackageTest.php --compact

# Run all credit-related tests
php artisan test tests/Feature/Credits --compact

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

- This feature depends on E001-F054 (Credit Balance Display) being built first. E001-F054 establishes the `video_credits` column, TypeScript types, and the `CreditBalance` navigation component. This feature adds the pricing page alongside the existing credit display.
- The "Buy Now" buttons on the pricing page are intentionally left as placeholders. E001-F061 (Stripe Checkout) will wire these buttons to initiate Stripe checkout sessions. The `data-package-id` attribute on each button makes it easy for E001-F061 to identify which package was selected.
- Prices are stored in cents (e.g., 2000 = $20.00) to match Stripe's pricing convention and avoid floating-point arithmetic. This means no conversion is needed when creating Stripe checkout sessions in E001-F061.
- The `CreditPackage::findById()` method is designed specifically for E001-F061, which will receive a package ID from the frontend, look up the package config, and use the price/credits to create a Stripe checkout session.
- The discount percentages (17%, 29%) are pre-calculated and stored in the config rather than computed from price/credit ratios. This gives the business team control over how discounts are presented. The base rate is $4.00/credit from the Starter package.
- The `CreditPackage` is a value object (not an Eloquent model) because credit packages are static business configuration, not database entities. This keeps the implementation simple and performant.
- The `config/credits.php` file can be extended in the future to add more packages, adjust pricing, or add Stripe Price IDs when E001-F061 is implemented.
- All commands should be run inside the Docker container. Use `make shell` to enter the container, or prefix with `docker compose exec app`.

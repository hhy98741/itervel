# Feature: Stripe Checkout

**Epic**: E007-payments-and-transactions.md
**Feature**: E007-F002
**Dependencies**: E007-F001

## Task Description

Stripe Checkout processes credit purchases securely through Stripe's hosted checkout page. When a user selects a credit package on the pricing page (created by E001-F060), they are redirected to Stripe's hosted checkout page to complete payment. After successful payment, the user is redirected back to a success page where credits are provisionally shown (actual credit addition will be handled by the payment webhook in E001-F062). The user also receives a receipt email from Stripe.

**What it does**: Processes credit purchases securely through Stripe's hosted checkout page.

**Expected outcome**: The user selects a credit package and is redirected to Stripe's checkout page. After successful payment, credits are added to their account and they receive a receipt email.

This feature depends on E001-F060 (Credit Package Purchase), which establishes:

- The `config/credits.php` configuration file with three packages (starter/popular/best-value) and prices in cents
- The `CreditPackage` value object at `app/ValueObjects/CreditPackage.php` with `findById()`, `toArray()`, and price data
- The `CreditPackageController` with the pricing page at `credits/pricing`
- The `credits.index` route for the pricing page
- The pricing page frontend at `resources/js/pages/credits/pricing.tsx` with placeholder "Buy Now" buttons

This feature creates:

1. The `stripe/stripe-php` Composer package dependency for server-side Stripe API integration.
2. Stripe configuration in `config/services.php` with environment variables for the Stripe secret key and webhook secret.
3. A `StripeCheckoutController` with two methods: `store()` to create a Stripe Checkout Session and redirect the user, and `success()` to render the post-payment success page.
4. A `CheckoutRequest` Form Request to validate the incoming package ID.
5. Success and cancel pages for post-checkout redirect handling.
6. Updates to the pricing page to wire the "Buy Now" buttons to initiate the checkout flow via Inertia form submission.
7. Routes for the checkout flow (`POST /credits/checkout`, `GET /credits/checkout/success`, `GET /credits/checkout/cancel`).

Note: Actual credit addition upon successful payment is handled by E001-F062 (Payment Webhook Processing) and E001-F090 (Stripe Webhook Signature Verification). This feature focuses on creating the Stripe Checkout Session and handling the redirect flow. The success page displays a "Payment received" message and informs the user that credits will be added shortly (webhook-driven).

## Objective

Create the Stripe Checkout integration that allows users to click "Buy Now" on a credit package, be redirected to Stripe's hosted checkout page for secure payment, and return to a success or cancel page. When complete, the full checkout flow works: user selects package -> POST to create checkout session -> redirect to Stripe -> payment -> redirect back to success page. The webhook processing (E001-F062) will handle the actual credit addition asynchronously.

## Solution Approach

### 1. Install Stripe PHP SDK

Install the official Stripe PHP library via Composer:

```bash
composer require stripe/stripe-php
```

This provides the `\Stripe\Stripe` and `\Stripe\Checkout\Session` classes needed to create checkout sessions server-side.

### 2. Stripe Configuration in `config/services.php`

Add Stripe credentials to the existing `config/services.php` file (following the established pattern for third-party services):

```php
'stripe' => [
    'secret' => env('STRIPE_SECRET_KEY'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
],
```

Add corresponding environment variables to `.env.example`:

```
STRIPE_SECRET_KEY=
STRIPE_WEBHOOK_SECRET=
```

This follows the Laravel convention of storing third-party credentials in `config/services.php` and only using `env()` in config files. The `webhook_secret` is included here for E001-F062/F090 to use later.

### 3. CheckoutRequest Form Request

Create a Form Request class `App\Http\Requests\CheckoutRequest` that validates the package ID:

```php
<?php

namespace App\Http\Requests;

use App\ValueObjects\CreditPackage;
use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'package_id' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail) {
                if (CreditPackage::findById($value) === null) {
                    $fail('The selected credit package is invalid.');
                }
            }],
        ];
    }
}
```

This uses a custom validation closure to verify the package ID exists in the config. It follows the array-based validation rule convention established by the existing form requests (e.g., `ProfileUpdateRequest`).

### 4. StripeCheckoutController

Create `App\Http\Controllers\StripeCheckoutController` with three methods:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\ValueObjects\CreditPackage;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Checkout\Session;
use Stripe\Stripe;

class StripeCheckoutController extends Controller
{
    public function store(CheckoutRequest $request): RedirectResponse
    {
        $package = CreditPackage::findById($request->validated('package_id'));

        Stripe::setApiKey(config('services.stripe.secret'));

        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => config('credits.currency'),
                    'product_data' => [
                        'name' => $package->name . ' Credit Package',
                        'description' => $package->credits . ' video credits',
                    ],
                    'unit_amount' => $package->price,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => route('credits.checkout.success') . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('credits.checkout.cancel'),
            'client_reference_id' => (string) $request->user()->id,
            'metadata' => [
                'user_id' => (string) $request->user()->id,
                'package_id' => $package->id,
                'credits' => (string) $package->credits,
            ],
        ]);

        return redirect()->away($session->url);
    }

    public function success(): Response
    {
        return Inertia::render('credits/checkout-success');
    }

    public function cancel(): Response
    {
        return Inertia::render('credits/checkout-cancel');
    }
}
```

Key design decisions:

- Uses `price_data` inline pricing (not pre-created Stripe Price objects) because the package prices are config-driven and may change without needing to update Stripe.
- Stores `user_id`, `package_id`, and `credits` in the session metadata so the webhook handler (E001-F062) can identify which user and package to credit.
- Uses `client_reference_id` for the user ID as an additional identifier.
- Uses `redirect()->away()` because Stripe's checkout URL is external.
- The `{CHECKOUT_SESSION_ID}` placeholder in `success_url` is replaced by Stripe with the actual session ID.
- The success page does NOT add credits directly -- that is done by E001-F062's webhook handler to avoid double-crediting.

### 5. Route Registration

Add routes to `routes/web.php` within the authenticated middleware group:

```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('credits/checkout', [StripeCheckoutController::class, 'store'])
        ->name('credits.checkout.store');
    Route::get('credits/checkout/success', [StripeCheckoutController::class, 'success'])
        ->name('credits.checkout.success');
    Route::get('credits/checkout/cancel', [StripeCheckoutController::class, 'cancel'])
        ->name('credits.checkout.cancel');
});
```

These routes should be placed alongside the existing `credits` route from E001-F060. The `POST` route for checkout creation is separate from the `GET` routes for success/cancel pages. All are protected by `auth` and `verified` middleware.

### 6. Update Pricing Page to Wire "Buy Now" Buttons

Update `resources/js/pages/credits/pricing.tsx` (created by E001-F060) to replace the placeholder buttons with Inertia `<Form>` components that POST to the checkout route:

```tsx
import { Form } from '@inertiajs/react';
import StripeCheckoutController from '@/actions/App/Http/Controllers/StripeCheckoutController';

// Inside each package card's footer:
<Form {...StripeCheckoutController.store.form()}>
    <input type="hidden" name="package_id" value={pkg.id} />
    <Button type="submit" className="w-full">
        Buy Now
    </Button>
</Form>;
```

This uses Wayfinder's generated action with Inertia's `<Form>` component to create a POST request that triggers the checkout flow. The form includes a hidden `package_id` field matching the package's ID.

### 7. Success Page (`resources/js/pages/credits/checkout-success.tsx`)

Create a post-checkout success page that confirms the payment was received:

```tsx
export default function CheckoutSuccess() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payment Successful" />
            <div className="px-4 py-6">
                <div className="mx-auto max-w-md text-center">
                    <CheckCircle className="mx-auto h-16 w-16 text-green-500" />
                    <h2 className="mt-4 text-2xl font-semibold">
                        Payment Successful!
                    </h2>
                    <p className="mt-2 text-muted-foreground">
                        Your payment has been received. Credits will be added to
                        your account shortly.
                    </p>
                    <Button asChild className="mt-6">
                        <Link href={creditsIndex().url}>Back to Credits</Link>
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
```

The page uses a simple centered layout with a success icon, confirmation message, and a link back to the credits page. It intentionally says "Credits will be added shortly" because the actual crediting happens via webhook (E001-F062).

### 8. Cancel Page (`resources/js/pages/credits/checkout-cancel.tsx`)

Create a cancel page for when the user abandons checkout:

```tsx
export default function CheckoutCancel() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payment Cancelled" />
            <div className="px-4 py-6">
                <div className="mx-auto max-w-md text-center">
                    <XCircle className="mx-auto h-16 w-16 text-muted-foreground" />
                    <h2 className="mt-4 text-2xl font-semibold">
                        Payment Cancelled
                    </h2>
                    <p className="mt-2 text-muted-foreground">
                        Your payment was not completed. No charges were made.
                    </p>
                    <Button asChild className="mt-6">
                        <Link href={creditsIndex().url}>Back to Credits</Link>
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
```

### 9. Testing Strategy

**Feature tests** for the checkout flow:

- Authenticated verified user can initiate checkout with a valid package ID (mocking Stripe)
- Invalid package ID returns validation error
- Missing package ID returns validation error
- Guests are redirected to login
- Unverified users are redirected to verification notice
- Success page is accessible to authenticated users
- Cancel page is accessible to authenticated users

**Unit tests** for the CheckoutRequest:

- Validation passes with a valid package ID
- Validation fails with an invalid package ID
- Validation fails without a package ID

The Stripe API calls must be mocked in tests. Use Mockery to mock `\Stripe\Checkout\Session::create()` to return a mock session object with a URL. This avoids real API calls during testing.

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/config/services.php` -- Existing third-party services config. Will be modified to add Stripe API key and webhook secret configuration.
- `/Users/young/Nextcloud/dev/Itervel/config/credits.php` -- Credit package config created by E001-F060. Defines the three packages with prices in cents and the currency. Referenced by the checkout controller for package lookup and Stripe session creation.
- `/Users/young/Nextcloud/dev/Itervel/app/ValueObjects/CreditPackage.php` -- Value object created by E001-F060. Provides `findById()` to look up packages by ID, and `price`, `credits`, `name` properties used when creating the Stripe checkout session.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Controller.php` -- Base controller class. The new `StripeCheckoutController` will extend this.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/CreditPackageController.php` -- Controller created by E001-F060. Referenced for understanding the Inertia rendering pattern. The checkout route complements this controller's pricing page.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/ProfileController.php` -- Reference controller showing patterns for Inertia page rendering and redirect responses.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/Settings/ProfileUpdateRequest.php` -- Reference Form Request showing validation rule patterns with array syntax.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/Settings/ProfileDeleteRequest.php` -- Reference Form Request for validation patterns.
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Main web routes file. The checkout routes will be added here within an authenticated middleware group.
- `/Users/young/Nextcloud/dev/Itervel/routes/settings.php` -- Reference for route registration patterns with middleware groups.
- `/Users/young/Nextcloud/dev/Itervel/bootstrap/app.php` -- Application bootstrap. Referenced for middleware configuration.
- `/Users/young/Nextcloud/dev/Itervel/.env.example` -- Environment variable template. Will be modified to add Stripe environment variables.
- `/Users/young/Nextcloud/dev/Itervel/composer.json` -- Composer configuration. Stripe PHP SDK will be added as a dependency.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/credits/pricing.tsx` -- Pricing page created by E001-F060. Will be modified to wire "Buy Now" buttons to the checkout flow using Inertia Form submission.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/dashboard.tsx` -- Reference for Inertia page component patterns (AppLayout, breadcrumbs, Head).
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/profile.tsx` -- Reference for Inertia page with Form component and Wayfinder action imports.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/button.tsx` -- Button UI component. Used in success/cancel pages and updated pricing page.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/heading.tsx` -- Heading component. May be used on success/cancel pages.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/app-layout.tsx` -- Main app layout. Success and cancel pages use this layout.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/lib/utils.ts` -- Utility functions including `cn()` for class merging.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/index.ts` -- SharedData type definition. Referenced for type imports.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/navigation.ts` -- BreadcrumbItem type. Referenced for breadcrumb setup in pages.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/DashboardTest.php` -- Reference for testing authenticated Inertia page access patterns.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/ProfileUpdateTest.php` -- Reference for testing form submissions and validation errors.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration. Feature tests use `RefreshDatabase` automatically.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` -- User factory for test setup.
- `/Users/young/Nextcloud/dev/Itervel/vite.config.ts` -- Vite config with Wayfinder plugin. Referenced for understanding auto-generated route files.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/StripeCheckoutController.php` -- Controller with `store()` method that creates a Stripe Checkout Session and redirects the user to Stripe, `success()` method that renders the post-payment success page, and `cancel()` method that renders the cancellation page.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/CheckoutRequest.php` -- Form Request class that validates the `package_id` field, ensuring it is a string that matches a valid credit package ID from config.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/credits/checkout-success.tsx` -- Inertia page component showing a success message after payment, with a green check icon and a link back to the credits page.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/credits/checkout-cancel.tsx` -- Inertia page component showing a cancellation message when the user abandons checkout, with a link back to the credits page.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Credits/StripeCheckoutTest.php` -- Feature tests for the Stripe checkout flow including session creation (with mocked Stripe API), validation errors, authentication guards, and success/cancel page access.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: stripe-checkout-backend-dev
    - Role: Installs the Stripe PHP SDK, creates the Stripe configuration, CheckoutRequest form request, StripeCheckoutController, and route registration. Handles all PHP backend work including environment variable setup.
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: stripe-checkout-frontend-dev
    - Role: Updates the pricing page to wire "Buy Now" buttons to the checkout flow, creates the checkout success and cancel pages, and ensures proper styling and responsive layout.
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: stripe-checkout-test-dev
    - Role: Writes feature tests for the checkout flow with mocked Stripe API calls, validation tests, and authentication guard tests.
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: stripe-checkout-reviewer
    - Role: Validates the complete Stripe checkout feature against acceptance criteria, runs all tests, checks types, runs linting and formatting.
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Install Stripe SDK and Create Backend Checkout Infrastructure

- **Task ID**: create-stripe-checkout-backend
- **Depends On**: none
- **Assigned To**: stripe-checkout-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- Install the Stripe PHP SDK: `composer require stripe/stripe-php --no-interaction`
- Modify `/Users/young/Nextcloud/dev/Itervel/config/services.php` to add Stripe configuration. Add the following array entry after the existing `'slack'` entry:
    ```php
    'stripe' => [
        'secret' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],
    ```
- Modify `/Users/young/Nextcloud/dev/Itervel/.env.example` to add Stripe environment variables. Add the following lines before the `VITE_APP_NAME` line:
    ```
    STRIPE_SECRET_KEY=
    STRIPE_WEBHOOK_SECRET=
    ```
- Create the Form Request. Manually create `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/CheckoutRequest.php`:
    - Namespace: `App\Http\Requests`
    - Extends `Illuminate\Foundation\Http\FormRequest`
    - `authorize()` returns `true` (authorization is handled by middleware)
    - `rules()` returns:
        ```php
        return [
            'package_id' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail) {
                if (CreditPackage::findById($value) === null) {
                    $fail('The selected credit package is invalid.');
                }
            }],
        ];
        ```
    - Import `App\ValueObjects\CreditPackage`
    - Add PHPDoc blocks for both methods
- Create the controller. Manually create `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/StripeCheckoutController.php`:
    - Namespace: `App\Http\Controllers`
    - Extends `Controller`
    - Import: `App\Http\Requests\CheckoutRequest`, `App\ValueObjects\CreditPackage`, `Illuminate\Http\RedirectResponse`, `Inertia\Inertia`, `Inertia\Response`, `Stripe\Checkout\Session`, `Stripe\Stripe`
    - Method `store(CheckoutRequest $request): RedirectResponse`:

        ```php
        public function store(CheckoutRequest $request): RedirectResponse
        {
            $package = CreditPackage::findById($request->validated('package_id'));

            Stripe::setApiKey(config('services.stripe.secret'));

            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => config('credits.currency'),
                        'product_data' => [
                            'name' => $package->name . ' Credit Package',
                            'description' => $package->credits . ' video credits',
                        ],
                        'unit_amount' => $package->price,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => route('credits.checkout.success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('credits.checkout.cancel'),
                'client_reference_id' => (string) $request->user()->id,
                'metadata' => [
                    'user_id' => (string) $request->user()->id,
                    'package_id' => $package->id,
                    'credits' => (string) $package->credits,
                ],
            ]);

            return redirect()->away($session->url);
        }
        ```

    - Method `success(): Response`:
        ```php
        public function success(): Response
        {
            return Inertia::render('credits/checkout-success');
        }
        ```
    - Method `cancel(): Response`:
        ```php
        public function cancel(): Response
        {
            return Inertia::render('credits/checkout-cancel');
        }
        ```
    - Add PHPDoc blocks for all methods

- Add routes to `/Users/young/Nextcloud/dev/Itervel/routes/web.php`:
    - Add `use App\Http\Controllers\StripeCheckoutController;` at the top imports
    - Add a route group before the `require __DIR__.'/settings.php';` line. The routes should be placed after the existing `credits` route from E001-F060 (the `Route::get('credits', ...)` line):
        ```php
        Route::middleware(['auth', 'verified'])->group(function () {
            Route::post('credits/checkout', [StripeCheckoutController::class, 'store'])
                ->name('credits.checkout.store');
            Route::get('credits/checkout/success', [StripeCheckoutController::class, 'success'])
                ->name('credits.checkout.success');
            Route::get('credits/checkout/cancel', [StripeCheckoutController::class, 'cancel'])
                ->name('credits.checkout.cancel');
        });
        ```
    - Note: If E001-F060 already has a middleware group, the checkout routes can be added inside it. If the credits route is standalone (not in a group), create a new group for the checkout routes.
- Run `vendor/bin/pint --dirty` to format all changed PHP files
- Verify routes are registered: `php artisan route:list --name=credits.checkout`

### 2. Create Checkout Success and Cancel Pages, Update Pricing Page

- **Task ID**: create-checkout-frontend
- **Depends On**: create-stripe-checkout-backend
- **Assigned To**: stripe-checkout-frontend-dev
- **Agent Type**: coder
- **Parallel**: false
- Create the directory `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/credits/` if it does not exist (it should already exist from E001-F060)
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/credits/checkout-success.tsx`:
    - Import `{ Head, Link }` from `@inertiajs/react`
    - Import `AppLayout` from `@/layouts/app-layout`
    - Import `{ Button }` from `@/components/ui/button`
    - Import `type { BreadcrumbItem }` from `@/types`
    - Import `{ CheckCircle }` from `lucide-react`
    - After running `npm run build`, import the Wayfinder route for credits index: `import { index as creditsIndex } from '@/routes/credits'`
    - Set up breadcrumbs:
        ```tsx
        const breadcrumbs: BreadcrumbItem[] = [
            { title: 'Buy Credits', href: creditsIndex().url },
            { title: 'Payment Successful', href: '#' },
        ];
        ```
    - Export default function `CheckoutSuccess()` that renders:
        - `AppLayout` with breadcrumbs
        - `Head` with title "Payment Successful"
        - A centered container `div` with `px-4 py-6`:
            - Inner `div` with `mx-auto max-w-md text-center` containing:
                - A `CheckCircle` icon with `mx-auto h-16 w-16 text-green-500`
                - An `h2` with `mt-4 text-2xl font-semibold` text "Payment Successful!"
                - A `p` with `mt-2 text-muted-foreground` text "Your payment has been received. Credits will be added to your account shortly."
                - A `p` with `mt-1 text-sm text-muted-foreground` text "You will receive a receipt email from Stripe."
                - A `Button` with `asChild` and `className="mt-6"` wrapping an Inertia `Link` with `href={creditsIndex().url}` text "Back to Credits"
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/credits/checkout-cancel.tsx`:
    - Import `{ Head, Link }` from `@inertiajs/react`
    - Import `AppLayout` from `@/layouts/app-layout`
    - Import `{ Button }` from `@/components/ui/button`
    - Import `type { BreadcrumbItem }` from `@/types`
    - Import `{ XCircle }` from `lucide-react`
    - After running `npm run build`, import the Wayfinder route: `import { index as creditsIndex } from '@/routes/credits'`
    - Set up breadcrumbs:
        ```tsx
        const breadcrumbs: BreadcrumbItem[] = [
            { title: 'Buy Credits', href: creditsIndex().url },
            { title: 'Payment Cancelled', href: '#' },
        ];
        ```
    - Export default function `CheckoutCancel()` that renders:
        - `AppLayout` with breadcrumbs
        - `Head` with title "Payment Cancelled"
        - A centered container `div` with `px-4 py-6`:
            - Inner `div` with `mx-auto max-w-md text-center` containing:
                - An `XCircle` icon with `mx-auto h-16 w-16 text-muted-foreground`
                - An `h2` with `mt-4 text-2xl font-semibold` text "Payment Cancelled"
                - A `p` with `mt-2 text-muted-foreground` text "Your payment was not completed. No charges were made."
                - A `Button` with `asChild` and `className="mt-6"` wrapping an Inertia `Link` with `href={creditsIndex().url}` text "Back to Credits"
- Modify `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/credits/pricing.tsx` to wire the "Buy Now" buttons:
    - Add import: `import { Form } from '@inertiajs/react'` (add `Form` to existing `@inertiajs/react` import if `Head` is already imported from there)
    - Add import for the Wayfinder action. After running `npm run build`: `import StripeCheckoutController from '@/actions/App/Http/Controllers/StripeCheckoutController'`
    - Replace each "Buy Now" `Button` placeholder with an Inertia `Form` submission:
        ```tsx
        <CardFooter>
            <Form {...StripeCheckoutController.store.form()} className="w-full">
                {({ processing }) => (
                    <>
                        <input type="hidden" name="package_id" value={pkg.id} />
                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                        >
                            {processing ? 'Redirecting...' : 'Buy Now'}
                        </Button>
                    </>
                )}
            </Form>
        </CardFooter>
        ```
    - The `Form` component uses Wayfinder's generated `StripeCheckoutController.store.form()` to POST to the checkout route. The render prop pattern provides the `processing` state to disable the button during submission. A hidden `package_id` input passes the selected package ID.
    - Note: The `Form` render prop pattern `{({ processing }) => (...)}` is the same pattern used in `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/profile.tsx`.
- Run `npm run build` to generate Wayfinder route/action functions
- Run `npm run types` to verify TypeScript types compile correctly
- Run `npm run lint:fix` to fix any linting issues
- Run `npm run format` to format with Prettier

### 3. Write Feature Tests for Checkout Flow

- **Task ID**: write-stripe-checkout-tests
- **Depends On**: create-stripe-checkout-backend
- **Assigned To**: stripe-checkout-test-dev
- **Agent Type**: coder
- **Parallel**: true
- Create the feature test directory `tests/Feature/Credits/` if it does not exist (it should already exist from E001-F060)
- Create the feature test using: `php artisan make:test Credits/StripeCheckoutTest --pest --no-interaction`
- Write the following tests in `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Credits/StripeCheckoutTest.php`:
    - Add imports at the top:
        ```php
        use App\Models\User;
        use Stripe\Checkout\Session;
        use Stripe\Stripe;
        ```
    - `test('authenticated user can initiate checkout with valid package')`:

        ```php
        $user = User::factory()->create();

        $mockSession = $this->mock(Session::class);

        // Mock the Stripe Session::create static method
        $this->partialMock(Session::class, function ($mock) {
            $mock->shouldReceive('create')
                ->once()
                ->andReturn((object) ['url' => 'https://checkout.stripe.com/test-session']);
        });

        $response = $this->actingAs($user)
            ->post(route('credits.checkout.store'), [
                'package_id' => 'starter',
            ]);

        $response->assertRedirect('https://checkout.stripe.com/test-session');
        ```

        Note: Mocking Stripe's static methods can be tricky. An alternative approach is to use a custom service class wrapper that can be mocked more easily. If `partialMock` on Stripe classes does not work, create a simple wrapper:
        - Create a test helper or use `Http::fake()` if Stripe uses HTTP under the hood, or
        - Use Mockery's `alias` mock: `Mockery::mock('alias:' . Session::class)` to mock the static `create()` method
        - The test should verify that:
            1. The response is a redirect to the Stripe checkout URL
            2. The correct package data is sent to Stripe (via the mock expectations)

    - `test('checkout requires a valid package id')`:

        ```php
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('credits.checkout.store'), [
                'package_id' => 'nonexistent-package',
            ]);

        $response->assertSessionHasErrors('package_id');
        ```

    - `test('checkout requires package id to be present')`:

        ```php
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('credits.checkout.store'), []);

        $response->assertSessionHasErrors('package_id');
        ```

    - `test('checkout requires package id to be a string')`:

        ```php
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('credits.checkout.store'), [
                'package_id' => 123,
            ]);

        $response->assertSessionHasErrors('package_id');
        ```

    - `test('guests are redirected to login from checkout')`:

        ```php
        $response = $this->post(route('credits.checkout.store'), [
            'package_id' => 'starter',
        ]);

        $response->assertRedirect(route('login'));
        ```

    - `test('unverified users are redirected from checkout')`:

        ```php
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)
            ->post(route('credits.checkout.store'), [
                'package_id' => 'starter',
            ]);

        $response->assertRedirect(route('verification.notice'));
        ```

    - `test('checkout success page is accessible to authenticated users')`:

        ```php
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('credits.checkout.success'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('credits/checkout-success')
        );
        ```

    - `test('checkout cancel page is accessible to authenticated users')`:

        ```php
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('credits.checkout.cancel'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('credits/checkout-cancel')
        );
        ```

    - `test('guests are redirected from success page')`:

        ```php
        $response = $this->get(route('credits.checkout.success'));

        $response->assertRedirect(route('login'));
        ```

    - `test('guests are redirected from cancel page')`:

        ```php
        $response = $this->get(route('credits.checkout.cancel'));

        $response->assertRedirect(route('login'));
        ```

    - `test('checkout works for each valid package id')`:

        ```php
        $user = User::factory()->create();

        foreach (['starter', 'popular', 'best-value'] as $packageId) {
            // Mock Stripe for each iteration
            $this->mock(Session::class, function ($mock) {
                $mock->shouldReceive('create')
                    ->once()
                    ->andReturn((object) ['url' => 'https://checkout.stripe.com/test']);
            });

            $response = $this->actingAs($user)
                ->post(route('credits.checkout.store'), [
                    'package_id' => $packageId,
                ]);

            $response->assertRedirect();
        }
        ```

    - IMPORTANT: Mocking Stripe's static `Session::create()` method requires careful handling. The recommended approach is:
        1. First try using Mockery's `alias` mock to override the static method
        2. If that does not work in the Pest/PHPUnit context, an alternative is to wrap the Stripe call in a service class (e.g., `App\Services\StripeService`) and inject/mock that instead
        3. The simplest approach for testability may be to create a small `App\Services\StripeCheckoutService` class that wraps `Session::create()`, inject it into the controller, and mock it in tests. This is an acceptable refactoring if direct static mocking proves difficult.
        4. If creating a wrapper service, the controller's `store` method would receive `StripeCheckoutService $stripe` as a parameter (method injection) and call `$stripe->createSession($params)`.
    - After writing all tests, adapt the Stripe mocking approach to what actually works in the test environment. The key requirement is that tests do not make real Stripe API calls.

- Run the tests: `php artisan test tests/Feature/Credits/StripeCheckoutTest.php --compact`
- Ensure all tests pass
- Run `vendor/bin/pint --dirty` to format any PHP files

### 4. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-stripe-checkout-backend, create-checkout-frontend, write-stripe-checkout-tests
- **Assigned To**: stripe-checkout-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed below
- Verify the Stripe PHP SDK is installed: check `composer.json` includes `stripe/stripe-php` in the `require` section
- Verify `/Users/young/Nextcloud/dev/Itervel/config/services.php` contains the `stripe` configuration block with `secret` and `webhook_secret` keys
- Verify `/Users/young/Nextcloud/dev/Itervel/.env.example` contains `STRIPE_SECRET_KEY` and `STRIPE_WEBHOOK_SECRET` environment variables
- Verify the Form Request `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/CheckoutRequest.php` exists with validation for `package_id` that checks against `CreditPackage::findById()`
- Verify the controller `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/StripeCheckoutController.php` exists with three methods: `store()`, `success()`, `cancel()`
- Verify the `store()` method creates a Stripe Checkout Session with correct parameters: `payment_method_types`, `line_items` with `price_data`, `mode`, `success_url`, `cancel_url`, `client_reference_id`, and `metadata` containing `user_id`, `package_id`, and `credits`
- Verify three routes exist: `php artisan route:list --name=credits.checkout`
    - `POST /credits/checkout` -> `credits.checkout.store`
    - `GET /credits/checkout/success` -> `credits.checkout.success`
    - `GET /credits/checkout/cancel` -> `credits.checkout.cancel`
- Verify the success page `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/credits/checkout-success.tsx` exists with a success message and link back to credits
- Verify the cancel page `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/credits/checkout-cancel.tsx` exists with a cancellation message and link back to credits
- Verify `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/credits/pricing.tsx` has been updated with Inertia `Form` components that POST to the checkout route with a hidden `package_id` field
- Verify no existing "Buy Now" placeholder buttons remain in the pricing page
- Run checkout tests: `php artisan test tests/Feature/Credits/StripeCheckoutTest.php --compact`
- Run all credit-related tests: `php artisan test tests/Feature/Credits --compact`
- Run full test suite for regression check: `php artisan test --compact`
- Run TypeScript type checking: `npm run types`
- Run ESLint: `npm run lint`
- Run Prettier formatting check: `npm run format:check`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Verify the checkout metadata includes all required fields for E001-F062 webhook processing: `user_id`, `package_id`, `credits`
- Confirm all acceptance criteria are met

## Acceptance Criteria

- The `stripe/stripe-php` Composer package is installed
- Stripe API credentials are configured in `config/services.php` with `stripe.secret` and `stripe.webhook_secret` keys
- Environment variables `STRIPE_SECRET_KEY` and `STRIPE_WEBHOOK_SECRET` are defined in `.env.example`
- A `CheckoutRequest` Form Request exists at `app/Http/Requests/CheckoutRequest.php` that validates `package_id` as required, string, and matching a valid credit package
- A `StripeCheckoutController` exists at `app/Http/Controllers/StripeCheckoutController.php` with `store()`, `success()`, and `cancel()` methods
- The `store()` method creates a Stripe Checkout Session with inline `price_data`, uses the package price in cents, includes `user_id`/`package_id`/`credits` in metadata, and redirects to Stripe's checkout URL
- Three routes are registered: `POST /credits/checkout` (credits.checkout.store), `GET /credits/checkout/success` (credits.checkout.success), `GET /credits/checkout/cancel` (credits.checkout.cancel)
- All checkout routes are protected by `auth` and `verified` middleware
- Guests are redirected to the login page when accessing checkout routes
- Unverified users are redirected to the email verification notice
- The pricing page's "Buy Now" buttons submit an Inertia Form POST to the checkout route with the selected package ID
- The buttons show a loading/disabled state during form submission
- A checkout success page exists showing a confirmation message with a green check icon
- A checkout cancel page exists showing a cancellation message with a link to return to pricing
- The success page message indicates credits will be added shortly (webhook-driven)
- Invalid package IDs are rejected with a validation error
- All feature tests pass for the checkout flow (with mocked Stripe API)
- All existing tests pass without regressions
- TypeScript types compile without errors
- ESLint and Prettier checks pass
- PHP code passes Pint formatting

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Verify Stripe PHP SDK is installed
composer show stripe/stripe-php

# Verify routes are registered
php artisan route:list --name=credits.checkout

# Run Stripe checkout tests
php artisan test tests/Feature/Credits/StripeCheckoutTest.php --compact

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

- This feature depends on E001-F060 (Credit Package Purchase) which creates the pricing page, CreditPackage value object, and credit configuration. The checkout controller uses `CreditPackage::findById()` to look up the selected package and `config('credits.currency')` for the Stripe currency.
- The feature does NOT add credits to the user's account. Credit addition is handled by E001-F062 (Payment Webhook Processing) which listens for Stripe's `checkout.session.completed` event and credits the user. This separation is intentional: the checkout session creation (this feature) and the fulfillment (E001-F062) are decoupled to avoid double-crediting if the user refreshes the success page or if there are network issues.
- The Stripe session metadata includes `user_id`, `package_id`, and `credits`. These fields are critical for E001-F062 to know which user to credit, which package was purchased, and how many credits to add. The `client_reference_id` provides an additional user identifier per Stripe's recommendation.
- Prices are stored in cents in `config/credits.php` (e.g., 2000 = $20.00), which matches Stripe's `unit_amount` format. No price conversion is needed.
- The `{CHECKOUT_SESSION_ID}` template in `success_url` is a Stripe convention. Stripe replaces it with the actual session ID when redirecting the user. This can be used by E001-F062 or future features to retrieve session details.
- The `cancel_url` points to a simple cancel page that does not attempt to create or modify any data. The user can simply click "Back to Credits" to return to the pricing page.
- For testing, Stripe's static `Session::create()` method needs to be mocked. The recommended approach is to wrap it in a small service class for better testability. If the implementing agent encounters difficulty mocking static methods, they should create an `App\Services\StripeCheckoutService` class and inject it.
- Receipt emails are handled by Stripe automatically when the checkout session completes. No application-level email sending is needed for this feature.
- The `webhook_secret` is included in the `config/services.php` Stripe block even though this feature does not use it directly. It is included for E001-F062 and E001-F090 to use. If the implementing agent prefers, they can defer this to those features.
- All commands should be run inside the Docker container. Use `make shell` to enter the container, or prefix with `docker compose exec app`.

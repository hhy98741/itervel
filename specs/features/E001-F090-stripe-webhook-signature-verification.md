# Feature: Stripe Webhook Signature Verification

**Epic**: E001-ai-powered-faceless-video-creation-platform.md
**Feature**: E001-F090
**Dependencies**: E001-F062

## Task Description

Stripe Webhook Signature Verification extracts the inline Stripe signature verification logic from the `StripeWebhookController` (created by E001-F062) into a dedicated, reusable middleware. This ensures that every incoming Stripe webhook request is cryptographically verified using Stripe's HMAC-SHA256 signature before it ever reaches the controller. Invalid or tampered requests are rejected with a 400 response and logged as security warnings. By moving this concern into middleware, the verification becomes a single, well-tested, reusable layer that can protect any future Stripe webhook routes without duplicating verification logic in controllers.

**What it does**: Verifies the authenticity of incoming Stripe webhook events to prevent tampering.

**Expected outcome**: Every incoming Stripe webhook request is verified using Stripe's signature verification before being processed. Invalid or tampered requests are rejected and logged.

This feature depends on E001-F062 (Payment Webhook Processing), which creates:

- The `StripeWebhookController` at `app/Http/Controllers/StripeWebhookController.php` as an invokable controller that handles `checkout.session.completed`, `payment_intent.payment_failed`, and `charge.refunded` events
- The `stripe/webhook` route registered in `routes/web.php` excluded from CSRF verification
- Inline signature verification using `Stripe\Webhook::constructEvent()` directly in the controller's `__invoke()` method
- The `Stripe-Signature` header reading and `config('services.stripe.webhook_secret')` usage within the controller
- The `stripe/stripe-php` Composer package (installed by E001-F061)
- Stripe configuration in `config/services.php` with `stripe.secret` and `stripe.webhook_secret` keys
- Environment variable `STRIPE_WEBHOOK_SECRET` in `.env.example`

This feature refactors the verification from inline controller logic into a middleware, then updates the controller to remove its own verification and rely on the middleware. The webhook route is updated to apply the middleware. Existing tests are updated to reflect the architectural change.

## Objective

Extract Stripe webhook signature verification into a dedicated `VerifyStripeWebhookSignature` middleware that validates the `Stripe-Signature` header against the raw request payload using the configured webhook secret. Apply this middleware to the `stripe/webhook` route. Refactor the `StripeWebhookController` to remove its inline verification logic, making the controller responsible only for event dispatching and handling. The verified Stripe event object is passed from middleware to controller via the request attributes. When complete, the signature verification is a clean, tested, reusable middleware layer and the controller is simpler and focused solely on business logic.

## Solution Approach

### 1. Create the `VerifyStripeWebhookSignature` Middleware

Create a new middleware at `app/Http/Middleware/VerifyStripeWebhookSignature.php` that:

1. Reads the raw request body via `$request->getContent()`
2. Reads the `Stripe-Signature` header via `$request->header('Stripe-Signature')`
3. Reads the webhook secret from `config('services.stripe.webhook_secret')`
4. Calls `Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret)` inside a try-catch
5. On `SignatureVerificationException`, logs a warning and returns a 400 JSON response: `{'error': 'Invalid signature'}`
6. On success, stores the verified `$event` object on the request via `$request->attributes->set('stripe_event', $event)` so the controller can retrieve it
7. Calls `$next($request)` to pass through to the controller

The middleware follows the same pattern as `HandleAppearance` middleware in the project: a `handle()` method with the standard `(Request $request, Closure $next): Response` signature.

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response;

class VerifyStripeWebhookSignature
{
    /**
     * Verify the Stripe webhook signature before passing to the controller.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature verification failed.', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $request->attributes->set('stripe_event', $event);

        return $next($request);
    }
}
```

Key design decisions:

- **Symfony request attributes**: Using `$request->attributes` (the Symfony ParameterBag) is the standard Laravel pattern for passing data between middleware and controllers. This avoids polluting the request input.
- **IP logging**: The middleware logs the request IP on verification failure for security auditing.
- **No fallback**: If the webhook secret is not configured (`null`), `Webhook::constructEvent()` will throw an exception, which is the correct behavior -- the endpoint should not process unverified requests.

### 2. Refactor the `StripeWebhookController`

The existing `StripeWebhookController` (from E001-F062) has inline signature verification at the top of its `__invoke()` method. This must be removed and replaced with reading the verified event from request attributes.

**Before** (lines from E001-F062's controller):

```php
public function __invoke(Request $request): JsonResponse
{
    $payload = $request->getContent();
    $sigHeader = $request->header('Stripe-Signature');
    $webhookSecret = config('services.stripe.webhook_secret');

    try {
        $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
    } catch (SignatureVerificationException $e) {
        Log::warning('Stripe webhook signature verification failed.', [
            'error' => $e->getMessage(),
        ]);

        return response()->json(['error' => 'Invalid signature'], 400);
    }

    // ... rest of handler
}
```

**After**:

```php
public function __invoke(Request $request): JsonResponse
{
    $event = $request->attributes->get('stripe_event');

    // Idempotency check: prevent duplicate processing
    // ... rest of handler unchanged
}
```

The refactored controller:

- Removes the `use Stripe\Exception\SignatureVerificationException;` and `use Stripe\Webhook;` imports (no longer needed)
- Removes the try-catch block for signature verification
- Retrieves the verified event from `$request->attributes->get('stripe_event')`
- All handler methods (`handleCheckoutSessionCompleted`, `handlePaymentFailed`, `handleChargeRefunded`) remain unchanged
- The idempotency check (Cache-based) remains unchanged
- All logging and error handling for business logic remains unchanged

### 3. Register the Middleware on the Webhook Route

Update the webhook route in `routes/web.php` to apply the middleware:

**Before** (from E001-F062):

```php
Route::post('stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');
```

**After**:

```php
use App\Http\Middleware\VerifyStripeWebhookSignature;

Route::post('stripe/webhook', StripeWebhookController::class)
    ->middleware(VerifyStripeWebhookSignature::class)
    ->name('stripe.webhook');
```

The middleware is applied directly on the route rather than globally or in a middleware group, because it is specific to Stripe webhook endpoints. If additional Stripe webhook routes are needed in the future, they can use the same middleware.

### 4. Update Tests

The existing tests in `tests/Feature/Webhooks/StripeWebhookTest.php` (from E001-F062) need to be updated to account for the middleware extraction:

1. **Signature verification tests**: The test `'webhook rejects request with invalid stripe signature'` should continue to pass as-is because the middleware handles the rejection in the same way the controller previously did.

2. **Mocking approach**: Tests that mock `Webhook::constructEvent()` will continue to work because the mock intercepts at the Stripe SDK level, which is now called from the middleware instead of the controller. The middleware sets `$request->attributes->set('stripe_event', $event)`, and the controller reads it -- the test mocking of `Webhook::constructEvent()` still provides the event object.

3. **New middleware-specific test**: Add a dedicated test that verifies the middleware sets the `stripe_event` attribute on the request and passes it through to the controller. This can be tested indirectly by checking that the controller successfully processes events (which it can only do if the middleware provided the event).

4. **Middleware isolation test** (optional but recommended): A test that directly instantiates the middleware and calls its `handle()` method with a mocked request, verifying it sets the attribute on success and returns 400 on failure.

### 5. Testing Strategy

The feature tests should verify:

- **Invalid signature rejection**: POST to `stripe/webhook` with an invalid or missing `Stripe-Signature` header returns 400 with `{'error': 'Invalid signature'}`.
- **Valid signature passes through**: POST with a valid (mocked) signature reaches the controller and processes the event.
- **Middleware sets stripe_event attribute**: The controller can retrieve the event from `$request->attributes->get('stripe_event')`.
- **Missing webhook secret**: If `config('services.stripe.webhook_secret')` is null, requests are rejected (the Stripe SDK throws when secret is null).
- **Logging on failure**: A warning is logged when signature verification fails.
- **Existing controller tests still pass**: All E001-F062 tests continue to pass after the refactor.

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/StripeWebhookController.php` -- The webhook controller created by E001-F062. Must be refactored to remove inline signature verification and instead read the verified event from `$request->attributes->get('stripe_event')`. Remove `Stripe\Webhook` and `Stripe\Exception\SignatureVerificationException` imports.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Middleware/HandleAppearance.php` -- Existing middleware example. Use as a pattern reference for the middleware class structure, PHPDoc format, and `handle()` method signature.
- `/Users/young/Nextcloud/dev/Itervel/bootstrap/app.php` -- Application bootstrap with middleware configuration. The CSRF exception for `stripe/webhook` (added by E001-F062) is here. This file does NOT need modification -- the new middleware is applied per-route, not globally.
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Main web routes file. The `stripe/webhook` route (added by E001-F062) must be updated to apply the `VerifyStripeWebhookSignature` middleware.
- `/Users/young/Nextcloud/dev/Itervel/config/services.php` -- Third-party services config. Contains `stripe.webhook_secret` (added by E001-F061). Referenced by the middleware for the webhook secret value.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Webhooks/StripeWebhookTest.php` -- Existing webhook tests from E001-F062. Must be updated to work with the middleware-based verification and supplemented with middleware-specific tests.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration. Feature tests use `RefreshDatabase` automatically.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Controller.php` -- Base controller class. The `StripeWebhookController` extends this.
- `/Users/young/Nextcloud/dev/Itervel/config/logging.php` -- Logging configuration. The middleware uses `Log::warning()` for failed signature verification.
- `/Users/young/Nextcloud/dev/Itervel/.env.example` -- Environment variable template. Already has `STRIPE_WEBHOOK_SECRET` from E001-F061.
- `/Users/young/Nextcloud/dev/Itervel/composer.json` -- Composer configuration. Already has `stripe/stripe-php` from E001-F061.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/app/Http/Middleware/VerifyStripeWebhookSignature.php` -- New middleware that verifies Stripe webhook signatures using `Stripe\Webhook::constructEvent()`. Reads the raw payload and `Stripe-Signature` header, validates against the configured webhook secret, and stores the verified event on `$request->attributes` for the controller. Returns 400 JSON response on failure with a logged warning.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: signature-middleware-dev
    - Role: Creates the VerifyStripeWebhookSignature middleware, refactors the StripeWebhookController to remove inline verification, and updates the webhook route to apply the middleware.
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: signature-test-dev
    - Role: Updates existing webhook tests to work with the middleware-based architecture and adds new middleware-specific tests for signature verification, attribute passing, logging, and edge cases.
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: signature-reviewer
    - Role: Validates the complete middleware extraction against acceptance criteria, runs all tests, checks code formatting, and verifies that the refactored architecture is clean and all existing functionality is preserved.
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Create Middleware and Refactor Controller

- **Task ID**: create-middleware-refactor-controller
- **Depends On**: none
- **Assigned To**: signature-middleware-dev
- **Agent Type**: coder
- **Parallel**: true
- Read the existing middleware pattern from `/Users/young/Nextcloud/dev/Itervel/app/Http/Middleware/HandleAppearance.php` to match the project's code style and PHPDoc conventions.
- Create `/Users/young/Nextcloud/dev/Itervel/app/Http/Middleware/VerifyStripeWebhookSignature.php`:
    - Namespace: `App\Http\Middleware`
    - Imports: `Closure`, `Illuminate\Http\Request`, `Illuminate\Support\Facades\Log`, `Stripe\Exception\SignatureVerificationException`, `Stripe\Webhook`, `Symfony\Component\HttpFoundation\Response`
    - Add a PHPDoc block for the class: `Verify the Stripe webhook request signature.`
    - Method `handle(Request $request, Closure $next): Response`:
        - Add the standard PHPDoc block: `@param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next`
        - Read the raw payload: `$payload = $request->getContent();`
        - Read the signature header: `$sigHeader = $request->header('Stripe-Signature');`
        - Read the webhook secret: `$webhookSecret = config('services.stripe.webhook_secret');`
        - Wrap `Webhook::constructEvent($payload, $sigHeader, $webhookSecret)` in a try-catch for `SignatureVerificationException`
        - On catch: Log a warning with `'Stripe webhook signature verification failed.'` including `'error' => $e->getMessage()` and `'ip' => $request->ip()`, then return `response()->json(['error' => 'Invalid signature'], 400)`
        - On success: Store the event on the request: `$request->attributes->set('stripe_event', $event);`
        - Return `$next($request);`
- Read the existing controller at `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/StripeWebhookController.php` to understand its current structure.
- Refactor the controller's `__invoke()` method:
    - Remove the entire try-catch block for signature verification (the lines that call `Webhook::constructEvent()` and catch `SignatureVerificationException`)
    - Remove the lines that read `$payload`, `$sigHeader`, and `$webhookSecret`
    - Replace them with a single line: `$event = $request->attributes->get('stripe_event');`
    - Remove the `use Stripe\Exception\SignatureVerificationException;` import from the top of the file (no longer needed in the controller)
    - Remove the `use Stripe\Webhook;` import from the top of the file (no longer needed in the controller)
    - Keep ALL other code unchanged: idempotency check, event type matching, handler methods, logging
- Read the current `/Users/young/Nextcloud/dev/Itervel/routes/web.php` to see the webhook route.
- Update the webhook route in `routes/web.php`:
    - Add `use App\Http\Middleware\VerifyStripeWebhookSignature;` to the imports
    - Change the route from:
        ```php
        Route::post('stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');
        ```
    - To:
        ```php
        Route::post('stripe/webhook', StripeWebhookController::class)
            ->middleware(VerifyStripeWebhookSignature::class)
            ->name('stripe.webhook');
        ```
- Run `vendor/bin/pint --dirty` to format all changed PHP files.
- Verify the route is still registered with the middleware: `php artisan route:list --name=stripe.webhook`

### 2. Update and Write Tests for Middleware-Based Verification

- **Task ID**: update-write-tests
- **Depends On**: create-middleware-refactor-controller
- **Assigned To**: signature-test-dev
- **Agent Type**: coder
- **Parallel**: false
- Read the existing tests at `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Webhooks/StripeWebhookTest.php` to understand the current test structure and mocking approach.
- Read the middleware at `/Users/young/Nextcloud/dev/Itervel/app/Http/Middleware/VerifyStripeWebhookSignature.php` to understand what needs to be tested.
- Read the refactored controller at `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/StripeWebhookController.php` to verify the controller now reads from `$request->attributes->get('stripe_event')`.
- Update the existing tests in `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Webhooks/StripeWebhookTest.php`:
    - The mocking approach for `Webhook::constructEvent()` should still work because the middleware calls the same static method. The mock intercepts at the SDK level regardless of whether the caller is the middleware or the controller. Verify and adapt if needed.
    - Ensure the test for `'webhook rejects request with invalid stripe signature'` still works. This test should NOT mock `Webhook::constructEvent()` so the real verification fails. It should:
        1. Set `config(['services.stripe.webhook_secret' => 'whsec_test_secret']);`
        2. Send a POST to `stripe/webhook` with an invalid `Stripe-Signature` header
        3. Assert 400 status and `['error' => 'Invalid signature']` in the response
    - Ensure all existing handler tests (checkout completed, payment failed, charge refunded, idempotency, missing user, missing metadata, unhandled event, CSRF exemption, etc.) still pass with the middleware in place.
- Add the following new tests to the same file:
    - `test('webhook rejects request with missing stripe signature header')`:

        ```php
        config(['services.stripe.webhook_secret' => 'whsec_test_secret']);

        $response = $this->postJson('stripe/webhook');

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Invalid signature']);
        ```

    - `test('webhook logs warning with request ip on signature failure')`:

        ```php
        config(['services.stripe.webhook_secret' => 'whsec_test_secret']);
        Log::spy();

        $this->postJson('stripe/webhook', [], [
            'Stripe-Signature' => 'invalid_sig',
        ]);

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context) {
                return $message === 'Stripe webhook signature verification failed.'
                    && isset($context['error'])
                    && isset($context['ip']);
            })
            ->once();
        ```

    - `test('middleware passes verified event to controller via request attributes')`:
      This is tested indirectly by the existing handler tests -- if the controller successfully processes checkout/payment/refund events, the middleware must be correctly passing the event. However, add an explicit test:

        ```php
        $user = User::factory()->create(['video_credits' => 0]);

        $session = new \stdClass();
        $session->id = 'cs_test_attr';
        $session->payment_intent = 'pi_test_attr';
        $session->metadata = new \stdClass();
        $session->metadata->user_id = (string) $user->id;
        $session->metadata->package_id = 'starter';
        $session->metadata->credits = '5';

        // Mock Webhook::constructEvent to return a properly structured event
        mockStripeWebhookEvent('checkout.session.completed', $session, 'evt_attr_test');

        $response = $this->postJson('stripe/webhook', [], [
            'Stripe-Signature' => 'valid_mock_signature',
        ]);

        $response->assertOk();
        // The controller could only process this if it received the event via request attributes
        expect($user->fresh()->video_credits)->toBe(5);
        ```

    - If the existing `mockStripeWebhookEvent` helper function needs adaptation due to the middleware change, update it accordingly. The function should mock `Stripe\Webhook::constructEvent()` which is called from the middleware now (same static method, same behavior).

- Run the tests: `php artisan test tests/Feature/Webhooks/StripeWebhookTest.php --compact`
- If any tests fail due to the mocking approach not working with middleware, adapt the mocking strategy:
    - **Option A** (preferred): If Mockery `alias` mock of `Stripe\Webhook` works, keep it as-is.
    - **Option B**: If the alias mock does not work from middleware context, create a thin service wrapper `App\Services\StripeSignatureVerifier` that wraps `Webhook::constructEvent()`, bind it in the container, inject it into the middleware constructor, and mock it in tests. Then update the middleware to use `$this->verifier->verify($payload, $sigHeader, $webhookSecret)` instead of calling `Webhook::constructEvent()` directly.
- Run `vendor/bin/pint --dirty` to format any changed PHP files.
- Run the full test suite to check for regressions: `php artisan test --compact`

### 3. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-middleware-refactor-controller, update-write-tests
- **Assigned To**: signature-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed below
- Verify `/Users/young/Nextcloud/dev/Itervel/app/Http/Middleware/VerifyStripeWebhookSignature.php` exists with:
    - Correct namespace `App\Http\Middleware`
    - A `handle(Request $request, Closure $next): Response` method
    - A try-catch around `Webhook::constructEvent($payload, $sigHeader, $webhookSecret)`
    - `Log::warning()` with error message and IP on `SignatureVerificationException`
    - Returns `response()->json(['error' => 'Invalid signature'], 400)` on failure
    - Sets `$request->attributes->set('stripe_event', $event)` on success
    - Calls and returns `$next($request)` on success
    - PHPDoc blocks matching project conventions (see `HandleAppearance` for reference)
- Verify `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/StripeWebhookController.php`:
    - Does NOT contain `Stripe\Webhook` import
    - Does NOT contain `Stripe\Exception\SignatureVerificationException` import
    - Does NOT contain `Webhook::constructEvent()` call
    - Does NOT contain a try-catch for signature verification
    - DOES contain `$event = $request->attributes->get('stripe_event');` at the start of `__invoke()`
    - All handler methods (`handleCheckoutSessionCompleted`, `handlePaymentFailed`, `handleChargeRefunded`) are unchanged
    - Idempotency check via Cache is unchanged
    - Logging statements are unchanged
- Verify `/Users/young/Nextcloud/dev/Itervel/routes/web.php`:
    - Has `use App\Http\Middleware\VerifyStripeWebhookSignature;` import
    - The webhook route has `->middleware(VerifyStripeWebhookSignature::class)` applied
    - Route name is still `stripe.webhook`
- Verify the route registration: `php artisan route:list --name=stripe.webhook`
    - Method: POST
    - URI: stripe/webhook
    - Name: stripe.webhook
    - Middleware includes `VerifyStripeWebhookSignature`
- Verify `/Users/young/Nextcloud/dev/Itervel/bootstrap/app.php` still has the CSRF exception for `stripe/webhook` (unchanged from E001-F062)
- Verify tests cover:
    - Invalid signature returns 400
    - Missing signature header returns 400
    - Logging includes IP address on failure
    - Valid (mocked) signature allows event processing
    - All existing handler tests pass (checkout, payment failed, refund, idempotency, edge cases)
- Run webhook tests: `php artisan test tests/Feature/Webhooks/StripeWebhookTest.php --compact`
- Run full test suite: `php artisan test --compact`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Confirm all acceptance criteria are met

## Acceptance Criteria

- A `VerifyStripeWebhookSignature` middleware exists at `app/Http/Middleware/VerifyStripeWebhookSignature.php`
- The middleware verifies the `Stripe-Signature` header against the raw request body using the configured `services.stripe.webhook_secret`
- The middleware uses `Stripe\Webhook::constructEvent()` for verification
- The middleware returns a 400 JSON response with `{'error': 'Invalid signature'}` when verification fails
- The middleware logs a warning including the error message and request IP on verification failure
- The middleware stores the verified Stripe event on `$request->attributes->set('stripe_event', $event)` on success
- The middleware passes the request to the next handler on success via `$next($request)`
- The `stripe/webhook` route in `routes/web.php` applies `VerifyStripeWebhookSignature::class` as route middleware
- The `StripeWebhookController` no longer contains any signature verification logic (no `Webhook::constructEvent()`, no try-catch for `SignatureVerificationException`)
- The `StripeWebhookController` retrieves the verified event via `$request->attributes->get('stripe_event')`
- All existing webhook handler logic (checkout completed, payment failed, charge refunded, idempotency, logging) is preserved and unchanged
- All existing feature tests continue to pass without regressions
- New tests cover: invalid signature rejection, missing signature header rejection, IP logging on failure, and middleware-to-controller event passing
- PHP code passes Pint formatting

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Verify webhook route is registered with middleware
php artisan route:list --name=stripe.webhook

# Run webhook feature tests
php artisan test tests/Feature/Webhooks/StripeWebhookTest.php --compact

# Run full test suite for regression check
php artisan test --compact

# PHP code formatting
vendor/bin/pint --dirty
```

## Notes

- This feature depends on E001-F062 (Payment Webhook Processing) being built first. E001-F062 creates the `StripeWebhookController` with inline signature verification, the webhook route, and the CSRF exception. This feature refactors that inline verification into a middleware.
- The `stripe/stripe-php` Composer package is installed by E001-F061 (Stripe Checkout) and provides both `Stripe\Webhook::constructEvent()` for verification and `Stripe\Exception\SignatureVerificationException` for error handling.
- The Stripe webhook secret is stored in `config('services.stripe.webhook_secret')` and sourced from the `STRIPE_WEBHOOK_SECRET` environment variable. Both are established by E001-F061.
- Using `$request->attributes` (Symfony's ParameterBag) to pass the verified event from middleware to controller is the standard Laravel/Symfony pattern for middleware-to-handler data passing. This avoids polluting `$request->input()` or `$request->merge()` with non-user-input data.
- The middleware is applied per-route (not globally) because it is specific to Stripe webhook endpoints. This keeps the middleware stack clean and avoids unnecessary processing on other routes.
- If additional Stripe webhook routes are needed in the future (e.g., subscription events, invoice events), the same middleware can be applied to those routes without duplicating verification logic.
- The CSRF exception in `bootstrap/app.php` (from E001-F062) remains necessary -- the middleware handles signature verification but CSRF verification must still be bypassed for Stripe's server-to-server requests.
- The middleware approach also enables future enhancements like rate limiting Stripe webhook requests or adding IP allowlisting for Stripe's webhook IP ranges, which can be composed as additional middleware on the same route.
- All commands should be run inside the Docker container. Use `make shell` to enter the container, or prefix with `docker compose exec app`.
- This feature has no frontend components. It is entirely a backend architectural improvement.

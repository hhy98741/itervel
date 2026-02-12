# Feature: Payment Webhook Processing

**Epic**: E007-payments-and-transactions.md
**Feature**: E007-F003
**Dependencies**: E007-F002

## Task Description

Payment Webhook Processing handles incoming Stripe webhook events to reliably update user accounts after payment-related activities. When a Stripe Checkout Session completes successfully, the system verifies the webhook signature, looks up the user from the session metadata, adds the purchased credits to their account, and records a `CreditTransaction` of type `Purchase`. When a payment fails, the system logs the failure and sends the user an email notification. When a refund is issued (either full or partial), the system deducts the corresponding credits and records a `CreditTransaction` of type `Refund`.

**What it does**: Handles payment event notifications from Stripe to update user accounts.

**Expected outcome**: When Stripe confirms a successful payment, the system automatically adds credits. Failed payments are logged and the user is notified. Refunds deduct the corresponding credits.

This feature depends on E001-F061 (Stripe Checkout), which establishes:

- The `stripe/stripe-php` Composer package for server-side Stripe API integration
- Stripe configuration in `config/services.php` with `stripe.secret` and `stripe.webhook_secret` keys
- Environment variables `STRIPE_SECRET_KEY` and `STRIPE_WEBHOOK_SECRET` in `.env.example`
- The `StripeCheckoutController` that creates Stripe Checkout Sessions with metadata containing `user_id`, `package_id`, and `credits`
- The `CreditPackage` value object from E001-F060 with `findById()` for looking up packages
- The `config/credits.php` configuration file with package definitions

This feature also interacts with structures created by sibling features:

- E001-F063 (Transaction History) creates the `CreditTransaction` model, `TransactionType` enum (`Purchase`, `Usage`, `Refund`, `Bonus`), and the `credit_transactions` migration. This feature creates `CreditTransaction` records when processing webhook events.
- E001-F055 (Free Credit for New Users) adds `addCredits()` and `deductCredits()` helper methods to the User model. This feature calls those methods when adding or removing credits.

This feature creates:

1. A `StripeWebhookController` that receives POST requests from Stripe, verifies the webhook signature, and dispatches handling based on the event type.
2. A dedicated `stripe/webhook` route excluded from CSRF verification (Stripe cannot send CSRF tokens).
3. Event handler methods for three Stripe events: `checkout.session.completed` (add credits), `payment_intent.payment_failed` (log failure, notify user), and `charge.refunded` (deduct credits).
4. A `PaymentFailedNotification` Notification class to inform users when a payment fails.
5. Idempotency protection using the Stripe event ID to prevent duplicate processing if Stripe retries a webhook delivery.
6. Comprehensive logging for all webhook events for debugging and audit purposes.

Note: E001-F090 (Stripe Webhook Signature Verification) is a separate feature that may extract the signature verification into its own middleware. For this feature, we include signature verification directly in the controller as the initial implementation. If E001-F090 is built later, it can refactor the verification into middleware.

## Objective

Create a Stripe webhook endpoint that reliably processes payment events: adding credits on successful checkout, logging and notifying on payment failures, and deducting credits on refunds. When complete, the full payment lifecycle is automated: Stripe sends webhook events -> the system verifies the signature -> processes the event -> updates the user's credit balance and transaction history. The system is idempotent (safe to receive the same event multiple times) and all events are logged for debugging.

## Solution Approach

### 1. CSRF Exception for Webhook Route

Stripe sends POST requests to the webhook endpoint from its servers, and cannot include a CSRF token. The webhook route must be excluded from CSRF verification.

In Laravel 12, CSRF exceptions are configured in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->validateCsrfTokens(except: [
        'stripe/webhook',
    ]);
    // ... existing middleware
})
```

### 2. Webhook Route Registration

Add a POST route for the webhook endpoint in `routes/web.php`. This route should NOT be behind auth middleware since Stripe sends the requests, not an authenticated user:

```php
use App\Http\Controllers\StripeWebhookController;

Route::post('stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');
```

Using an invokable controller (single `__invoke` method) keeps the webhook endpoint focused and follows the single responsibility principle. The route name `stripe.webhook` provides a clear identifier.

### 3. StripeWebhookController (Invokable)

Create `App\Http\Controllers\StripeWebhookController` as an invokable controller:

```php
<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Models\CreditTransaction;
use App\Models\User;
use App\Notifications\PaymentFailedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
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

        // Idempotency check: prevent duplicate processing
        $cacheKey = 'stripe_event_' . $event->id;
        if (Cache::has($cacheKey)) {
            Log::info('Stripe webhook event already processed.', ['event_id' => $event->id]);

            return response()->json(['status' => 'already processed']);
        }

        Log::info('Stripe webhook received.', [
            'event_id' => $event->id,
            'type' => $event->type,
        ]);

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutSessionCompleted($event->data->object),
            'payment_intent.payment_failed' => $this->handlePaymentFailed($event->data->object),
            'charge.refunded' => $this->handleChargeRefunded($event->data->object),
            default => Log::info('Unhandled Stripe webhook event type.', ['type' => $event->type]),
        };

        // Mark event as processed (cache for 24 hours)
        Cache::put($cacheKey, true, now()->addHours(24));

        return response()->json(['status' => 'processed']);
    }

    protected function handleCheckoutSessionCompleted(object $session): void
    {
        $userId = $session->metadata->user_id ?? null;
        $packageId = $session->metadata->package_id ?? null;
        $credits = (int) ($session->metadata->credits ?? 0);

        if (! $userId || ! $credits) {
            Log::error('Stripe checkout.session.completed missing required metadata.', [
                'session_id' => $session->id ?? 'unknown',
                'metadata' => (array) ($session->metadata ?? []),
            ]);
            return;
        }

        $user = User::find($userId);

        if (! $user) {
            Log::error('Stripe checkout.session.completed user not found.', [
                'user_id' => $userId,
                'session_id' => $session->id,
            ]);
            return;
        }

        $user->addCredits($credits);

        CreditTransaction::create([
            'user_id' => $user->id,
            'type' => TransactionType::Purchase,
            'credits' => $credits,
            'balance' => $user->fresh()->video_credits,
            'description' => "Purchased {$credits} credits",
            'metadata' => [
                'stripe_session_id' => $session->id,
                'stripe_payment_intent' => $session->payment_intent ?? null,
                'package_id' => $packageId,
            ],
        ]);

        Log::info('Credits added via Stripe checkout.', [
            'user_id' => $userId,
            'credits' => $credits,
            'package_id' => $packageId,
            'session_id' => $session->id,
        ]);
    }

    protected function handlePaymentFailed(object $paymentIntent): void
    {
        $userId = $paymentIntent->metadata->user_id ?? null;

        Log::warning('Stripe payment failed.', [
            'payment_intent_id' => $paymentIntent->id ?? 'unknown',
            'user_id' => $userId,
            'failure_message' => $paymentIntent->last_payment_error->message ?? 'Unknown error',
        ]);

        if ($userId) {
            $user = User::find($userId);

            if ($user) {
                $user->notify(new PaymentFailedNotification(
                    $paymentIntent->last_payment_error->message ?? 'Your payment could not be processed.'
                ));
            }
        }
    }

    protected function handleChargeRefunded(object $charge): void
    {
        $paymentIntentId = $charge->payment_intent ?? null;

        if (! $paymentIntentId) {
            Log::warning('Stripe charge.refunded missing payment_intent.', [
                'charge_id' => $charge->id ?? 'unknown',
            ]);
            return;
        }

        // Find the original transaction by the Stripe payment intent ID
        $originalTransaction = CreditTransaction::query()
            ->where('type', TransactionType::Purchase)
            ->whereJsonContains('metadata->stripe_payment_intent', $paymentIntentId)
            ->first();

        if (! $originalTransaction) {
            Log::warning('Stripe charge.refunded could not find original transaction.', [
                'payment_intent_id' => $paymentIntentId,
                'charge_id' => $charge->id,
            ]);
            return;
        }

        $user = $originalTransaction->user;

        // Calculate refund credits: use the original transaction credits
        // For partial refunds, we could calculate proportionally, but for simplicity
        // and safety, we deduct the full credit amount on any refund event.
        $creditsToDeduct = $originalTransaction->credits;

        $user->deductCredits(min($creditsToDeduct, $user->video_credits));

        CreditTransaction::create([
            'user_id' => $user->id,
            'type' => TransactionType::Refund,
            'credits' => -$creditsToDeduct,
            'balance' => $user->fresh()->video_credits,
            'description' => "Refund for {$creditsToDeduct} credits",
            'metadata' => [
                'stripe_charge_id' => $charge->id,
                'stripe_payment_intent' => $paymentIntentId,
                'original_transaction_id' => $originalTransaction->id,
            ],
        ]);

        Log::info('Credits deducted via Stripe refund.', [
            'user_id' => $user->id,
            'credits_deducted' => $creditsToDeduct,
            'charge_id' => $charge->id,
        ]);
    }
}
```

Key design decisions:

- **Invokable controller**: A single `__invoke` method receives all webhook events and dispatches to type-specific handler methods. This keeps the routing simple (one endpoint) while organizing the handling logic.
- **Signature verification**: Uses Stripe's `Webhook::constructEvent()` which validates the HMAC signature. This prevents forged webhook requests. The webhook secret comes from `config('services.stripe.webhook_secret')`.
- **Idempotency via Cache**: Uses Laravel's cache (configured as database in this project) to track processed event IDs. If the same event is received again (Stripe retries on timeout), it returns early. The cache entry expires after 24 hours.
- **Error handling**: Missing metadata, missing users, and missing original transactions are all logged as errors/warnings but do not cause 500 responses. The webhook should always return 200 to Stripe to prevent excessive retries (Stripe interprets non-2xx as failure and retries).
- **Refund handling**: Looks up the original purchase transaction by `stripe_payment_intent` in the metadata JSON column. This is why the `checkout.session.completed` handler stores the `payment_intent` ID in metadata. The `whereJsonContains` query leverages MariaDB's JSON support.
- **Credit operations**: Uses `addCredits()` and `deductCredits()` methods from the User model (established by E001-F055) rather than direct column manipulation, ensuring consistency across the application.
- **Transaction recording**: Creates `CreditTransaction` records (from E001-F063) for both purchases and refunds, maintaining a complete audit trail. The `balance` field records the balance after the transaction for historical accuracy.

### 4. PaymentFailedNotification

Create a Laravel Notification class to inform users when a payment fails:

```php
<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $reason) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Payment Failed')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('We were unable to process your recent payment.')
            ->line('Reason: ' . $this->reason)
            ->line('Please check your payment method and try again.')
            ->action('Try Again', url('/credits/pricing'))
            ->line('If you continue to experience issues, please contact our support team.');
    }
}
```

Key decisions:

- Implements `ShouldQueue` so the email is sent asynchronously via the queue (the project already has a database queue configured and `composer run dev` starts a queue worker).
- Uses the `Notifiable` trait already on the User model.
- The notification includes the failure reason from Stripe, a link to the pricing page to retry, and a support message.

### 5. Testing Strategy

**Feature tests** for the webhook controller:

1. **Successful checkout**: Send a properly signed (or mocked) webhook event for `checkout.session.completed`, verify credits are added and a `CreditTransaction` is created.
2. **Invalid signature**: Send a request without proper Stripe signature, verify 400 response.
3. **Payment failed**: Send a `payment_intent.payment_failed` event, verify the notification is dispatched.
4. **Charge refunded**: Send a `charge.refunded` event, verify credits are deducted and a refund transaction is created.
5. **Idempotency**: Send the same event twice, verify credits are only added once.
6. **Missing user**: Send a `checkout.session.completed` with a non-existent user ID, verify graceful handling (no crash, logged error).
7. **Missing metadata**: Send a `checkout.session.completed` without required metadata, verify graceful handling.
8. **Unhandled event type**: Send an event with an unknown type, verify 200 response (don't block Stripe).
9. **CSRF exemption**: Verify the webhook route is accessible without a CSRF token.

For testing, the Stripe `Webhook::constructEvent()` static method needs to be mocked. The recommended approach is to mock it using Mockery's `alias` mock or by wrapping the verification in a method that can be overridden in tests. A simpler approach for testing is to mock the entire `Webhook` class or bypass signature verification in tests by setting the webhook secret to a known value and computing a valid signature.

A practical testing approach: create a helper trait or use `beforeEach` to mock `Webhook::constructEvent()` to return a properly structured event object, allowing tests to focus on the handling logic rather than signature verification mechanics.

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/bootstrap/app.php` -- Application bootstrap with middleware configuration. Must be modified to add CSRF exception for the `stripe/webhook` route.
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Main web routes file. The webhook route will be added here, outside of any auth middleware group.
- `/Users/young/Nextcloud/dev/Itervel/config/services.php` -- Third-party services config. Already contains `stripe.secret` and `stripe.webhook_secret` keys (added by E001-F061). Referenced by the webhook controller for the webhook secret.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Controller.php` -- Base controller class. The new `StripeWebhookController` will extend this.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/StripeCheckoutController.php` -- Created by E001-F061. Referenced to understand the Stripe session metadata structure (`user_id`, `package_id`, `credits`, `payment_intent`).
- `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` -- User model. Has `addCredits()` and `deductCredits()` methods (from E001-F055), `video_credits` column, and `Notifiable` trait. Called by the webhook handler to modify credits and send notifications.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/CreditTransaction.php` -- CreditTransaction model created by E001-F063. Used to record purchase and refund transactions. Has `metadata` JSON column for storing Stripe-related IDs.
- `/Users/young/Nextcloud/dev/Itervel/app/Enums/TransactionType.php` -- TransactionType enum created by E001-F063. Provides `Purchase` and `Refund` cases used when creating transactions.
- `/Users/young/Nextcloud/dev/Itervel/app/ValueObjects/CreditPackage.php` -- CreditPackage value object created by E001-F060. Referenced for understanding package structure (not directly used by webhook handler since credits come from session metadata).
- `/Users/young/Nextcloud/dev/Itervel/config/credits.php` -- Credit package configuration created by E001-F060. Referenced for understanding the credit system.
- `/Users/young/Nextcloud/dev/Itervel/app/Providers/AppServiceProvider.php` -- Service provider. Referenced for understanding the application bootstrap pattern.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Middleware/HandleInertiaRequests.php` -- Inertia middleware. Not modified, but referenced to understand how auth.user data is shared.
- `/Users/young/Nextcloud/dev/Itervel/config/queue.php` -- Queue configuration. The `PaymentFailedNotification` uses `ShouldQueue` which dispatches via the `database` queue connection.
- `/Users/young/Nextcloud/dev/Itervel/config/logging.php` -- Logging configuration. The webhook controller logs all events for debugging. Uses the default `stack` channel.
- `/Users/young/Nextcloud/dev/Itervel/config/mail.php` -- Mail configuration. The `PaymentFailedNotification` sends email via the configured mailer (default `log` in dev).
- `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` -- User factory for test setup. Used to create users in webhook tests.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/CreditTransactionFactory.php` -- CreditTransaction factory created by E001-F063. Used in tests to create existing transactions for refund testing.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration. Feature tests use `RefreshDatabase` automatically.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/ProfileUpdateTest.php` -- Reference for testing patterns with `actingAs`, assertions, and Pest conventions.
- `/Users/young/Nextcloud/dev/Itervel/.env.example` -- Environment variable template. Already has `STRIPE_WEBHOOK_SECRET` from E001-F061.
- `/Users/young/Nextcloud/dev/Itervel/composer.json` -- Composer configuration. Already has `stripe/stripe-php` from E001-F061.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/StripeWebhookController.php` -- Invokable controller that receives Stripe webhook POST requests, verifies the signature using `Webhook::constructEvent()`, checks idempotency via cache, and dispatches to handler methods for `checkout.session.completed`, `payment_intent.payment_failed`, and `charge.refunded` events.
- `/Users/young/Nextcloud/dev/Itervel/app/Notifications/PaymentFailedNotification.php` -- Queued mail notification sent to users when a Stripe payment fails. Includes the failure reason, a link to retry, and a support message.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Webhooks/StripeWebhookTest.php` -- Feature tests for the Stripe webhook endpoint covering successful checkout processing, payment failure notification, refund handling, signature verification, idempotency, missing data handling, and CSRF exemption.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: webhook-backend-dev
    - Role: Creates the StripeWebhookController, PaymentFailedNotification, webhook route, and CSRF exception. Handles all PHP backend work including the event handling logic, idempotency mechanism, and logging.
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: webhook-test-dev
    - Role: Writes comprehensive feature tests for the Stripe webhook endpoint covering all event types, signature verification, idempotency, error handling, and edge cases.
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: webhook-reviewer
    - Role: Validates the complete webhook processing feature against acceptance criteria, runs all tests, checks code formatting, and verifies the integration with the credit system.
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Create Webhook Controller, Notification, Route, and CSRF Exception

- **Task ID**: create-webhook-backend
- **Depends On**: none
- **Assigned To**: webhook-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- Modify `/Users/young/Nextcloud/dev/Itervel/bootstrap/app.php` to add CSRF exception for the webhook route:
    - Add a `validateCsrfTokens` call inside the `withMiddleware` closure, BEFORE the existing `encryptCookies` call:
        ```php
        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
        ]);
        ```
    - The full `withMiddleware` section should look like:

        ```php
        ->withMiddleware(function (Middleware $middleware): void {
            $middleware->validateCsrfTokens(except: [
                'stripe/webhook',
            ]);

            $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

            $middleware->web(append: [
                HandleAppearance::class,
                HandleInertiaRequests::class,
                AddLinkHeadersForPreloadedAssets::class,
            ]);
        })
        ```

- Create the Notifications directory: `mkdir -p /Users/young/Nextcloud/dev/Itervel/app/Notifications`
- Create `/Users/young/Nextcloud/dev/Itervel/app/Notifications/PaymentFailedNotification.php`:
    - Namespace: `App\Notifications`
    - Extends `Illuminate\Notifications\Notification`
    - Implements `Illuminate\Contracts\Queue\ShouldQueue`
    - Uses `Illuminate\Bus\Queueable` trait
    - Constructor: `public function __construct(public string $reason) {}`
    - Method `via(object $notifiable): array` returns `['mail']`
    - Method `toMail(object $notifiable): MailMessage`:
        ```php
        return (new MailMessage)
            ->subject('Payment Failed')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('We were unable to process your recent payment.')
            ->line('Reason: ' . $this->reason)
            ->line('Please check your payment method and try again.')
            ->action('Try Again', url('/credits/pricing'))
            ->line('If you continue to experience issues, please contact our support team.');
        ```
    - Import `Illuminate\Notifications\Messages\MailMessage`
    - Add PHPDoc blocks for all methods
- Create `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/StripeWebhookController.php` as an invokable controller:
    - Namespace: `App\Http\Controllers`
    - Extends `Controller`
    - Imports: `App\Enums\TransactionType`, `App\Models\CreditTransaction`, `App\Models\User`, `App\Notifications\PaymentFailedNotification`, `Illuminate\Http\JsonResponse`, `Illuminate\Http\Request`, `Illuminate\Support\Facades\Cache`, `Illuminate\Support\Facades\Log`, `Stripe\Exception\SignatureVerificationException`, `Stripe\Webhook`
    - Method `__invoke(Request $request): JsonResponse`:
        1. Get the raw payload: `$payload = $request->getContent();`
        2. Get the signature header: `$sigHeader = $request->header('Stripe-Signature');`
        3. Get the webhook secret: `$webhookSecret = config('services.stripe.webhook_secret');`
        4. Try `Webhook::constructEvent($payload, $sigHeader, $webhookSecret)` inside a try-catch for `SignatureVerificationException`. On failure, log a warning and return `response()->json(['error' => 'Invalid signature'], 400)`.
        5. Idempotency check: create `$cacheKey = 'stripe_event_' . $event->id`. If `Cache::has($cacheKey)`, log info and return `response()->json(['status' => 'already processed'])`.
        6. Log the received event with `event_id` and `type`.
        7. Use a `match` expression on `$event->type` to dispatch to handler methods:
            - `'checkout.session.completed'` => `$this->handleCheckoutSessionCompleted($event->data->object)`
            - `'payment_intent.payment_failed'` => `$this->handlePaymentFailed($event->data->object)`
            - `'charge.refunded'` => `$this->handleChargeRefunded($event->data->object)`
            - `default` => Log unhandled event type
        8. Cache the event ID for 24 hours: `Cache::put($cacheKey, true, now()->addHours(24));`
        9. Return `response()->json(['status' => 'processed'])`
    - Method `handleCheckoutSessionCompleted(object $session): void`:
        1. Extract `$userId`, `$packageId`, `$credits` from `$session->metadata`
        2. If `$userId` or `$credits` is missing, log error with session details and return
        3. Find the user: `$user = User::find($userId)`. If not found, log error and return
        4. Call `$user->addCredits($credits)` (method from E001-F055)
        5. Create a `CreditTransaction` with type `TransactionType::Purchase`, positive credits amount, balance from `$user->fresh()->video_credits`, description `"Purchased {$credits} credits"`, and metadata containing `stripe_session_id`, `stripe_payment_intent`, and `package_id`
        6. Log the successful credit addition with user_id, credits, package_id, and session_id
    - Method `handlePaymentFailed(object $paymentIntent): void`:
        1. Extract `$userId` from `$paymentIntent->metadata->user_id`
        2. Log the payment failure with payment_intent_id, user_id, and failure_message
        3. If `$userId` exists, find the user and send `PaymentFailedNotification` with the failure reason from `$paymentIntent->last_payment_error->message`
    - Method `handleChargeRefunded(object $charge): void`:
        1. Extract `$paymentIntentId` from `$charge->payment_intent`. If missing, log warning and return
        2. Find the original `CreditTransaction` of type `Purchase` where `metadata->stripe_payment_intent` matches the `$paymentIntentId` using `whereJsonContains`
        3. If no original transaction found, log warning and return
        4. Get the user from the original transaction relationship
        5. Calculate `$creditsToDeduct` as the original transaction's credits amount
        6. Deduct credits using `$user->deductCredits(min($creditsToDeduct, $user->video_credits))` to prevent negative balance
        7. Create a `CreditTransaction` with type `TransactionType::Refund`, negative credits amount (`-$creditsToDeduct`), updated balance, description `"Refund for {$creditsToDeduct} credits"`, and metadata containing `stripe_charge_id`, `stripe_payment_intent`, and `original_transaction_id`
        8. Log the refund with user_id, credits_deducted, and charge_id
    - Add PHPDoc blocks for all methods
- Add the webhook route to `/Users/young/Nextcloud/dev/Itervel/routes/web.php`:
    - Add `use App\Http\Controllers\StripeWebhookController;` to the imports at the top
    - Add the following route BEFORE any auth-protected route groups (and before the `require __DIR__.'/settings.php';` line):
        ```php
        Route::post('stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');
        ```
    - This route is intentionally NOT inside any middleware group -- it is accessed by Stripe's servers, not authenticated users. The CSRF exception in `bootstrap/app.php` ensures it works without a token.
- Run `vendor/bin/pint --dirty` to format all changed PHP files
- Verify the route is registered: `php artisan route:list --name=stripe.webhook`
- Verify the CSRF exception works by checking `bootstrap/app.php` has the `validateCsrfTokens` configuration

### 2. Write Feature Tests for Webhook Processing

- **Task ID**: write-webhook-tests
- **Depends On**: create-webhook-backend
- **Assigned To**: webhook-test-dev
- **Agent Type**: coder
- **Parallel**: false
- Create the test directory `tests/Feature/Webhooks/` if it does not exist
- Create the feature test using: `php artisan make:test Webhooks/StripeWebhookTest --pest --no-interaction`
- Write the following tests in `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Webhooks/StripeWebhookTest.php`:
    - Add imports at the top:
        ```php
        use App\Enums\TransactionType;
        use App\Models\CreditTransaction;
        use App\Models\User;
        use App\Notifications\PaymentFailedNotification;
        use Illuminate\Support\Facades\Cache;
        use Illuminate\Support\Facades\Log;
        use Illuminate\Support\Facades\Notification;
        use Stripe\Event;
        use Stripe\Webhook;
        ```
    - IMPORTANT: Mocking Stripe's `Webhook::constructEvent()` is critical. Since it is a static method, use Mockery's `alias` mock. Set this up in a `beforeEach` block or use a helper function:

        ```php
        function mockStripeWebhookEvent(string $type, object $dataObject, string $eventId = 'evt_test_123'): void
        {
            $event = new \stdClass();
            $event->id = $eventId;
            $event->type = $type;
            $event->data = new \stdClass();
            $event->data->object = $dataObject;

            Mockery::mock('alias:' . Webhook::class)
                ->shouldReceive('constructEvent')
                ->once()
                ->andReturn($event);
        }
        ```

        Note: If Mockery `alias` mocking of Stripe classes causes issues in the Pest test runner, an alternative approach is to:
        1. Extract `Webhook::constructEvent()` into a protected method on the controller
        2. In tests, override that method via a mock/spy or partial mock of the controller
        3. OR: Create a simple `App\Services\StripeWebhookVerifier` service class that wraps `Webhook::constructEvent()`, bind it in the container, and mock it in tests
           The test developer should try the `alias` approach first. If it fails, implement the service wrapper approach.

    - `test('webhook rejects request with invalid stripe signature')`:

        ```php
        // Do NOT mock Webhook -- let the real verification fail
        // Set a webhook secret in config for the test
        config(['services.stripe.webhook_secret' => 'whsec_test_secret']);

        $response = $this->postJson('stripe/webhook', [], [
            'Stripe-Signature' => 'invalid_signature',
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Invalid signature']);
        ```

        Note: This test relies on the real `Webhook::constructEvent()` throwing `SignatureVerificationException` when given an invalid signature. The webhook secret must be set in config for this to work.

    - `test('webhook processes checkout session completed and adds credits')`:

        ```php
        $user = User::factory()->create(['video_credits' => 0]);

        $session = new \stdClass();
        $session->id = 'cs_test_123';
        $session->payment_intent = 'pi_test_123';
        $session->metadata = new \stdClass();
        $session->metadata->user_id = (string) $user->id;
        $session->metadata->package_id = 'starter';
        $session->metadata->credits = '5';

        mockStripeWebhookEvent('checkout.session.completed', $session);

        $response = $this->postJson('stripe/webhook', [], [
            'Stripe-Signature' => 'valid_mock_signature',
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'processed']);

        $user->refresh();
        expect($user->video_credits)->toBe(5);

        $transaction = CreditTransaction::where('user_id', $user->id)->first();
        expect($transaction)->not->toBeNull();
        expect($transaction->type)->toBe(TransactionType::Purchase);
        expect($transaction->credits)->toBe(5);
        expect($transaction->balance)->toBe(5);
        expect($transaction->metadata['stripe_session_id'])->toBe('cs_test_123');
        expect($transaction->metadata['stripe_payment_intent'])->toBe('pi_test_123');
        expect($transaction->metadata['package_id'])->toBe('starter');
        ```

    - `test('webhook handles payment failed and notifies user')`:

        ```php
        Notification::fake();

        $user = User::factory()->create();

        $paymentIntent = new \stdClass();
        $paymentIntent->id = 'pi_test_failed';
        $paymentIntent->metadata = new \stdClass();
        $paymentIntent->metadata->user_id = (string) $user->id;
        $paymentIntent->last_payment_error = new \stdClass();
        $paymentIntent->last_payment_error->message = 'Your card was declined.';

        mockStripeWebhookEvent('payment_intent.payment_failed', $paymentIntent);

        $response = $this->postJson('stripe/webhook', [], [
            'Stripe-Signature' => 'valid_mock_signature',
        ]);

        $response->assertOk();

        Notification::assertSentTo($user, PaymentFailedNotification::class, function ($notification) {
            return $notification->reason === 'Your card was declined.';
        });
        ```

    - `test('webhook handles charge refunded and deducts credits')`:

        ```php
        $user = User::factory()->create(['video_credits' => 10]);

        // Create the original purchase transaction that will be referenced
        CreditTransaction::factory()->for($user)->purchase(5)->create([
            'metadata' => [
                'stripe_payment_intent' => 'pi_test_refund',
                'stripe_session_id' => 'cs_test_refund',
                'package_id' => 'starter',
            ],
        ]);

        $charge = new \stdClass();
        $charge->id = 'ch_test_refund';
        $charge->payment_intent = 'pi_test_refund';

        mockStripeWebhookEvent('charge.refunded', $charge, 'evt_refund_123');

        $response = $this->postJson('stripe/webhook', [], [
            'Stripe-Signature' => 'valid_mock_signature',
        ]);

        $response->assertOk();

        $user->refresh();
        expect($user->video_credits)->toBe(5); // 10 - 5 = 5

        $refundTransaction = CreditTransaction::where('user_id', $user->id)
            ->where('type', TransactionType::Refund)
            ->first();
        expect($refundTransaction)->not->toBeNull();
        expect($refundTransaction->credits)->toBe(-5);
        expect($refundTransaction->balance)->toBe(5);
        expect($refundTransaction->metadata['stripe_charge_id'])->toBe('ch_test_refund');
        ```

    - `test('webhook is idempotent and skips duplicate events')`:

        ```php
        $user = User::factory()->create(['video_credits' => 0]);

        $session = new \stdClass();
        $session->id = 'cs_test_idempotent';
        $session->payment_intent = 'pi_test_idempotent';
        $session->metadata = new \stdClass();
        $session->metadata->user_id = (string) $user->id;
        $session->metadata->package_id = 'starter';
        $session->metadata->credits = '5';

        // First request
        mockStripeWebhookEvent('checkout.session.completed', $session, 'evt_idempotent_123');

        $response = $this->postJson('stripe/webhook', [], [
            'Stripe-Signature' => 'valid_mock_signature',
        ]);

        $response->assertOk();
        expect($user->fresh()->video_credits)->toBe(5);

        // Second request with same event ID -- need to mock again for the second call
        mockStripeWebhookEvent('checkout.session.completed', $session, 'evt_idempotent_123');

        $response = $this->postJson('stripe/webhook', [], [
            'Stripe-Signature' => 'valid_mock_signature',
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'already processed']);

        // Credits should still be 5, not 10
        expect($user->fresh()->video_credits)->toBe(5);
        expect(CreditTransaction::where('user_id', $user->id)->count())->toBe(1);
        ```

        Note: The Mockery `alias` mock may need to be reset between calls. If the above approach does not work, the test developer should handle re-mocking or use a different approach (e.g., manually set the cache key before the second request).

    - `test('webhook handles checkout with missing user gracefully')`:

        ```php
        $session = new \stdClass();
        $session->id = 'cs_test_no_user';
        $session->payment_intent = 'pi_test_no_user';
        $session->metadata = new \stdClass();
        $session->metadata->user_id = '99999';
        $session->metadata->package_id = 'starter';
        $session->metadata->credits = '5';

        mockStripeWebhookEvent('checkout.session.completed', $session, 'evt_no_user_123');

        $response = $this->postJson('stripe/webhook', [], [
            'Stripe-Signature' => 'valid_mock_signature',
        ]);

        // Should still return 200 to Stripe (don't cause retries)
        $response->assertOk();
        expect(CreditTransaction::count())->toBe(0);
        ```

    - `test('webhook handles checkout with missing metadata gracefully')`:

        ```php
        $session = new \stdClass();
        $session->id = 'cs_test_no_meta';
        $session->metadata = new \stdClass();
        // No user_id or credits in metadata

        mockStripeWebhookEvent('checkout.session.completed', $session, 'evt_no_meta_123');

        $response = $this->postJson('stripe/webhook', [], [
            'Stripe-Signature' => 'valid_mock_signature',
        ]);

        $response->assertOk();
        expect(CreditTransaction::count())->toBe(0);
        ```

    - `test('webhook returns 200 for unhandled event types')`:

        ```php
        $unknownObject = new \stdClass();
        $unknownObject->id = 'unknown_123';

        mockStripeWebhookEvent('some.unknown.event', $unknownObject, 'evt_unknown_123');

        $response = $this->postJson('stripe/webhook', [], [
            'Stripe-Signature' => 'valid_mock_signature',
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'processed']);
        ```

    - `test('webhook route is accessible without csrf token')`:

        ```php
        // This test verifies the CSRF exception is working
        // We send a POST without CSRF token and without Stripe-Signature
        // It should reach the controller (not get 419 CSRF error)
        // and then fail with 400 (invalid signature), NOT 419 (CSRF)
        config(['services.stripe.webhook_secret' => 'whsec_test_secret']);

        $response = $this->postJson('stripe/webhook', [], [
            'Stripe-Signature' => 'invalid',
        ]);

        // Should be 400 (invalid signature), NOT 419 (CSRF)
        $response->assertStatus(400);
        ```

    - `test('webhook handles payment failed without user id gracefully')`:

        ```php
        Notification::fake();

        $paymentIntent = new \stdClass();
        $paymentIntent->id = 'pi_test_no_user';
        $paymentIntent->metadata = new \stdClass();
        // No user_id in metadata
        $paymentIntent->last_payment_error = new \stdClass();
        $paymentIntent->last_payment_error->message = 'Card declined.';

        mockStripeWebhookEvent('payment_intent.payment_failed', $paymentIntent, 'evt_no_user_fail');

        $response = $this->postJson('stripe/webhook', [], [
            'Stripe-Signature' => 'valid_mock_signature',
        ]);

        $response->assertOk();
        Notification::assertNothingSent();
        ```

    - `test('webhook handles refund without matching original transaction gracefully')`:

        ```php
        $charge = new \stdClass();
        $charge->id = 'ch_test_no_match';
        $charge->payment_intent = 'pi_nonexistent';

        mockStripeWebhookEvent('charge.refunded', $charge, 'evt_no_match_refund');

        $response = $this->postJson('stripe/webhook', [], [
            'Stripe-Signature' => 'valid_mock_signature',
        ]);

        $response->assertOk();
        expect(CreditTransaction::where('type', TransactionType::Refund)->count())->toBe(0);
        ```

    - `test('refund does not create negative balance')`:

        ```php
        $user = User::factory()->create(['video_credits' => 2]);

        CreditTransaction::factory()->for($user)->purchase(5)->create([
            'metadata' => [
                'stripe_payment_intent' => 'pi_test_negative',
            ],
        ]);

        $charge = new \stdClass();
        $charge->id = 'ch_test_negative';
        $charge->payment_intent = 'pi_test_negative';

        mockStripeWebhookEvent('charge.refunded', $charge, 'evt_negative_balance');

        $response = $this->postJson('stripe/webhook', [], [
            'Stripe-Signature' => 'valid_mock_signature',
        ]);

        $response->assertOk();

        $user->refresh();
        // User had 2 credits, refund was for 5 credits purchase
        // Should deduct min(5, 2) = 2 credits, balance should be 0
        expect($user->video_credits)->toBeGreaterThanOrEqual(0);
        ```

- After writing all tests, adapt the Stripe mocking approach to what actually works in the test environment. The key requirements are:
    1. Tests do not make real Stripe API calls
    2. The mocking approach allows testing each event handler independently
    3. Idempotency is testable
    4. If Mockery `alias` mocking does not work, create an `App\Services\StripeWebhookVerifier` service class that wraps `Webhook::constructEvent()`, bind it in the container, and mock it in tests. Then update the controller to inject this service via method injection: `public function __invoke(Request $request, StripeWebhookVerifier $verifier): JsonResponse`
- Run the tests: `php artisan test tests/Feature/Webhooks/StripeWebhookTest.php --compact`
- Ensure all tests pass (iterate on mocking approach if needed)
- Run `vendor/bin/pint --dirty` to format any PHP files

### 3. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-webhook-backend, write-webhook-tests
- **Assigned To**: webhook-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed below
- Verify `/Users/young/Nextcloud/dev/Itervel/bootstrap/app.php` has `validateCsrfTokens(except: ['stripe/webhook'])` in the middleware configuration
- Verify the controller `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/StripeWebhookController.php` exists as an invokable controller with `__invoke()` method
- Verify the controller handles three event types: `checkout.session.completed`, `payment_intent.payment_failed`, `charge.refunded`
- Verify the controller verifies the Stripe webhook signature using `Webhook::constructEvent()`
- Verify the controller implements idempotency checking using `Cache::has()` and `Cache::put()` with the Stripe event ID
- Verify the controller returns JSON responses (not redirects or Inertia responses) for all cases
- Verify the controller returns 400 for invalid signatures and 200 for all other cases (including errors)
- Verify the `handleCheckoutSessionCompleted` method:
    - Extracts `user_id`, `package_id`, `credits` from session metadata
    - Calls `$user->addCredits()` to add credits
    - Creates a `CreditTransaction` with type `TransactionType::Purchase`, positive credits, correct balance, and Stripe metadata
    - Handles missing metadata and missing user gracefully (no exception, logged)
- Verify the `handlePaymentFailed` method:
    - Sends `PaymentFailedNotification` to the user
    - Handles missing user_id gracefully
    - Logs the failure details
- Verify the `handleChargeRefunded` method:
    - Finds the original purchase transaction by `stripe_payment_intent` in metadata
    - Calls `$user->deductCredits()` to remove credits
    - Creates a `CreditTransaction` with type `TransactionType::Refund`, negative credits, correct balance, and Stripe metadata
    - Prevents negative credit balance
    - Handles missing original transaction gracefully
- Verify `/Users/young/Nextcloud/dev/Itervel/app/Notifications/PaymentFailedNotification.php` exists with `ShouldQueue` interface, `Queueable` trait, `via()` returning `['mail']`, and `toMail()` with subject, greeting, failure reason, retry link, and support message
- Verify the webhook route is registered: `php artisan route:list --name=stripe.webhook`
    - Method: POST
    - URI: stripe/webhook
    - Name: stripe.webhook
    - No auth middleware
- Verify all logging statements use appropriate levels: `Log::info()` for normal events, `Log::warning()` for non-critical issues, `Log::error()` for missing data
- Run webhook tests: `php artisan test tests/Feature/Webhooks/StripeWebhookTest.php --compact`
- Run all credit-related tests: `php artisan test tests/Feature/Credits --compact` (if they exist)
- Run full test suite for regression check: `php artisan test --compact`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Confirm all acceptance criteria are met

## Acceptance Criteria

- A `StripeWebhookController` exists at `app/Http/Controllers/StripeWebhookController.php` as an invokable controller
- The webhook endpoint is registered at `POST /stripe/webhook` with route name `stripe.webhook`
- The webhook route is excluded from CSRF verification in `bootstrap/app.php`
- The webhook route has NO auth middleware (accessible by Stripe's servers)
- The controller verifies the Stripe webhook signature using `Webhook::constructEvent()` and returns 400 for invalid signatures
- The controller implements idempotency using Cache to prevent duplicate event processing
- The controller handles `checkout.session.completed` events by:
    - Looking up the user from session metadata
    - Adding credits using `$user->addCredits()`
    - Creating a `CreditTransaction` of type `Purchase` with Stripe metadata
    - Logging the successful credit addition
- The controller handles `payment_intent.payment_failed` events by:
    - Logging the failure details
    - Sending a `PaymentFailedNotification` email to the user
- The controller handles `charge.refunded` events by:
    - Finding the original purchase transaction by `stripe_payment_intent` in metadata
    - Deducting credits using `$user->deductCredits()` (capped at current balance to prevent negative)
    - Creating a `CreditTransaction` of type `Refund` with negative credits and Stripe metadata
    - Logging the refund details
- The controller handles unrecognized event types gracefully (logs and returns 200)
- The controller handles missing metadata, missing users, and missing original transactions gracefully without throwing exceptions
- A `PaymentFailedNotification` exists at `app/Notifications/PaymentFailedNotification.php` that implements `ShouldQueue` and sends an email with the failure reason and a link to retry
- All feature tests pass for the webhook endpoint
- All existing tests pass without regressions
- PHP code passes Pint formatting

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Verify webhook route is registered
php artisan route:list --name=stripe.webhook

# Run webhook feature tests
php artisan test tests/Feature/Webhooks/StripeWebhookTest.php --compact

# Run all credit-related tests (if they exist)
php artisan test tests/Feature/Credits --compact

# Run full test suite for regression check
php artisan test --compact

# PHP code formatting
vendor/bin/pint --dirty
```

## Notes

- This feature depends on E001-F061 (Stripe Checkout) being built first. E001-F061 installs the `stripe/stripe-php` Composer package, configures `config/services.php` with Stripe credentials, and creates the `StripeCheckoutController` that stores `user_id`, `package_id`, and `credits` in the Stripe Checkout Session metadata. The webhook handler reads this metadata to know which user to credit and how many credits to add.
- This feature also depends on structures from E001-F063 (Transaction History) and E001-F055 (Free Credit for New Users). E001-F063 creates the `CreditTransaction` model, `TransactionType` enum, and migration. E001-F055 adds `addCredits()` and `deductCredits()` methods to the User model. Both of these may be built in parallel with or before this feature. If they have not been built yet when this feature is implemented, the implementing agent should check if those models/methods exist and create minimal stubs if needed, or coordinate with the team lead to sequence dependencies correctly.
- The webhook endpoint returns JSON (not Inertia/HTML) because Stripe's servers are the client, not a browser. All responses are JSON with appropriate status codes: 400 for invalid signatures, 200 for everything else. Returning 200 even for handled errors (missing user, missing metadata) prevents Stripe from retrying -- the error is logged for debugging.
- The CSRF exception is necessary because Stripe cannot include a Laravel CSRF token in its POST requests. The webhook signature verification provides equivalent security -- it proves the request came from Stripe and was not tampered with.
- Idempotency is critical because Stripe may retry webhook deliveries if it does not receive a timely 2xx response. The cache-based approach (storing processed event IDs for 24 hours) is lightweight and sufficient. Stripe's retry window is typically much shorter than 24 hours. An alternative approach would be to store processed event IDs in a database table, but cache is simpler and adequate for this use case.
- The refund handler finds the original transaction by `stripe_payment_intent` stored in the purchase transaction's metadata JSON column. This requires that the `checkout.session.completed` handler stores the `payment_intent` ID in metadata, which it does. The `whereJsonContains` query leverages MariaDB's JSON column support.
- The `deductCredits()` call in the refund handler uses `min($creditsToDeduct, $user->video_credits)` to prevent the user's balance from going negative. This handles the edge case where a user has already spent some of the purchased credits before a refund is issued.
- The `PaymentFailedNotification` uses `ShouldQueue` to send emails asynchronously. The project has a database queue configured and `composer run dev` starts a queue worker. In tests, the notification can be asserted using `Notification::fake()`.
- E001-F090 (Stripe Webhook Signature Verification) is a planned separate feature that may extract the signature verification into reusable middleware. This feature includes signature verification directly in the controller as the initial implementation. If E001-F090 is built later, it can refactor the verification logic into a middleware without changing the handler logic.
- All commands should be run inside the Docker container. Use `make shell` to enter the container, or prefix with `docker compose exec app`.
- This feature has no frontend components. All interactions are server-to-server (Stripe to the application). The user-facing impact is automatic: credits appear in their balance (visible via E001-F054's `CreditBalance` component) and transactions appear in their history (visible via E001-F063's transaction page).

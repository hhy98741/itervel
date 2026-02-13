# Feature: Credit Refund on Failure

**Epic**: E005-credits-and-billing.md
**Feature**: E005-F004
**Epic depends on**: E002-user-authentication.md
**Feature depends on**: E005-F003

## Task Description

Credit Refund on Failure automatically refunds the credit if video generation fails and cannot be recovered. This is a critical consumer protection feature -- users should not be charged for work that was not delivered. When the rendering process fails irreversibly, the user's credit is restored to their balance.

**What it does**: Automatically refunds the credit if video generation fails.

**Expected outcome**: If the rendering process fails and cannot be recovered, the user's credit is restored to their balance.

This feature builds on E005-F003 (Credit Deduction on Completion), which creates:

- The `CreditTransaction` model and `credit_transactions` table for recording all credit movements
- The `CreditService` class for handling credit operations with transaction logging
- The `CreditTransactionFactory` with factory states for different transaction types

E005-F003 provides the deduction mechanism (`deductForVideoCompletion()`). This feature adds the counterpart: a `refundForVideoFailure()` method to the `CreditService` that adds 1 credit back to the user's balance and creates a refund transaction record. The refund transaction can optionally reference the original deduction transaction for traceability.

Since the video rendering system (E014) does not exist yet, this feature builds the **refund infrastructure** that will be consumed by E014-F005 (Render Failure Handling) and E014-F006 (Shotstack Completion Webhook). The actual trigger for the refund (detecting rendering failure, retrying, then refunding) is the responsibility of those downstream features.

The refund flow is:

1. A video starts rendering (credit was already deducted via `deductForVideoCompletion()`)
2. The rendering fails and cannot be recovered (after retry attempts)
3. The system calls `CreditService::refundForVideoFailure()`, which adds 1 credit back and creates a refund transaction record
4. The user's balance is updated immediately

## Objective

Add a `refundForVideoFailure()` method to the `CreditService` that atomically restores 1 credit to the user's balance and creates a refund `CreditTransaction` record. The refund transaction is linked to the original deduction transaction (when available) for audit traceability. All operations are covered by comprehensive tests, including the full deduction-then-refund lifecycle.

## Solution Approach

### 1. Add `refundForVideoFailure()` to CreditService

Extend the `CreditService` (created in E005-F003) with a refund method:

```php
/**
 * Refund 1 credit for a failed video rendering.
 *
 * @param  CreditTransaction|null  $originalTransaction  The deduction transaction to link this refund to
 * @param  \Illuminate\Database\Eloquent\Model|null  $reference  The video model (or null if not yet created)
 * @param  array<string, mixed>  $metadata  Additional context
 */
public function refundForVideoFailure(
    User $user,
    ?CreditTransaction $originalTransaction = null,
    ?\Illuminate\Database\Eloquent\Model $reference = null,
    array $metadata = [],
): CreditTransaction {
    return DB::transaction(function () use ($user, $originalTransaction, $reference, $metadata) {
        $user->addCredits(1);

        $refundMetadata = $metadata;
        if ($originalTransaction) {
            $refundMetadata['original_transaction_id'] = $originalTransaction->id;
        }

        return CreditTransaction::create([
            'user_id' => $user->id,
            'amount' => 1,
            'balance_after' => $user->fresh()->video_credits,
            'type' => 'refund',
            'reason' => 'video_failure_refund',
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference?->getKey(),
            'metadata' => $refundMetadata ?: null,
        ]);
    });
}
```

Key design decisions:

- **Database transaction**: Like the deduction method, the refund is wrapped in `DB::transaction()` to ensure atomicity -- the credit addition and transaction record are created together or not at all.
- **Positive amount**: Refund transactions store `amount` as `+1` (positive), contrasting with deductions (`-1`). This maintains the accounting convention where positive = credit added, negative = credit removed.
- **Original transaction reference**: The refund can optionally link to the original deduction transaction via `metadata.original_transaction_id`. This creates a traceable refund chain without adding extra database columns. When E014 builds the video failure handling, it can pass the deduction transaction to the refund method.
- **Delegates to User model**: The service calls `$user->addCredits(1)` from E005-F002, which handles validation (positive amount check) and uses atomic `increment()`.

### 2. Add Convenience `deductAndRefundOnFailure()` Method (Optional)

For downstream features that need the common pattern of "deduct credit, try to render, refund on failure", add a convenience method that accepts a callback:

This is NOT needed for the current feature scope. The deduction and refund are separate operations that will be called at different points in the video rendering lifecycle (deduction at render start, refund on failure after retries). The separate methods provide the correct granularity.

### 3. Testing Approach

Write feature tests that validate:

- The `refundForVideoFailure()` method restores the credit and creates a transaction record
- The refund transaction has the correct type, reason, and positive amount
- The `balance_after` field accurately reflects the post-refund balance
- The optional link to the original deduction transaction works via metadata
- The full deduction-then-refund lifecycle: deduct 1 credit, then refund it, verifying the balance returns to the original value
- The refund works for a user with 0 credits (restoring from 0 to 1)
- Multiple refunds are tracked correctly
- Atomicity: if `addCredits()` fails (non-positive amount), no transaction record is created

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Services/CreditService.php` -- The CreditService created in E005-F003. Must be modified to add the `refundForVideoFailure()` method. Currently has `deductForVideoCompletion()`.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/CreditTransaction.php` -- The CreditTransaction model created in E005-F003. Has `user()`, `reference()` relationships and casts for `amount`, `balance_after`, `metadata`. No changes needed; referenced for understanding the model structure.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` -- The User Eloquent model. After E005-F002, has `addCredits()`, `deductCredits()`, `hasCredits()`, `hasSufficientCredits()` methods. After E005-F003, has `creditTransactions(): HasMany` relationship. No changes needed; referenced for understanding the credit API.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/CreditTransactionFactory.php` -- The CreditTransaction factory created in E005-F003. Has `refund()` state that creates refund records. No changes needed; used in tests.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` -- User factory. After E002-F001, includes `video_credits => 0` in defaults. Used in tests to create users with specific credit values.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Credits/CreditDeductionTest.php` -- The deduction tests from E005-F003. Referenced for understanding test patterns and ensuring the refund tests are consistent.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration. All Feature tests use `RefreshDatabase` automatically.
- `/Users/young/Nextcloud/dev/Itervel/specs/features/E005-F003-credit-deduction-on-completion.md` -- The dependency feature spec. Details the CreditTransaction model, CreditService, and deduction flow. Critical reference for understanding the infrastructure this feature extends.
- `/Users/young/Nextcloud/dev/Itervel/specs/features/E005-F002-free-credit-for-new-users.md` -- The foundation feature spec. Details the `addCredits()` method on the User model that the refund uses internally.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Credits/CreditRefundTest.php` -- Pest feature tests for credit refund functionality. Tests the `refundForVideoFailure()` method, refund transaction records, the deduction-then-refund lifecycle, balance tracking, and metadata storage including original transaction linking.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: credit-refund-backend-dev
    - Role: Adds the `refundForVideoFailure()` method to the CreditService. Verifies E005-F003's CreditTransaction model and CreditService are in place.
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: credit-refund-test-dev
    - Role: Writes comprehensive feature tests for the credit refund flow, including the full deduction-then-refund lifecycle, balance tracking, and metadata storage
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: credit-refund-reviewer
    - Role: Validates the complete credit refund feature against acceptance criteria, runs all tests, checks types, runs linting and formatting
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Add Refund Method to CreditService

- **Task ID**: add-refund-method
- **Depends On**: none
- **Assigned To**: credit-refund-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- Verify that E005-F003's changes are in place:
    - Confirm `/Users/young/Nextcloud/dev/Itervel/app/Services/CreditService.php` exists and has the `deductForVideoCompletion()` method
    - Confirm `/Users/young/Nextcloud/dev/Itervel/app/Models/CreditTransaction.php` exists with the expected model structure
    - Confirm the User model at `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` has `addCredits()` method (from E005-F002) and `creditTransactions()` relationship (from E005-F003)
    - If any are missing, flag this as a blocker -- these are prerequisites from E005-F003
- Read the existing `CreditService` at `/Users/young/Nextcloud/dev/Itervel/app/Services/CreditService.php` to understand the current structure and `deductForVideoCompletion()` method pattern
- Add the `refundForVideoFailure()` method to the `CreditService`. Place it after `deductForVideoCompletion()`:

    ```php
    /**
     * Refund 1 credit for a failed video rendering.
     *
     * @param  CreditTransaction|null  $originalTransaction  The original deduction transaction to link
     * @param  \Illuminate\Database\Eloquent\Model|null  $reference  The video model
     * @param  array<string, mixed>  $metadata  Additional context
     */
    public function refundForVideoFailure(
        User $user,
        ?CreditTransaction $originalTransaction = null,
        ?\Illuminate\Database\Eloquent\Model $reference = null,
        array $metadata = [],
    ): CreditTransaction {
        return DB::transaction(function () use ($user, $originalTransaction, $reference, $metadata) {
            $user->addCredits(1);

            $refundMetadata = $metadata;
            if ($originalTransaction) {
                $refundMetadata['original_transaction_id'] = $originalTransaction->id;
            }

            return CreditTransaction::create([
                'user_id' => $user->id,
                'amount' => 1,
                'balance_after' => $user->fresh()->video_credits,
                'type' => 'refund',
                'reason' => 'video_failure_refund',
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference?->getKey(),
                'metadata' => $refundMetadata ?: null,
            ]);
        });
    }
    ```

- Ensure the `CreditTransaction` import is already present at the top of the file (it should be from E005-F003)
- Run `vendor/bin/pint --dirty` to format the PHP files
- Run `php artisan test tests/Feature/Credits/CreditDeductionTest.php --compact` to ensure existing deduction tests still pass

### 2. Write Credit Refund Tests

- **Task ID**: write-credit-refund-tests
- **Depends On**: add-refund-method
- **Assigned To**: credit-refund-test-dev
- **Agent Type**: coder
- **Parallel**: false
- Create the test file using: `php artisan make:test Credits/CreditRefundTest --pest --no-interaction`
- Write the following tests in `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Credits/CreditRefundTest.php`:
    - Add imports at the top:
        ```php
        use App\Models\CreditTransaction;
        use App\Models\User;
        use App\Services\CreditService;
        ```
    - `test('credit service refunds credit for video failure')`:

        ```php
        test('credit service refunds credit for video failure', function () {
            $user = User::factory()->create(['video_credits' => 2]);
            $service = new CreditService();

            $transaction = $service->refundForVideoFailure($user);

            $user->refresh();
            expect($user->video_credits)->toBe(3);
            expect($transaction)->toBeInstanceOf(CreditTransaction::class);
            expect($transaction->amount)->toBe(1);
            expect($transaction->balance_after)->toBe(3);
            expect($transaction->type)->toBe('refund');
            expect($transaction->reason)->toBe('video_failure_refund');
            expect($transaction->user_id)->toBe($user->id);
        });
        ```

    - `test('credit refund creates transaction record')`:

        ```php
        test('credit refund creates transaction record', function () {
            $user = User::factory()->create(['video_credits' => 1]);
            $service = new CreditService();

            $service->refundForVideoFailure($user);

            expect(CreditTransaction::where('user_id', $user->id)->count())->toBe(1);

            $transaction = CreditTransaction::where('user_id', $user->id)->first();
            expect($transaction->amount)->toBe(1);
            expect($transaction->balance_after)->toBe(2);
            expect($transaction->type)->toBe('refund');
            expect($transaction->reason)->toBe('video_failure_refund');
        });
        ```

    - `test('credit refund works for user with zero credits')`:

        ```php
        test('credit refund works for user with zero credits', function () {
            $user = User::factory()->create(['video_credits' => 0]);
            $service = new CreditService();

            $transaction = $service->refundForVideoFailure($user);

            $user->refresh();
            expect($user->video_credits)->toBe(1);
            expect($transaction->balance_after)->toBe(1);
        });
        ```

    - `test('credit refund links to original deduction transaction')`:

        ```php
        test('credit refund links to original deduction transaction', function () {
            $user = User::factory()->create(['video_credits' => 5]);
            $service = new CreditService();

            $deduction = $service->deductForVideoCompletion($user);
            $refund = $service->refundForVideoFailure($user, $deduction);

            expect($refund->metadata)->toHaveKey('original_transaction_id');
            expect($refund->metadata['original_transaction_id'])->toBe($deduction->id);
        });
        ```

    - `test('credit refund without original transaction has no link in metadata')`:

        ```php
        test('credit refund without original transaction has no link in metadata', function () {
            $user = User::factory()->create(['video_credits' => 1]);
            $service = new CreditService();

            $transaction = $service->refundForVideoFailure($user);

            expect($transaction->metadata)->toBeNull();
        });
        ```

    - `test('full deduction then refund lifecycle restores balance')`:

        ```php
        test('full deduction then refund lifecycle restores balance', function () {
            $user = User::factory()->create(['video_credits' => 3]);
            $service = new CreditService();

            // Deduct 1 credit for video completion
            $deduction = $service->deductForVideoCompletion($user);
            expect($user->fresh()->video_credits)->toBe(2);

            // Video fails, refund the credit
            $refund = $service->refundForVideoFailure($user, $deduction);
            expect($user->fresh()->video_credits)->toBe(3);

            // Verify transaction trail
            $transactions = CreditTransaction::where('user_id', $user->id)
                ->orderBy('id')
                ->get();

            expect($transactions)->toHaveCount(2);
            expect($transactions[0]->type)->toBe('deduction');
            expect($transactions[0]->amount)->toBe(-1);
            expect($transactions[0]->balance_after)->toBe(2);
            expect($transactions[1]->type)->toBe('refund');
            expect($transactions[1]->amount)->toBe(1);
            expect($transactions[1]->balance_after)->toBe(3);
            expect($transactions[1]->metadata['original_transaction_id'])->toBe($transactions[0]->id);
        });
        ```

    - `test('credit refund stores custom metadata')`:

        ```php
        test('credit refund stores custom metadata', function () {
            $user = User::factory()->create(['video_credits' => 1]);
            $service = new CreditService();

            $transaction = $service->refundForVideoFailure($user, null, null, [
                'failure_reason' => 'rendering_timeout',
                'retry_count' => 2,
            ]);

            expect($transaction->metadata)->toBe([
                'failure_reason' => 'rendering_timeout',
                'retry_count' => 2,
            ]);
        });
        ```

    - `test('credit refund merges original transaction id with custom metadata')`:

        ```php
        test('credit refund merges original transaction id with custom metadata', function () {
            $user = User::factory()->create(['video_credits' => 5]);
            $service = new CreditService();

            $deduction = $service->deductForVideoCompletion($user);
            $refund = $service->refundForVideoFailure($user, $deduction, null, [
                'failure_reason' => 'api_error',
            ]);

            expect($refund->metadata)->toHaveKey('original_transaction_id');
            expect($refund->metadata['original_transaction_id'])->toBe($deduction->id);
            expect($refund->metadata)->toHaveKey('failure_reason');
            expect($refund->metadata['failure_reason'])->toBe('api_error');
        });
        ```

    - `test('multiple refunds track correct balance_after')`:

        ```php
        test('multiple refunds track correct balance_after', function () {
            $user = User::factory()->create(['video_credits' => 0]);
            $service = new CreditService();

            $tx1 = $service->refundForVideoFailure($user);
            $tx2 = $service->refundForVideoFailure($user);
            $tx3 = $service->refundForVideoFailure($user);

            expect($tx1->balance_after)->toBe(1);
            expect($tx2->balance_after)->toBe(2);
            expect($tx3->balance_after)->toBe(3);
            expect($user->fresh()->video_credits)->toBe(3);
        });
        ```

    - `test('credit refund without reference stores null reference')`:

        ```php
        test('credit refund without reference stores null reference', function () {
            $user = User::factory()->create(['video_credits' => 1]);
            $service = new CreditService();

            $transaction = $service->refundForVideoFailure($user);

            expect($transaction->reference_type)->toBeNull();
            expect($transaction->reference_id)->toBeNull();
        });
        ```

- Run the tests: `php artisan test tests/Feature/Credits/CreditRefundTest.php --compact`
- Ensure all tests pass
- Run `vendor/bin/pint --dirty` to format the test file

### 3. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: add-refund-method, write-credit-refund-tests
- **Assigned To**: credit-refund-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed in the Validation Commands section below
- Verify the `CreditService` at `/Users/young/Nextcloud/dev/Itervel/app/Services/CreditService.php`:
    - Has both `deductForVideoCompletion()` and `refundForVideoFailure()` methods
    - `refundForVideoFailure()` uses `DB::transaction()` for atomicity
    - `refundForVideoFailure()` calls `$user->addCredits(1)` internally
    - `refundForVideoFailure()` creates a `CreditTransaction` with `type => 'refund'`, `reason => 'video_failure_refund'`, `amount => 1`
    - `refundForVideoFailure()` stores `original_transaction_id` in metadata when an original transaction is provided
    - `refundForVideoFailure()` merges custom metadata with the original transaction ID
    - Parameters include `$user`, optional `$originalTransaction`, optional `$reference`, and optional `$metadata`
- Verify the refund method has a PHPDoc block with proper documentation
- Verify credit refund tests pass: `php artisan test tests/Feature/Credits/CreditRefundTest.php --compact`
- Verify credit deduction tests still pass: `php artisan test tests/Feature/Credits/CreditDeductionTest.php --compact`
- Verify all credit-related tests pass: `php artisan test tests/Feature/Credits --compact`
- Run the full test suite for regression check: `php artisan test --compact`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Run TypeScript type checking: `npm run types`
- Run ESLint: `npm run lint`
- Confirm all acceptance criteria are met

## Acceptance Criteria

- The `CreditService` has a `refundForVideoFailure()` method at `app/Services/CreditService.php`
- `refundForVideoFailure()` atomically adds 1 credit to the user's balance and creates a refund `CreditTransaction` record
- The refund transaction has `type` set to 'refund', `reason` set to 'video_failure_refund', and `amount` set to 1 (positive)
- The refund transaction `balance_after` accurately reflects the post-refund balance
- When an original deduction transaction is provided, the refund metadata contains `original_transaction_id` linking to it
- When no original transaction is provided, the metadata is null (unless custom metadata is passed)
- Custom metadata is correctly merged with the original transaction ID when both are provided
- The refund works for users with 0 credits (restoring balance from 0 to 1)
- The full deduction-then-refund lifecycle correctly restores the user's balance to its original value
- Multiple refunds are tracked with correct `balance_after` values
- Nullable `reference_type` and `reference_id` are supported
- All credit refund tests pass
- All existing credit deduction tests pass without regressions
- All existing tests pass without regressions
- PHP code passes Pint formatting
- TypeScript types compile without errors
- ESLint checks pass

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run credit refund tests
php artisan test tests/Feature/Credits/CreditRefundTest.php --compact

# Run credit deduction tests (verify no regressions)
php artisan test tests/Feature/Credits/CreditDeductionTest.php --compact

# Run all credit-related tests
php artisan test tests/Feature/Credits --compact

# Run full test suite for regression check
php artisan test --compact

# PHP code formatting
vendor/bin/pint --dirty

# TypeScript type checking
npm run types

# ESLint linting
npm run lint
```

## Notes

- **Refund does not require sufficient credits**: Unlike `deductCredits()`, which requires the user to have enough credits, `addCredits()` (used by the refund) always succeeds for positive amounts. A user can have 0 credits and receive a refund, bringing them to 1. This is correct behavior -- a refund restores what was taken.
- **Original transaction linking via metadata**: The refund links to the original deduction transaction via `metadata.original_transaction_id` rather than a dedicated foreign key column. This is a pragmatic choice -- a dedicated column would require an additional migration and adds complexity for a relationship that is informational rather than structural. The metadata approach is flexible and can be queried via JSON operators if needed (`WHERE metadata->>'original_transaction_id' = ?`).
- **No notification/email on refund**: This feature does not send notifications when a refund is issued. User notifications for video failures and refunds are the responsibility of E014-F005 (Render Failure Handling), which orchestrates the failure detection, retry, refund, and notification flow.
- **Downstream consumers**: The `refundForVideoFailure()` method will be consumed by:
    - E014-F005 (Render Failure Handling) -- calls refund after retry failure
    - E014-F006 (Shotstack Completion Webhook) -- calls refund when webhook reports permanent failure
    - E007-F004 (Transaction History) -- displays refund transactions to the user
- **The CreditService remains stateless**: No constructor dependencies, no injected services. Both `deductForVideoCompletion()` and `refundForVideoFailure()` are self-contained operations that can be called independently. This keeps the service simple and easily testable.
- **All commands should be run inside the Docker container**. Use `make shell` to enter the container, or prefix with `docker compose exec app`.

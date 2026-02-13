# Feature: Credit Deduction on Completion

**Epic**: E005-credits-and-billing.md
**Feature**: E005-F003
**Epic depends on**: E002-user-authentication.md
**Feature depends on**: E005-F002

## Task Description

Credit Deduction on Completion deducts 1 credit from the user's balance when a video is successfully completed. This is a critical business logic feature -- credits are the platform's currency, and deduction on successful video rendering ensures users are charged only for completed work.

**What it does**: Deducts 1 credit from the user's balance when a video is successfully completed.

**Expected outcome**: Upon successful video rendering, 1 credit is automatically deducted. The balance updates immediately.

This feature builds on E005-F002 (Free Credit for New Users), which adds the `deductCredits()`, `addCredits()`, `hasCredits()`, and `hasSufficientCredits()` helper methods to the User model. Those methods provide atomic credit operations. This feature layers on top by adding:

1. **Credit transaction logging**: A `CreditTransaction` model and `credit_transactions` database table that records every credit movement (deductions, refunds, purchases) as an immutable audit trail. This is essential for financial accountability and debugging.

2. **A `CreditService`**: A service class that wraps credit operations with transaction logging. Instead of calling `$user->deductCredits()` directly (which only updates the balance), downstream features (video rendering jobs, webhook handlers) call the `CreditService`, which deducts the credit AND creates a transaction record atomically.

3. **Tests**: Comprehensive tests for the credit transaction model, the service, and the deduction flow.

Since the video rendering system (E014) does not exist yet, this feature builds the **deduction infrastructure** that video rendering features will consume. The `CreditService::deductForVideoCompletion()` method will be called by the video rendering job/webhook handler when a video successfully completes. No video routes, controllers, or models are created here.

The `CreditTransaction` model also serves as the foundation for E007-F004 (Transaction History), which will display a list of all credit transactions to the user.

## Objective

Create a `CreditTransaction` model with migration to record all credit movements, and a `CreditService` class that handles credit deduction with transaction logging. When a video completes successfully, the service deducts 1 credit from the user and creates a transaction record. The deduction is wrapped in a database transaction to ensure atomicity. All operations are covered by comprehensive tests.

## Solution Approach

### 1. Create `CreditTransaction` Model and Migration

Create a `credit_transactions` table that records every credit movement:

```php
Schema::create('credit_transactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->integer('amount'); // positive for additions, negative for deductions
    $table->unsignedInteger('balance_after'); // user's balance after this transaction
    $table->string('type'); // 'deduction', 'refund', 'purchase', 'bonus'
    $table->string('reason'); // e.g., 'video_completion', 'video_failure_refund', 'credit_purchase', 'registration_bonus'
    $table->string('reference_type')->nullable(); // polymorphic: e.g., 'App\Models\Video'
    $table->unsignedBigInteger('reference_id')->nullable(); // polymorphic: e.g., video ID
    $table->json('metadata')->nullable(); // additional context (e.g., video title, package name)
    $table->timestamps();

    $table->index(['user_id', 'type']);
    $table->index(['reference_type', 'reference_id']);
});
```

The `CreditTransaction` model:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CreditTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'balance_after',
        'type',
        'reason',
        'reference_type',
        'reference_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'balance_after' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
```

Add a `creditTransactions` relationship to the User model:

```php
public function creditTransactions(): HasMany
{
    return $this->hasMany(CreditTransaction::class);
}
```

### 2. Create `CreditService`

Create a service class at `app/Services/CreditService.php` that wraps credit operations with transaction logging:

```php
namespace App\Services;

use App\Models\CreditTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreditService
{
    /**
     * Deduct 1 credit for a completed video.
     *
     * @param  \Illuminate\Database\Eloquent\Model|null  $reference  The video model (or null if not yet created)
     * @param  array<string, mixed>  $metadata  Additional context
     */
    public function deductForVideoCompletion(
        User $user,
        ?\Illuminate\Database\Eloquent\Model $reference = null,
        array $metadata = [],
    ): CreditTransaction {
        return DB::transaction(function () use ($user, $reference, $metadata) {
            $user->deductCredits(1);

            return CreditTransaction::create([
                'user_id' => $user->id,
                'amount' => -1,
                'balance_after' => $user->fresh()->video_credits,
                'type' => 'deduction',
                'reason' => 'video_completion',
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference?->getKey(),
                'metadata' => $metadata,
            ]);
        });
    }
}
```

Key design decisions:

- **Database transaction**: The deduction and transaction logging are wrapped in `DB::transaction()` to ensure atomicity -- if either the deduction or the logging fails, both are rolled back.
- **Negative amounts for deductions**: The `amount` column stores negative values for deductions and positive values for additions. This makes calculating totals simple (`SUM(amount)`) and follows accounting conventions.
- **`balance_after`**: Records the user's balance after the transaction. This creates a verifiable audit trail -- you can reconstruct the balance history from transactions.
- **Polymorphic reference**: The `reference_type` and `reference_id` columns use a polymorphic pattern to link transactions to any model (videos, packages, etc.). This is nullable since the Video model doesn't exist yet, but will be used when video rendering features (E014) are built.
- **Metadata**: A JSON column for storing additional context (e.g., video title, rendering duration). This avoids needing to add columns for every possible piece of contextual data.
- **Delegates to User model methods**: The service calls `$user->deductCredits()` from E005-F002 rather than directly manipulating `video_credits`. This maintains the single responsibility of the User model's credit methods (validation, atomic SQL) while the service handles business logic (transaction logging, context).

### 3. Create Factory for CreditTransaction

Create a factory for the `CreditTransaction` model for use in tests:

```php
namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CreditTransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount' => -1,
            'balance_after' => 0,
            'type' => 'deduction',
            'reason' => 'video_completion',
            'reference_type' => null,
            'reference_id' => null,
            'metadata' => null,
        ];
    }

    public function refund(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => 1,
            'type' => 'refund',
            'reason' => 'video_failure_refund',
        ]);
    }

    public function purchase(int $amount = 5): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => $amount,
            'type' => 'purchase',
            'reason' => 'credit_purchase',
        ]);
    }

    public function bonus(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => 1,
            'type' => 'bonus',
            'reason' => 'registration_bonus',
        ]);
    }
}
```

### 4. Testing Approach

Write feature tests that validate:

- The `CreditTransaction` model and its relationships
- The `CreditService::deductForVideoCompletion()` method
- Atomicity: if deduction fails (insufficient credits), no transaction record is created
- The `balance_after` field accurately reflects the post-deduction balance
- The `creditTransactions` relationship on the User model
- Transaction metadata is stored correctly

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` -- The User Eloquent model. After E005-F002, will have `deductCredits()`, `addCredits()`, `hasCredits()`, `hasSufficientCredits()` methods, `video_credits` in `$fillable`, and `video_credits` cast to integer. Must be modified to add the `creditTransactions()` HasMany relationship.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` -- User factory. After E002-F001, includes `video_credits => 0` in defaults. Used in tests to create users with specific credit values.
- `/Users/young/Nextcloud/dev/Itervel/database/migrations/0001_01_01_000000_create_users_table.php` -- The original users migration. Referenced for understanding the database schema patterns and conventions used in this project.
- `/Users/young/Nextcloud/dev/Itervel/app/Providers/AppServiceProvider.php` -- Application service provider. Referenced for understanding if any services need to be registered. The `CreditService` does not need to be registered since Laravel auto-resolves concrete classes.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Middleware/HandleInertiaRequests.php` -- The Inertia shared data middleware. Shares `auth.user` on every request. No changes needed; referenced to confirm that `video_credits` is automatically included in the shared user data after deduction.
- `/Users/young/Nextcloud/dev/Itervel/bootstrap/app.php` -- Application bootstrap. No changes needed for this feature.
- `/Users/young/Nextcloud/dev/Itervel/config/queue.php` -- Queue configuration (database driver default, Redis available). Referenced for understanding how async jobs will work when video rendering features are built. The `CreditService` is synchronous -- it's called by the job/webhook handler, not dispatched as a job itself.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration. All Feature tests use `RefreshDatabase` automatically. Referenced for test setup.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/DashboardTest.php` -- Existing feature test. Referenced for understanding test patterns.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Auth/RegistrationTest.php` -- Existing registration test. Referenced for understanding test patterns with POST requests and model assertions.
- `/Users/young/Nextcloud/dev/Itervel/specs/features/E005-F002-free-credit-for-new-users.md` -- The dependency feature spec. Details the `deductCredits()`, `addCredits()`, `hasCredits()`, `hasSufficientCredits()` methods on the User model. Critical reference for understanding the credit API this feature builds on.
- `/Users/young/Nextcloud/dev/Itervel/specs/features/E005-F006-insufficient-credits-block.md` -- Sibling feature spec. The `EnsureSufficientCredits` middleware blocks requests for users with 0 credits. Referenced for understanding how credit enforcement works at the route level.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/app/Models/CreditTransaction.php` -- Eloquent model for credit transactions. Has `user()` BelongsTo relationship, `reference()` MorphTo relationship, and casts for `amount` (integer), `balance_after` (integer), and `metadata` (array).
- `/Users/young/Nextcloud/dev/Itervel/database/migrations/xxxx_xx_xx_xxxxxx_create_credit_transactions_table.php` -- Migration creating the `credit_transactions` table with columns: `id`, `user_id`, `amount`, `balance_after`, `type`, `reason`, `reference_type`, `reference_id`, `metadata`, `timestamps`. Includes indexes on `[user_id, type]` and `[reference_type, reference_id]`.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/CreditTransactionFactory.php` -- Factory for CreditTransaction model with default state (deduction) and custom states: `refund()`, `purchase()`, `bonus()`.
- `/Users/young/Nextcloud/dev/Itervel/app/Services/CreditService.php` -- Service class for credit operations with transaction logging. Contains `deductForVideoCompletion()` method that atomically deducts credits and creates a transaction record.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Credits/CreditDeductionTest.php` -- Pest feature tests for credit deduction functionality. Tests the CreditTransaction model, CreditService, deduction flow, atomicity, and User model relationship.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: credit-deduction-backend-dev
    - Role: Creates the CreditTransaction model and migration, the CreditTransactionFactory, the CreditService class, and adds the creditTransactions relationship to the User model. Verifies E005-F002's credit helper methods are in place.
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: credit-deduction-test-dev
    - Role: Writes comprehensive feature tests for the CreditTransaction model, CreditService deduction flow, atomicity guarantees, and User model relationship
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: credit-deduction-reviewer
    - Role: Validates the complete credit deduction feature against acceptance criteria, runs all tests, checks types, runs linting and formatting
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Create CreditTransaction Model, Migration, Factory, and CreditService

- **Task ID**: create-credit-transaction-infrastructure
- **Depends On**: none
- **Assigned To**: credit-deduction-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- Verify that E005-F002's changes are in place on the User model at `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php`: confirm that `deductCredits()`, `addCredits()`, `hasCredits()`, and `hasSufficientCredits()` methods exist, `video_credits` is in `$fillable`, and `video_credits` is cast to `integer` in the `casts()` method. If any are missing, add them as specified in the E005-F002 plan.
- Create the model and migration together using: `php artisan make:model CreditTransaction -mf --no-interaction` (this creates the model, migration, and factory)
- Edit the generated migration file to define the `credit_transactions` table with:
    - `$table->id()`
    - `$table->foreignId('user_id')->constrained()->cascadeOnDelete()`
    - `$table->integer('amount')` -- positive for additions, negative for deductions
    - `$table->unsignedInteger('balance_after')` -- user's balance after this transaction
    - `$table->string('type')` -- 'deduction', 'refund', 'purchase', 'bonus'
    - `$table->string('reason')` -- e.g., 'video_completion', 'video_failure_refund'
    - `$table->string('reference_type')->nullable()` -- polymorphic model class
    - `$table->unsignedBigInteger('reference_id')->nullable()` -- polymorphic model ID
    - `$table->json('metadata')->nullable()` -- additional context
    - `$table->timestamps()`
    - `$table->index(['user_id', 'type'])`
    - `$table->index(['reference_type', 'reference_id'])`
- Edit the generated `/Users/young/Nextcloud/dev/Itervel/app/Models/CreditTransaction.php` model:
    - Set `$fillable` to: `['user_id', 'amount', 'balance_after', 'type', 'reason', 'reference_type', 'reference_id', 'metadata']`
    - Add `casts()` method returning: `['amount' => 'integer', 'balance_after' => 'integer', 'metadata' => 'array']`
    - Add `user(): BelongsTo` relationship: `return $this->belongsTo(User::class);`
    - Add `reference(): MorphTo` relationship: `return $this->morphTo();`
    - Add `use HasFactory` trait and import `Illuminate\Database\Eloquent\Factories\HasFactory`
    - Add appropriate imports: `BelongsTo`, `MorphTo` from `Illuminate\Database\Eloquent\Relations`
- Edit the generated `/Users/young/Nextcloud/dev/Itervel/database/factories/CreditTransactionFactory.php`:
    - Set the `definition()` to return: `['user_id' => User::factory(), 'amount' => -1, 'balance_after' => 0, 'type' => 'deduction', 'reason' => 'video_completion', 'reference_type' => null, 'reference_id' => null, 'metadata' => null]`
    - Add `refund(): static` state: sets `amount => 1`, `type => 'refund'`, `reason => 'video_failure_refund'`
    - Add `purchase(int $amount = 5): static` state: sets `amount => $amount`, `type => 'purchase'`, `reason => 'credit_purchase'`
    - Add `bonus(): static` state: sets `amount => 1`, `type => 'bonus'`, `reason => 'registration_bonus'`
    - Import `App\Models\User`
- Add a `creditTransactions(): HasMany` relationship to the User model at `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php`:
    - Import `Illuminate\Database\Eloquent\Relations\HasMany`
    - Add the method after the credit helper methods: `public function creditTransactions(): HasMany { return $this->hasMany(CreditTransaction::class); }`
    - Import `App\Models\CreditTransaction` (or use the full namespace in the relationship)
- Create the `app/Services` directory if it doesn't exist
- Create `/Users/young/Nextcloud/dev/Itervel/app/Services/CreditService.php`:
    - Namespace: `App\Services`
    - Import: `App\Models\CreditTransaction`, `App\Models\User`, `Illuminate\Support\Facades\DB`
    - Add a `deductForVideoCompletion()` method:

        ```php
        /**
         * Deduct 1 credit for a completed video.
         *
         * @param  \Illuminate\Database\Eloquent\Model|null  $reference
         * @param  array<string, mixed>  $metadata
         */
        public function deductForVideoCompletion(
            User $user,
            ?\Illuminate\Database\Eloquent\Model $reference = null,
            array $metadata = [],
        ): CreditTransaction {
            return DB::transaction(function () use ($user, $reference, $metadata) {
                $user->deductCredits(1);

                return CreditTransaction::create([
                    'user_id' => $user->id,
                    'amount' => -1,
                    'balance_after' => $user->fresh()->video_credits,
                    'type' => 'deduction',
                    'reason' => 'video_completion',
                    'reference_type' => $reference ? get_class($reference) : null,
                    'reference_id' => $reference?->getKey(),
                    'metadata' => $metadata,
                ]);
            });
        }
        ```

- Run the migration: `php artisan migrate --no-interaction`
- Run `vendor/bin/pint --dirty` to format all PHP files

### 2. Write Credit Deduction Tests

- **Task ID**: write-credit-deduction-tests
- **Depends On**: create-credit-transaction-infrastructure
- **Assigned To**: credit-deduction-test-dev
- **Agent Type**: coder
- **Parallel**: false
- Create the test file using: `php artisan make:test Credits/CreditDeductionTest --pest --no-interaction`
- Write the following tests in `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Credits/CreditDeductionTest.php`:
    - Add imports at the top:
        ```php
        use App\Models\CreditTransaction;
        use App\Models\User;
        use App\Services\CreditService;
        ```
    - `test('credit transaction model belongs to a user')`:

        ```php
        test('credit transaction model belongs to a user', function () {
            $user = User::factory()->create(['video_credits' => 5]);
            $transaction = CreditTransaction::factory()->create([
                'user_id' => $user->id,
                'balance_after' => 4,
            ]);

            expect($transaction->user->id)->toBe($user->id);
        });
        ```

    - `test('user has many credit transactions')`:

        ```php
        test('user has many credit transactions', function () {
            $user = User::factory()->create(['video_credits' => 5]);
            CreditTransaction::factory()->count(3)->create([
                'user_id' => $user->id,
                'balance_after' => 4,
            ]);

            expect($user->creditTransactions)->toHaveCount(3);
        });
        ```

    - `test('credit service deducts credit for video completion')`:

        ```php
        test('credit service deducts credit for video completion', function () {
            $user = User::factory()->create(['video_credits' => 3]);
            $service = new CreditService();

            $transaction = $service->deductForVideoCompletion($user);

            $user->refresh();
            expect($user->video_credits)->toBe(2);
            expect($transaction)->toBeInstanceOf(CreditTransaction::class);
            expect($transaction->amount)->toBe(-1);
            expect($transaction->balance_after)->toBe(2);
            expect($transaction->type)->toBe('deduction');
            expect($transaction->reason)->toBe('video_completion');
            expect($transaction->user_id)->toBe($user->id);
        });
        ```

    - `test('credit service creates transaction record on deduction')`:

        ```php
        test('credit service creates transaction record on deduction', function () {
            $user = User::factory()->create(['video_credits' => 5]);
            $service = new CreditService();

            $service->deductForVideoCompletion($user);

            expect(CreditTransaction::where('user_id', $user->id)->count())->toBe(1);

            $transaction = CreditTransaction::where('user_id', $user->id)->first();
            expect($transaction->amount)->toBe(-1);
            expect($transaction->balance_after)->toBe(4);
            expect($transaction->type)->toBe('deduction');
            expect($transaction->reason)->toBe('video_completion');
        });
        ```

    - `test('credit service throws exception when user has insufficient credits')`:

        ```php
        test('credit service throws exception when user has insufficient credits', function () {
            $user = User::factory()->create(['video_credits' => 0]);
            $service = new CreditService();

            expect(fn () => $service->deductForVideoCompletion($user))
                ->toThrow(\RuntimeException::class, 'Insufficient credits.');
        });
        ```

    - `test('no transaction record is created when deduction fails')`:

        ```php
        test('no transaction record is created when deduction fails', function () {
            $user = User::factory()->create(['video_credits' => 0]);
            $service = new CreditService();

            try {
                $service->deductForVideoCompletion($user);
            } catch (\RuntimeException $e) {
                // expected
            }

            expect(CreditTransaction::where('user_id', $user->id)->count())->toBe(0);
            expect($user->fresh()->video_credits)->toBe(0);
        });
        ```

    - `test('credit deduction stores metadata')`:

        ```php
        test('credit deduction stores metadata', function () {
            $user = User::factory()->create(['video_credits' => 5]);
            $service = new CreditService();

            $transaction = $service->deductForVideoCompletion($user, null, [
                'video_title' => 'My First Video',
                'duration' => 120,
            ]);

            expect($transaction->metadata)->toBe([
                'video_title' => 'My First Video',
                'duration' => 120,
            ]);
        });
        ```

    - `test('credit deduction without reference stores null reference')`:

        ```php
        test('credit deduction without reference stores null reference', function () {
            $user = User::factory()->create(['video_credits' => 5]);
            $service = new CreditService();

            $transaction = $service->deductForVideoCompletion($user);

            expect($transaction->reference_type)->toBeNull();
            expect($transaction->reference_id)->toBeNull();
        });
        ```

    - `test('multiple deductions track correct balance_after')`:

        ```php
        test('multiple deductions track correct balance_after', function () {
            $user = User::factory()->create(['video_credits' => 5]);
            $service = new CreditService();

            $tx1 = $service->deductForVideoCompletion($user);
            $tx2 = $service->deductForVideoCompletion($user);
            $tx3 = $service->deductForVideoCompletion($user);

            expect($tx1->balance_after)->toBe(4);
            expect($tx2->balance_after)->toBe(3);
            expect($tx3->balance_after)->toBe(2);
            expect($user->fresh()->video_credits)->toBe(2);
        });
        ```

    - `test('credit transaction factory creates valid records')`:

        ```php
        test('credit transaction factory creates valid records', function () {
            $transaction = CreditTransaction::factory()->create([
                'balance_after' => 4,
            ]);

            expect($transaction)->toBeInstanceOf(CreditTransaction::class);
            expect($transaction->type)->toBe('deduction');
            expect($transaction->amount)->toBe(-1);
        });
        ```

    - `test('credit transaction factory refund state creates refund records')`:

        ```php
        test('credit transaction factory refund state creates refund records', function () {
            $transaction = CreditTransaction::factory()->refund()->create([
                'balance_after' => 6,
            ]);

            expect($transaction->type)->toBe('refund');
            expect($transaction->amount)->toBe(1);
            expect($transaction->reason)->toBe('video_failure_refund');
        });
        ```

    - `test('credit transaction casts metadata to array')`:

        ```php
        test('credit transaction casts metadata to array', function () {
            $transaction = CreditTransaction::factory()->create([
                'balance_after' => 4,
                'metadata' => ['key' => 'value'],
            ]);

            $fresh = CreditTransaction::find($transaction->id);
            expect($fresh->metadata)->toBe(['key' => 'value']);
        });
        ```

- Run the tests: `php artisan test tests/Feature/Credits/CreditDeductionTest.php --compact`
- Ensure all tests pass
- Run `vendor/bin/pint --dirty` to format the test file

### 3. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-credit-transaction-infrastructure, write-credit-deduction-tests
- **Assigned To**: credit-deduction-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed in the Validation Commands section below
- Verify the `CreditTransaction` model at `/Users/young/Nextcloud/dev/Itervel/app/Models/CreditTransaction.php`:
    - Has correct `$fillable` array with all required columns
    - Has `casts()` method casting `amount` to integer, `balance_after` to integer, `metadata` to array
    - Has `user(): BelongsTo` relationship
    - Has `reference(): MorphTo` relationship
    - Uses `HasFactory` trait
- Verify the migration file exists in `/Users/young/Nextcloud/dev/Itervel/database/migrations/` and creates the `credit_transactions` table with all specified columns and indexes
- Verify the factory at `/Users/young/Nextcloud/dev/Itervel/database/factories/CreditTransactionFactory.php`:
    - Has correct default state (deduction, amount -1)
    - Has `refund()`, `purchase()`, and `bonus()` states
- Verify the `CreditService` at `/Users/young/Nextcloud/dev/Itervel/app/Services/CreditService.php`:
    - Has `deductForVideoCompletion()` method
    - Uses `DB::transaction()` for atomicity
    - Calls `$user->deductCredits(1)` internally
    - Creates a `CreditTransaction` record with correct type, reason, and balance_after
    - Handles nullable reference and metadata parameters
- Verify the User model has a `creditTransactions(): HasMany` relationship
- Verify credit deduction tests pass: `php artisan test tests/Feature/Credits/CreditDeductionTest.php --compact`
- If credit tests from E005-F002 exist, verify they still pass: `php artisan test tests/Feature/Credits --compact`
- Run the full test suite for regression check: `php artisan test --compact`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Run TypeScript type checking: `npm run types`
- Run ESLint: `npm run lint`
- Confirm all acceptance criteria are met

## Acceptance Criteria

- A `CreditTransaction` Eloquent model exists at `app/Models/CreditTransaction.php` with `user()` BelongsTo and `reference()` MorphTo relationships
- A `credit_transactions` migration exists and creates the table with columns: `id`, `user_id`, `amount`, `balance_after`, `type`, `reason`, `reference_type`, `reference_id`, `metadata`, `timestamps`
- The `credit_transactions` table has indexes on `[user_id, type]` and `[reference_type, reference_id]`
- A `CreditTransactionFactory` exists with default state and `refund()`, `purchase()`, `bonus()` states
- The `User` model has a `creditTransactions(): HasMany` relationship to `CreditTransaction`
- A `CreditService` class exists at `app/Services/CreditService.php` with a `deductForVideoCompletion()` method
- `CreditService::deductForVideoCompletion()` atomically deducts 1 credit from the user and creates a `CreditTransaction` record
- The transaction record stores `amount` as -1, `type` as 'deduction', `reason` as 'video_completion', and the correct `balance_after`
- If the user has insufficient credits, the deduction throws a `\RuntimeException` and no transaction record is created (atomic rollback)
- The `metadata` column correctly stores and retrieves JSON data
- Nullable `reference_type` and `reference_id` are supported (for use when the Video model is not yet available)
- All credit deduction tests pass
- All existing tests pass without regressions
- PHP code passes Pint formatting
- TypeScript types compile without errors
- ESLint checks pass

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run credit deduction tests
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

- **No Video model exists yet**: The video rendering system is built in E014. The `CreditService::deductForVideoCompletion()` method accepts an optional `$reference` parameter (polymorphic) that will be used to link the transaction to the Video model once it exists. For now, `reference_type` and `reference_id` will be null when called without a reference.
- **Atomicity via DB::transaction()**: The deduction and transaction logging are wrapped in a database transaction. If `deductCredits()` throws (insufficient credits), the entire transaction is rolled back, ensuring no orphaned transaction records. The `deductCredits()` method uses `$this->decrement()` which issues an atomic SQL UPDATE, and the `DB::transaction()` wraps the entire operation for consistency.
- **The `balance_after` field**: This is calculated by calling `$user->fresh()->video_credits` after the deduction. Since the operation runs inside a DB transaction, the fresh read sees the just-decremented value. This creates an audit trail that can be used to verify balance consistency.
- **CreditService is stateless**: It has no constructor dependencies and no state. It can be instantiated directly (`new CreditService()`) or resolved from the container. This keeps it simple and testable.
- **Downstream consumers**: The `CreditService` will be consumed by:
    - E014-F005 (Render Failure Handling) -- for credit refunds (via E005-F004's `refundForVideoFailure()`)
    - E014-F006 (Shotstack Completion Webhook) -- for credit deduction on successful render
    - E007-F003 (Payment Webhook Processing) -- for adding purchased credits
    - E007-F004 (Transaction History) -- for displaying the transaction log to the user
- **All commands should be run inside the Docker container**. Use `make shell` to enter the container, or prefix with `docker compose exec app`.

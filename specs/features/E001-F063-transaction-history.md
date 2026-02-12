# Feature: Transaction History

**Epic**: E001-ai-powered-faceless-video-creation-platform.md
**Feature**: E001-F063
**Dependencies**: E001-F054

## Task Description

Transaction History shows a chronological list of all credit-related transactions for a user, including purchases, video credit usage, refunds, and bonuses. This is an essential feature for user trust and transparency in the credit-based business model -- users need to see where their credits came from and where they went.

**What it does**: Shows a history of all credit purchases, deductions, and refunds.

**Expected outcome**: The user sees a chronological list of all their transactions including purchases, video credit usage, refunds, and bonuses.

This feature depends on E001-F054 (Credit Balance Display), which establishes:

- The `video_credits` column on the users table
- The `video_credits` property on the TypeScript `User` type
- The `CreditBalance` component in the navigation
- The `HandleInertiaRequests` middleware sharing `auth.user.video_credits`

The Transaction History feature creates:

1. A `TransactionType` PHP enum (backed by string) defining the four transaction types: `Purchase`, `Usage`, `Refund`, and `Bonus`.
2. A `CreditTransaction` Eloquent model with migration, factory, and seeder. The model tracks each individual credit event with: user ID, type, credits amount (positive for additions, negative for deductions), balance after transaction, description, and optional metadata (e.g., Stripe payment ID, video ID).
3. A `CreditTransactionController` that serves the transaction history page via Inertia with paginated results.
4. A frontend page (`resources/js/pages/credits/transactions.tsx`) displaying the transaction list in a clean card-based or table-like layout with type badges, credit amounts (color-coded green for additions, red for deductions), descriptions, and timestamps.
5. Routes accessible to authenticated, verified users.
6. A navigation link in the sidebar to access the transaction history.

Other features will create transactions as they operate. For example:

- E001-F055 (Free Credit for New Users) will create a `Bonus` transaction when granting the initial credit
- E001-F056 (Credit Deduction on Completion) will create a `Usage` transaction
- E001-F057 (Credit Refund on Failure) will create a `Refund` transaction
- E001-F062 (Payment Webhook Processing) will create a `Purchase` transaction

This feature only creates the model, migration, controller, and display page. It does NOT create the logic that records transactions (that is done by the features listed above). However, the factory and seeder provide test data for development.

## Objective

Create the `CreditTransaction` model, database migration, factory, enum, controller, and frontend page so users can view a paginated, chronological list of all their credit transactions. When complete, authenticated users can navigate to a "Transaction History" page that displays each transaction with its type (purchase/usage/refund/bonus), credit amount, running balance, description, and date. The page should be empty for new users and will be populated as other features create transactions.

## Solution Approach

### 1. TransactionType Enum (`app/Enums/TransactionType.php`)

Create a string-backed PHP enum to define the four transaction types. Using an enum (instead of raw strings or constants) provides type safety, validation, and a central place to define display labels and colors.

```php
<?php

namespace App\Enums;

enum TransactionType: string
{
    case Purchase = 'purchase';
    case Usage = 'usage';
    case Refund = 'refund';
    case Bonus = 'bonus';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Purchase',
            self::Usage => 'Usage',
            self::Refund => 'Refund',
            self::Bonus => 'Bonus',
        };
    }

    public function isCredit(): bool
    {
        return match ($this) {
            self::Purchase, self::Refund, self::Bonus => true,
            self::Usage => false,
        };
    }
}
```

The `label()` method provides human-readable names for frontend display. The `isCredit()` method indicates whether the transaction type adds credits (positive) or deducts them (negative), useful for validation.

### 2. CreditTransaction Model and Migration

Create via `php artisan make:model CreditTransaction -mf --no-interaction`.

**Migration** (`create_credit_transactions_table`):

```php
Schema::create('credit_transactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('type');              // TransactionType enum value
    $table->integer('credits');          // Positive for additions, negative for deductions
    $table->unsignedInteger('balance');  // Balance after this transaction
    $table->string('description');       // Human-readable description
    $table->json('metadata')->nullable(); // Optional metadata (stripe_payment_id, video_id, etc.)
    $table->timestamps();

    $table->index(['user_id', 'created_at']); // For efficient chronological queries
});
```

Key design decisions:

- `credits` is a signed integer: positive for purchases/refunds/bonuses, negative for usage. This makes summing and displaying straightforward.
- `balance` records the running balance at the time of the transaction, so the history is self-contained and queryable without recalculation.
- `metadata` is a JSON column for flexible storage of related entity IDs (Stripe payment ID, video ID, etc.) without coupling the schema to those features.
- `cascadeOnDelete` ensures transactions are cleaned up when a user is deleted (GDPR compliance via E001-F009).
- A composite index on `[user_id, created_at]` optimizes the primary query pattern: fetching a user's transactions in chronological order.

**Model** (`app/Models/CreditTransaction.php`):

```php
<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'credits',
        'balance',
        'description',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'credits' => 'integer',
            'balance' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

**User Model Relationship**: Add a `creditTransactions()` HasMany relationship to the User model:

```php
public function creditTransactions(): HasMany
{
    return $this->hasMany(CreditTransaction::class)->latest();
}
```

### 3. CreditTransaction Factory

```php
<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CreditTransactionFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement(TransactionType::cases());
        $credits = $type->isCredit()
            ? fake()->numberBetween(1, 35)
            : -1;

        return [
            'user_id' => User::factory(),
            'type' => $type,
            'credits' => $credits,
            'balance' => fake()->numberBetween(0, 50),
            'description' => fake()->sentence(),
            'metadata' => null,
        ];
    }

    public function purchase(int $credits = 5): static
    {
        return $this->state(fn () => [
            'type' => TransactionType::Purchase,
            'credits' => $credits,
            'description' => "Purchased {$credits} credits",
        ]);
    }

    public function usage(): static
    {
        return $this->state(fn () => [
            'type' => TransactionType::Usage,
            'credits' => -1,
            'description' => 'Video generation',
        ]);
    }

    public function refund(): static
    {
        return $this->state(fn () => [
            'type' => TransactionType::Refund,
            'credits' => 1,
            'description' => 'Refund for failed video generation',
        ]);
    }

    public function bonus(int $credits = 1): static
    {
        return $this->state(fn () => [
            'type' => TransactionType::Bonus,
            'credits' => $credits,
            'description' => 'Welcome bonus',
        ]);
    }
}
```

### 4. CreditTransactionController

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreditTransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $transactions = $request->user()
            ->creditTransactions()
            ->paginate(20);

        return Inertia::render('credits/transactions', [
            'transactions' => $transactions,
        ]);
    }
}
```

The controller is minimal. It fetches the authenticated user's transactions (already scoped and ordered by `latest()` from the relationship) with pagination at 20 items per page. Inertia automatically serializes the paginator including links for the frontend.

### 5. Route Registration

Add to `routes/web.php` within the authenticated middleware:

```php
Route::get('credits/transactions', [CreditTransactionController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('credits.transactions');
```

This route should be placed alongside the existing `credits` route (from E001-F060 if built, otherwise standalone).

### 6. Frontend Transaction History Page

Create `resources/js/pages/credits/transactions.tsx` following the established Inertia page patterns (like `dashboard.tsx` and `settings/profile.tsx`).

The page displays:

- A `Heading` with title "Transaction History" and description
- An empty state message when there are no transactions
- A list of transactions rendered as card-like rows in a styled container
- Each transaction row shows:
    - A type badge (color-coded by type using the `Badge` component)
    - Description text
    - Credit amount (green with `+` prefix for additions, red with `-` prefix for deductions)
    - Balance after transaction
    - Formatted date
- Pagination links at the bottom using Inertia's `Link` component

TypeScript types for the transaction data:

```tsx
type CreditTransactionData = {
    id: number;
    type: string;
    credits: number;
    balance: number;
    description: string;
    metadata: Record<string, unknown> | null;
    created_at: string;
};

type PaginatedData<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        per_page: number;
        to: number | null;
        total: number;
    };
};
```

The transaction type badge mapping:

- `purchase` -- `default` variant (primary color), "Purchase" label
- `usage` -- `secondary` variant, "Usage" label
- `refund` -- `outline` variant, "Refund" label
- `bonus` -- `default` variant (primary color), "Bonus" label

### 7. Sidebar Navigation

Add a "Transaction History" link to the sidebar navigation in `app-sidebar.tsx`, using the `Receipt` icon from `lucide-react` (semantic for transaction history). This should appear after the "Buy Credits" link (from E001-F060 if built) or after "Dashboard".

### 8. Testing Strategy

**Feature tests** (`tests/Feature/Credits/CreditTransactionTest.php`):

- Transaction history page is accessible to authenticated verified users
- Transaction history page returns paginated transactions via Inertia
- Transactions are ordered by newest first (latest)
- Users only see their own transactions
- Guests are redirected to login
- Unverified users are redirected to verification notice
- Empty transaction list returns empty paginated data
- Pagination works correctly when there are more than 20 transactions

**Unit tests** (`tests/Unit/Enums/TransactionTypeTest.php`):

- All enum cases have correct values
- `label()` returns correct labels
- `isCredit()` returns correct values for each type

**Unit tests** (`tests/Unit/Models/CreditTransactionTest.php`):

- Model casts `type` to `TransactionType` enum
- Model casts `metadata` to array
- Model belongs to a user
- Factory states produce correct data

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` -- The User model. Must be modified to add a `creditTransactions()` HasMany relationship. After E001-F054, includes `video_credits` in `$fillable`.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Controller.php` -- Base controller class. The new `CreditTransactionController` will extend this.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/ProfileController.php` -- Reference controller showing the pattern for Inertia page rendering with `Inertia::render()`, pagination, and request handling.
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Main web routes file. The transaction history route will be added here within the authenticated middleware group.
- `/Users/young/Nextcloud/dev/Itervel/routes/settings.php` -- Reference for route registration patterns with middleware groups.
- `/Users/young/Nextcloud/dev/Itervel/bootstrap/app.php` -- Application bootstrap. Referenced for understanding middleware configuration.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Middleware/HandleInertiaRequests.php` -- Inertia shared data middleware. Shares `auth.user` with `video_credits`. No changes needed.
- `/Users/young/Nextcloud/dev/Itervel/database/migrations/0001_01_01_000000_create_users_table.php` -- Reference migration for table creation patterns and column types.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` -- Reference factory showing factory state patterns (`unverified()`, `withTwoFactor()`). Used in tests to create users.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/dashboard.tsx` -- Reference for Inertia page component patterns (AppLayout, breadcrumbs, Head).
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/profile.tsx` -- Reference for Inertia page with form handling and Wayfinder action imports.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/card.tsx` -- Card UI component (Card, CardHeader, CardTitle, CardDescription, CardContent). Will be used for the transaction list container.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/badge.tsx` -- Badge UI component with variants (default, secondary, destructive, outline). Will be used for transaction type badges.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/button.tsx` -- Button UI component. Will be used for pagination navigation buttons.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/separator.tsx` -- Separator UI component. May be used between transaction rows.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/heading.tsx` -- Heading component with title, description, and variant props. Will be used on the transaction history page.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/app-sidebar.tsx` -- Sidebar navigation component. Will be modified to add a "Transaction History" navigation item.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/app-layout.tsx` -- Main app layout. The transaction history page will use this layout.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/lib/utils.ts` -- Utility functions including `cn()` for class merging.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/index.ts` -- SharedData type definition. Referenced for type imports.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/auth.ts` -- User type with `video_credits`. Referenced for understanding the user data shape.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/navigation.ts` -- NavItem and BreadcrumbItem types. Referenced for navigation integration.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/DashboardTest.php` -- Reference for testing authenticated Inertia page access patterns.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/ProfileUpdateTest.php` -- Reference for testing Inertia responses with `assertInertia()`.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration. Feature tests use `RefreshDatabase` automatically.
- `/Users/young/Nextcloud/dev/Itervel/config/services.php` -- Reference for config file patterns.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/app/Enums/TransactionType.php` -- String-backed PHP enum defining four transaction types (Purchase, Usage, Refund, Bonus) with `label()` and `isCredit()` helper methods.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/CreditTransaction.php` -- Eloquent model for credit transactions. Has `fillable` fields, casts `type` to `TransactionType` enum and `metadata` to array, belongs to User.
- `/Users/young/Nextcloud/dev/Itervel/database/migrations/xxxx_xx_xx_xxxxxx_create_credit_transactions_table.php` -- Migration creating the `credit_transactions` table with columns: id, user_id (foreign key), type (string), credits (integer), balance (unsigned integer), description (string), metadata (json nullable), timestamps. Includes composite index on `[user_id, created_at]`.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/CreditTransactionFactory.php` -- Factory for CreditTransaction with default state and named states: `purchase()`, `usage()`, `refund()`, `bonus()`.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/CreditTransactionController.php` -- Controller with `index` method that renders the `credits/transactions` Inertia page with paginated transaction data.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/credits/transactions.tsx` -- Inertia page component displaying a paginated list of credit transactions with type badges, credit amounts (color-coded), descriptions, balances, and dates within the AppLayout.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Credits/CreditTransactionTest.php` -- Feature tests for the transaction history page (auth access, Inertia response data, pagination, isolation, guest redirect, unverified redirect).
- `/Users/young/Nextcloud/dev/Itervel/tests/Unit/Enums/TransactionTypeTest.php` -- Unit tests for the TransactionType enum (values, labels, isCredit).
- `/Users/young/Nextcloud/dev/Itervel/tests/Unit/Models/CreditTransactionTest.php` -- Unit tests for the CreditTransaction model (casts, relationships, factory states).

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: transaction-backend-dev
    - Role: Creates the TransactionType enum, CreditTransaction model, migration, factory, controller, routes, and User model relationship. Handles all PHP backend work.
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: transaction-frontend-dev
    - Role: Creates the transaction history page component, adds the navigation link to the sidebar, and ensures proper styling and responsive layout.
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: transaction-test-dev
    - Role: Writes feature tests for the transaction history controller and unit tests for the TransactionType enum and CreditTransaction model.
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: transaction-reviewer
    - Role: Validates the complete transaction history feature against acceptance criteria, runs all tests, checks types, runs linting and formatting.
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Create TransactionType Enum, CreditTransaction Model, Migration, Factory, Controller, and Routes

- **Task ID**: create-transaction-backend
- **Depends On**: none
- **Assigned To**: transaction-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- Create the directory `/Users/young/Nextcloud/dev/Itervel/app/Enums/` if it does not exist
- Create `/Users/young/Nextcloud/dev/Itervel/app/Enums/TransactionType.php` as a string-backed enum:
    - Namespace: `App\Enums`
    - Four cases: `Purchase = 'purchase'`, `Usage = 'usage'`, `Refund = 'refund'`, `Bonus = 'bonus'`
    - Method `label(): string` that returns human-readable names using `match` (e.g., `self::Purchase => 'Purchase'`)
    - Method `isCredit(): bool` that returns `true` for Purchase, Refund, Bonus and `false` for Usage
- Create the model, migration, and factory using: `php artisan make:model CreditTransaction -mf --no-interaction`
- Edit the generated migration file at `/Users/young/Nextcloud/dev/Itervel/database/migrations/*_create_credit_transactions_table.php`:
    - Add columns inside `Schema::create('credit_transactions', ...)`:
        ```php
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->string('type');
        $table->integer('credits');
        $table->unsignedInteger('balance');
        $table->string('description');
        $table->json('metadata')->nullable();
        $table->timestamps();
        $table->index(['user_id', 'created_at']);
        ```
- Edit the generated model at `/Users/young/Nextcloud/dev/Itervel/app/Models/CreditTransaction.php`:
    - Add `use HasFactory;` trait (should be auto-generated)
    - Set `$fillable` to: `['user_id', 'type', 'credits', 'balance', 'description', 'metadata']`
    - Add `casts()` method:
        ```php
        protected function casts(): array
        {
            return [
                'type' => TransactionType::class,
                'credits' => 'integer',
                'balance' => 'integer',
                'metadata' => 'array',
            ];
        }
        ```
    - Add `user()` BelongsTo relationship: `return $this->belongsTo(User::class);`
    - Import `App\Enums\TransactionType`, `Illuminate\Database\Eloquent\Relations\BelongsTo`
- Edit the generated factory at `/Users/young/Nextcloud/dev/Itervel/database/factories/CreditTransactionFactory.php`:
    - Import `App\Enums\TransactionType` and `App\Models\User`
    - In `definition()` return:
        ```php
        $type = fake()->randomElement(TransactionType::cases());
        $credits = $type->isCredit() ? fake()->numberBetween(1, 35) : -1;
        return [
            'user_id' => User::factory(),
            'type' => $type,
            'credits' => $credits,
            'balance' => fake()->numberBetween(0, 50),
            'description' => fake()->sentence(),
            'metadata' => null,
        ];
        ```
    - Add factory state `purchase(int $credits = 5): static` that sets type to `TransactionType::Purchase`, credits to `$credits`, and description to `"Purchased {$credits} credits"`
    - Add factory state `usage(): static` that sets type to `TransactionType::Usage`, credits to `-1`, and description to `'Video generation'`
    - Add factory state `refund(): static` that sets type to `TransactionType::Refund`, credits to `1`, and description to `'Refund for failed video generation'`
    - Add factory state `bonus(int $credits = 1): static` that sets type to `TransactionType::Bonus`, credits to `$credits`, and description to `'Welcome bonus'`
- Modify `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php`:
    - Add `use Illuminate\Database\Eloquent\Relations\HasMany;` import
    - Add the `creditTransactions()` method:
        ```php
        /** @return HasMany<CreditTransaction, $this> */
        public function creditTransactions(): HasMany
        {
            return $this->hasMany(CreditTransaction::class)->latest();
        }
        ```
    - Add `use App\Models\CreditTransaction;` import (or reference via the relationship return type)
- Create the controller. Manually create `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/CreditTransactionController.php`:
    - Namespace: `App\Http\Controllers`
    - Extends `Controller`
    - Has one method `index(Request $request): Response` (using `Illuminate\Http\Request` and `Inertia\Response`)
    - The `index` method:

        ```php
        public function index(Request $request): Response
        {
            $transactions = $request->user()
                ->creditTransactions()
                ->paginate(20);

            return Inertia::render('credits/transactions', [
                'transactions' => $transactions,
            ]);
        }
        ```

    - Import `Inertia\Inertia` and `Inertia\Response`

- Add the route to `/Users/young/Nextcloud/dev/Itervel/routes/web.php`:
    - Add `use App\Http\Controllers\CreditTransactionController;` at the top imports
    - Add the following route before the `require __DIR__.'/settings.php';` line, after the dashboard route (and after the credits route from E001-F060 if it exists):
        ```php
        Route::get('credits/transactions', [CreditTransactionController::class, 'index'])
            ->middleware(['auth', 'verified'])
            ->name('credits.transactions');
        ```
- Run the migration: `php artisan migrate --no-interaction`
- Run `vendor/bin/pint --dirty` to format all changed PHP files
- Verify the route is registered: `php artisan route:list --name=credits.transactions`

### 2. Create Transaction History Frontend Page

- **Task ID**: create-transaction-frontend
- **Depends On**: create-transaction-backend
- **Assigned To**: transaction-frontend-dev
- **Agent Type**: coder
- **Parallel**: false
- Create the directory `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/credits/` if it does not exist
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/credits/transactions.tsx` with the following structure:
    - Import `{ Head, Link }` from `@inertiajs/react`
    - Import `AppLayout` from `@/layouts/app-layout`
    - Import `Heading` from `@/components/heading`
    - Import `{ Badge }` from `@/components/ui/badge`
    - Import `{ Button }` from `@/components/ui/button`
    - Import `{ cn }` from `@/lib/utils`
    - Import `type { BreadcrumbItem }` from `@/types`
    - Import `{ Receipt } from 'lucide-react'`
    - After running `npm run build`, import the Wayfinder route: `import { transactions } from '@/routes/credits'`
    - Define TypeScript types:

        ```tsx
        type CreditTransactionData = {
            id: number;
            type: string;
            credits: number;
            balance: number;
            description: string;
            metadata: Record<string, unknown> | null;
            created_at: string;
        };

        type PaginationLink = {
            url: string | null;
            label: string;
            active: boolean;
        };

        type PaginatedTransactions = {
            data: CreditTransactionData[];
            links: PaginationLink[];
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
            from: number | null;
            to: number | null;
        };
        ```

    - Define props interface:
        ```tsx
        interface TransactionsProps {
            transactions: PaginatedTransactions;
        }
        ```
    - Set up breadcrumbs:
        ```tsx
        const breadcrumbs: BreadcrumbItem[] = [
            { title: 'Transaction History', href: transactions().url },
        ];
        ```
    - Create a helper function to map transaction type to Badge variant:

        ```tsx
        function getTypeBadgeVariant(
            type: string,
        ): 'default' | 'secondary' | 'outline' | 'destructive' {
            switch (type) {
                case 'purchase':
                    return 'default';
                case 'usage':
                    return 'secondary';
                case 'refund':
                    return 'outline';
                case 'bonus':
                    return 'default';
                default:
                    return 'secondary';
            }
        }

        function getTypeLabel(type: string): string {
            switch (type) {
                case 'purchase':
                    return 'Purchase';
                case 'usage':
                    return 'Usage';
                case 'refund':
                    return 'Refund';
                case 'bonus':
                    return 'Bonus';
                default:
                    return type;
            }
        }
        ```

    - Export default function `Transactions({ transactions }: TransactionsProps)` that renders:
        - `AppLayout` with breadcrumbs
        - `Head` with title "Transaction History"
        - A container div with `px-4 py-6` padding (matching settings layout pattern)
        - `Heading` with title "Transaction History" and description "View your credit purchases, usage, refunds, and bonuses"
        - A `max-w-4xl` container for the content
        - **Empty state**: If `transactions.data.length === 0`, show a centered message: "No transactions yet" with a muted description "Your credit transactions will appear here."
        - **Transaction list**: A `div` with `space-y-1` containing each transaction as a row:
            - Each row is a `div` with `flex items-center justify-between rounded-lg border px-4 py-3` and appropriate hover styles
            - Left side (`flex items-center gap-3`):
                - `Badge` with variant from `getTypeBadgeVariant(transaction.type)` showing `getTypeLabel(transaction.type)`
                - Transaction description in `text-sm`
            - Right side (`flex items-center gap-4 text-right`):
                - Credit amount: Use `text-sm font-medium tabular-nums`. If `credits > 0`, show `+{credits}` with `text-green-600 dark:text-green-400`. If `credits < 0`, show `{credits}` (already negative) with `text-red-600 dark:text-red-400`.
                - The word "credits" in `text-xs text-muted-foreground`
                - Balance after: `text-xs text-muted-foreground` showing `Bal: {balance}`
                - Date formatted as a short date string using `new Date(transaction.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })`
        - **Pagination**: If `transactions.last_page > 1`, render pagination below the list:
            - A `div` with `mt-6 flex items-center justify-center gap-1`
            - Map over `transactions.links` and render each as an Inertia `Link` or `span`:
                - If `link.url` is null, render a disabled `Button` variant `outline` size `sm`
                - If `link.active`, render a `Button` variant `default` size `sm`
                - Otherwise, render a `Button` variant `outline` size `sm` wrapped in an Inertia `Link` with `href={link.url}` and `preserveScroll`
                - Strip HTML entities from link labels (the `&laquo;` and `&raquo;` pagination arrows)
    - The page should be responsive and work well on mobile (stack transaction details vertically on small screens using `flex-col sm:flex-row` patterns)

- Modify `/Users/young/Nextcloud/dev/Itervel/resources/js/components/app-sidebar.tsx`:
    - Import `Receipt` from `lucide-react` (add it to the existing lucide-react import line)
    - After running `npm run build`, import the Wayfinder route: `import { transactions as transactionsRoute } from '@/routes/credits'`
    - Add a new nav item to the `mainNavItems` array after "Dashboard" (and after "Buy Credits" from E001-F060 if it exists):
        ```tsx
        {
            title: 'Transactions',
            href: transactionsRoute(),
            icon: Receipt,
        },
        ```
    - Note: If E001-F060 has not been built yet, the import should just be `import { transactions as transactionsRoute } from '@/routes/credits'`. If E001-F060 is already built and has a credits index route, the import may need to be `import { transactions as transactionsRoute } from '@/routes/credits'` alongside the existing credits import.
- Run `npm run build` to generate Wayfinder route functions (this creates or updates `resources/js/routes/credits.ts`)
- Run `npm run types` to verify TypeScript types compile correctly
- Run `npm run lint:fix` to fix any linting issues
- Run `npm run format` to format with Prettier

### 3. Write Feature and Unit Tests

- **Task ID**: write-transaction-tests
- **Depends On**: create-transaction-backend
- **Assigned To**: transaction-test-dev
- **Agent Type**: coder
- **Parallel**: true
- Create the unit test directory `tests/Unit/Enums/` if it does not exist
- Create the unit test using: `php artisan make:test Enums/TransactionTypeTest --pest --unit --no-interaction`
- Write the following tests in `/Users/young/Nextcloud/dev/Itervel/tests/Unit/Enums/TransactionTypeTest.php`:
    - Add `use App\Enums\TransactionType;` at the top
    - `test('all transaction types have correct string values')`:
        ```php
        expect(TransactionType::Purchase->value)->toBe('purchase');
        expect(TransactionType::Usage->value)->toBe('usage');
        expect(TransactionType::Refund->value)->toBe('refund');
        expect(TransactionType::Bonus->value)->toBe('bonus');
        ```
    - `test('label returns human-readable names')`:
        ```php
        expect(TransactionType::Purchase->label())->toBe('Purchase');
        expect(TransactionType::Usage->label())->toBe('Usage');
        expect(TransactionType::Refund->label())->toBe('Refund');
        expect(TransactionType::Bonus->label())->toBe('Bonus');
        ```
    - `test('isCredit returns true for credit-adding types')`:
        ```php
        expect(TransactionType::Purchase->isCredit())->toBeTrue();
        expect(TransactionType::Refund->isCredit())->toBeTrue();
        expect(TransactionType::Bonus->isCredit())->toBeTrue();
        ```
    - `test('isCredit returns false for usage type')`:
        ```php
        expect(TransactionType::Usage->isCredit())->toBeFalse();
        ```
    - `test('there are exactly four transaction types')`:
        ```php
        expect(TransactionType::cases())->toHaveCount(4);
        ```
- Create the unit test directory `tests/Unit/Models/` if it does not exist
- Create the unit test using: `php artisan make:test Models/CreditTransactionTest --pest --unit --no-interaction`
- Write the following tests in `/Users/young/Nextcloud/dev/Itervel/tests/Unit/Models/CreditTransactionTest.php`:
    - Add `use App\Enums\TransactionType;` and `use App\Models\CreditTransaction;` at the top
    - `test('type is cast to TransactionType enum')`:
        ```php
        $transaction = new CreditTransaction(['type' => 'purchase']);
        expect($transaction->type)->toBe(TransactionType::Purchase);
        ```
    - `test('metadata is cast to array')`:
        ```php
        $transaction = new CreditTransaction(['metadata' => '{"key": "value"}']);
        expect($transaction->metadata)->toBeArray();
        expect($transaction->metadata['key'])->toBe('value');
        ```
    - `test('credits is cast to integer')`:
        ```php
        $transaction = new CreditTransaction(['credits' => '5']);
        expect($transaction->credits)->toBeInt();
        expect($transaction->credits)->toBe(5);
        ```
    - `test('balance is cast to integer')`:
        ```php
        $transaction = new CreditTransaction(['balance' => '10']);
        expect($transaction->balance)->toBeInt();
        expect($transaction->balance)->toBe(10);
        ```
- Create the feature test directory `tests/Feature/Credits/` if it does not exist
- Create the feature test using: `php artisan make:test Credits/CreditTransactionTest --pest --no-interaction`
- Write the following tests in `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Credits/CreditTransactionTest.php`:
    - Add `use App\Models\CreditTransaction;` and `use App\Models\User;` at the top
    - `test('transaction history page is displayed for authenticated verified users')`:

        ```php
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('credits.transactions'));

        $response->assertOk();
        ```

    - `test('transaction history page returns paginated transactions via inertia')`:

        ```php
        $user = User::factory()->create();
        CreditTransaction::factory()->count(3)->for($user)->purchase()->create();

        $response = $this->actingAs($user)->get(route('credits.transactions'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('credits/transactions')
            ->has('transactions.data', 3)
        );
        ```

    - `test('transactions are ordered by newest first')`:

        ```php
        $user = User::factory()->create();
        $oldest = CreditTransaction::factory()->for($user)->purchase()->create([
            'created_at' => now()->subDays(2),
            'description' => 'Oldest',
        ]);
        $newest = CreditTransaction::factory()->for($user)->usage()->create([
            'created_at' => now(),
            'description' => 'Newest',
        ]);

        $response = $this->actingAs($user)->get(route('credits.transactions'));

        $response->assertInertia(fn ($page) => $page
            ->where('transactions.data.0.description', 'Newest')
            ->where('transactions.data.1.description', 'Oldest')
        );
        ```

    - `test('users only see their own transactions')`:

        ```php
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        CreditTransaction::factory()->for($user)->purchase()->create(['description' => 'My transaction']);
        CreditTransaction::factory()->for($otherUser)->purchase()->create(['description' => 'Other transaction']);

        $response = $this->actingAs($user)->get(route('credits.transactions'));

        $response->assertInertia(fn ($page) => $page
            ->has('transactions.data', 1)
            ->where('transactions.data.0.description', 'My transaction')
        );
        ```

    - `test('guests are redirected to login from transaction history')`:

        ```php
        $response = $this->get(route('credits.transactions'));

        $response->assertRedirect(route('login'));
        ```

    - `test('unverified users are redirected from transaction history')`:

        ```php
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get(route('credits.transactions'));

        $response->assertRedirect(route('verification.notice'));
        ```

    - `test('empty transaction list returns empty paginated data')`:

        ```php
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('credits.transactions'));

        $response->assertInertia(fn ($page) => $page
            ->component('credits/transactions')
            ->has('transactions.data', 0)
            ->where('transactions.total', 0)
        );
        ```

    - `test('pagination works when there are more than 20 transactions')`:

        ```php
        $user = User::factory()->create();
        CreditTransaction::factory()->count(25)->for($user)->create();

        $response = $this->actingAs($user)->get(route('credits.transactions'));

        $response->assertInertia(fn ($page) => $page
            ->has('transactions.data', 20)
            ->where('transactions.last_page', 2)
            ->where('transactions.total', 25)
        );

        $response = $this->actingAs($user)->get(route('credits.transactions', ['page' => 2]));

        $response->assertInertia(fn ($page) => $page
            ->has('transactions.data', 5)
            ->where('transactions.current_page', 2)
        );
        ```

    - `test('transaction data includes all expected fields')`:

        ```php
        $user = User::factory()->create();
        CreditTransaction::factory()->for($user)->purchase(10)->create([
            'balance' => 15,
            'metadata' => ['stripe_payment_id' => 'pi_test123'],
        ]);

        $response = $this->actingAs($user)->get(route('credits.transactions'));

        $response->assertInertia(fn ($page) => $page
            ->has('transactions.data.0', fn ($transaction) => $transaction
                ->has('id')
                ->where('type', 'purchase')
                ->where('credits', 10)
                ->where('balance', 15)
                ->has('description')
                ->has('metadata')
                ->has('created_at')
                ->etc()
            )
        );
        ```

    - `test('user has credit transactions relationship')`:

        ```php
        $user = User::factory()->create();
        CreditTransaction::factory()->count(3)->for($user)->create();

        expect($user->creditTransactions)->toHaveCount(3);
        expect($user->creditTransactions->first())->toBeInstanceOf(CreditTransaction::class);
        ```

- Run the unit tests: `php artisan test tests/Unit/Enums/TransactionTypeTest.php --compact`
- Run the model unit tests: `php artisan test tests/Unit/Models/CreditTransactionTest.php --compact`
- Run the feature tests: `php artisan test tests/Feature/Credits/CreditTransactionTest.php --compact`
- Ensure all tests pass
- Run `vendor/bin/pint --dirty` to format any PHP files

### 4. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-transaction-backend, create-transaction-frontend, write-transaction-tests
- **Assigned To**: transaction-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed below
- Verify the enum `/Users/young/Nextcloud/dev/Itervel/app/Enums/TransactionType.php` exists with four cases (Purchase, Usage, Refund, Bonus) and helper methods `label()` and `isCredit()`
- Verify the model `/Users/young/Nextcloud/dev/Itervel/app/Models/CreditTransaction.php` exists with correct `$fillable`, `casts()` method (type cast to TransactionType, metadata to array), and `user()` BelongsTo relationship
- Verify the migration creates the `credit_transactions` table with correct columns: id, user_id (foreign key with cascadeOnDelete), type (string), credits (integer), balance (unsigned integer), description (string), metadata (json nullable), timestamps, and composite index on `[user_id, created_at]`
- Verify the factory `/Users/young/Nextcloud/dev/Itervel/database/factories/CreditTransactionFactory.php` exists with states: `purchase()`, `usage()`, `refund()`, `bonus()`
- Verify `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` has a `creditTransactions()` HasMany relationship that returns results ordered by `latest()`
- Verify the controller `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/CreditTransactionController.php` exists with an `index` method that paginates at 20 items
- Verify the route `credits.transactions` exists: `php artisan route:list --name=credits.transactions`
- Verify the frontend page `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/credits/transactions.tsx` exists and renders transaction list with type badges, credit amounts (color-coded), descriptions, balances, dates, pagination, and empty state
- Verify the sidebar navigation in `/Users/young/Nextcloud/dev/Itervel/resources/js/components/app-sidebar.tsx` includes a "Transactions" link with `Receipt` icon
- Run unit tests for enum: `php artisan test tests/Unit/Enums/TransactionTypeTest.php --compact`
- Run unit tests for model: `php artisan test tests/Unit/Models/CreditTransactionTest.php --compact`
- Run feature tests: `php artisan test tests/Feature/Credits/CreditTransactionTest.php --compact`
- Run full test suite for regression check: `php artisan test --compact`
- Run TypeScript type checking: `npm run types`
- Run ESLint: `npm run lint`
- Run Prettier formatting check: `npm run format:check`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Confirm all acceptance criteria are met

## Acceptance Criteria

- A `TransactionType` string-backed enum exists at `app/Enums/TransactionType.php` with four cases: Purchase, Usage, Refund, Bonus
- The enum has `label()` and `isCredit()` helper methods
- A `CreditTransaction` Eloquent model exists at `app/Models/CreditTransaction.php`
- The `credit_transactions` database table is created via migration with columns: id, user_id (foreign key), type, credits, balance, description, metadata (nullable JSON), timestamps
- The table has a composite index on `[user_id, created_at]` for efficient queries
- The User model has a `creditTransactions()` HasMany relationship that orders by latest first
- The `CreditTransaction` model casts `type` to `TransactionType` enum and `metadata` to array
- A `CreditTransactionFactory` exists with states: `purchase()`, `usage()`, `refund()`, `bonus()`
- A `CreditTransactionController` exists with an `index` method rendering `credits/transactions` via Inertia with paginated data (20 per page)
- The transaction history page is accessible at the `credits.transactions` route (`GET /credits/transactions`) for authenticated, verified users
- Guests are redirected to the login page when accessing the transaction history
- Unverified users are redirected to the email verification notice
- The transaction history page displays transactions with type badges, credit amounts (color-coded green for additions, red for deductions), descriptions, running balance, and formatted dates
- The page shows an empty state message when no transactions exist
- Pagination is functional when there are more than 20 transactions
- Users can only see their own transactions (not other users' transactions)
- A "Transactions" navigation link with a Receipt icon appears in the sidebar
- All feature tests pass for the transaction history controller
- All unit tests pass for the TransactionType enum and CreditTransaction model
- All existing tests pass without regressions
- TypeScript types compile without errors
- ESLint and Prettier checks pass
- PHP code passes Pint formatting

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Verify route is registered
php artisan route:list --name=credits.transactions

# Run enum unit tests
php artisan test tests/Unit/Enums/TransactionTypeTest.php --compact

# Run model unit tests
php artisan test tests/Unit/Models/CreditTransactionTest.php --compact

# Run transaction history feature tests
php artisan test tests/Feature/Credits/CreditTransactionTest.php --compact

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

- This feature depends on E001-F054 (Credit Balance Display) being built first. E001-F054 establishes the `video_credits` column on the users table and the credit display in navigation. This feature adds the transaction model and history page alongside the existing credit system.
- The `CreditTransaction` model is designed to be used by many other features. The `credits` column uses a signed integer: positive values for credit additions (purchases, refunds, bonuses) and negative values for deductions (usage). The `balance` column records the running balance at the time of each transaction, providing an audit trail.
- The `metadata` JSON column provides flexible storage for related entity IDs without coupling the schema. For example:
    - E001-F061/F062 (Stripe Checkout/Webhooks) will store `{ "stripe_payment_id": "pi_xxx", "package_id": "starter" }`
    - E001-F056 (Credit Deduction on Completion) will store `{ "video_id": 123 }`
    - E001-F057 (Credit Refund on Failure) will store `{ "video_id": 123, "reason": "render_failed" }`
    - E001-F055 (Free Credit for New Users) will store `{ "type": "welcome" }`
- This feature does NOT create the logic that records transactions. That responsibility belongs to the features that perform credit operations: E001-F055 (bonus on registration), E001-F056 (deduction on completion), E001-F057 (refund on failure), E001-F062 (purchase via webhook). Each of those features should create a `CreditTransaction` record when modifying the user's credit balance.
- The `cascadeOnDelete` on the `user_id` foreign key ensures all transactions are deleted when a user deletes their account (supporting GDPR compliance via E001-F009). The `DeleteUserAccount` action from E001-F009 will automatically clean up transactions through the cascade.
- The composite index on `[user_id, created_at]` optimizes the primary query pattern: fetching a single user's transactions ordered by date. This is the only query pattern used by the controller.
- The factory states (`purchase()`, `usage()`, `refund()`, `bonus()`) provide convenient test helpers that will be used not only by this feature's tests but also by tests in E001-F055, F056, F057, F062, and F085.
- If E001-F060 (Credit Package Purchase) has been built, the `credits` route group already exists and the transaction route fits alongside it (`/credits` for pricing, `/credits/transactions` for history). If E001-F060 has not been built yet, the route is standalone.
- The pagination is set at 20 items per page, matching the pattern established by the Video Library List (E001-F050) specification.
- All commands should be run inside the Docker container. Use `make shell` to enter the container, or prefix with `docker compose exec app`.

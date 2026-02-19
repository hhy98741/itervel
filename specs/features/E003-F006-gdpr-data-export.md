# Feature: GDPR Data Export

**Epic**: E003-user-profile-and-account-management.md
**Feature**: E003-F006
**Epic depends on**: E002-user-authentication.md
**Feature depends on**: E003-F001

## Task Description

GDPR Data Export allows authenticated users to request and download a complete export of all their personal data stored in the Itervel platform. This is a legal requirement under GDPR Article 20 ("Right to data portability") and is a standard compliance feature for any platform that stores user data.

**What it does**: Allows users to export all of their personal data stored in the system.

**Expected outcome**: The user can request an export of their data, receiving a downloadable file containing all their account information, project data, and video metadata.

This feature depends on E001-F005 (User Profile Viewing), which creates the `/settings/account` page with the `AccountController`. After E001-F005 is built:

- The settings area will have an "Account" page at `/settings/account` rendered by `AccountController`
- The settings sidebar will include an "Account" nav item at the top
- The User model will have `video_credits` and implement `MustVerifyEmail` (from E001-F001 via the dependency chain)
- The `settings/account.tsx` page component will exist, displaying account overview information

The GDPR data export feature adds a new section to the account settings page (or a dedicated sub-page) where users can request a data export. Since generating a comprehensive data export (collecting data from multiple tables, assembling files, creating a ZIP archive) can be time-consuming, this is implemented as a queued job. The flow is:

1. User clicks "Request data export" on the account page
2. A queued job is dispatched to generate the export
3. The export is stored as a JSON file inside a ZIP archive in the user's private storage directory
4. The user's `data_export` record is updated with the file path and status
5. The user can download the export file from the account page once it is ready

The export includes: account information (name, email, join date, settings), project data (names, settings), video metadata (titles, statuses, creation dates, settings), and transaction/credit history. Actual video/audio/image files are NOT included in the export (they would be too large); only metadata references are included.

A `data_exports` table tracks export requests with status (pending, processing, completed, failed), timestamps, and the file path. This prevents users from spamming export requests (rate limited to 1 request per 24 hours) and provides a history of exports.

## Objective

Implement a GDPR-compliant personal data export system that allows authenticated users to request a downloadable archive of all their personal data. The system uses a queued job for asynchronous generation, a database table for tracking export status, and provides a download endpoint for the completed export file. The feature integrates into the existing account settings page.

## Solution Approach

### 1. Database: Create data_exports Migration

Create a `data_exports` table to track export requests:

```php
Schema::create('data_exports', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('status')->default('pending'); // pending, processing, completed, failed
    $table->string('file_path')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->timestamps();
});
```

Exports expire after 7 days. The `file_path` stores the relative path within the `local` disk where the ZIP file is stored (e.g., `users/{user_id}/exports/data-export-{timestamp}.zip`).

### 2. Model: Create DataExport Model

Create an Eloquent model for the `data_exports` table with a `belongsTo` relationship to `User` and a `hasMany` relationship from `User` to `DataExport`.

```php
namespace App\Models;

class DataExport extends Model
{
    protected $fillable = ['user_id', 'status', 'file_path', 'completed_at', 'expires_at'];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isDownloadable(): bool
    {
        return $this->status === 'completed'
            && $this->file_path !== null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
```

### 3. Job: Create GenerateDataExport Queued Job

Create a queued job that:

1. Updates the export status to "processing"
2. Collects all user data into a structured array
3. Writes the data as a JSON file
4. Packages it into a ZIP archive
5. Stores the ZIP in the user's private storage directory
6. Updates the export record with the file path, status "completed", and expiry date (7 days)

The job collects data from all user-related tables. Since the platform is in early development and many models (projects, videos, etc.) do not yet exist, the job is designed to be easily extended. Initially it exports:

- Account information (name, email, created_at, email_verified_at, updated_at)
- Account settings (video_credits, two_factor_enabled status)

As future features add models (projects, videos, transactions, etc.), their data collection should be added to this job.

```php
namespace App\Jobs;

class GenerateDataExport implements ShouldQueue
{
    use Queueable;

    public function __construct(public DataExport $dataExport) {}

    public function handle(): void
    {
        $this->dataExport->update(['status' => 'processing']);

        try {
            $user = $this->dataExport->user;
            $data = $this->collectUserData($user);
            $filePath = $this->createExportArchive($user, $data);

            $this->dataExport->update([
                'status' => 'completed',
                'file_path' => $filePath,
                'completed_at' => now(),
                'expires_at' => now()->addDays(7),
            ]);
        } catch (\Throwable $e) {
            $this->dataExport->update(['status' => 'failed']);
            throw $e;
        }
    }

    protected function collectUserData(User $user): array
    {
        return [
            'export_generated_at' => now()->toIso8601String(),
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'created_at' => $user->created_at->toIso8601String(),
                'updated_at' => $user->updated_at->toIso8601String(),
            ],
            'settings' => [
                'video_credits' => $user->video_credits ?? 0,
                'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
            ],
            // Future: projects, videos, transactions, etc.
        ];
    }

    protected function createExportArchive(User $user, array $data): string
    {
        $directory = "users/{$user->id}/exports";
        $filename = "data-export-" . now()->format('Y-m-d-His') . ".zip";
        $filePath = "{$directory}/{$filename}";

        // Create temp files, build ZIP, store to disk
        // ...
        return $filePath;
    }
}
```

### 4. Backend: Create DataExportController

Create a controller with three actions:

- `store()` -- Creates a new export request and dispatches the job (POST)
- `download()` -- Downloads a completed export file (GET)

The controller is placed in the Settings namespace following existing patterns.

```php
namespace App\Http\Controllers\Settings;

class DataExportController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Rate limit: max 1 export request per 24 hours
        $recentExport = $user->dataExports()
            ->where('created_at', '>', now()->subDay())
            ->exists();

        if ($recentExport) {
            return back()->withErrors([
                'export' => 'You can only request one data export per 24 hours.',
            ]);
        }

        $export = $user->dataExports()->create(['status' => 'pending']);
        GenerateDataExport::dispatch($export);

        return back()->with('status', 'export-requested');
    }

    public function download(Request $request, DataExport $dataExport): StreamedResponse
    {
        // Authorize: user can only download their own exports
        if ($dataExport->user_id !== $request->user()->id) {
            abort(403);
        }

        if (!$dataExport->isDownloadable()) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $dataExport->file_path,
            'itervel-data-export.zip'
        );
    }
}
```

### 5. Routes

Add routes in `routes/settings.php` within the `['auth', 'verified']` middleware group:

```php
Route::post('settings/data-export', [DataExportController::class, 'store'])
    ->middleware('throttle:3,1440')
    ->name('data-export.store');

Route::get('settings/data-export/{dataExport}/download', [DataExportController::class, 'download'])
    ->name('data-export.download');
```

The `throttle:3,1440` middleware provides additional rate limiting at the route level (3 requests per 24 hours / 1440 minutes).

### 6. Frontend: Add Data Export Section to Account Page

Add a "Data export" section to the existing `settings/account.tsx` page. The section shows:

- A heading and description explaining what the export contains
- The status of the most recent export (if any), passed as an Inertia prop from `AccountController`
- A "Request data export" button that submits a POST to the store route
- A "Download" button when an export is completed and available
- A status indicator (pending/processing/completed/failed)

The `AccountController::show()` method is updated to pass the latest export data as a prop:

```php
public function show(Request $request): Response
{
    $user = $request->user();
    $latestExport = $user->dataExports()
        ->latest()
        ->first();

    return Inertia::render('settings/account', [
        'joinedAt' => $user->created_at->toDateString(),
        'videoCredits' => $user->video_credits ?? 0,
        'latestExport' => $latestExport ? [
            'id' => $latestExport->id,
            'status' => $latestExport->status,
            'created_at' => $latestExport->created_at->toIso8601String(),
            'completed_at' => $latestExport->completed_at?->toIso8601String(),
            'expires_at' => $latestExport->expires_at?->toIso8601String(),
            'is_downloadable' => $latestExport->isDownloadable(),
        ] : null,
    ]);
}
```

### 7. Frontend Component

Add a `DataExportSection` within the account page (or as a separate component in `resources/js/components/data-export-section.tsx`) that:

- Uses Inertia's `<Form>` component with the Wayfinder-generated `DataExportController.store.form()` action for the request button
- Shows a download link using the Wayfinder-generated download route when the export is ready
- Displays the export status with appropriate styling (pending/processing with spinner, completed with success message, failed with error)
- Shows the export date and expiry date when available
- Disables the request button when an export is already pending/processing

### 8. TypeScript Types

Add type for the export data in the account page props:

```tsx
interface DataExportInfo {
    id: number;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    created_at: string;
    completed_at: string | null;
    expires_at: string | null;
    is_downloadable: boolean;
}

interface AccountProps {
    joinedAt: string;
    videoCredits: number;
    latestExport: DataExportInfo | null;
}
```

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/ProfileController.php` -- Sibling controller in the Settings namespace. Follow its patterns for controller structure, imports, and return types.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Controller.php` -- Base controller class that all controllers extend.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` -- The User model. After E001-F005 dependency chain, it will have `video_credits` and implement `MustVerifyEmail`. A `dataExports()` hasMany relationship must be added.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Middleware/HandleInertiaRequests.php` -- Shares `auth.user` data with all Inertia pages. Referenced for understanding shared data flow.
- `/Users/young/Nextcloud/dev/Itervel/routes/settings.php` -- Settings route definitions. New data export routes must be added here.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/profile.tsx` -- Existing settings page. Follow its exact pattern for page component structure.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/password.tsx` -- Another settings page showing the Form component pattern with Wayfinder actions.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/settings/layout.tsx` -- Settings sidebar layout. No changes needed since the export lives within the existing account page.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/app-layout.tsx` -- Main app layout wrapper.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/heading.tsx` -- Heading component with `variant` prop used for section headers.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/button.tsx` -- Button component with variant and size props.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/badge.tsx` -- Badge component for showing export status.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/spinner.tsx` -- Spinner component for loading states.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/alert.tsx` -- Alert component for status messages.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/alert-error.tsx` -- Alert error component for displaying error messages.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/delete-user.tsx` -- Example of a standalone section component within settings pages. Shows the pattern for a self-contained action section with heading, description, and form submission.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/index.ts` -- Re-exports all types and defines SharedData.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/auth.ts` -- User type definition.
- `/Users/young/Nextcloud/dev/Itervel/database/migrations/0001_01_01_000000_create_users_table.php` -- Reference for migration patterns used in this project.
- `/Users/young/Nextcloud/dev/Itervel/database/migrations/0001_01_01_000002_create_jobs_table.php` -- Jobs table migration. Confirms queue infrastructure exists.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` -- User factory for tests. Referenced for test setup patterns.
- `/Users/young/Nextcloud/dev/Itervel/config/queue.php` -- Queue configuration. Default driver is `database`. Confirms async jobs are supported.
- `/Users/young/Nextcloud/dev/Itervel/config/filesystems.php` -- Filesystem configuration. The `local` disk stores files in `storage/app/private`. Export ZIP files are stored here.
- `/Users/young/Nextcloud/dev/Itervel/app/Actions/Fortify/CreateNewUser.php` -- Example action class pattern. Referenced for structural conventions.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/ProfileUpdateTest.php` -- Existing settings test. Follow its patterns for test structure.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration. Feature tests use RefreshDatabase automatically.
- `/Users/young/Nextcloud/dev/Itervel/bootstrap/app.php` -- Application bootstrap with middleware configuration.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/database/migrations/xxxx_xx_xx_xxxxxx_create_data_exports_table.php` -- Migration creating the `data_exports` table with columns: id, user_id (foreign key), status (string, default 'pending'), file_path (nullable string), completed_at (nullable timestamp), expires_at (nullable timestamp), timestamps. Created via `php artisan make:migration create_data_exports_table`.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/DataExport.php` -- Eloquent model for data exports. Has `belongsTo(User)` relationship, `isDownloadable()` helper method, and appropriate casts for datetime columns. Created via `php artisan make:model DataExport`.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/DataExportFactory.php` -- Factory for creating test DataExport instances with states for pending, processing, completed, failed, and expired. Created via `php artisan make:factory DataExportFactory`.
- `/Users/young/Nextcloud/dev/Itervel/app/Jobs/GenerateDataExport.php` -- Queued job that collects all user data, assembles it into a JSON structure, packages it as a ZIP archive, and stores it in the user's private storage directory. Created via `php artisan make:job GenerateDataExport`.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/DataExportController.php` -- Controller with `store()` (request new export) and `download()` (download completed export) methods. Created via `php artisan make:controller Settings/DataExportController`.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/DataExportTest.php` -- Pest feature tests for the data export feature. Tests request creation, rate limiting, download authorization, job execution, and edge cases. Created via `php artisan make:test Settings/DataExportTest --pest`.
- `tests/Browser/Settings/DataExportTest.php` -- Pest browser tests: smoke test for the account page with data export section, dark mode spot check, and data export request flow test using `data-test` selectors.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: gdpr-backend-dev
    - Role: Creates the migration, model, factory, queued job, controller, and routes for the GDPR data export feature. Handles all PHP/Laravel backend work.
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: gdpr-frontend-dev
    - Role: Updates the account page to include the data export section with request button, status display, and download link. Creates the data export section component.
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: gdpr-test-dev
    - Role: Writes comprehensive Pest feature tests for the data export endpoints, job execution, authorization, rate limiting, and edge cases.
    - Agent Type: coder
    - Resume: false

- Browser Test Developer
    - Name: gdpr-browser-test-dev
    - Role: Writes Pest browser tests (smoke test, dark mode check, data export request flow) for the data export section on the account page
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: gdpr-reviewer
    - Role: Validates the complete feature against acceptance criteria, runs all tests, checks types, runs linting and formatting, and verifies no regressions.
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Create Migration, Model, Factory, and User Relationship

- **Task ID**: create-data-export-model
- **Depends On**: none
- **Assigned To**: gdpr-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- Read `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` to understand the User model structure
- Read `/Users/young/Nextcloud/dev/Itervel/database/migrations/0001_01_01_000000_create_users_table.php` to understand migration patterns
- Read `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` to understand factory patterns
- Create the migration using artisan: `php artisan make:migration create_data_exports_table --no-interaction`
- Edit the generated migration file to create the `data_exports` table:

    ```php
    Schema::create('data_exports', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->string('status')->default('pending');
        $table->string('file_path')->nullable();
        $table->timestamp('completed_at')->nullable();
        $table->timestamp('expires_at')->nullable();
        $table->timestamps();
    });
    ```

    - The `cascadeOnDelete()` ensures exports are removed when a user is deleted
    - Status values: `pending`, `processing`, `completed`, `failed`

- Create the model using artisan: `php artisan make:model DataExport --no-interaction`
- Edit `/Users/young/Nextcloud/dev/Itervel/app/Models/DataExport.php`:

    ```php
    <?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Factories\HasFactory;
    use Illuminate\Database\Eloquent\Model;
    use Illuminate\Database\Eloquent\Relations\BelongsTo;

    class DataExport extends Model
    {
        /** @use HasFactory<\Database\Factories\DataExportFactory> */
        use HasFactory;

        protected $fillable = [
            'user_id',
            'status',
            'file_path',
            'completed_at',
            'expires_at',
        ];

        protected function casts(): array
        {
            return [
                'completed_at' => 'datetime',
                'expires_at' => 'datetime',
            ];
        }

        public function user(): BelongsTo
        {
            return $this->belongsTo(User::class);
        }

        /**
         * Determine if this export is available for download.
         */
        public function isDownloadable(): bool
        {
            return $this->status === 'completed'
                && $this->file_path !== null
                && ($this->expires_at === null || $this->expires_at->isFuture());
        }
    }
    ```

- Create the factory using artisan: `php artisan make:factory DataExportFactory --no-interaction`
- Edit `/Users/young/Nextcloud/dev/Itervel/database/factories/DataExportFactory.php`:

    ```php
    <?php

    namespace Database\Factories;

    use App\Models\User;
    use Illuminate\Database\Eloquent\Factories\Factory;

    /**
     * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DataExport>
     */
    class DataExportFactory extends Factory
    {
        public function definition(): array
        {
            return [
                'user_id' => User::factory(),
                'status' => 'pending',
                'file_path' => null,
                'completed_at' => null,
                'expires_at' => null,
            ];
        }

        public function processing(): static
        {
            return $this->state(fn (array $attributes) => [
                'status' => 'processing',
            ]);
        }

        public function completed(): static
        {
            return $this->state(fn (array $attributes) => [
                'status' => 'completed',
                'file_path' => 'users/1/exports/data-export-2026-01-01-120000.zip',
                'completed_at' => now(),
                'expires_at' => now()->addDays(7),
            ]);
        }

        public function failed(): static
        {
            return $this->state(fn (array $attributes) => [
                'status' => 'failed',
            ]);
        }

        public function expired(): static
        {
            return $this->state(fn (array $attributes) => [
                'status' => 'completed',
                'file_path' => 'users/1/exports/data-export-old.zip',
                'completed_at' => now()->subDays(8),
                'expires_at' => now()->subDay(),
            ]);
        }
    }
    ```

- Add the `dataExports()` relationship to `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php`:
    - Add `use Illuminate\Database\Eloquent\Relations\HasMany;` import
    - Add the relationship method:
        ```php
        public function dataExports(): HasMany
        {
            return $this->hasMany(DataExport::class);
        }
        ```
- Run the migration: `php artisan migrate`
- Run `vendor/bin/pint --dirty` to fix any formatting issues

### 2. Create GenerateDataExport Queued Job

- **Task ID**: create-export-job
- **Depends On**: create-data-export-model
- **Assigned To**: gdpr-backend-dev
- **Agent Type**: coder
- **Parallel**: false
- Read `/Users/young/Nextcloud/dev/Itervel/app/Models/DataExport.php` (just created) to understand the model
- Read `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` to understand what user data is available
- Read `/Users/young/Nextcloud/dev/Itervel/config/filesystems.php` to understand storage configuration
- Create the job using artisan: `php artisan make:job GenerateDataExport --no-interaction`
- Edit `/Users/young/Nextcloud/dev/Itervel/app/Jobs/GenerateDataExport.php`:

    ```php
    <?php

    namespace App\Jobs;

    use App\Models\DataExport;
    use App\Models\User;
    use Illuminate\Contracts\Queue\ShouldQueue;
    use Illuminate\Foundation\Queue\Queueable;
    use Illuminate\Support\Facades\Storage;
    use ZipArchive;

    class GenerateDataExport implements ShouldQueue
    {
        use Queueable;

        public function __construct(public DataExport $dataExport) {}

        public function handle(): void
        {
            $this->dataExport->update(['status' => 'processing']);

            try {
                $user = $this->dataExport->user;
                $data = $this->collectUserData($user);
                $filePath = $this->createExportArchive($user, $data);

                $this->dataExport->update([
                    'status' => 'completed',
                    'file_path' => $filePath,
                    'completed_at' => now(),
                    'expires_at' => now()->addDays(7),
                ]);
            } catch (\Throwable $e) {
                $this->dataExport->update(['status' => 'failed']);

                throw $e;
            }
        }

        /**
         * Collect all user data for the export.
         *
         * @return array<string, mixed>
         */
        protected function collectUserData(User $user): array
        {
            return [
                'export_generated_at' => now()->toIso8601String(),
                'platform' => 'Itervel',
                'account' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                    'created_at' => $user->created_at->toIso8601String(),
                    'updated_at' => $user->updated_at->toIso8601String(),
                ],
                'settings' => [
                    'video_credits' => $user->video_credits ?? 0,
                    'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
                ],
                // Future models will be added here as they are built:
                // 'projects' => $this->collectProjects($user),
                // 'videos' => $this->collectVideos($user),
                // 'transactions' => $this->collectTransactions($user),
            ];
        }

        /**
         * Create a ZIP archive containing the exported data.
         */
        protected function createExportArchive(User $user, array $data): string
        {
            $directory = "users/{$user->id}/exports";
            $filename = 'data-export-' . now()->format('Y-m-d-His') . '.zip';
            $relativePath = "{$directory}/{$filename}";

            $disk = Storage::disk('local');

            // Ensure the directory exists
            $disk->makeDirectory($directory);

            $jsonContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $tempJsonPath = sys_get_temp_dir() . '/itervel-export-' . $user->id . '.json';
            $tempZipPath = sys_get_temp_dir() . '/itervel-export-' . $user->id . '.zip';

            file_put_contents($tempJsonPath, $jsonContent);

            $zip = new ZipArchive();
            $zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $zip->addFile($tempJsonPath, 'data-export.json');
            $zip->close();

            $disk->put($relativePath, file_get_contents($tempZipPath));

            // Clean up temp files
            @unlink($tempJsonPath);
            @unlink($tempZipPath);

            return $relativePath;
        }
    }
    ```

- Key design decisions:
    - The job uses `ShouldQueue` so it runs asynchronously via the queue worker
    - Temp files are used for ZIP creation since `ZipArchive` requires filesystem paths
    - The final ZIP is stored on the `local` disk (private storage) so it is not publicly accessible
    - If the job fails, the status is set to `failed` and the exception is re-thrown for queue failure handling
    - The `collectUserData` method is structured so that future features can easily add their data sections
- Run `vendor/bin/pint --dirty` to fix any formatting issues

### 3. Create DataExportController and Routes

- **Task ID**: create-export-controller
- **Depends On**: create-export-job
- **Assigned To**: gdpr-backend-dev
- **Agent Type**: coder
- **Parallel**: false
- Read `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/ProfileController.php` to follow controller patterns
- Read `/Users/young/Nextcloud/dev/Itervel/routes/settings.php` to understand route structure
- Create the controller using artisan: `php artisan make:controller Settings/DataExportController --no-interaction`
- Edit `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/DataExportController.php`:

    ```php
    <?php

    namespace App\Http\Controllers\Settings;

    use App\Http\Controllers\Controller;
    use App\Jobs\GenerateDataExport;
    use App\Models\DataExport;
    use Illuminate\Http\RedirectResponse;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\Storage;
    use Symfony\Component\HttpFoundation\StreamedResponse;

    class DataExportController extends Controller
    {
        /**
         * Request a new data export.
         */
        public function store(Request $request): RedirectResponse
        {
            $user = $request->user();

            $recentExport = $user->dataExports()
                ->where('created_at', '>', now()->subDay())
                ->whereIn('status', ['pending', 'processing', 'completed'])
                ->exists();

            if ($recentExport) {
                return back()->withErrors([
                    'export' => 'You can only request one data export per 24 hours.',
                ]);
            }

            $export = $user->dataExports()->create(['status' => 'pending']);

            GenerateDataExport::dispatch($export);

            return back()->with('status', 'export-requested');
        }

        /**
         * Download a completed data export.
         */
        public function download(Request $request, DataExport $dataExport): StreamedResponse
        {
            if ($dataExport->user_id !== $request->user()->id) {
                abort(403);
            }

            if (! $dataExport->isDownloadable()) {
                abort(404);
            }

            return Storage::disk('local')->download(
                $dataExport->file_path,
                'itervel-data-export.zip'
            );
        }
    }
    ```

- Edit `/Users/young/Nextcloud/dev/Itervel/routes/settings.php` to add the data export routes:
    - Add `use App\Http\Controllers\Settings\DataExportController;` to the imports at the top
    - Add the following routes inside the `Route::middleware(['auth', 'verified'])->group(function () {` block:

        ```php
        Route::post('settings/data-export', [DataExportController::class, 'store'])
            ->name('data-export.store');

        Route::get('settings/data-export/{dataExport}/download', [DataExportController::class, 'download'])
            ->name('data-export.download');
        ```

- Update the `AccountController::show()` method (created by E001-F005 at `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/AccountController.php`) to pass the latest export data:
    - Read the existing `AccountController` to understand its current structure
    - Add the `latestExport` prop to the Inertia render call:

        ```php
        public function show(Request $request): Response
        {
            $user = $request->user();
            $latestExport = $user->dataExports()
                ->latest()
                ->first();

            return Inertia::render('settings/account', [
                'joinedAt' => $user->created_at->toDateString(),
                'videoCredits' => $user->video_credits ?? 0,
                'latestExport' => $latestExport ? [
                    'id' => $latestExport->id,
                    'status' => $latestExport->status,
                    'created_at' => $latestExport->created_at->toIso8601String(),
                    'completed_at' => $latestExport->completed_at?->toIso8601String(),
                    'expires_at' => $latestExport->expires_at?->toIso8601String(),
                    'is_downloadable' => $latestExport->isDownloadable(),
                ] : null,
            ]);
        }
        ```

- Run `vendor/bin/pint --dirty` to fix any formatting issues
- Run `npm run build` to regenerate Wayfinder routes (so `@/actions/App/Http/Controllers/Settings/DataExportController` and `@/routes/data-export` are available)
- Verify routes exist: `php artisan route:list --path=settings/data-export`

### 4. Update Account Page with Data Export Section

- **Task ID**: create-frontend-export-section
- **Depends On**: create-export-controller
- **Assigned To**: gdpr-frontend-dev
- **Agent Type**: coder
- **Parallel**: false
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/components/delete-user.tsx` to understand the pattern for a self-contained action section within settings pages (heading, description, action button)
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/components/heading.tsx` for the Heading component API
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/button.tsx` for Button variants
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/badge.tsx` for Badge variants (used for status display)
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/spinner.tsx` for the Spinner component
- Check what Wayfinder routes were generated by looking in `/Users/young/Nextcloud/dev/Itervel/resources/js/actions/App/Http/Controllers/Settings/DataExportController/` and `/Users/young/Nextcloud/dev/Itervel/resources/js/routes/data-export/`
- Read the existing `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/account.tsx` (created by E001-F005)
- Create a new component `/Users/young/Nextcloud/dev/Itervel/resources/js/components/data-export-section.tsx`:

    ```tsx
    import { Form, Link } from '@inertiajs/react';
    import Heading from '@/components/heading';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Spinner } from '@/components/ui/spinner';
    import DataExportController from '@/actions/App/Http/Controllers/Settings/DataExportController';
    import { download } from '@/routes/data-export';

    interface DataExportInfo {
        id: number;
        status: 'pending' | 'processing' | 'completed' | 'failed';
        created_at: string;
        completed_at: string | null;
        expires_at: string | null;
        is_downloadable: boolean;
    }

    export default function DataExportSection({
        latestExport,
        status,
    }: {
        latestExport: DataExportInfo | null;
        status?: string;
    }) {
        const isPending =
            latestExport?.status === 'pending' ||
            latestExport?.status === 'processing';
        const isDownloadable = latestExport?.is_downloadable === true;

        return (
            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Data export"
                    description="Download a copy of all your personal data"
                />

                <div className="space-y-4">
                    <p className="text-sm text-muted-foreground">
                        Request an export of all your personal data stored in
                        Itervel. The export includes your account information,
                        settings, and metadata. The download will be available
                        for 7 days.
                    </p>

                    {latestExport && (
                        <div className="flex items-center gap-3 text-sm">
                            <span className="text-muted-foreground">
                                Last export:
                            </span>
                            {/* status badge */}
                            {/* timestamp */}
                            {/* download link if available */}
                        </div>
                    )}

                    {status === 'export-requested' && (
                        <p className="text-sm text-green-600">
                            Your data export has been requested and is being
                            prepared.
                        </p>
                    )}

                    <div className="flex items-center gap-3">
                        <Form
                            {...DataExportController.store.form()}
                            options={{ preserveScroll: true }}
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    disabled={processing || isPending}
                                >
                                    {processing || isPending ? (
                                        <>
                                            <Spinner />
                                            Processing...
                                        </>
                                    ) : (
                                        'Request data export'
                                    )}
                                </Button>
                            )}
                        </Form>

                        {isDownloadable && latestExport && (
                            <Button variant="outline" asChild>
                                <a
                                    href={download({
                                        dataExport: latestExport.id,
                                    })}
                                >
                                    Download export
                                </a>
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        );
    }
    ```

    - Add `data-test` attributes to interactive elements for browser testing:
        - `data-test="request-data-export-button"` on the "Request data export" submit button
        - `data-test="download-export-button"` on the "Download export" button/link
        - `data-test="export-status-badge"` on the export status Badge
        - `data-test="export-requested-message"` on the success message paragraph
    - Note: The exact Wayfinder import paths may differ. Check the generated files in `resources/js/actions/` and `resources/js/routes/` after the backend build step
    - The component displays the export status using Badge variants: `secondary` for pending/processing, `default` for completed, `destructive` for failed
    - When an export is pending or processing, the request button is disabled and shows a spinner
    - When an export is downloadable, a "Download export" button appears
    - The component shows a flash message when an export was just requested

- Update `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/account.tsx` to include the `DataExportSection` component:
    - Add the `DataExportInfo` type to the page props (or import from the component)
    - Add `latestExport` to the destructured props
    - Import and render `DataExportSection` below the existing account overview content
    - Pass `latestExport` and `status` (from page props) to the component
    - Follow the pattern of how `profile.tsx` includes the `DeleteUser` component at the bottom of the page
- Run `npm run types` to verify TypeScript types compile correctly
- Run `npm run lint:fix` to fix any linting issues
- Run `npm run format` to ensure Prettier formatting

### 5. Write Data Export Tests

- **Task ID**: write-export-tests
- **Depends On**: create-export-controller
- **Assigned To**: gdpr-test-dev
- **Agent Type**: coder
- **Parallel**: true
- Read `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/ProfileUpdateTest.php` for test patterns
- Read `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` for Pest configuration
- Read `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/DataExportController.php` to understand what needs testing
- Read `/Users/young/Nextcloud/dev/Itervel/app/Jobs/GenerateDataExport.php` to understand job behavior
- Read `/Users/young/Nextcloud/dev/Itervel/app/Models/DataExport.php` to understand model methods
- Create the test file using artisan: `php artisan make:test Settings/DataExportTest --pest --no-interaction`
- Edit `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/DataExportTest.php` with the following tests:

    **Request Export Tests:**

    ```php
    use App\Jobs\GenerateDataExport;
    use App\Models\DataExport;
    use App\Models\User;
    use Illuminate\Support\Facades\Queue;
    use Illuminate\Support\Facades\Storage;

    test('authenticated user can request a data export', function () {
        Queue::fake();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('data-export.store'));

        $response->assertSessionHasNoErrors()
            ->assertRedirect();

        expect($user->dataExports()->count())->toBe(1);
        expect($user->dataExports()->first()->status)->toBe('pending');

        Queue::assertPushed(GenerateDataExport::class);
    });

    test('guests cannot request a data export', function () {
        $response = $this->post(route('data-export.store'));

        $response->assertRedirect(route('login'));
    });

    test('user cannot request more than one export per 24 hours', function () {
        Queue::fake();
        $user = User::factory()->create();
        DataExport::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'created_at' => now()->subHours(12),
        ]);

        $response = $this->actingAs($user)
            ->post(route('data-export.store'));

        $response->assertSessionHasErrors('export');
        expect($user->dataExports()->count())->toBe(1);
    });

    test('user can request export after 24 hours', function () {
        Queue::fake();
        $user = User::factory()->create();
        DataExport::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'created_at' => now()->subHours(25),
        ]);

        $response = $this->actingAs($user)
            ->post(route('data-export.store'));

        $response->assertSessionHasNoErrors();
        expect($user->dataExports()->count())->toBe(2);
    });
    ```

    **Download Tests:**

    ```php
    test('user can download their completed export', function () {
        Storage::fake('local');
        $user = User::factory()->create();
        $filePath = "users/{$user->id}/exports/data-export.zip";
        Storage::disk('local')->put($filePath, 'fake zip content');

        $export = DataExport::factory()->completed()->create([
            'user_id' => $user->id,
            'file_path' => $filePath,
        ]);

        $response = $this->actingAs($user)
            ->get(route('data-export.download', $export));

        $response->assertOk();
        $response->assertDownload('itervel-data-export.zip');
    });

    test('user cannot download another users export', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $export = DataExport::factory()->completed()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('data-export.download', $export));

        $response->assertForbidden();
    });

    test('user cannot download a pending export', function () {
        $user = User::factory()->create();
        $export = DataExport::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)
            ->get(route('data-export.download', $export));

        $response->assertNotFound();
    });

    test('user cannot download an expired export', function () {
        $user = User::factory()->create();
        $export = DataExport::factory()->expired()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('data-export.download', $export));

        $response->assertNotFound();
    });

    test('guests cannot download exports', function () {
        $export = DataExport::factory()->completed()->create();

        $response = $this->get(route('data-export.download', $export));

        $response->assertRedirect(route('login'));
    });
    ```

    **Job Execution Tests:**

    ```php
    test('generate data export job creates a zip file', function () {
        Storage::fake('local');
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
        $export = DataExport::factory()->create([
            'user_id' => $user->id,
        ]);

        $job = new GenerateDataExport($export);
        $job->handle();

        $export->refresh();
        expect($export->status)->toBe('completed');
        expect($export->file_path)->not->toBeNull();
        expect($export->completed_at)->not->toBeNull();
        expect($export->expires_at)->not->toBeNull();
        expect(Storage::disk('local')->exists($export->file_path))->toBeTrue();
    });

    test('generate data export job includes user account data', function () {
        Storage::fake('local');
        $user = User::factory()->create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
        ]);
        $export = DataExport::factory()->create([
            'user_id' => $user->id,
        ]);

        $job = new GenerateDataExport($export);
        $job->handle();

        $export->refresh();

        // Download and extract the ZIP to verify contents
        $zipContent = Storage::disk('local')->get($export->file_path);
        $tempZip = sys_get_temp_dir() . '/test-export.zip';
        file_put_contents($tempZip, $zipContent);

        $zip = new \ZipArchive();
        $zip->open($tempZip);
        $jsonContent = $zip->getFromName('data-export.json');
        $zip->close();
        @unlink($tempZip);

        $data = json_decode($jsonContent, true);
        expect($data['account']['name'])->toBe('Jane Smith');
        expect($data['account']['email'])->toBe('jane@example.com');
        expect($data['platform'])->toBe('Itervel');
    });

    test('generate data export job sets status to failed on error', function () {
        $user = User::factory()->create();
        $export = DataExport::factory()->create([
            'user_id' => $user->id,
        ]);

        // Mock Storage to throw an exception
        Storage::shouldReceive('disk')
            ->with('local')
            ->andThrow(new \RuntimeException('Storage error'));

        $job = new GenerateDataExport($export);

        try {
            $job->handle();
        } catch (\RuntimeException $e) {
            // Expected
        }

        $export->refresh();
        expect($export->status)->toBe('failed');
    });
    ```

    **Model Tests:**

    ```php
    test('data export is downloadable when completed and not expired', function () {
        $export = DataExport::factory()->completed()->make();

        expect($export->isDownloadable())->toBeTrue();
    });

    test('data export is not downloadable when pending', function () {
        $export = DataExport::factory()->make();

        expect($export->isDownloadable())->toBeFalse();
    });

    test('data export is not downloadable when expired', function () {
        $export = DataExport::factory()->expired()->make();

        expect($export->isDownloadable())->toBeFalse();
    });

    test('data export is not downloadable when failed', function () {
        $export = DataExport::factory()->failed()->make();

        expect($export->isDownloadable())->toBeFalse();
    });

    test('data export belongs to user', function () {
        $user = User::factory()->create();
        $export = DataExport::factory()->create(['user_id' => $user->id]);

        expect($export->user->id)->toBe($user->id);
    });

    test('user has many data exports', function () {
        $user = User::factory()->create();
        DataExport::factory()->count(3)->create(['user_id' => $user->id]);

        expect($user->dataExports()->count())->toBe(3);
    });

    test('data exports are deleted when user is deleted', function () {
        $user = User::factory()->create();
        DataExport::factory()->count(2)->create(['user_id' => $user->id]);

        $user->delete();

        expect(DataExport::where('user_id', $user->id)->count())->toBe(0);
    });
    ```

    **Account Page Integration Test:**

    ```php
    test('account page shows latest export information', function () {
        $user = User::factory()->create();
        $export = DataExport::factory()->completed()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('account.show'));

        $response->assertInertia(fn ($page) => $page
            ->component('settings/account')
            ->has('latestExport')
            ->where('latestExport.id', $export->id)
            ->where('latestExport.status', 'completed')
            ->where('latestExport.is_downloadable', true)
        );
    });

    test('account page shows null when no exports exist', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('account.show'));

        $response->assertInertia(fn ($page) => $page
            ->component('settings/account')
            ->where('latestExport', null)
        );
    });
    ```

- Ensure all necessary imports are at the top of the test file
- Run the tests: `php artisan test tests/Feature/Settings/DataExportTest.php --compact`
- If any tests fail, debug and fix until all pass
- Run `vendor/bin/pint --dirty` to fix any formatting issues

### 6. Write Browser Tests

- **Task ID**: write-browser-tests
- **Depends On**: create-frontend-export-section, write-export-tests
- **Assigned To**: gdpr-browser-test-dev
- **Agent Type**: coder
- **Parallel**: false
- Create `tests/Browser/Settings/DataExportTest.php`
- Write a smoke test for the account page with data export section:
    - Create and authenticate a verified user
    - Visit `/settings/account`
    - Assert the page loads successfully with no JavaScript errors
    - Assert the data export section is visible
- Write a dark mode spot check:
    - Visit the account page, switch to dark color scheme using `colorScheme('dark')`, assert no JavaScript errors
- Write a data export request flow test:
    - Create and authenticate a verified user
    - Visit the account page
    - Click the request button using `[data-test="request-data-export-button"]`
    - Assert the success message appears using `[data-test="export-requested-message"]`
    - Assert no JavaScript errors
- Use `data-test` selectors for all element interactions
- Ensure all browser tests use `assertNoJavaScriptErrors()`
- Run browser tests: `php artisan test tests/Browser/Settings/DataExportTest.php --compact`

### 7. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-data-export-model, create-export-job, create-export-controller, create-frontend-export-section, write-export-tests, write-browser-tests
- **Assigned To**: gdpr-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed below
- Verify that the `data_exports` migration exists and creates the correct table schema
- Verify that `/Users/young/Nextcloud/dev/Itervel/app/Models/DataExport.php` exists with `belongsTo(User)` relationship, `isDownloadable()` method, and proper casts
- Verify that `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` has the `dataExports()` hasMany relationship
- Verify that `/Users/young/Nextcloud/dev/Itervel/database/factories/DataExportFactory.php` exists with `pending`, `processing`, `completed`, `failed`, and `expired` states
- Verify that `/Users/young/Nextcloud/dev/Itervel/app/Jobs/GenerateDataExport.php` implements `ShouldQueue`, collects user data, creates a ZIP archive, and handles failures
- Verify that `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/DataExportController.php` exists with `store()` and `download()` methods
- Verify the routes exist: `php artisan route:list --path=settings/data-export`
- Verify the route names are `data-export.store` and `data-export.download`
- Verify the `AccountController::show()` method passes `latestExport` as a prop
- Verify the `DataExportSection` component exists and is rendered on the account page
- Verify the frontend shows the request button, status display, and download link appropriately
- Run browser tests: `php artisan test tests/Browser/Settings/DataExportTest.php --compact`
- Verify `data-test` attributes exist on interactive elements in the data export section component
- Run all data export tests: `php artisan test tests/Feature/Settings/DataExportTest.php --compact`
- Run all settings tests: `php artisan test tests/Feature/Settings --compact`
- Run all auth tests for regression check: `php artisan test tests/Feature/Auth --compact`
- Run the full test suite: `php artisan test --compact`
- Run TypeScript type checking: `npm run types`
- Run ESLint: `npm run lint`
- Run Prettier formatting: `npm run format`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Confirm all acceptance criteria are met

## Acceptance Criteria

- A `data_exports` table exists with columns: id, user_id (foreign key with cascade delete), status, file_path, completed_at, expires_at, timestamps
- The `DataExport` model has a `belongsTo(User)` relationship and an `isDownloadable()` method
- The `User` model has a `dataExports()` hasMany relationship
- A `DataExportFactory` exists with states for pending, processing, completed, failed, and expired exports
- The `GenerateDataExport` queued job collects user account data, settings, and assembles it into a JSON file inside a ZIP archive
- The export JSON includes: platform name, export timestamp, account information (name, email, verified status, join/update dates), and settings (video credits, 2FA status)
- The ZIP file is stored in the user's private storage directory at `users/{id}/exports/`
- The `DataExportController` has a `store()` endpoint that creates an export record and dispatches the job
- The `store()` endpoint enforces a rate limit of 1 export request per 24 hours (rejects if a recent pending/processing/completed export exists)
- The `DataExportController` has a `download()` endpoint that streams the completed ZIP file
- The `download()` endpoint verifies the requesting user owns the export (returns 403 otherwise)
- The `download()` endpoint returns 404 for non-downloadable exports (pending, failed, expired)
- Routes are registered at `POST /settings/data-export` (name: `data-export.store`) and `GET /settings/data-export/{dataExport}/download` (name: `data-export.download`)
- Both routes require authentication and email verification (`['auth', 'verified']` middleware)
- The `AccountController::show()` passes a `latestExport` prop to the account page with the most recent export's id, status, timestamps, and downloadability
- The account page displays a "Data export" section with a heading, description, request button, and status/download information
- The request button is disabled when an export is pending or processing
- A "Download export" button/link appears when the export is completed and not expired
- The frontend shows a success message when an export is requested
- All data export tests pass
- All existing tests pass without regressions
- TypeScript types compile without errors
- ESLint and Prettier checks pass
- PHP code passes Pint formatting
- Data exports are automatically deleted when the user is deleted (cascade on delete)
- The account page (with data export section) has a smoke test (no JavaScript errors)
- Dark mode spot check passes for the account page
- Data export request flow passes browser test using `data-test` selectors
- Interactive frontend elements have `data-test` attributes
- All browser tests pass

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run data export tests
php artisan test tests/Feature/Settings/DataExportTest.php --compact

# Run all settings tests
php artisan test tests/Feature/Settings --compact

# Run all auth tests for regression check
php artisan test tests/Feature/Auth --compact

# Run full test suite for regression check
php artisan test --compact

# TypeScript type checking
npm run types

# ESLint linting
npm run lint

# Prettier formatting check
npm run format

# Run browser tests
php artisan test tests/Browser/Settings/DataExportTest.php --compact

# PHP code formatting
vendor/bin/pint --dirty

# Verify routes exist
php artisan route:list --path=settings/data-export
```

## Notes

- The `GenerateDataExport` job is designed to be extended as new features are built. When models like Project, Video, Transaction, etc. are created in later features, their data collection should be added to the `collectUserData()` method. Each new model section should follow the pattern: `'model_name' => $this->collectModelData($user)`.
- The export includes only metadata, not actual binary files (videos, thumbnails, audio). Including large binary files in the export would be impractical. Users can download individual files from the video library.
- The ZIP archive uses a `data-export.json` file inside it. This provides a clean, well-structured export that can be opened by any JSON viewer. The JSON is formatted with `JSON_PRETTY_PRINT` for readability.
- Export files expire after 7 days. A separate cleanup mechanism (e.g., a scheduled command) should be created in a future feature to delete expired export files from storage. For now, the `isDownloadable()` method prevents downloads of expired exports.
- The rate limit (1 export per 24 hours) prevents abuse. The check looks for any export created in the last 24 hours with status `pending`, `processing`, or `completed`. Failed exports do not count against the rate limit, allowing users to retry.
- The `data_exports` table uses `cascadeOnDelete()` on the `user_id` foreign key, so exports are automatically cleaned up when a user deletes their account. However, the physical files on disk are cleaned up by the `DeleteUserAccount` action (from E001-F009) which deletes the entire `users/{id}/` directory.
- The `store()` endpoint dispatches the job to the queue. In development (with `QUEUE_CONNECTION=sync`), the job runs synchronously. In production (with `database` or `redis` queue), it runs asynchronously. The frontend handles both cases since it checks the export status from the page props.
- The `download()` endpoint uses `Storage::disk('local')->download()` which streams the file directly from the private storage. This ensures the file is never publicly accessible via a direct URL.
- All commands should be run inside the Docker container. Use `make shell` to enter the container, or prefix with `docker compose exec app`.

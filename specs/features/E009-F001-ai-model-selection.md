# Feature: AI Model Selection

**Epic**: E009-ai-configuration-and-title-generation.md
**Feature**: E009-F001
**Epic depends on**: E006-brand-guide-and-video-input.md
**Feature depends on**: E006-F003

## Task Description

AI Model Selection lets users choose which AI model to use for each step of the video creation process (title, outline, script, critique, thumbnails). This is the first feature in the AI configuration epic and establishes the foundational infrastructure for AI model management, per-video configuration storage, and user default preferences.

**What it does**: Lets users choose which AI model to use for each step of the video creation process (title, outline, script, critique, thumbnails).

**Expected outcome**: By default, "Use recommended settings" is checked (all Sonnet). Users can uncheck this to customize the AI model for each step via dropdowns. A cost preview updates as they change selections. Their last configuration is remembered.

This feature creates:

1. A `config/ai.php` configuration file defining available AI models, their capabilities, cost multipliers, and the steps that use AI.
2. An `ai_config` JSON column on the `videos` table to store the per-video AI model selections.
3. An `ai_preferences` JSON column on the `users` table to store the user's default AI preferences (remembered across videos).
4. A video configuration step page in the video creation wizard where users select models.
5. A `VideoConfigController` to handle the configuration step.
6. An `UpdateVideoConfigRequest` Form Request for validation.

The configuration step appears in the video creation wizard after the topic and reference URL inputs (E006-F003, E006-F004) and before title generation (E009-F003). The user can either accept the recommended defaults or customize each step's model.

**Dependency on E006-F003 (Topic Input)**: Creates the Video model, VideoController, VideoFactory, and the video creation wizard flow. This feature adds a new step to that wizard.

## Objective

Create AI model configuration infrastructure: a config file defining available models and steps, database columns for per-video and per-user AI settings, a configuration step page in the video creation wizard with model selection dropdowns, a "Use recommended settings" toggle, an inline cost indicator that updates as selections change, and persistence of user preferences. When complete, users can configure which AI model powers each step of their video creation.

## Solution Approach

### 1. Configuration: AI Models and Steps

Create `config/ai.php` defining the available AI models and the steps that use them:

```php
return [
    'models' => [
        'haiku' => [
            'id' => 'claude-haiku-4-5-20251001',
            'name' => 'Haiku',
            'description' => 'Fast and affordable',
            'provider' => 'anthropic',
            'cost_multiplier' => 0.5,
        ],
        'sonnet' => [
            'id' => 'claude-sonnet-4-5-20250929',
            'name' => 'Sonnet',
            'description' => 'Best balance of speed and quality',
            'provider' => 'anthropic',
            'cost_multiplier' => 1.0,
        ],
        'opus' => [
            'id' => 'claude-opus-4-6',
            'name' => 'Opus',
            'description' => 'Highest quality, slower',
            'provider' => 'anthropic',
            'cost_multiplier' => 3.0,
        ],
    ],

    'steps' => [
        'title' => [
            'name' => 'Title Generation',
            'description' => 'Generate title options for the video',
            'default_model' => 'sonnet',
            'allowed_models' => ['haiku', 'sonnet', 'opus'],
            'base_credit_cost' => 0.1,
        ],
        'outline' => [
            'name' => 'Outline Generation',
            'description' => 'Create a structured outline',
            'default_model' => 'sonnet',
            'allowed_models' => ['haiku', 'sonnet', 'opus'],
            'base_credit_cost' => 0.1,
        ],
        'script' => [
            'name' => 'Script Writing',
            'description' => 'Write the full video script',
            'default_model' => 'sonnet',
            'allowed_models' => ['haiku', 'sonnet', 'opus'],
            'base_credit_cost' => 0.3,
        ],
        'critique' => [
            'name' => 'Script Critique',
            'description' => 'Review and refine the script',
            'default_model' => 'sonnet',
            'allowed_models' => ['haiku', 'sonnet', 'opus'],
            'base_credit_cost' => 0.2,
        ],
        'thumbnails' => [
            'name' => 'Thumbnail Generation',
            'description' => 'Generate thumbnail concepts',
            'default_model' => 'sonnet',
            'allowed_models' => ['haiku', 'sonnet', 'opus'],
            'base_credit_cost' => 0.1,
        ],
    ],

    'defaults' => [
        'use_recommended' => true,
        'script_iterations' => 3,
    ],
];
```

### 2. Database: Add Configuration Columns

**Videos table** -- add `ai_config` JSON column:

```php
Schema::table('videos', function (Blueprint $table) {
    $table->json('ai_config')->nullable()->after('is_free_tier');
});
```

The `ai_config` JSON structure:

```json
{
    "use_recommended": false,
    "models": {
        "title": "sonnet",
        "outline": "sonnet",
        "script": "opus",
        "critique": "sonnet",
        "thumbnails": "haiku"
    },
    "script_iterations": 3
}
```

**Users table** -- add `ai_preferences` JSON column to remember last configuration:

```php
Schema::table('users', function (Blueprint $table) {
    $table->json('ai_preferences')->nullable()->after('has_completed_welcome_tour');
});
```

### 3. Video Model Updates

Add `ai_config` to Video model:

```php
// Add to $fillable
'ai_config',

// Add to casts()
'ai_config' => 'array',

// Helper methods
public function getModelForStep(string $step): string
{
    if ($this->ai_config && ! ($this->ai_config['use_recommended'] ?? true)) {
        return $this->ai_config['models'][$step] ?? config("ai.steps.{$step}.default_model");
    }

    return config("ai.steps.{$step}.default_model");
}

public function getScriptIterations(): int
{
    return $this->ai_config['script_iterations'] ?? config('ai.defaults.script_iterations');
}
```

### 4. User Model Updates

Add `ai_preferences` to User model:

```php
// Add to $fillable
'ai_preferences',

// Add to casts()
'ai_preferences' => 'array',
```

### 5. Controller: VideoConfigController

Create a controller for the AI configuration step:

```php
namespace App\Http\Controllers;

use App\Http\Requests\UpdateVideoConfigRequest;
use App\Models\Video;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class VideoConfigController extends Controller
{
    public function edit(Video $video): Response
    {
        abort_unless($video->user_id === auth()->id(), 403);

        $user = auth()->user();
        $isFreeTier = $video->isFreeTier();

        return Inertia::render('videos/configure', [
            'video' => $video->only(['id', 'topic', 'status', 'ai_config', 'is_free_tier']),
            'aiModels' => config('ai.models'),
            'aiSteps' => config('ai.steps'),
            'defaults' => config('ai.defaults'),
            'userPreferences' => $user->ai_preferences,
            'isFreeTier' => $isFreeTier,
            'maxScriptIterations' => $isFreeTier
                ? config('video.free_tier.max_script_iterations')
                : config('video.paid.max_script_iterations'),
        ]);
    }

    public function update(UpdateVideoConfigRequest $request, Video $video): RedirectResponse
    {
        $validated = $request->validated();

        $video->update(['ai_config' => $validated['ai_config']]);

        // Save as user's default preferences for next video
        $request->user()->update(['ai_preferences' => $validated['ai_config']]);

        return to_route('videos.titles', $video);
    }
}
```

### 6. Form Request: UpdateVideoConfigRequest

```php
class UpdateVideoConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->id === $this->route('video')->user_id;
    }

    public function rules(): array
    {
        $allowedModels = array_keys(config('ai.models'));

        return [
            'ai_config' => ['required', 'array'],
            'ai_config.use_recommended' => ['required', 'boolean'],
            'ai_config.models' => ['required_if:ai_config.use_recommended,false', 'array'],
            'ai_config.models.title' => ['sometimes', 'string', Rule::in($allowedModels)],
            'ai_config.models.outline' => ['sometimes', 'string', Rule::in($allowedModels)],
            'ai_config.models.script' => ['sometimes', 'string', Rule::in($allowedModels)],
            'ai_config.models.critique' => ['sometimes', 'string', Rule::in($allowedModels)],
            'ai_config.models.thumbnails' => ['sometimes', 'string', Rule::in($allowedModels)],
            'ai_config.script_iterations' => ['required', 'integer', 'min:0', 'max:5'],
        ];
    }
}
```

### 7. Routes

Add to `routes/web.php` inside the auth+verified middleware group:

```php
Route::get('videos/{video}/configure', [VideoConfigController::class, 'edit'])->name('videos.configure');
Route::put('videos/{video}/configure', [VideoConfigController::class, 'update'])->name('videos.configure.update');
```

### 8. Frontend: Configuration Page

Create `resources/js/pages/videos/configure.tsx` with:

- A "Use recommended settings" checkbox (default: checked). When checked, all dropdowns are disabled and show "Sonnet"
- When unchecked, 5 `Select` dropdowns appear (one per AI step) allowing model selection
- Each dropdown shows the model name and its cost description
- An inline cost summary section showing the per-step credit cost based on selected models
- A "Continue" button that saves the configuration
- Free-tier users see a locked iterations count of 1 (from E008-F001)
- The form pre-fills with: video's existing `ai_config` > user's `ai_preferences` > system defaults

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Models/Video.php` -- Video model (created by E006-F003). Must add `ai_config` to fillable, casts, and helper methods.
- `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` -- User model. Must add `ai_preferences` to fillable and casts.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/VideoFactory.php` -- Video factory. Must add `ai_config` default.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` -- User factory. Must add `ai_preferences` default.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/VideoController.php` -- Existing video controller. Referenced for patterns.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/StoreVideoRequest.php` -- Existing form request. Referenced for validation patterns.
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Must add configuration routes.
- `/Users/young/Nextcloud/dev/Itervel/config/video.php` -- Existing video config (from E008-F001). Referenced for tier limits.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/videos/create.tsx` -- Existing video creation page. Referenced for wizard patterns.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/select.tsx` -- Select dropdown component. Used for model selection.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/checkbox.tsx` -- Checkbox component. Used for "Use recommended" toggle.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/card.tsx` -- Card component. Used for step configuration cards.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/label.tsx` -- Label component.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/tooltip.tsx` -- Tooltip component for model descriptions.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/badge.tsx` -- Badge component for cost indicators.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/heading.tsx` -- Heading component.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/input-error.tsx` -- Error display component.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/app-layout.tsx` -- App layout for authenticated pages.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/lib/utils.ts` -- Contains `cn()` utility.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/index.ts` -- TypeScript types.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration.

### New Files

- `/Users/young/Nextcloud/dev/Itervel/config/ai.php` -- Configuration file defining available AI models, video creation steps, cost multipliers, and defaults.
- `/Users/young/Nextcloud/dev/Itervel/database/migrations/YYYY_MM_DD_HHMMSS_add_ai_config_to_videos_table.php` -- Migration to add `ai_config` JSON column to videos table.
- `/Users/young/Nextcloud/dev/Itervel/database/migrations/YYYY_MM_DD_HHMMSS_add_ai_preferences_to_users_table.php` -- Migration to add `ai_preferences` JSON column to users table.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/VideoConfigController.php` -- Controller with `edit` and `update` methods for the AI configuration step.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/UpdateVideoConfigRequest.php` -- Form Request with validation for AI config (model selections, iteration count).
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/videos/configure.tsx` -- Inertia React page for the AI model selection and configuration step.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/ai.ts` -- TypeScript type definitions for AI models, steps, and configuration.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/VideoConfigTest.php` -- Pest feature tests for AI model configuration.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: ai-config-backend-dev
    - Role: Creates the AI config file, database migrations, VideoConfigController, UpdateVideoConfigRequest, updates Video and User models, and adds routes
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: ai-config-frontend-dev
    - Role: Creates the video configuration page with model selection dropdowns, recommended settings toggle, inline cost preview, and TypeScript types
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: ai-config-test-dev
    - Role: Writes Pest feature tests for AI configuration CRUD, validation, authorization, and preference persistence
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: ai-config-reviewer
    - Role: Validates the complete AI model selection implementation against acceptance criteria
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Create AI Configuration and Database Migrations

- **Task ID**: create-config-and-migrations
- **Depends On**: none
- **Assigned To**: ai-config-backend-dev
- **Agent Type**: coder
- **Parallel**: true
- Create `/Users/young/Nextcloud/dev/Itervel/config/ai.php` with `models` (haiku, sonnet, opus with id, name, description, provider, cost_multiplier), `steps` (title, outline, script, critique, thumbnails with name, description, default_model, allowed_models, base_credit_cost), and `defaults` (use_recommended: true, script_iterations: 3) arrays as detailed in Solution Approach section 1
- Create migration: `php artisan make:migration add_ai_config_to_videos_table --table=videos --no-interaction`
    - `up()`: `$table->json('ai_config')->nullable()->after('is_free_tier');`
    - `down()`: `$table->dropColumn('ai_config');`
- Create migration: `php artisan make:migration add_ai_preferences_to_users_table --table=users --no-interaction`
    - `up()`: `$table->json('ai_preferences')->nullable()->after('has_completed_welcome_tour');`
    - `down()`: `$table->dropColumn('ai_preferences');`
- Update `/Users/young/Nextcloud/dev/Itervel/app/Models/Video.php`:
    - Add `'ai_config'` to `$fillable`
    - Add `'ai_config' => 'array'` to `casts()`
    - Add `getModelForStep(string $step): string` method -- returns the configured model key for a step, falling back to the step's default_model from config
    - Add `getScriptIterations(): int` method -- returns configured iteration count or default
- Update `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php`:
    - Add `'ai_preferences'` to `$fillable`
    - Add `'ai_preferences' => 'array'` to `casts()`
- Update `/Users/young/Nextcloud/dev/Itervel/database/factories/VideoFactory.php`:
    - Add `'ai_config' => null` to `definition()`
    - Add a `withAiConfig(array $config = []): static` state method with default config using all sonnet models and 3 iterations
- Update `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php`:
    - Add `'ai_preferences' => null` to `definition()`
- Run migrations: `php artisan migrate --no-interaction`
- Run `vendor/bin/pint --dirty`

### 2. Create VideoConfigController, Form Request, and Routes

- **Task ID**: create-controller-and-routes
- **Depends On**: create-config-and-migrations
- **Assigned To**: ai-config-backend-dev
- **Agent Type**: coder
- **Parallel**: false
- Create form request: `php artisan make:request UpdateVideoConfigRequest --no-interaction`
- Edit `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/UpdateVideoConfigRequest.php`:
    - `authorize()`: check `$this->user()->id === $this->route('video')->user_id`
    - `rules()`: validate `ai_config` array structure with model selections from `config('ai.models')` keys and `script_iterations` as integer 0-5
    - Add `use Illuminate\Validation\Rule;` import
- Create controller: `php artisan make:controller VideoConfigController --no-interaction`
- Edit `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/VideoConfigController.php`:
    - Add `edit(Video $video): Response` method -- checks ownership (abort 403), renders `videos/configure` with video data, AI models/steps from config, user's AI preferences, isFreeTier status, and maxScriptIterations from video config
    - Add `update(UpdateVideoConfigRequest $request, Video $video): RedirectResponse` method -- saves validated `ai_config` on the video, also saves to `$request->user()->ai_preferences`, redirects to `videos.cost-preview` (or `videos.titles` if F005 is not built yet)
- Edit `/Users/young/Nextcloud/dev/Itervel/routes/web.php`:
    - Add `use App\Http\Controllers\VideoConfigController;`
    - Add routes inside auth+verified middleware group:
        ```php
        Route::get('videos/{video}/configure', [VideoConfigController::class, 'edit'])->name('videos.configure');
        Route::put('videos/{video}/configure', [VideoConfigController::class, 'update'])->name('videos.configure.update');
        ```
- Also update `VideoController::store()` to redirect to `videos.configure` instead of `videos.show` after creating a video, so the wizard flows: topic → configure
- Run `vendor/bin/pint --dirty`
- Run existing tests: `php artisan test --compact`

### 3. Create Frontend Configuration Page

- **Task ID**: create-frontend-page
- **Depends On**: create-controller-and-routes
- **Assigned To**: ai-config-frontend-dev
- **Agent Type**: coder
- **Parallel**: false
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/types/ai.ts`:

    ```typescript
    export type AiModel = {
        id: string;
        name: string;
        description: string;
        provider: string;
        cost_multiplier: number;
    };

    export type AiStep = {
        name: string;
        description: string;
        default_model: string;
        allowed_models: string[];
        base_credit_cost: number;
    };

    export type AiConfig = {
        use_recommended: boolean;
        models: Record<string, string>;
        script_iterations: number;
    };

    export type AiModels = Record<string, AiModel>;
    export type AiSteps = Record<string, AiStep>;
    ```

- Run `npm run build` to generate Wayfinder routes for the new controller
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/videos/configure.tsx`:
    - Import `Head`, `useForm`, `Link` from `@inertiajs/react`
    - Import UI components: `Select`, `SelectContent`, `SelectItem`, `SelectTrigger`, `SelectValue`, `Checkbox`, `Label`, `Card`, `CardContent`, `CardHeader`, `CardTitle`, `Badge`, `Button`, `Tooltip`
    - Import `Heading`, `InputError` from components
    - Import `AppLayout` from layouts
    - Import Wayfinder-generated action for `VideoConfigController.update`
    - Define `ConfigureProps` interface with: `video`, `aiModels: AiModels`, `aiSteps: AiSteps`, `defaults`, `userPreferences: AiConfig | null`, `isFreeTier: boolean`, `maxScriptIterations: number`
    - Initialize form with `useForm`: populate from `video.ai_config ?? userPreferences ?? defaults`. For defaults, construct the full config with `use_recommended: true` and all step models set to their default_model
    - Render:
        - Breadcrumbs: "Dashboard" > "Video" > "AI Configuration"
        - A `Heading` with title "AI Configuration" and description
        - A `Checkbox` for "Use recommended settings (all Sonnet)" -- when checked, disable all select dropdowns
        - A grid of `Card` components, one per AI step. Each card shows: step name, step description, a `Select` dropdown for model selection (disabled when use_recommended is checked), and a small cost indicator `Badge` showing the cost in credits (step.base_credit_cost \* model.cost_multiplier)
        - A total cost summary section at the bottom showing the sum of all step costs
        - A "Continue" button that submits the form
    - Use `cn()` for conditional styling (disabled state on dropdowns)
    - Support dark mode with Tailwind theme variables
- Run `npm run types`
- Run `npm run lint`, fix with `npm run lint:fix` if needed
- Run `npm run build`

### 4. Write Feature Tests

- **Task ID**: write-config-tests
- **Depends On**: create-controller-and-routes
- **Assigned To**: ai-config-test-dev
- **Agent Type**: coder
- **Parallel**: true (can run in parallel with frontend)
- Create test file: `php artisan make:test VideoConfigTest --pest --no-interaction`
- Write tests in `/Users/young/Nextcloud/dev/Itervel/tests/Feature/VideoConfigTest.php`:
    - `test('video configuration page is displayed for the video owner')` -- create a user and video, GET configure route, assert 200 and Inertia component `videos/configure` with `aiModels`, `aiSteps`, `defaults` props
    - `test('video configuration page returns 403 for non-owner')` -- assert 403
    - `test('guests are redirected to login')` -- assert redirect to login
    - `test('ai configuration can be saved with recommended settings')` -- PUT with `use_recommended: true`, assert video's `ai_config` is updated
    - `test('ai configuration can be saved with custom model selections')` -- PUT with `use_recommended: false` and custom model keys, assert saved
    - `test('invalid model key is rejected')` -- PUT with an invalid model key, assert validation error
    - `test('script iterations must be between 0 and 5')` -- test boundary values
    - `test('script iterations above 5 is rejected')` -- assert validation error
    - `test('script iterations below 0 is rejected')` -- assert validation error
    - `test('user preferences are saved after updating video config')` -- PUT config, then check `$user->ai_preferences` matches
    - `test('user preferences are loaded as defaults for new video config page')` -- save preferences on user, visit config for a new video, assert `userPreferences` prop matches
    - `test('getModelForStep returns configured model')` -- unit-style test on Video model
    - `test('getModelForStep returns default when use_recommended is true')` -- assert default model
    - `test('getScriptIterations returns configured value')` -- assert correct value
    - `test('ai config is stored as JSON on the video')` -- create video with config, refresh from DB, assert array structure
- Run tests: `php artisan test tests/Feature/VideoConfigTest.php --compact`
- Run `vendor/bin/pint --dirty`
- Run full test suite: `php artisan test --compact`

### 5. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-config-and-migrations, create-controller-and-routes, create-frontend-page, write-config-tests
- **Assigned To**: ai-config-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands
- Verify `config/ai.php` exists with models, steps, and defaults
- Verify migrations add `ai_config` to videos and `ai_preferences` to users
- Verify Video model has `ai_config` in fillable/casts and helper methods
- Verify User model has `ai_preferences` in fillable/casts
- Verify VideoConfigController has `edit` and `update` methods with authorization
- Verify UpdateVideoConfigRequest validates model keys against config
- Verify routes are registered: `videos.configure`, `videos.configure.update`
- Verify frontend page has "Use recommended settings" toggle, model dropdowns, cost indicators
- Verify user preferences persist across videos
- Confirm all acceptance criteria are met

## Acceptance Criteria

- A `config/ai.php` file exists with model definitions (haiku, sonnet, opus) including cost multipliers, and step definitions (title, outline, script, critique, thumbnails) with default models and base credit costs
- The `videos` table has an `ai_config` JSON column
- The `users` table has an `ai_preferences` JSON column
- The video configuration page is accessible at `/videos/{video}/configure` for the video owner
- Non-owners receive 403 when accessing the configuration page
- Guests are redirected to login
- "Use recommended settings" is checked by default, with all dropdowns showing "Sonnet" and disabled
- Unchecking "Use recommended" enables all model selection dropdowns
- Each step dropdown shows the available AI models (Haiku, Sonnet, Opus) with descriptions
- A per-step cost indicator shows the credit cost based on the selected model
- A total cost summary shows the aggregate credit cost
- Only valid model keys (haiku, sonnet, opus) are accepted by the backend
- Script iterations must be between 0 and 5
- After saving, the user's `ai_preferences` are updated with the last configuration
- When visiting the config page for a new video, the user's saved preferences are pre-loaded
- The `Video` model provides `getModelForStep()` and `getScriptIterations()` helper methods
- All tests pass
- TypeScript compiles without errors
- ESLint passes
- PHP formatting passes

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run AI config tests
php artisan test tests/Feature/VideoConfigTest.php --compact

# Run full test suite
php artisan test --compact

# Verify routes
php artisan route:list --name=videos.configure

# TypeScript type checking
npm run types

# ESLint linting
npm run lint

# PHP code formatting
vendor/bin/pint --dirty

# Prettier formatting check
npm run format:check
```

## Notes

- The AI models listed (Haiku, Sonnet, Opus) are Claude models from Anthropic. The actual API integration is not built in this feature -- that happens in E009-F003 (Title Generation). This feature only sets up the configuration infrastructure.
- The `cost_multiplier` on each model is relative to Sonnet (1.0). Haiku is 0.5x (cheaper), Opus is 3.0x (more expensive). The actual credit cost for a step is `step.base_credit_cost * model.cost_multiplier`.
- The `config/ai.php` file is separate from `config/video.php` (free-tier limits) to maintain separation of concerns. AI configuration is about model selection and costs; video configuration is about tier limits and watermarks.
- The `ai_preferences` column on users provides the "remembered" experience. When a user configures their first video with custom settings, those settings become the defaults for their next video.
- The script iterations configuration is included in the `ai_config` JSON structure but its full UI (slider with warnings) is built in E009-F002. This feature stores the value; F002 adds the enhanced UI.
- Free-tier video iteration limits are enforced via E008-F001's config values. The configuration page reads `config('video.free_tier.max_script_iterations')` to cap the iterations dropdown for free-tier videos.
- All commands should be run inside the Docker container.

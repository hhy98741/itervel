# Feature: Brand Guide Upload

**Epic**: E001-ai-powered-faceless-video-creation-platform.md
**Feature**: E001-F015
**Dependencies**: E001-F010

## Task Description

Brand Guide Upload lets users upload a brand guide document (.txt or .md) to customize the AI's voice and tone for a specific project. The brand guide content is extracted from the uploaded file and stored as text on the project record. When generating scripts for that project (E001-F026 Script Writing), the brand guide content is provided as context to the AI model. Users can also override the brand guide for individual videos (handled by video creation features).

**What it does**: Lets users upload a brand guide document (.txt or .md) to customize the AI's voice and tone for a specific project.

**Expected outcome**: The user uploads a brand guide file (max 100KB). The system extracts the content and uses it as context when generating scripts for that project. Users can also override the brand guide for individual videos.

This feature builds on E001-F010 (Create Project), which establishes the `Project` model, `projects` table, `ProjectController`, `ProjectFactory`, and project routes. It adds a `brand_guide_content` text column to the `projects` table via a new migration, a dedicated `BrandGuideController` with `store` (upload) and `destroy` (remove) actions, a `StoreBrandGuideRequest` Form Request for validation, an Inertia React page for managing the brand guide, and comprehensive tests.

The brand guide content is stored directly in the database as a text column rather than as a file on disk. Since the maximum file size is 100KB and the content is plain text (.txt or .md), storing it inline is simpler, eliminates filesystem dependencies, and makes it trivially available when passing context to AI model calls during script generation. The uploaded file itself is not persisted -- only the extracted text content.

**Dependency on E001-F010 (Create Project)**: The Create Project feature establishes the `Project` model, migration, factory, `ProjectController`, and routes. This feature adds a new column to the `projects` table and new routes/controller for brand guide management within a project.

**Related feature E001-F013 (Edit Project Settings)**: The Edit Project Settings feature adds edit/update methods to `ProjectController` and a project edit page. The brand guide management page will be a separate page accessible from the project view, not embedded in the edit form, to keep concerns separated. The brand guide page could be linked from the edit page or project index.

## Objective

Implement brand guide upload functionality by adding a `brand_guide_content` text column to the `projects` table, a `BrandGuideController` with `show`, `store`, and `destroy` actions, a `StoreBrandGuideRequest` with ownership authorization and file validation, an Inertia React brand guide management page with drag-and-drop file upload, routes for viewing/uploading/removing the brand guide, and comprehensive Pest feature tests covering authorization, file validation, content extraction, and removal.

## Solution Approach

### 1. Database: Add brand_guide_content column to projects table

Create a migration to add the `brand_guide_content` column to the `projects` table:

```php
Schema::table('projects', function (Blueprint $table) {
    $table->mediumText('brand_guide_content')->nullable()->after('speaking_pace');
});
```

Using `mediumText` (up to 16MB in MySQL/MariaDB) because the MySQL `TEXT` type has a 64KB limit, which is less than the 100KB maximum file size. With multi-byte UTF-8 content, a 100KB file could easily exceed the 64KB byte limit of `TEXT`. `mediumText` provides ample room. A nullable column allows projects to exist without a brand guide.

### 2. Update Project Model

Add `brand_guide_content` to the `$fillable` array on the `Project` model (which was created by E001-F010):

```php
protected $fillable = [
    'name',
    'target_audience',
    'tone',
    'speaking_pace',
    'brand_guide_content',
];
```

### 3. Form Request: StoreBrandGuideRequest

Create `App\Http\Requests\StoreBrandGuideRequest` to validate the uploaded file and authorize ownership:

```php
class StoreBrandGuideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->id === $this->route('project')->user_id;
    }

    public function rules(): array
    {
        return [
            'brand_guide' => ['required', 'file', 'max:100', 'mimes:txt,md'],
        ];
    }

    public function messages(): array
    {
        return [
            'brand_guide.max' => 'The brand guide file must not exceed 100KB.',
            'brand_guide.mimes' => 'The brand guide must be a .txt or .md file.',
        ];
    }
}
```

Note: The `max:100` rule uses kilobytes, so `100` = 100KB as required.

### 4. Controller: BrandGuideController

Create `App\Http\Controllers\BrandGuideController` with three methods:

```php
class BrandGuideController extends Controller
{
    public function show(Project $project): Response
    {
        if ($project->user_id !== auth()->id()) {
            abort(403);
        }

        return Inertia::render('projects/brand-guide', [
            'project' => $project->only(['id', 'name', 'brand_guide_content']),
        ]);
    }

    public function store(StoreBrandGuideRequest $request, Project $project): RedirectResponse
    {
        $content = $request->file('brand_guide')->get();

        $project->update([
            'brand_guide_content' => $content,
        ]);

        return to_route('projects.brand-guide', $project)
            ->with('success', 'Brand guide uploaded successfully.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        if ($project->user_id !== auth()->id()) {
            abort(403);
        }

        $project->update([
            'brand_guide_content' => null,
        ]);

        return to_route('projects.brand-guide', $project)
            ->with('success', 'Brand guide removed.');
    }
}
```

The `store` method reads the file content using `$request->file('brand_guide')->get()` to extract the text and stores it directly in the database. The file itself is not persisted to disk. The `destroy` method sets the column to `null` to remove the brand guide.

### 5. Routes

Add brand guide routes to `routes/web.php` inside the authenticated and verified middleware group, alongside the existing project routes (created by E001-F010):

```php
use App\Http\Controllers\BrandGuideController;

Route::get('projects/{project}/brand-guide', [BrandGuideController::class, 'show'])->name('projects.brand-guide');
Route::post('projects/{project}/brand-guide', [BrandGuideController::class, 'store'])->name('projects.brand-guide.store');
Route::delete('projects/{project}/brand-guide', [BrandGuideController::class, 'destroy'])->name('projects.brand-guide.destroy');
```

### 6. Frontend: Brand Guide Management Page

Create `resources/js/pages/projects/brand-guide.tsx` with:

- Display of current brand guide content (if present) in a read-only scrollable text area
- A file upload area with drag-and-drop support for .txt and .md files
- A file input that accepts `.txt,.md` files
- A "Remove brand guide" button (with confirmation) if a brand guide is already uploaded
- Visual feedback showing the file name and size before upload
- Validation error display
- Breadcrumbs: "Projects" > project name > "Brand Guide"

The form uses a standard HTML file input with Inertia's `router.post()` (since `<Form>` does not natively handle `multipart/form-data` well for file uploads). Use Inertia's `useForm` hook for file upload state management.

```tsx
const { data, setData, post, processing, errors, reset } = useForm<{
    brand_guide: File | null;
}>({
    brand_guide: null,
});

const handleSubmit = (e: FormEvent) => {
    e.preventDefault();
    post(BrandGuideController.store({ project: project.id }), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => reset(),
    });
};
```

### 7. Update ProjectFactory

Add `brand_guide_content` to the `ProjectFactory` definition as `null` by default, and add a `withBrandGuide()` factory state for testing:

```php
public function definition(): array
{
    return [
        // ... existing fields ...
        'brand_guide_content' => null,
    ];
}

public function withBrandGuide(): static
{
    return $this->state(fn (array $attributes) => [
        'brand_guide_content' => "# Brand Guide\n\nTone: Professional and educational\nAudience: Tech enthusiasts\nVoice: Authoritative but approachable",
    ]);
}
```

### 8. TypeScript Types

Update the `Project` TypeScript type (created by E001-F010 in `resources/js/types/project.ts`) to include the `brand_guide_content` field:

```typescript
export type Project = {
    id: number;
    name: string;
    target_audience: string | null;
    tone: string | null;
    speaking_pace: number | null;
    brand_guide_content: string | null;
    created_at: string;
    updated_at: string;
};
```

Note: Since this feature may be built before or after E001-F013, the type should be updated to include the new field regardless of the current state.

### 9. Tests

Write comprehensive Pest feature tests covering:

- Brand guide page displays for the project owner
- Brand guide page returns 403 for non-owners
- Guests are redirected to login
- Brand guide can be uploaded with a valid .txt file
- Brand guide can be uploaded with a valid .md file
- Brand guide content is extracted and stored correctly
- Upload is rejected for files exceeding 100KB
- Upload is rejected for invalid file types (.pdf, .docx, etc.)
- Upload is rejected when no file is provided
- Brand guide can be removed by the project owner
- Non-owners cannot upload or remove a brand guide
- Brand guide content displays on the management page

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/ProfileController.php` -- Reference for controller patterns (edit/update, Inertia rendering, return types).
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Controller.php` -- Base abstract controller that BrandGuideController will extend.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/Settings/ProfileUpdateRequest.php` -- Reference for Form Request patterns (array-syntax rules, return types).
- `/Users/young/Nextcloud/dev/Itervel/app/Models/User.php` -- User model with `projects()` relationship (added by E001-F010). Needed to understand the ownership check.
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Must add brand guide routes inside the authenticated/verified middleware group alongside existing project routes.
- `/Users/young/Nextcloud/dev/Itervel/routes/settings.php` -- Reference for route grouping patterns with middleware.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/profile.tsx` -- Primary reference for form page patterns (Form component, Wayfinder actions, InputError, Label, Input, Button, Transition for success feedback).
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/dashboard.tsx` -- Reference for Inertia page component pattern (AppLayout, breadcrumbs, Head).
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/heading.tsx` -- Reusable heading component for page titles.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/input-error.tsx` -- Reusable validation error display component.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/alert-error.tsx` -- Alert component for displaying error messages (reference for error display pattern).
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/button.tsx` -- Button UI component with variant and size props.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/input.tsx` -- Input UI component (reference for creating file input styling).
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/label.tsx` -- Label UI component for form fields.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/card.tsx` -- Card UI component for wrapping the brand guide content display and upload sections.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/alert.tsx` -- Alert UI component for success/info messages.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/app-layout.tsx` -- App layout wrapper for authenticated pages.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/index.ts` -- TypeScript type exports.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/navigation.ts` -- BreadcrumbItem type definition.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/lib/utils.ts` -- Utility functions (cn helper).
- `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` -- User factory for test setup. Has `paid()` state (added by E001-F010).
- `/Users/young/Nextcloud/dev/Itervel/config/filesystems.php` -- Filesystem disk configuration (reference only; brand guide content is stored in database, not on disk).
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/ProfileUpdateTest.php` -- Reference for comprehensive CRUD test patterns with Pest.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/DashboardTest.php` -- Reference for simple feature test patterns.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration. All Feature tests use RefreshDatabase.
- `/Users/young/Nextcloud/dev/Itervel/bootstrap/app.php` -- Middleware and routing configuration reference.
- `/Users/young/Nextcloud/dev/Itervel/docker-compose.yml` -- Docker container configuration (commands run in `app` container).
- `/Users/young/Nextcloud/dev/Itervel/Makefile` -- Makefile with Docker and development shortcuts.

### New Files

- `database/migrations/xxxx_xx_xx_xxxxxx_add_brand_guide_content_to_projects_table.php` -- Migration to add `brand_guide_content` nullable mediumText column to the `projects` table after `speaking_pace`.
- `app/Http/Controllers/BrandGuideController.php` -- Controller with `show` (display brand guide management page), `store` (upload and extract brand guide), and `destroy` (remove brand guide) methods.
- `app/Http/Requests/StoreBrandGuideRequest.php` -- Form Request with ownership authorization and file validation rules (required file, max 100KB, .txt or .md only).
- `resources/js/pages/projects/brand-guide.tsx` -- Inertia React page for viewing, uploading, and removing the brand guide for a project. Includes file upload form, content preview, and remove functionality.
- `tests/Feature/BrandGuideTest.php` -- Pest feature tests covering authorization, file validation, content extraction, removal, and display.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend Developer
    - Name: brand-guide-backend
    - Role: Creates the database migration, updates the Project model, creates BrandGuideController, creates StoreBrandGuideRequest, updates ProjectFactory with brand guide state, registers routes, and runs formatting
    - Agent Type: coder
    - Resume: false

- Frontend Developer
    - Name: brand-guide-frontend
    - Role: Creates the brand-guide.tsx Inertia page with file upload, content preview, and remove functionality; updates the Project TypeScript type; ensures Wayfinder route generation
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: brand-guide-tester
    - Role: Writes comprehensive Pest feature tests covering authorization, file validation, content extraction, removal, and page display
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: brand-guide-reviewer
    - Role: Validates the complete feature against acceptance criteria, runs all tests, checks TypeScript types, runs linting and formatting
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Create Database Migration, Update Project Model, Create Controller, Form Request, Factory State, and Routes

- **Task ID**: create-backend-foundation
- **Depends On**: none
- **Assigned To**: brand-guide-backend
- **Agent Type**: coder
- **Parallel**: true
- Read the existing `Project` model at `/Users/young/Nextcloud/dev/Itervel/app/Models/Project.php` (created by E001-F010) to confirm the current fillable fields and structure
- Read the existing `ProjectFactory` at `/Users/young/Nextcloud/dev/Itervel/database/factories/ProjectFactory.php` to understand the current factory definition
- Read the existing `ProjectController` at `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/ProjectController.php` for controller pattern reference
- Read `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/Settings/ProfileUpdateRequest.php` for Form Request pattern reference
- Read `/Users/young/Nextcloud/dev/Itervel/routes/web.php` to understand the current route structure and the existing project routes group
- Create a migration using `php artisan make:migration add_brand_guide_content_to_projects_table --table=projects --no-interaction` inside the Docker container (`docker compose exec app` or `make shell`)
- Edit the generated migration file:
    - In `up()`: `$table->mediumText('brand_guide_content')->nullable()->after('speaking_pace');`
    - In `down()`: `$table->dropColumn('brand_guide_content');`
- Edit `/Users/young/Nextcloud/dev/Itervel/app/Models/Project.php`:
    - Add `'brand_guide_content'` to the `$fillable` array
- Edit `/Users/young/Nextcloud/dev/Itervel/database/factories/ProjectFactory.php`:
    - Add `'brand_guide_content' => null` to the `definition()` return array
    - Add a `withBrandGuide(): static` factory state method:
        ```php
        public function withBrandGuide(): static
        {
            return $this->state(fn (array $attributes) => [
                'brand_guide_content' => "# Brand Guide\n\nTone: Professional and educational\nAudience: Tech enthusiasts aged 25-40\nVoice: Authoritative but approachable\n\n## Content Pillars\n- Technology reviews\n- Tutorial walkthroughs\n- Industry analysis",
            ]);
        }
        ```
- Create the Form Request using `php artisan make:request StoreBrandGuideRequest --no-interaction`
- Edit `/Users/young/Nextcloud/dev/Itervel/app/Http/Requests/StoreBrandGuideRequest.php`:
    - Set `authorize()` to check ownership: `return $this->user()->id === $this->route('project')->user_id;`
    - Set `rules()` to return:
        ```php
        return [
            'brand_guide' => ['required', 'file', 'max:100', 'mimes:txt,md'],
        ];
        ```
    - Add a `messages()` method returning:
        ```php
        return [
            'brand_guide.max' => 'The brand guide file must not exceed 100KB.',
            'brand_guide.mimes' => 'The brand guide must be a .txt or .md file.',
        ];
        ```
- Create the controller using `php artisan make:controller BrandGuideController --no-interaction`
- Edit `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/BrandGuideController.php`:
    - Add imports: `use App\Http\Requests\StoreBrandGuideRequest`, `use App\Models\Project`, `use Illuminate\Http\RedirectResponse`, `use Inertia\Inertia`, `use Inertia\Response`
    - Add `show(Project $project): Response` method:

        ```php
        public function show(Project $project): Response
        {
            if ($project->user_id !== auth()->id()) {
                abort(403);
            }

            return Inertia::render('projects/brand-guide', [
                'project' => $project->only(['id', 'name', 'brand_guide_content']),
            ]);
        }
        ```

    - Add `store(StoreBrandGuideRequest $request, Project $project): RedirectResponse` method:

        ```php
        public function store(StoreBrandGuideRequest $request, Project $project): RedirectResponse
        {
            $content = $request->file('brand_guide')->get();

            $project->update([
                'brand_guide_content' => $content,
            ]);

            return to_route('projects.brand-guide', $project);
        }
        ```

    - Add `destroy(Project $project): RedirectResponse` method:

        ```php
        public function destroy(Project $project): RedirectResponse
        {
            if ($project->user_id !== auth()->id()) {
                abort(403);
            }

            $project->update([
                'brand_guide_content' => null,
            ]);

            return to_route('projects.brand-guide', $project);
        }
        ```

- Edit `/Users/young/Nextcloud/dev/Itervel/routes/web.php`:
    - Add `use App\Http\Controllers\BrandGuideController;` import at the top
    - Add inside the existing `Route::middleware(['auth', 'verified'])` group (alongside the other project routes added by E001-F010):
        ```php
        Route::get('projects/{project}/brand-guide', [BrandGuideController::class, 'show'])->name('projects.brand-guide');
        Route::post('projects/{project}/brand-guide', [BrandGuideController::class, 'store'])->name('projects.brand-guide.store');
        Route::delete('projects/{project}/brand-guide', [BrandGuideController::class, 'destroy'])->name('projects.brand-guide.destroy');
        ```
- Run `php artisan migrate` to apply the new migration
- Run `vendor/bin/pint --dirty` to fix any formatting issues
- Verify routes are registered: `php artisan route:list --name=brand-guide`

### 2. Create Frontend Brand Guide Management Page and Update TypeScript Types

- **Task ID**: create-frontend-page
- **Depends On**: create-backend-foundation
- **Assigned To**: brand-guide-frontend
- **Agent Type**: coder
- **Parallel**: false
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/profile.tsx` for the form page pattern reference (Form, Wayfinder actions, errors, Transition)
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/dashboard.tsx` for the basic page layout pattern (AppLayout, breadcrumbs, Head)
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/card.tsx` for Card component usage
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/button.tsx` for Button variants
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/components/heading.tsx` for Heading component props
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/components/input-error.tsx` for error display
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/types/index.ts` for type export patterns
- Run `npm run build` first to generate the Wayfinder routes for the new BrandGuideController methods
- Check the generated Wayfinder files in `resources/js/actions/App/Http/Controllers/BrandGuideController/` and `resources/js/routes/` to confirm the correct import paths for the brand guide routes
- Edit `/Users/young/Nextcloud/dev/Itervel/resources/js/types/project.ts` (created by E001-F010) to add `brand_guide_content: string | null;` to the Project type. If this file does not exist yet (because E001-F010 has not been built), create it with the full Project type including `brand_guide_content`:
    ```typescript
    export type Project = {
        id: number;
        name: string;
        target_audience: string | null;
        tone: string | null;
        speaking_pace: number | null;
        brand_guide_content: string | null;
        created_at: string;
        updated_at: string;
    };
    ```
- Create `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/projects/brand-guide.tsx` with the following structure:

    ```tsx
    import { Transition } from '@headlessui/react';
    import { Head, Link, router, useForm } from '@inertiajs/react';
    import { FormEvent, useRef, useState, DragEvent } from 'react';
    import Heading from '@/components/heading';
    import InputError from '@/components/input-error';
    import { Button } from '@/components/ui/button';
    import {
        Card,
        CardContent,
        CardDescription,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import { Label } from '@/components/ui/label';
    import AppLayout from '@/layouts/app-layout';
    import type { BreadcrumbItem } from '@/types';
    import BrandGuideController from '@/actions/App/Http/Controllers/BrandGuideController';
    // Import Wayfinder route for breadcrumb links
    // Adjust import paths based on generated Wayfinder files

    interface BrandGuideProps {
        project: {
            id: number;
            name: string;
            brand_guide_content: string | null;
        };
    }

    export default function BrandGuide({ project }: BrandGuideProps) {
        const breadcrumbs: BreadcrumbItem[] = [
            {
                title: 'Projects',
                href: '/projects', // Use Wayfinder route if available
            },
            {
                title: project.name,
                href: `/projects/${project.id}/edit`, // Use Wayfinder route if available
            },
            {
                title: 'Brand Guide',
                href: `/projects/${project.id}/brand-guide`,
            },
        ];

        const fileInputRef = useRef<HTMLInputElement>(null);
        const [isDragging, setIsDragging] = useState(false);
        const [selectedFileName, setSelectedFileName] = useState<string | null>(
            null,
        );
        const [recentlySuccessful, setRecentlySuccessful] = useState(false);

        const { data, setData, post, processing, errors, reset } = useForm<{
            brand_guide: File | null;
        }>({
            brand_guide: null,
        });

        const handleFileSelect = (file: File) => {
            setData('brand_guide', file);
            setSelectedFileName(file.name);
        };

        const handleDragOver = (e: DragEvent) => {
            e.preventDefault();
            setIsDragging(true);
        };

        const handleDragLeave = (e: DragEvent) => {
            e.preventDefault();
            setIsDragging(false);
        };

        const handleDrop = (e: DragEvent) => {
            e.preventDefault();
            setIsDragging(false);
            const file = e.dataTransfer.files[0];
            if (file) {
                handleFileSelect(file);
            }
        };

        const handleSubmit = (e: FormEvent) => {
            e.preventDefault();
            post(BrandGuideController.store({ project: project.id }), {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    reset();
                    setSelectedFileName(null);
                    setRecentlySuccessful(true);
                    setTimeout(() => setRecentlySuccessful(false), 2000);
                },
            });
        };

        const handleRemove = () => {
            if (confirm('Are you sure you want to remove the brand guide?')) {
                router.delete(
                    BrandGuideController.destroy({ project: project.id }),
                    { preserveScroll: true },
                );
            }
        };

        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title={`Brand Guide - ${project.name}`} />

                <div className="px-4 py-6">
                    <Heading
                        title="Brand Guide"
                        description="Upload a brand guide to customize the AI's voice and tone for this project"
                    />

                    <div className="max-w-2xl space-y-6">
                        {/* Current Brand Guide Display */}
                        {project.brand_guide_content && (
                            <Card>
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <CardTitle>
                                                Current Brand Guide
                                            </CardTitle>
                                            <CardDescription>
                                                This content is used as context
                                                when generating scripts
                                            </CardDescription>
                                        </div>
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            onClick={handleRemove}
                                        >
                                            Remove
                                        </Button>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <pre className="max-h-96 overflow-auto rounded-md border bg-muted p-4 text-sm whitespace-pre-wrap">
                                        {project.brand_guide_content}
                                    </pre>
                                </CardContent>
                            </Card>
                        )}

                        {/* Upload Form */}
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    {project.brand_guide_content
                                        ? 'Replace Brand Guide'
                                        : 'Upload Brand Guide'}
                                </CardTitle>
                                <CardDescription>
                                    Upload a .txt or .md file (max 100KB)
                                    containing your brand voice guidelines
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form
                                    onSubmit={handleSubmit}
                                    className="space-y-4"
                                >
                                    <div className="grid gap-2">
                                        <Label htmlFor="brand_guide">
                                            Brand guide file
                                        </Label>
                                        <div
                                            onDragOver={handleDragOver}
                                            onDragLeave={handleDragLeave}
                                            onDrop={handleDrop}
                                            onClick={() =>
                                                fileInputRef.current?.click()
                                            }
                                            className={cn(
                                                'flex cursor-pointer flex-col items-center justify-center rounded-md border-2 border-dashed p-8 text-center transition-colors',
                                                isDragging
                                                    ? 'border-primary bg-primary/5'
                                                    : 'border-muted-foreground/25 hover:border-muted-foreground/50',
                                            )}
                                        >
                                            {/* Upload icon and text */}
                                            {selectedFileName ? (
                                                <p className="text-sm font-medium">
                                                    {selectedFileName}
                                                </p>
                                            ) : (
                                                <>
                                                    <p className="text-sm text-muted-foreground">
                                                        Drag and drop your file
                                                        here, or click to browse
                                                    </p>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        Supports .txt and .md
                                                        files up to 100KB
                                                    </p>
                                                </>
                                            )}
                                            <input
                                                ref={fileInputRef}
                                                id="brand_guide"
                                                type="file"
                                                accept=".txt,.md"
                                                className="hidden"
                                                onChange={(e) => {
                                                    const file =
                                                        e.target.files?.[0];
                                                    if (file)
                                                        handleFileSelect(file);
                                                }}
                                            />
                                        </div>
                                        <InputError
                                            message={errors.brand_guide}
                                        />
                                    </div>

                                    <div className="flex items-center gap-4">
                                        <Button
                                            type="submit"
                                            disabled={
                                                processing || !data.brand_guide
                                            }
                                        >
                                            {processing
                                                ? 'Uploading...'
                                                : 'Upload'}
                                        </Button>

                                        <Transition
                                            show={recentlySuccessful}
                                            enter="transition ease-in-out"
                                            enterFrom="opacity-0"
                                            leave="transition ease-in-out"
                                            leaveTo="opacity-0"
                                        >
                                            <p className="text-sm text-neutral-600">
                                                Saved
                                            </p>
                                        </Transition>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </AppLayout>
        );
    }
    ```

- Note: The `cn` utility must be imported from `@/lib/utils`. Add `import { cn } from '@/lib/utils';` to the imports.
- Note: The actual Wayfinder import paths for `BrandGuideController` may vary. After running `npm run build`, check the generated files in `resources/js/actions/App/Http/Controllers/BrandGuideController/` to confirm the correct import path. Adjust imports accordingly.
- Note: The breadcrumb hrefs should use Wayfinder route functions where available. After `npm run build`, import the appropriate route functions. If Wayfinder routes for projects index or project edit exist, use those. Otherwise use string paths as a fallback.
- Run `npm run build` to compile assets and verify Wayfinder route generation
- Run `npm run types` to verify no TypeScript errors
- Run `npm run lint` and `npm run format` to fix any linting/formatting issues

### 3. Write Comprehensive Feature Tests for Brand Guide Upload

- **Task ID**: write-brand-guide-tests
- **Depends On**: create-backend-foundation
- **Assigned To**: brand-guide-tester
- **Agent Type**: coder
- **Parallel**: true (can run in parallel with task 2)
- Read `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/ProfileUpdateTest.php` for test patterns
- Read `/Users/young/Nextcloud/dev/Itervel/tests/Feature/DashboardTest.php` for simple test patterns
- Read the `ProjectFactory` at `/Users/young/Nextcloud/dev/Itervel/database/factories/ProjectFactory.php` for available factory methods (including `withBrandGuide()` added in task 1)
- Create `/Users/young/Nextcloud/dev/Itervel/tests/Feature/BrandGuideTest.php` using `php artisan make:test BrandGuideTest --pest --no-interaction`
- Write the following tests:

    ```php
    <?php

    use App\Models\Project;
    use App\Models\User;
    use Illuminate\Http\UploadedFile;

    // --- Brand Guide Page Display Tests ---

    test('guests are redirected to login from brand guide page', function () {
        $project = Project::factory()->for(User::factory())->create();

        $response = $this->get(route('projects.brand-guide', $project));

        $response->assertRedirect(route('login'));
    });

    test('brand guide page is displayed for the project owner', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->get(route('projects.brand-guide', $project));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('projects/brand-guide')
            ->has('project')
            ->where('project.id', $project->id)
            ->where('project.name', $project->name)
        );
    });

    test('brand guide page returns 403 for non-owner', function () {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $response = $this->actingAs($otherUser)->get(route('projects.brand-guide', $project));

        $response->assertForbidden();
    });

    test('brand guide page shows existing brand guide content', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->withBrandGuide()->create();

        $response = $this->actingAs($user)->get(route('projects.brand-guide', $project));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('project.brand_guide_content', $project->brand_guide_content)
        );
    });

    test('brand guide page shows null content when no brand guide uploaded', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->get(route('projects.brand-guide', $project));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('project.brand_guide_content', null)
        );
    });

    // --- Brand Guide Upload Tests ---

    test('brand guide can be uploaded with a valid txt file', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $file = UploadedFile::fake()->createWithContent(
            'brand-guide.txt',
            'Tone: Professional and educational'
        );

        $response = $this->actingAs($user)->post(
            route('projects.brand-guide.store', $project),
            ['brand_guide' => $file]
        );

        $response->assertRedirect(route('projects.brand-guide', $project));
        $response->assertSessionHasNoErrors();

        $project->refresh();
        expect($project->brand_guide_content)->toBe('Tone: Professional and educational');
    });

    test('brand guide can be uploaded with a valid md file', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $content = "# Brand Guide\n\n## Tone\nProfessional and approachable";
        $file = UploadedFile::fake()->createWithContent(
            'brand-guide.md',
            $content
        );

        $response = $this->actingAs($user)->post(
            route('projects.brand-guide.store', $project),
            ['brand_guide' => $file]
        );

        $response->assertRedirect(route('projects.brand-guide', $project));
        $response->assertSessionHasNoErrors();

        $project->refresh();
        expect($project->brand_guide_content)->toBe($content);
    });

    test('brand guide content is extracted correctly from the file', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $expectedContent = "Line 1\nLine 2\nLine 3\n\nParagraph 2";
        $file = UploadedFile::fake()->createWithContent(
            'guide.txt',
            $expectedContent
        );

        $this->actingAs($user)->post(
            route('projects.brand-guide.store', $project),
            ['brand_guide' => $file]
        );

        expect($project->refresh()->brand_guide_content)->toBe($expectedContent);
    });

    test('uploading a new brand guide replaces the existing one', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->withBrandGuide()->create();
        $originalContent = $project->brand_guide_content;

        $newContent = 'Updated brand voice: Casual and fun';
        $file = UploadedFile::fake()->createWithContent(
            'updated-guide.txt',
            $newContent
        );

        $this->actingAs($user)->post(
            route('projects.brand-guide.store', $project),
            ['brand_guide' => $file]
        );

        $project->refresh();
        expect($project->brand_guide_content)->toBe($newContent);
        expect($project->brand_guide_content)->not->toBe($originalContent);
    });

    test('guests cannot upload a brand guide', function () {
        $project = Project::factory()->for(User::factory())->create();

        $file = UploadedFile::fake()->createWithContent('guide.txt', 'content');

        $response = $this->post(
            route('projects.brand-guide.store', $project),
            ['brand_guide' => $file]
        );

        $response->assertRedirect(route('login'));
    });

    test('non-owner cannot upload a brand guide', function () {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $file = UploadedFile::fake()->createWithContent('guide.txt', 'content');

        $response = $this->actingAs($otherUser)->post(
            route('projects.brand-guide.store', $project),
            ['brand_guide' => $file]
        );

        $response->assertForbidden();
        expect($project->refresh()->brand_guide_content)->toBeNull();
    });

    // --- Validation Tests ---

    test('upload is rejected when no file is provided', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(
            route('projects.brand-guide.store', $project),
            ['brand_guide' => null]
        );

        $response->assertSessionHasErrors('brand_guide');
    });

    test('upload is rejected for files exceeding 100KB', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        // Create a file larger than 100KB (101KB)
        $file = UploadedFile::fake()->create('large-guide.txt', 101);

        $response = $this->actingAs($user)->post(
            route('projects.brand-guide.store', $project),
            ['brand_guide' => $file]
        );

        $response->assertSessionHasErrors('brand_guide');
        expect($project->refresh()->brand_guide_content)->toBeNull();
    });

    test('upload is rejected for pdf files', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $file = UploadedFile::fake()->create('guide.pdf', 10);

        $response = $this->actingAs($user)->post(
            route('projects.brand-guide.store', $project),
            ['brand_guide' => $file]
        );

        $response->assertSessionHasErrors('brand_guide');
    });

    test('upload is rejected for docx files', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $file = UploadedFile::fake()->create('guide.docx', 10);

        $response = $this->actingAs($user)->post(
            route('projects.brand-guide.store', $project),
            ['brand_guide' => $file]
        );

        $response->assertSessionHasErrors('brand_guide');
    });

    test('upload is rejected for image files', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $file = UploadedFile::fake()->image('guide.jpg');

        $response = $this->actingAs($user)->post(
            route('projects.brand-guide.store', $project),
            ['brand_guide' => $file]
        );

        $response->assertSessionHasErrors('brand_guide');
    });

    test('file at exactly 100KB is accepted', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $file = UploadedFile::fake()->create('guide.txt', 100);

        $response = $this->actingAs($user)->post(
            route('projects.brand-guide.store', $project),
            ['brand_guide' => $file]
        );

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('projects.brand-guide', $project));
    });

    // --- Brand Guide Removal Tests ---

    test('brand guide can be removed by the project owner', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->withBrandGuide()->create();

        expect($project->brand_guide_content)->not->toBeNull();

        $response = $this->actingAs($user)->delete(
            route('projects.brand-guide.destroy', $project)
        );

        $response->assertRedirect(route('projects.brand-guide', $project));
        expect($project->refresh()->brand_guide_content)->toBeNull();
    });

    test('guests cannot remove a brand guide', function () {
        $project = Project::factory()->for(User::factory())->withBrandGuide()->create();

        $response = $this->delete(
            route('projects.brand-guide.destroy', $project)
        );

        $response->assertRedirect(route('login'));
        expect($project->refresh()->brand_guide_content)->not->toBeNull();
    });

    test('non-owner cannot remove a brand guide', function () {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::factory()->for($owner)->withBrandGuide()->create();

        $response = $this->actingAs($otherUser)->delete(
            route('projects.brand-guide.destroy', $project)
        );

        $response->assertForbidden();
        expect($project->refresh()->brand_guide_content)->not->toBeNull();
    });

    test('removing a brand guide when none exists is a no-op', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        expect($project->brand_guide_content)->toBeNull();

        $response = $this->actingAs($user)->delete(
            route('projects.brand-guide.destroy', $project)
        );

        $response->assertRedirect(route('projects.brand-guide', $project));
        expect($project->refresh()->brand_guide_content)->toBeNull();
    });

    test('removing a brand guide does not affect other project fields', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->withBrandGuide()->create([
            'name' => 'My Channel',
            'target_audience' => 'Tech enthusiasts',
            'tone' => 'Professional',
        ]);

        $this->actingAs($user)->delete(
            route('projects.brand-guide.destroy', $project)
        );

        $project->refresh();
        expect($project->brand_guide_content)->toBeNull();
        expect($project->name)->toBe('My Channel');
        expect($project->target_audience)->toBe('Tech enthusiasts');
        expect($project->tone)->toBe('Professional');
    });
    ```

- Run the tests: `php artisan test tests/Feature/BrandGuideTest.php --compact`
- Fix any failing tests until all pass
- Run `vendor/bin/pint --dirty` to format the test file

### 4. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-backend-foundation, create-frontend-page, write-brand-guide-tests
- **Assigned To**: brand-guide-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed below
- Verify brand guide tests pass: `php artisan test tests/Feature/BrandGuideTest.php --compact`
- Run the full test suite: `php artisan test --compact`
- Run TypeScript type checking: `npm run types`
- Run ESLint: `npm run lint`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Verify the following files exist and are correct:
    - A migration file adding `brand_guide_content` to the `projects` table exists with correct schema
    - `app/Models/Project.php` has `brand_guide_content` in the `$fillable` array
    - `app/Http/Controllers/BrandGuideController.php` has `show`, `store`, and `destroy` methods with correct ownership checks
    - `app/Http/Requests/StoreBrandGuideRequest.php` has authorization (ownership) and validation rules (required file, max 100KB, .txt or .md)
    - `database/factories/ProjectFactory.php` has `brand_guide_content => null` in definition and `withBrandGuide()` state
    - `resources/js/pages/projects/brand-guide.tsx` renders the brand guide management page with upload form, content preview, and remove button
    - `resources/js/types/project.ts` has `brand_guide_content: string | null` in the Project type
    - Routes are correctly defined: `projects.brand-guide`, `projects.brand-guide.store`, `projects.brand-guide.destroy`
- Verify routes exist: `php artisan route:list --name=brand-guide`
- Confirm all acceptance criteria are met

## Acceptance Criteria

- Authenticated project owners can view the brand guide management page at `/projects/{project}/brand-guide`
- Guests are redirected to the login page when accessing brand guide routes
- Non-owners receive a 403 Forbidden response when attempting to view, upload, or remove a brand guide
- Users can upload a .txt file as a brand guide and its content is extracted and stored on the project
- Users can upload a .md file as a brand guide and its content is extracted and stored on the project
- Uploaded files must not exceed 100KB; files over 100KB are rejected with a validation error
- Only .txt and .md file types are accepted; other file types (.pdf, .docx, .jpg, etc.) are rejected with a validation error
- A file must be provided; submitting without a file produces a validation error
- The brand guide management page displays the current brand guide content if one is uploaded
- The brand guide management page shows a file upload area when no brand guide exists or when replacing an existing one
- Uploading a new brand guide replaces the existing one
- Users can remove an existing brand guide, setting the content to null
- Removing a brand guide does not affect other project fields (name, target_audience, tone, speaking_pace)
- The file upload area supports drag-and-drop interaction
- The `ProjectFactory` has a `withBrandGuide()` factory state for testing
- The `Project` TypeScript type includes `brand_guide_content: string | null`
- All brand guide tests pass
- All existing tests continue to pass (no regressions)
- PHP code passes Pint formatting
- TypeScript passes type checking
- ESLint reports no errors

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run brand guide tests
php artisan test tests/Feature/BrandGuideTest.php --compact

# Run full test suite for regression check
php artisan test --compact

# Verify routes are registered
php artisan route:list --name=brand-guide

# TypeScript type checking
npm run types

# ESLint linting
npm run lint

# PHP code formatting
vendor/bin/pint --dirty
```

## Notes

- This feature depends on E001-F010 (Create Project) which creates the Project model, migration, factory, ProjectController, and routes. The `projects` table must exist with the columns defined by E001-F010 (`name`, `target_audience`, `tone`, `speaking_pace`) before this migration can add `brand_guide_content`.
- The brand guide content is stored directly in the `projects` table as a `mediumText` column rather than as a file on the filesystem. This simplifies the implementation (no file management, no cleanup on deletion, no storage driver concerns) and makes the content immediately available for AI context without an additional file read. `mediumText` (16MB limit) is used instead of `text` (64KB limit) because a 100KB file with multi-byte UTF-8 characters could exceed the 64KB byte limit of MySQL's `TEXT` type.
- The `mimes:txt,md` validation rule checks the file extension, not the MIME type. For stricter validation, `mimetypes:text/plain,text/markdown,text/x-markdown` could be used instead, but extension-based validation is simpler and sufficient for this use case since the content is just read as plain text.
- The "override the brand guide for individual videos" functionality mentioned in the expected outcome is not implemented in this feature. It will be handled by the video creation features (E001-F026 Script Writing, E001-F017 Topic Input) where the user can optionally provide custom brand context per video. This feature establishes the project-level brand guide that serves as the default.
- E001-F016 (Brand Guide Template Download) is a related feature that provides a downloadable template. It is separate from this upload feature and does not need to be built first.
- E001-F013 (Edit Project Settings) adds a project edit page. The brand guide page is intentionally separate from the edit page to keep the file upload concern decoupled from the settings form. However, the edit page or project index could link to the brand guide page for discoverability.
- All `php artisan` commands should be run inside the Docker container. Use `make shell` to enter the container, or prefix with `docker compose exec app`.
- After the backend is built and `npm run build` is executed, Wayfinder will auto-generate route functions in `resources/js/actions/App/Http/Controllers/BrandGuideController/`. The frontend code should import from these generated paths. The exact paths should be verified after generation.

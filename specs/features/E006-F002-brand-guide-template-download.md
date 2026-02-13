# Feature: Brand Guide Template Download

**Epic**: E006-brand-guide-and-video-input.md
**Feature**: E006-F002
**Epic depends on**: E004-project-management.md
**Feature depends on**: E006-F001

## Task Description

Brand Guide Template Download provides a pre-formatted sample brand guide template that users can download, fill in with their own channel details, and then upload via the brand guide upload feature (E001-F015). The template is a Markdown file containing structured sections for describing the channel's voice, tone, target audience, content pillars, and preferred language. It serves as a guided starting point so users know exactly what information the AI needs to generate on-brand scripts.

**What it does**: Provides a sample brand guide template that users can download and fill in.

**Expected outcome**: The user downloads a pre-formatted template showing how to describe their channel's voice, tone, target audience, content pillars, and preferred language.

This feature builds on E001-F015 (Brand Guide Upload), which establishes the brand guide management page at `/projects/{project}/brand-guide` with the `BrandGuideController`, routes, and the Inertia React brand guide page. E001-F016 adds a download endpoint to the `BrandGuideController` and a "Download Template" button to the existing brand guide page. It also creates the actual template file that gets served to users.

The template is stored as a static Markdown file in `storage/app/private/templates/brand-guide-template.md`. It is served through a dedicated controller action rather than as a publicly accessible file, because the download is only available to authenticated users within the context of a project. The controller uses Laravel's `response()->download()` to serve the file with proper headers (Content-Disposition: attachment) so the browser downloads it rather than displaying it inline.

**Dependency on E001-F015 (Brand Guide Upload)**: E001-F015 creates the `BrandGuideController` with `show`, `store`, and `destroy` methods, the brand guide management page at `resources/js/pages/projects/brand-guide.tsx`, and the brand guide routes. This feature adds a `downloadTemplate` method to the existing `BrandGuideController` and a new route, and updates the existing brand guide page to include a download button.

## Objective

Implement the brand guide template download by creating a Markdown template file containing structured sections for voice, tone, target audience, content pillars, and preferred language; adding a `downloadTemplate` action to the existing `BrandGuideController`; registering a new route for the download; updating the existing brand guide management page with a "Download Template" button; and writing comprehensive Pest feature tests covering authentication, file download, content type, and response headers.

## Solution Approach

### 1. Create the Brand Guide Template File

Create a Markdown template file at `storage/app/private/templates/brand-guide-template.md`. This location follows Laravel's convention for private application files that should not be publicly accessible. The file is a well-structured Markdown document with clear sections and placeholder text that guides users on what to fill in.

Template content structure:

- **Channel Overview**: Channel name, niche, one-line description
- **Voice & Tone**: How the channel sounds (e.g., authoritative, conversational, humorous), emotional register, formality level
- **Target Audience**: Demographics, interests, knowledge level, what they want from the content
- **Content Pillars**: The 3-5 core topics the channel focuses on
- **Preferred Language**: Vocabulary preferences, words/phrases to use, words/phrases to avoid, jargon handling
- **Script Style**: Preferred intro style, CTA patterns, hook approach, pacing notes
- **Examples**: Sample phrases that capture the channel's voice

The template should be approximately 2-4KB in size -- substantial enough to be genuinely useful but well within the 100KB upload limit.

### 2. Add downloadTemplate Method to BrandGuideController

Add a new `downloadTemplate` method to the existing `BrandGuideController` (created by E001-F015):

```php
public function downloadTemplate(): BinaryFileResponse
{
    $path = storage_path('app/private/templates/brand-guide-template.md');

    return response()->download($path, 'brand-guide-template.md', [
        'Content-Type' => 'text/markdown',
    ]);
}
```

This method:

- Uses `response()->download()` which sets `Content-Disposition: attachment` automatically
- Serves the file from the private storage directory
- Sets the download filename to `brand-guide-template.md`
- Specifies `text/markdown` as the content type
- Does not require a project parameter since the template is the same for all projects
- Returns `BinaryFileResponse` (from Symfony) as the return type

Note: The `downloadTemplate` action does not need a `{project}` route parameter since the template is static and not project-specific. However, the route is still placed under authenticated middleware so only logged-in users can download it.

### 3. Register the Download Route

Add a new route to `routes/web.php` inside the authenticated and verified middleware group, alongside the existing brand guide routes (created by E001-F015):

```php
Route::get('brand-guide/template', [BrandGuideController::class, 'downloadTemplate'])
    ->name('brand-guide.template');
```

The route is placed at `/brand-guide/template` (not under `/projects/{project}/brand-guide/template`) because the template is static and not project-specific. This avoids requiring a project parameter for a file that is identical across all projects.

### 4. Update the Brand Guide Frontend Page

Update the existing `resources/js/pages/projects/brand-guide.tsx` (created by E001-F015) to add a "Download Template" section. This should appear either:

- Above the upload form as a helpful prompt for users who do not yet have a brand guide
- Or as a persistent helper section visible regardless of whether a brand guide is already uploaded

The download link uses a standard HTML anchor tag (`<a>`) with the `download` attribute rather than an Inertia `<Link>`, because this is a file download, not an SPA navigation:

```tsx
<a href={BrandGuideController.downloadTemplate().url} download className="...">
    Download Template
</a>
```

Alternatively, use the Wayfinder-generated route function. The key is to use a native `<a>` tag so the browser handles the file download natively.

The download section should be wrapped in a Card component for visual consistency with the rest of the page. It should include:

- A title like "Need a starting point?"
- A brief description explaining the template
- A download button with a download icon (from lucide-react)

### 5. Tests

Write Pest feature tests covering:

- Authenticated users can download the template file
- Guests are redirected to login when attempting to download
- The response has the correct content type (text/markdown or application/octet-stream)
- The response has a Content-Disposition header indicating attachment with the correct filename
- The response content matches the template file content
- The downloaded file is not empty

## Relevant Files

Use these files to complete the task:

- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/BrandGuideController.php` -- The existing controller (created by E001-F015) where the `downloadTemplate` method will be added. Currently has `show`, `store`, and `destroy` methods.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Controller.php` -- Base abstract controller for reference.
- `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/Settings/ProfileController.php` -- Reference for controller patterns (Inertia rendering, return types).
- `/Users/young/Nextcloud/dev/Itervel/routes/web.php` -- Must add the template download route inside the authenticated/verified middleware group alongside the existing brand guide routes.
- `/Users/young/Nextcloud/dev/Itervel/routes/settings.php` -- Reference for route grouping patterns with middleware.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/projects/brand-guide.tsx` -- The existing brand guide management page (created by E001-F015) that must be updated to include the download template button.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/settings/profile.tsx` -- Reference for page layout patterns (AppLayout, breadcrumbs, Head, Button, Card).
- `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/dashboard.tsx` -- Reference for Inertia page component pattern (Wayfinder route imports).
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/button.tsx` -- Button UI component with variant and size props. Used for the download button.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/card.tsx` -- Card UI component for wrapping the download template section.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/components/heading.tsx` -- Heading component for section titles.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/layouts/app-layout.tsx` -- App layout wrapper used by authenticated pages.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/lib/utils.ts` -- Utility functions (cn helper).
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/index.ts` -- TypeScript type exports.
- `/Users/young/Nextcloud/dev/Itervel/resources/js/types/navigation.ts` -- BreadcrumbItem type definition.
- `/Users/young/Nextcloud/dev/Itervel/config/filesystems.php` -- Filesystem disk configuration. The `local` disk root is `storage/app/private`, confirming the template file path.
- `/Users/young/Nextcloud/dev/Itervel/database/factories/UserFactory.php` -- User factory for test setup.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/ProfileUpdateTest.php` -- Reference for comprehensive Pest feature test patterns.
- `/Users/young/Nextcloud/dev/Itervel/tests/Feature/BrandGuideTest.php` -- The existing brand guide tests (created by E001-F015). Reference for test patterns specific to brand guide routes.
- `/Users/young/Nextcloud/dev/Itervel/tests/Pest.php` -- Pest configuration. All Feature tests use RefreshDatabase.
- `/Users/young/Nextcloud/dev/Itervel/bootstrap/app.php` -- Middleware and routing configuration reference.
- `/Users/young/Nextcloud/dev/Itervel/docker-compose.yml` -- Docker container configuration (commands run in `app` container).
- `/Users/young/Nextcloud/dev/Itervel/Makefile` -- Makefile with Docker and development shortcuts.

### New Files

- `storage/app/private/templates/brand-guide-template.md` -- The Markdown brand guide template file containing structured sections for voice, tone, target audience, content pillars, preferred language, and script style with placeholder text and examples.
- `tests/Feature/BrandGuideTemplateDownloadTest.php` -- Pest feature tests for the template download endpoint covering authentication, response headers, content type, and file content.

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

- Backend & Frontend Developer
    - Name: template-download-dev
    - Role: Creates the brand guide template file, adds the downloadTemplate method to BrandGuideController, registers the download route, updates the brand guide frontend page with the download button, and runs formatting
    - Agent Type: coder
    - Resume: false

- Test Developer
    - Name: template-download-tester
    - Role: Writes comprehensive Pest feature tests covering authentication, file download response, content type, headers, and file content
    - Agent Type: coder
    - Resume: false

- Reviewer
    - Name: template-download-reviewer
    - Role: Validates the complete feature against acceptance criteria, runs all tests, checks TypeScript types, runs linting and formatting
    - Agent Type: reviewer
    - Resume: false

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. Create Brand Guide Template File, Add Controller Method, Register Route, and Update Frontend Page

- **Task ID**: create-template-and-endpoint
- **Depends On**: none
- **Assigned To**: template-download-dev
- **Agent Type**: coder
- **Parallel**: true
- Read the existing `BrandGuideController` at `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/BrandGuideController.php` (created by E001-F015) to confirm the current methods and imports
- Read `/Users/young/Nextcloud/dev/Itervel/routes/web.php` to understand the current route structure and identify where the brand guide routes are registered
- Read the existing brand guide page at `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/projects/brand-guide.tsx` (created by E001-F015) to understand the current page layout and components used
- Read `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/card.tsx` and `/Users/young/Nextcloud/dev/Itervel/resources/js/components/ui/button.tsx` for component patterns
- Create the directory `storage/app/private/templates/` if it does not exist
- Create the template file at `/Users/young/Nextcloud/dev/Itervel/storage/app/private/templates/brand-guide-template.md` with this content:

    ```markdown
    # Brand Guide Template

    Fill in each section below to help the AI understand your channel's unique voice and style. Save this file and upload it to your project's brand guide settings.

    ---

    ## Channel Overview

    **Channel Name**: [Your channel name]
    **Niche**: [e.g., Technology, Finance, Science, Lifestyle, Gaming]
    **One-Line Description**: [A single sentence describing what your channel is about]

    ---

    ## Voice & Tone

    Describe how your channel "sounds" to viewers.

    **Overall Voice**: [e.g., Authoritative, Conversational, Enthusiastic, Calm, Witty]
    **Formality Level**: [e.g., Very casual, Slightly informal, Neutral, Professional, Academic]
    **Emotional Register**: [e.g., Excited and energetic, Thoughtful and measured, Warm and encouraging]
    **Humor Style**: [e.g., No humor, Dry wit, Self-deprecating, Playful, Sarcastic]

    Example: "We sound like a knowledgeable friend explaining something over coffee -- informed but never condescending, with occasional dry humor."

    ---

    ## Target Audience

    **Age Range**: [e.g., 18-24, 25-34, 35-50]
    **Interests**: [e.g., Software development, Personal finance, Space exploration]
    **Knowledge Level**: [e.g., Complete beginners, Intermediate, Advanced practitioners]
    **What They Want**: [e.g., Practical tutorials, In-depth analysis, Quick overviews, Entertainment]
    **Common Pain Points**: [e.g., Overwhelmed by options, Need step-by-step guidance, Want honest reviews]

    ---

    ## Content Pillars

    List the 3-5 core topics your channel focuses on.

    1. [e.g., Product Reviews -- Honest, detailed reviews of the latest tech]
    2. [e.g., Tutorials -- Step-by-step guides for common tasks]
    3. [e.g., Industry News -- Weekly roundups of important developments]
    4. [e.g., Opinion Pieces -- Hot takes on trending topics]
    5. [e.g., Comparisons -- Side-by-side breakdowns of competing products]

    ---

    ## Preferred Language

    ### Words and Phrases to Use

    - [e.g., "Let's dive in", "Here's the thing", "What's interesting is..."]
    - [e.g., Use "we" to include the audience, not "I"]
    - [e.g., Technical terms with brief explanations]

    ### Words and Phrases to Avoid

    - [e.g., "Smash that like button", overly clickbait phrases]
    - [e.g., Excessive jargon without explanation]
    - [e.g., Negative language about competitors]

    ### Jargon Handling

    - [e.g., Always define technical terms on first use]
    - [e.g., Use industry terms freely -- our audience knows them]
    - [e.g., Provide analogies for complex concepts]

    ---

    ## Script Style

    **Preferred Intro Style**: [e.g., Start with a provocative question, Open with a surprising statistic, Jump straight into the topic]
    **Hook Approach**: [e.g., Problem-solution, Curiosity gap, Bold claim, Story-based]
    **Call-to-Action Style**: [e.g., Subtle and woven into content, Direct ask at the end, No CTAs]
    **Pacing Notes**: [e.g., Fast-paced with quick cuts, Measured and deliberate, Mix of fast and slow sections]
    **Closing Style**: [e.g., Summary of key points, Teaser for next video, Thought-provoking question]

    ---

    ## Examples

    Write 2-3 example sentences that capture your channel's voice perfectly.

    1. "[e.g., Look, I know everyone's hyped about this new feature, but let me show you why the old way might actually be better for most people.]"
    2. "[e.g., This might sound counterintuitive, but hear me out -- the data tells a completely different story than what you'd expect.]"
    3. "[e.g., Alright, let's break this down step by step. By the end of this video, you'll know exactly how to set this up yourself.]"

    ---

    ## Additional Notes

    Add any other guidelines that help define your channel's identity.

    - [e.g., We always cite our sources]
    - [e.g., We avoid political topics unless directly relevant]
    - [e.g., We prioritize accuracy over speed]
    - [e.g., Our videos should feel like a conversation, not a lecture]
    ```

- Edit `/Users/young/Nextcloud/dev/Itervel/app/Http/Controllers/BrandGuideController.php`:
    - Add `use Symfony\Component\HttpFoundation\BinaryFileResponse;` import at the top
    - Add the `downloadTemplate` method:

        ```php
        /**
         * Download the brand guide template file.
         */
        public function downloadTemplate(): BinaryFileResponse
        {
            $path = storage_path('app/private/templates/brand-guide-template.md');

            return response()->download($path, 'brand-guide-template.md', [
                'Content-Type' => 'text/markdown',
            ]);
        }
        ```

- Edit `/Users/young/Nextcloud/dev/Itervel/routes/web.php`:
    - Confirm that `use App\Http\Controllers\BrandGuideController;` is already imported (added by E001-F015)
    - Add the template download route inside the existing `Route::middleware(['auth', 'verified'])` group, alongside the other brand guide routes:
        ```php
        Route::get('brand-guide/template', [BrandGuideController::class, 'downloadTemplate'])
            ->name('brand-guide.template');
        ```
- Run `npm run build` to generate Wayfinder routes for the new `downloadTemplate` action
- Check the generated Wayfinder files in `resources/js/actions/App/Http/Controllers/BrandGuideController/` to confirm the import path for the `downloadTemplate` action
- Edit `/Users/young/Nextcloud/dev/Itervel/resources/js/pages/projects/brand-guide.tsx`:
    - Add a `Download` icon import from `lucide-react`: `import { Download } from 'lucide-react';`
    - Import the Wayfinder-generated route for the template download. After running `npm run build`, check the generated files. The import will likely be one of:
        - `import * as BrandGuideController from '@/actions/App/Http/Controllers/BrandGuideController';` (if already imported, just use the existing import)
        - Or a specific route import from `@/routes/brand-guide`
    - Add a "Download Template" Card section inside the `<div className="max-w-2xl space-y-6">` container. Place it before the upload form card so users see the template option first:
        ```tsx
        {
            /* Download Template Section */
        }
        <Card>
            <CardHeader>
                <CardTitle>Need a starting point?</CardTitle>
                <CardDescription>
                    Download our brand guide template with structured sections
                    for voice, tone, audience, and more. Fill it in and upload
                    it above.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <a href={BrandGuideController.downloadTemplate().url} download>
                    <Button variant="outline" type="button">
                        <Download className="size-4" />
                        Download Template
                    </Button>
                </a>
            </CardContent>
        </Card>;
        ```
    - Note: The `<a>` tag wraps the `<Button>` to handle the native file download. Use `Button` with `asChild` prop if the design system supports it, or wrap with a plain anchor. If `Button` has an `asChild` prop (it does -- from Radix Slot), the preferred approach is:
        ```tsx
        <Button variant="outline" asChild>
            <a href={BrandGuideController.downloadTemplate().url} download>
                <Download className="size-4" />
                Download Template
            </a>
        </Button>
        ```
        This is the cleaner approach because `asChild` renders the `<a>` tag with Button styles, avoiding nested interactive elements.
    - Adjust the placement: if the project already has a brand guide uploaded (i.e., `project.brand_guide_content` is not null), place the download template card after the current brand guide display and before the upload/replace form. If no brand guide exists, place it before the upload form as a prominent helper.
- Run `vendor/bin/pint --dirty` to fix any PHP formatting issues
- Run `npm run build` to compile assets
- Run `npm run types` to verify no TypeScript errors
- Run `npm run lint` and `npm run format` to fix any linting/formatting issues
- Verify the route is registered: `php artisan route:list --name=brand-guide.template`

### 2. Write Comprehensive Feature Tests for Brand Guide Template Download

- **Task ID**: write-template-download-tests
- **Depends On**: create-template-and-endpoint
- **Assigned To**: template-download-tester
- **Agent Type**: coder
- **Parallel**: false
- Read `/Users/young/Nextcloud/dev/Itervel/tests/Feature/BrandGuideTest.php` (created by E001-F015) for test patterns specific to brand guide routes
- Read `/Users/young/Nextcloud/dev/Itervel/tests/Feature/Settings/ProfileUpdateTest.php` for general Pest test patterns
- Read the template file at `/Users/young/Nextcloud/dev/Itervel/storage/app/private/templates/brand-guide-template.md` to understand the expected content
- Create `/Users/young/Nextcloud/dev/Itervel/tests/Feature/BrandGuideTemplateDownloadTest.php` using `php artisan make:test BrandGuideTemplateDownloadTest --pest --no-interaction`
- Write the following tests:

    ```php
    <?php

    use App\Models\User;

    test('guests are redirected to login when downloading the brand guide template', function () {
        $response = $this->get(route('brand-guide.template'));

        $response->assertRedirect(route('login'));
    });

    test('authenticated users can download the brand guide template', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('brand-guide.template'));

        $response->assertOk();
        $response->assertDownload('brand-guide-template.md');
    });

    test('downloaded template has the correct content type', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('brand-guide.template'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/markdown');
    });

    test('downloaded template file is not empty', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('brand-guide.template'));

        $response->assertOk();

        $templatePath = storage_path('app/private/templates/brand-guide-template.md');
        $content = file_get_contents($templatePath);

        expect($content)->not->toBeEmpty();
    });

    test('downloaded template contains expected sections', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('brand-guide.template'));

        $response->assertOk();

        $templatePath = storage_path('app/private/templates/brand-guide-template.md');
        $content = file_get_contents($templatePath);

        expect($content)->toContain('## Channel Overview');
        expect($content)->toContain('## Voice & Tone');
        expect($content)->toContain('## Target Audience');
        expect($content)->toContain('## Content Pillars');
        expect($content)->toContain('## Preferred Language');
        expect($content)->toContain('## Script Style');
        expect($content)->toContain('## Examples');
    });

    test('downloaded template is a valid markdown file under 100KB', function () {
        $templatePath = storage_path('app/private/templates/brand-guide-template.md');

        expect(file_exists($templatePath))->toBeTrue();

        $size = filesize($templatePath);

        // Template should be under 100KB (the upload limit)
        expect($size)->toBeLessThan(100 * 1024);
        // Template should be substantial (at least 1KB)
        expect($size)->toBeGreaterThan(1024);
    });

    test('template file exists at the expected storage path', function () {
        $templatePath = storage_path('app/private/templates/brand-guide-template.md');

        expect(file_exists($templatePath))->toBeTrue();
    });
    ```

- Run the tests: `php artisan test tests/Feature/BrandGuideTemplateDownloadTest.php --compact`
- Fix any failing tests until all pass
- Run `vendor/bin/pint --dirty` to format the test file

### 3. Validate Complete Implementation

- **Task ID**: validate-all
- **Depends On**: create-template-and-endpoint, write-template-download-tests
- **Assigned To**: template-download-reviewer
- **Agent Type**: reviewer
- **Parallel**: false
- Run all validation commands listed below
- Verify template download tests pass: `php artisan test tests/Feature/BrandGuideTemplateDownloadTest.php --compact`
- Run the full test suite: `php artisan test --compact`
- Run TypeScript type checking: `npm run types`
- Run ESLint: `npm run lint`
- Run PHP formatting: `vendor/bin/pint --dirty`
- Verify the following files exist and are correct:
    - `storage/app/private/templates/brand-guide-template.md` exists and contains structured sections for Channel Overview, Voice & Tone, Target Audience, Content Pillars, Preferred Language, Script Style, and Examples
    - `app/Http/Controllers/BrandGuideController.php` has the `downloadTemplate()` method that returns a `BinaryFileResponse` with the template file
    - The `downloadTemplate` method uses `response()->download()` with the correct path, filename, and Content-Type header
    - The route `brand-guide.template` is registered at `GET /brand-guide/template` inside the auth/verified middleware group
    - `resources/js/pages/projects/brand-guide.tsx` includes a "Download Template" card section with a download link using Wayfinder-generated route and a `<Button>` with a download icon
    - The download link uses a native `<a>` tag (not an Inertia Link) so the browser handles the file download natively
- Verify route exists: `php artisan route:list --name=brand-guide.template`
- Confirm all acceptance criteria are met

## Acceptance Criteria

- Authenticated users can download the brand guide template by visiting the template download route
- Guests are redirected to the login page when attempting to download the template
- The downloaded file is named `brand-guide-template.md`
- The downloaded file has a `text/markdown` content type
- The downloaded file contains structured sections for: Channel Overview, Voice & Tone, Target Audience, Content Pillars, Preferred Language, Script Style, and Examples
- The template file is under 100KB (so it can be uploaded via the brand guide upload feature after being filled in)
- The template file is at least 1KB (substantial enough to be useful)
- The brand guide management page (`/projects/{project}/brand-guide`) displays a "Download Template" card section with a download button
- The download button uses a native anchor tag (`<a>`) so the browser handles the file download, not Inertia navigation
- The download button includes a download icon from lucide-react
- The template download route is registered as `brand-guide.template` under authenticated middleware
- All template download tests pass
- All existing tests continue to pass (no regressions)
- PHP code passes Pint formatting
- TypeScript passes type checking
- ESLint reports no errors

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run template download tests
php artisan test tests/Feature/BrandGuideTemplateDownloadTest.php --compact

# Run brand guide tests (to verify no regressions to E001-F015)
php artisan test tests/Feature/BrandGuideTest.php --compact

# Run full test suite for regression check
php artisan test --compact

# Verify route is registered
php artisan route:list --name=brand-guide.template

# TypeScript type checking
npm run types

# ESLint linting
npm run lint

# PHP code formatting
vendor/bin/pint --dirty
```

## Notes

- This feature depends on E001-F015 (Brand Guide Upload) which creates the `BrandGuideController`, brand guide routes, and the `resources/js/pages/projects/brand-guide.tsx` page. All of those must exist before this feature can be built.
- The template is stored in `storage/app/private/templates/` rather than in `public/` because we want to gate access behind authentication middleware. If the template were public, anyone (including unauthenticated users and crawlers) could download it. While not sensitive, keeping it behind auth is consistent with the application's approach.
- The template is a Markdown file (`.md`) rather than `.txt` because Markdown provides better structure with headings, lists, and formatting that is easy to read and edit. Markdown is also one of the accepted file types for brand guide upload (E001-F015 validates `mimes:txt,md`), so users can download the template, fill it in, and upload it directly without changing the file format.
- The download route does not include a `{project}` parameter because the template is the same for all projects. However, the download button is placed on the project-specific brand guide page so users discover it in context.
- The `response()->download()` method automatically sets the `Content-Disposition: attachment; filename="brand-guide-template.md"` header, which tells the browser to download the file rather than display it inline.
- The `assertDownload` test assertion (available in Laravel 9+) verifies that the response is a file download with the expected filename. This is cleaner than manually checking Content-Disposition headers.
- The template content is designed to be comprehensive but not overwhelming. Each section includes placeholder text in brackets (e.g., `[Your channel name]`) and concrete examples so users understand what kind of information to provide.
- All `php artisan` commands should be run inside the Docker container. Use `make shell` to enter the container, or prefix with `docker compose exec app`.
- After the backend is built and `npm run build` is executed, Wayfinder will auto-generate a route function for the new `downloadTemplate` action in `resources/js/actions/App/Http/Controllers/BrandGuideController/`. The frontend code should import from the generated path. The exact path should be verified after generation.

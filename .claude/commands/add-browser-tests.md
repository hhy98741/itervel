---
name: add-browser-tests
description: Takes one or more feature plan files and adds browser testing requirements (Pest Browser Plugin/Playwright smoke tests, dark mode checks, data-test attributes, browser test tasks) into each file.
disable-model-invocation: true
argument-hint: <feature-file-1.md> [feature-file-2.md] [...]
model: opus
allowed-tools: Read, Edit, Glob, Grep
---

# Add Browser Tests to Feature Plans

Read one or more existing feature plan files and update each to include browser testing requirements using the Pest Browser Plugin (Playwright).

## Arguments

The user provides one or more paths to feature plan files. These are markdown files in `specs/features/` following the standard feature plan format.

If no arguments are provided, ask the user which feature files to update. You can list available files from `specs/features/`.

## Browser Testing Context

This project uses:

- **Pest 4 Browser Plugin** with Playwright for browser testing
- Browser tests live in `tests/Browser/`
- Tests run in desktop Chrome only (unless specified otherwise)
- Use `data-test` attributes on interactive elements for stable selectors
- Use `assertNoJavaScriptErrors()` in all browser tests
- Smoke tests use `visit([...urls])->assertNoJavaScriptErrors()` pattern
- Dark mode spot checks use color scheme switching for key pages

### Browser Test Patterns

**Smoke test for new pages:**

```php
it('loads without errors', function () {
    $this->actingAs(User::factory()->create());

    $page = visit('/the-route');

    $page->assertSuccessful()
        ->assertNoJavaScriptErrors();
});
```

**Dark mode spot check:**

```php
it('renders correctly in dark mode', function () {
    $this->actingAs(User::factory()->create());

    $page = visit('/the-route');

    $page->assertSuccessful()
        ->assertNoJavaScriptErrors()
        ->colorScheme('dark')
        ->assertNoJavaScriptErrors();
});
```

**Interactive flow test:**

```php
it('completes the user flow', function () {
    $this->actingAs(User::factory()->create());

    $page = visit('/the-route');

    $page->assertSee('Expected Text')
        ->assertNoJavaScriptErrors()
        ->click('[data-test="submit-button"]')
        ->assertSee('Success');
});
```

## Instructions

For each feature file provided:

1. **Read the file** completely to understand the feature, its pages, routes, and interactive elements.

2. **Determine what browser tests are needed** based on the feature:
    - **Smoke tests**: Every new page/route should have a smoke test (`assertNoJavaScriptErrors()`)
    - **Dark mode spot check**: At least one key page per feature should have a dark mode check
    - **Interactive flow tests**: Any user-facing flows with forms, buttons, modals, or multi-step interactions should have browser tests covering the happy path
    - Only test core user flows — not every edge case

3. **Identify interactive elements** that need `data-test` attributes. Look at the frontend tasks/components described in the feature and determine which elements need selectors:
    - Form submit buttons
    - Navigation links specific to the feature
    - Modal triggers/close buttons
    - Multi-step navigation (next/previous/skip)
    - Key interactive elements that browser tests would target

4. **Update the feature file** with these additions. Apply ALL of the following changes:

### A. Add to "New Files" section

Add the browser test file entry:

```
- `tests/Browser/<FeatureAreaTest>.php` -- Pest browser tests: smoke test for new pages, dark mode spot check, and core user flow tests using `data-test` selectors.
```

### B. Add `data-test` attribute requirements to frontend tasks

Find the step(s) that create or modify frontend components. Add bullet points specifying which `data-test` attributes to add to interactive elements. For example:

```
- Add `data-test` attributes to interactive elements for browser testing:
    - `data-test="create-project-button"` on the submit button
    - `data-test="project-name-input"` on the name input field
    - `data-test="skip-tour-button"` on the skip button
```

### C. Add a browser test task to "Step by Step Tasks"

Insert a new numbered task BEFORE the final validation task. The task should:

- Have a descriptive Task ID like `write-browser-tests`
- Depend on both the backend and frontend tasks being complete
- Be assigned to a team member (use an existing test developer or add a new one)
- Include specific browser test cases to write

Example format:

```markdown
### N. Write Browser Tests

- **Task ID**: write-browser-tests
- **Depends On**: <frontend-task-id>, <backend-task-id>
- **Assigned To**: <test-dev-name>
- **Agent Type**: coder
- **Parallel**: false
- Create `tests/Browser/<FeatureAreaTest>.php`
- Write a smoke test for each new page route:
    - Visit the page as an authenticated user
    - Assert no JavaScript errors
- Write a dark mode spot check for the primary page:
    - Visit the page, switch to dark color scheme, assert no JavaScript errors
- Write interactive flow tests for core user flows:
    - <describe specific flow test using data-test selectors>
- Use `data-test` selectors for all element interactions (never CSS classes or text content for targeting)
- Ensure all browser tests use `assertNoJavaScriptErrors()`
- Run browser tests: `php artisan test tests/Browser/<FeatureAreaTest>.php --compact`
```

### D. Add a browser test team member (if not already present)

If the feature doesn't already have a team member suitable for browser tests, add one to the "Team Members" section:

```markdown
- Browser Test Developer
    - Name: <feature>-browser-test-dev
    - Role: Writes Pest browser tests (smoke tests, dark mode checks, interactive flow tests) for the feature's new pages and user flows
    - Agent Type: coder
    - Resume: false
```

### E. Update the final validation task

Add browser test validation to the existing `validate-all` task:

```
- Run browser tests: `php artisan test tests/Browser/<FeatureAreaTest>.php --compact`
- Verify `data-test` attributes exist on interactive elements in frontend components
```

### F. Add browser test acceptance criteria

Add to the "Acceptance Criteria" section:

```
- All new pages have smoke tests (no JavaScript errors)
- Dark mode spot check passes for the primary page
- Core user flows pass browser tests using `data-test` selectors
- Interactive frontend elements have `data-test` attributes
- All browser tests pass
```

### G. Add browser test validation commands

Add to the "Validation Commands" section:

```bash
# Run browser tests
php artisan test tests/Browser/<FeatureAreaTest>.php --compact
```

5. **Renumber tasks** if inserting a new task changed the numbering. Ensure the final validation task is always the last task and its "Depends On" includes the new browser test task ID.

6. **Verify the edit** by re-reading the file after editing to confirm all sections were updated correctly.

## What NOT to Do

- Do NOT rewrite the entire file — only add/modify the specific sections listed above
- Do NOT change the feature's core implementation approach
- Do NOT add browser tests for backend-only features (e.g., a feature that only creates models/services with no frontend pages)
- Do NOT add excessive browser tests — focus on smoke tests, one dark mode check, and core happy-path flows
- Do NOT remove or modify existing test tasks — add browser tests as a separate task

## Report

After processing all files, provide a summary:

```
Browser testing requirements added.

Files updated:
- <filename>: Added <N> browser test cases, <N> data-test attributes, browser test task
- <filename>: Skipped (backend-only feature, no new pages)

Changes per file:
- New Files: added browser test file entry
- Frontend tasks: added data-test attribute requirements
- Step by Step Tasks: added browser test task (#N)
- Team Members: added browser test developer (if needed)
- Acceptance Criteria: added browser test criteria
- Validation Commands: added browser test command
```

---
name: create-features
description: Finds the next epic in specs/epics/, explores the codebase once, creates implementation plans for all remaining features, and moves the epic to done.
disable-model-invocation: true
model: opus
allowed-tools: Read, Write, Edit, Glob, Grep, Bash(mv:*), Bash(mkdir:*)
---

# Create Features

Find the next epic, explore the codebase to understand relevant files, then write a detailed implementation plan for each feature in the epic. Since all features in an epic are related and touch the same area of the codebase, this is done in a single pass — explore once, plan all.

## Project Context

This is a **Laravel 12 / Inertia v2 / React 19 / TypeScript / Pest 4** project. You already know the stack — use this knowledge when writing plans.

**Backend (PHP 8.4)**:

- Thin controllers → Form Request classes for validation (array-syntax rules, shared via `app/Concerns/` traits)
- `Inertia::render()` for pages, `to_route()` / `back()` for redirects
- Models: `casts()` method, `$fillable`, `$hidden`. Create with `php artisan make:model -mfs --no-interaction`
- Middleware registered in `bootstrap/app.php`. Per-controller via `HasMiddleware` interface
- Named routes with dot notation. `route()` helper everywhere
- Eloquent relationships and `Model::query()` over `DB::`. Eager loading to prevent N+1
- Fortify for auth actions in `app/Actions/Fortify/`

**Frontend (React 19 + TypeScript)**:

- Pages in `resources/js/pages/` (`kebab-case.tsx`), layouts in `resources/js/layouts/`
- Forms: `<Form {...Controller.action.form()}>` render-prop pattern (Inertia v2)
- Wayfinder: controller actions from `@/actions/...`, named routes from `@/routes/...`
- Layouts: `<AppLayout>` (authenticated), `<AuthLayout>` (public), `<SettingsLayout>` (settings)
- UI: shadcn/ui components in `@/components/ui/`, `cn()` for conditional classes, lucide-react icons
- Types in `resources/js/types/`, shared data via `usePage<SharedData>()`

**Testing (Pest 4)**:

- Feature tests in `tests/Feature/` (auto-RefreshDatabase), unit tests in `tests/Unit/`
- Flat `test()` functions, lowercase sentence-style names
- `User::factory()->create()` with states, `fake()` helper, named route assertions
- `assertInertia()` for Inertia page assertions
- Browser tests in `tests/Browser/` using Pest Browser Plugin (Playwright) — core user flows only, desktop/Chrome only
- Smoke tests (`assertNoSmoke()`) for all new pages, dark mode spot check for key pages
- Use `@data-test` selectors in browser tests; add `data-test` attributes to interactive elements in React components

**Formatting**: `vendor/bin/pint --dirty` (PHP), `npm run lint` + `npm run format` (TS/JS), `npm run types` (TypeScript)

## Variables

OUTPUT_DIRECTORY: `specs/features/`
EPICS_DIRECTORY: `specs/epics/`
EPICS_DONE_DIRECTORY: `specs/epics/done/`
SUBAGENTS_DIR: `${CLAUDE_PLUGIN_ROOT}/agents/`

## Instructions

- You are both the **planner and the writer**. You explore the codebase, design the approach, and write all feature plans yourself.
- Each epic has at most 7 related features. Since they share the same area of the codebase, you explore the codebase once and use that understanding across all feature plans.
- Write plans detailed enough that an executing agent can build each feature without any additional context.
- Do NOT build, write code, or deploy agents. Your only output is the plan documents.

## Workflow

1. **Find Next Epic**
    - List all `.md` files in `EPICS_DIRECTORY` matching the pattern `E###-*.md`.
    - Select the file with the **lowest** `E###` number. This is the next epic to process.
    - If no epic files are found, stop and tell the user there are no epics to process in `EPICS_DIRECTORY`.

2. **Read Epic**
    - Read the epic file.
    - Extract the epic number from the filename (e.g., `E001` from `E001-user-authentication.md`).
    - Parse the `**Depends on**` line to capture epic-level dependencies (other epic filenames, or "None").
    - Parse every numbered feature. Each feature is a `## N. Feature Name` section with `**What it does**` and `**Expected outcome**` fields.
    - Build a list of features with: number, name, what it does, expected outcome, and assigned feature ID (E###-F###).

3. **Determine Remaining Features**
    - List existing files in `OUTPUT_DIRECTORY` that start with the current epic number (e.g., `E001-F*.md`).
    - Match each existing file to the feature list from the epic. A feature is already planned if a file matching its `E###-F###` pattern exists.
    - Build the list of **remaining features** — those that do NOT yet have a corresponding plan file.
    - If all features already have plans, skip to step 7 (Validate files).

4. **Discover Team Agents**
    - Read all files in `SUBAGENTS_DIR` to build a roster of available team agents.
    - For each agent, capture: name, description, and any tool restrictions.
    - You will reference these agents in the Team Orchestration section of each feature plan.

5. **Explore Codebase**
    - You already know the project's stack and conventions (see Project Context above). Do NOT spend time rediscovering the general architecture.
    - Focus exploration on **feature-specific files**: existing controllers, models, pages, routes, tests, and components that the epic's features will touch or extend.
    - Use Read, Glob, and Grep to find: relevant existing files, database tables/migrations in the feature area, existing tests for related functionality, route definitions, and any infrastructure to build on.
    - This is the ONE exploration pass — make it thorough for the feature domain. Everything you learn here informs all feature plans.

6. **Analyze Dependencies and Write Plans**
    - Look at ALL features in the epic (both already-planned and remaining) and determine:
        - **Dependencies**: Which features depend on another? (e.g., "edit user profile" depends on "create user profile")
        - **Build order**: What order should features be built in? Foundational features first.
    - For each **remaining** feature, in build order, write a complete implementation plan to `OUTPUT_DIRECTORY/E###-F###-descriptive-name.md` using the Plan Format below.
    - After writing each plan file, verify it was created using Glob. If it was not, retry once.

7. **Validate files**
    - Re-read every feature plan file you just created using Read. Verify each file contains:
        - `**Epic**` and `**Feature**` identifiers at the top
        - `**Epic depends on**` and `**Feature depends on**` lines
        - `## Task Description`
        - `## Objective`
        - `## Solution Approach`
        - `## Relevant Files`
        - `## Team Orchestration` with a `### Team Members` subsection
        - `## Step by Step Tasks` with at least one numbered task
        - `## Acceptance Criteria`
        - `## Validation Commands`
    - If any file is missing required content, fix it using Edit before continuing.

8. **Validate completeness**
    - List all files in `OUTPUT_DIRECTORY` matching the epic number.
    - Compare against the feature list from the epic. Verify every feature has a corresponding file.
    - If any are missing, write the missing plans and validate them per step 7.

9. **Archive Epic**
    - Ensure `EPICS_DONE_DIRECTORY` exists. If not, create it with `mkdir -p` via Bash.
    - Move the epic file from `EPICS_DIRECTORY` to `EPICS_DONE_DIRECTORY` using Bash `mv`. Keep the same filename.
    - Confirm the file exists at its new location using Glob.

10. **Report**

- Provide a summary of all files created.

## Plan Format

Write each feature plan in this EXACT format. Replace `<requested content>` placeholders with actual content. Anything NOT in `<requested content>` should be written EXACTLY as shown.

````md
# Feature: <Feature Name>

**Epic**: <epic filename>
**Feature**: <E###-F###>
**Epic depends on**: <list of epic filenames from the Depends on line, or "None">
**Feature depends on**: <list of feature IDs this depends on, or "None">

## Task Description

<describe the feature in detail. What it does, who it's for, how it fits into the broader epic. Include the original "What it does" and "Expected outcome" from the epic, then expand with implementation-relevant context.>

## Objective

<clearly state what will be accomplished when this feature is complete>

## Solution Approach

<describe the proposed technical approach using project-specific patterns: which controllers, Form Requests, models, migrations, React pages, and components to create or modify. Reference concrete patterns — e.g., "Create a Form Request with array-syntax rules", "Use Inertia v2 <Form> render-prop pattern", "Add Pest feature tests using factory states". Include code examples or pseudo-code where appropriate.>

## Relevant Files

Use these files to complete the task:

<list existing files relevant to this feature with bullet points explaining why each is relevant>

### New Files

<list any new files that need to be created, with a description of what each contains>

## Team Orchestration

- The executing agent operates as the team lead and orchestrates the team to execute this plan.
- The team lead is responsible for deploying the right team members with the right context.
- IMPORTANT: The team lead NEVER operates directly on the codebase. It uses `Task` and `Task*` tools to deploy team members for building, validating, testing, and other tasks.
- Take note of the session id of each team member for referencing them.

### Team Members

<list the team members needed for this feature. Select from the available agents discovered. Assign unique names so multiple instances of the same agent type can be distinguished.>

- <Agent Role>
  - Name: <unique name for this agent instance>
  - Role: <the specific focus this agent will have for this feature>
  - Agent Type: <the subagent_type value — must match an available agent name or use "general-purpose">
  - Resume: <true if the agent should continue with preserved context, false for a fresh start>
- <continue with additional team members as needed>

## Step by Step Tasks

- IMPORTANT: Each task maps directly to a `TaskCreate` call when the plan is executed.
- Tasks should be ordered: foundational work first, then core implementation, then validation.

### 1. <First Task Name>

- **Task ID**: <unique kebab-case identifier>
- **Depends On**: <Task ID(s) this depends on, or "none">
- **Assigned To**: <team member name from Team Members section>
- **Agent Type**: <subagent_type>
- **Parallel**: <true if can run alongside other tasks, false if must be sequential>
- <specific action to complete>
- <specific action to complete>

### 2. <Second Task Name>

- **Task ID**: <unique-id>
- **Depends On**: <previous Task ID>
- **Assigned To**: <team member name>
- **Agent Type**: <subagent_type>
- **Parallel**: <true/false>
- <specific action>
- <specific action>

<continue with additional tasks as needed>

### N. <Final Validation Task>

- **Task ID**: validate-all
- **Depends On**: <all previous Task IDs>
- **Assigned To**: <validator team member>
- **Agent Type**: <validator agent type>
- **Parallel**: false
- Run all validation commands
- Verify acceptance criteria met

## Acceptance Criteria

<list specific, measurable criteria that must be met for this feature to be considered complete>

## Validation Commands

Execute these commands to validate the feature is complete:

```bash
# Run feature-specific tests
php artisan test --compact --filter=<relevant test file or name>

# TypeScript type checking (if frontend changes)
npm run types
```
````

> Formatting (Pint, ESLint, Prettier) is applied automatically by the coder agent's Stop hook and does not need to be run manually.

<add or modify commands as needed for this specific feature>

## Notes

<optional additional context, considerations, edge cases, or dependencies>

```

## Report

After all features are planned, provide this summary:

```

Feature plans created.

Epic: <epic filename>
Epic depends on: <epic dependencies or "None">
Features planned: <count of newly planned features>
Features skipped (already planned): <count or "None">
Available agents: <list agent names discovered>

Build Order:

1. <E###-F### feature name> (no feature dependencies)
2. <E###-F### feature name> (depends on F###)
3. ...

Files created:

- <E###-F###-name.md>
- <E###-F###-name.md>
- ...

Epic moved to: <EPICS_DONE_DIRECTORY/<filename>>

Validation: <PASS or FAIL>
<If PASS: "All features from the epic have corresponding feature plan files.">
<If FAIL: list any features from the epic that are missing a file>

```

```

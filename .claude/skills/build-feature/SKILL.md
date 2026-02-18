---
name: build-feature
description: Picks the next feature from specs/features/ whose epic and feature dependencies are all done, builds it with team agents, and moves it to done.
disable-model-invocation: true
allowed-tools: Read, Write, Edit, Glob, Grep, Bash(mv:*), Bash(mkdir:*), Bash(git:*), Bash(gh:*), Task, TaskCreate, TaskUpdate, TaskList, TaskGet, TaskOutput
---

# Build Feature

Pick the next eligible feature from `FEATURES_DIRECTORY`, execute the implementation plan using team agents, validate the work, then move the feature file to `DONE_DIRECTORY`. A feature is eligible only when all its epic dependencies and feature dependencies have been completed.

## Variables

FEATURES_DIRECTORY: `specs/features/`
IN_PROGRESS_DIRECTORY: `specs/features/in-progress/`
DONE_DIRECTORY: `specs/features/done/`
SUBAGENTS_DIR: `${CLAUDE_PLUGIN_ROOT}/agents/`

## Instructions

- You are a **team lead / orchestrator**. You do NOT write code or modify the codebase directly. You deploy team agents to do the work.
- Your job is to read the feature plan, create tasks, deploy agents, monitor progress, and validate completion.
- **IMPORTANT**: Never write code yourself. Use `Task` to deploy coder agents for implementation work and reviewer agents to review and validate.
- If a team member fails or produces incorrect work, redeploy them with corrected instructions. Do not fix their work yourself.
- Features still in `FEATURES_DIRECTORY` have NOT been built yet. Features in `DONE_DIRECTORY` have been completed.

## Workflow

1. **Select Feature**
    - List all `.md` files in `FEATURES_DIRECTORY` using Glob (not subdirectories — only files directly in this directory).
    - If `FEATURES_DIRECTORY` is empty or does not exist, stop and tell the user there are no features to build.
    - **Check dependencies to find the next eligible feature.** Go through each candidate file, starting from the lowest `E###-F###` number:
        - Read the file and find the `**Epic depends on**` and `**Feature depends on**` fields near the top.
        - **Epic dependency check**: If `**Epic depends on**` lists epic filenames (e.g., `E001-user-auth.md`), extract the epic number from each (e.g., `E001`). For each dependent epic, check that NO feature files matching that epic number (e.g., `E001-F*.md`) exist in `FEATURES_DIRECTORY` or `IN_PROGRESS_DIRECTORY`. If any do, this epic dependency is not yet satisfied.
        - **Feature dependency check**: If `**Feature depends on**` lists feature IDs (e.g., `E001-F002`), check that a file matching each ID exists in `DONE_DIRECTORY`. If any dependency is NOT in `DONE_DIRECTORY`, this feature dependency is not yet satisfied.
        - If BOTH epic and feature dependencies are satisfied (or "None"), this feature is eligible — select it.
        - If not, skip it and check the next file.
    - If NO features have all dependencies satisfied, stop and tell the user which features are waiting on which dependencies. List what needs to be completed first.

2. **Create Feature Branch**
    - Record the current branch name using `git branch --show-current`. Store this as `BASE_BRANCH` — you will need it later when creating the pull request.
    - Extract the feature ID (e.g., `E001-F003`) and a short description from the feature filename. The filename is typically like `E001-F003-some-feature-name.md`.
    - Create a new branch named `feature/<feature-id>-<short-description>` (e.g., `feature/E001-F003-some-feature-name`) using `git checkout -b <branch-name>`.
    - Confirm you are on the new branch with `git branch --show-current`.

3. **Move to In-Progress**
    - Ensure `IN_PROGRESS_DIRECTORY` exists. If not, create it with `mkdir -p` via Bash.
    - Move the feature file to `IN_PROGRESS_DIRECTORY` using Bash `mv`. Keep the same filename.
    - Confirm the file now exists at its new location using Glob.
    - Store the new path — this is where you'll read the plan from for the rest of the workflow.

4. **Read Plan**
    - Read the feature plan from its new location in `IN_PROGRESS_DIRECTORY`.
    - Parse the plan to extract:
        - **Task Description** and **Objective** — to understand what needs to be built
        - **Team Members** — who to deploy (agent types and roles)
        - **Step by Step Tasks** — the ordered task list with dependencies, assignments, and actions
        - **Acceptance Criteria** — what success looks like
        - **Validation Commands** — commands to run to verify completion

5. **Discover Team Agents**
    - Read all files in `SUBAGENTS_DIR` to understand what agent types are available.
    - Match the team members listed in the plan to available agent types.
    - If the plan references an agent type that doesn't exist in `SUBAGENTS_DIR`, use `general-purpose` as a fallback.

6. **Create Task List**
    - For each step in **Step by Step Tasks**, call `TaskCreate` with:
        - `subject`: The task name from the plan
        - `description`: All the specific actions listed under that task, plus any relevant context from the plan (relevant files, acceptance criteria for that task, etc.)
        - `activeForm`: A present-continuous description (e.g., "Implementing user service")
    - After creating all tasks, set up dependencies using `TaskUpdate` with `addBlockedBy` matching the **Depends On** fields from the plan.

7. **Execute Tasks**
    - Work through the task list in dependency order. For each task:
        - Use `TaskUpdate` to set it to `in_progress`.
        - Deploy the assigned team agent using `Task` with:
            - `subagent_type`: The agent type from the plan (e.g., `coder`, `reviewer`)
            - `model`: `opus` for complex tasks, `sonnet` for straightforward ones
            - `prompt`: Include the task description, specific actions, relevant file paths, and any context needed. Reference the feature plan path so the agent can read more detail if needed.
        - When the agent completes, review its output.
        - If the agent succeeded, use `TaskUpdate` to mark the task `completed`.
        - If the agent failed, retry once with clarified instructions. If it fails again, note the failure and continue to the next task.
    - **Task deployment guidelines**:
        - Tasks marked `Parallel: true` with no pending dependencies CAN be launched with `run_in_background: true` simultaneously. Use `TaskOutput` to wait for them.
        - Tasks marked `Parallel: false` or with pending dependencies MUST be run sequentially.
        - Always deploy the final validation task last, after all other tasks are complete.
    - **Resume pattern**: Store agent IDs. If a coder needs follow-up work (e.g., fixing issues found by reviewer), use `resume` to continue with its existing context.

8. **Review and Validate**
    - After all coding tasks are complete, deploy a **reviewer** agent to review the code and validate it works.
    - The reviewer receives:
        - The feature plan path (in `IN_PROGRESS_DIRECTORY`)
        - The list of all files changed or created during the build (collected from coder reports)
        - The acceptance criteria from the plan
        - The validation commands from the plan (tests, type checks, linting)
    - The reviewer will run validation commands, check acceptance criteria, review code quality, and return a verdict: **APPROVED** or **CHANGES_REQUIRED**.
    - **If APPROVED**: Proceed to Move to Done. Note any "Recommended" items in the final report but do not act on them.
    - **If CHANGES_REQUIRED**:
        - Read the reviewer's "Must Fix" list.
        - Deploy a **coder** agent with the must-fix items as its task. Include the reviewer's specific findings (file paths, line numbers, what's wrong, what's expected) so the coder knows exactly what to fix.
        - After the coder finishes, deploy the **reviewer** again to re-review.
        - Repeat the review → fix cycle up to **2 times**. If the reviewer still returns CHANGES_REQUIRED after 2 fix rounds, move to reporting with a FAIL status. The feature file stays in `IN_PROGRESS_DIRECTORY`. The feature branch is left as-is (do not create a PR or switch branches).

9. **Move to Done**
    - Only if the reviewer verdict is APPROVED.
    - Ensure `DONE_DIRECTORY` exists. If not, create it with `mkdir -p` via Bash.
    - Move the feature file from `IN_PROGRESS_DIRECTORY` to `DONE_DIRECTORY` using Bash `mv`.
    - Confirm the file exists at its new location using Glob.

10. **Commit and Create Pull Request**
    - Only if the reviewer verdict is APPROVED.
    - Stage all changes: `git add -A`.
    - Commit with a brief message: `git commit -m "feat(<feature-id>): <short description of what was built>"` (e.g., `feat(E001-F003): add user profile page`).
    - Push the feature branch to the remote: `git push -u origin <branch-name>`.
    - Create a pull request into `BASE_BRANCH` (the branch recorded in step 2) using the GitHub CLI:
        ```
        gh pr create --base <BASE_BRANCH> --title "<feature-id>: <feature name>" --body "## Feature\n<feature name>\n\n## Feature ID\n<E###-F###>\n\n## Summary\n<brief description of what was built>\n\n## Files Changed\n<list of files>"
        ```
    - Record the PR URL from the output.

11. **Report**
    - Provide a summary of what was built.

## Prompt Template for Coder Agent

When deploying a coder agent, include this context in the prompt:

```
You are building part of a feature for a Laravel 12 / Inertia v2 / React 19 / Pest 4 project.

## Your Task
<task name and description from the plan>

## Actions
<specific actions listed under this task in the plan>

## Context
- **Feature Plan**: <path to the feature plan in specs/features/in-progress/>
- **Feature**: <feature name>
- **Objective**: <from the plan>

## Relevant Files
<relevant files from the plan that apply to this task>

## Instructions
- Complete ALL actions listed above.
- Read sibling files before writing to match existing patterns.
- PHP: Thin controllers, Form Requests for validation (array-syntax rules), `casts()` method on models, explicit return types, `route()` helper for named routes.
- React: `<Form {...Controller.action.form()}>` for forms, Wayfinder imports from `@/actions/` and `@/routes/`, `kebab-case.tsx` files, existing `@/components/ui/` components.
- Tests: Pest 4, flat `test()` functions, `User::factory()->create()`, named routes in assertions.
- Browser tests: `tests/Browser/` for core user flows only. Smoke test all new pages with `assertNoSmoke()`. One dark mode spot check per feature. Desktop/Chrome only. Use `@data-test` selectors.
- Run `php artisan test --compact` for relevant tests and `npm run types` for frontend changes.
- Formatting (Pint, ESLint, Prettier) runs automatically via Stop hook — do not run formatters.
- When done, provide a summary of what you built and what files you changed.
```

## Prompt Template for Reviewer Agent

```
You are reviewing code for a Laravel 12 / Inertia v2 / React 19 / Pest 4 project.

## Feature
- **Name**: <feature name>
- **Feature ID**: <E###-F###>
- **Plan**: <path to feature plan in specs/features/in-progress/>

## Files Changed
<list every file created or modified during the build, one per line>
- <file1>
- <file2>

## Acceptance Criteria
<paste all acceptance criteria from the plan>

## Validation Commands
<paste all validation commands from the plan>

## Instructions
- Read the feature plan to understand what was supposed to be built.
- Read every file listed above.
- Run every validation command and record the results.
- Check every acceptance criterion.
- Review for: correctness, proper Laravel/React patterns (Form Requests, Wayfinder, Inertia v2 forms, Pest tests), security, quality, codebase fit.
- Formatting is handled by the coder's Stop hook — do not check formatting.
- Group findings into Must Fix and Recommended.
- Return your verdict: APPROVED or CHANGES_REQUIRED.
```

## Prompt Template for Reviewer Fix Round

When redeploying a coder to address must-fix items from a review:

```
You are fixing issues found during code review for a Laravel 12 / Inertia v2 / React 19 / Pest 4 project.

## Review Findings — Must Fix
<paste the numbered must-fix items from the reviewer's report>

## Context
- **Feature Plan**: <path to the feature plan in specs/features/in-progress/>
- **Feature**: <feature name>

## Instructions
- Fix every must-fix item listed above. The reviewer provided file paths and descriptions of what's wrong.
- Do NOT address "Recommended" items — only fix what's in the Must Fix list.
- Run `php artisan test --compact` for affected tests and `npm run types` for frontend changes.
- Formatting (Pint, ESLint, Prettier) runs automatically via Stop hook — do not run formatters.
- Report what you fixed and what files you changed.
```

## Report

After building and validating (or failing), provide this summary:

```
Feature build complete.

Feature: <feature name>
Feature ID: <E###-F###>
Epic depends on: <epic dependencies or "None">
Feature depends on: <feature dependencies or "None">
Plan: <path to feature plan>
Branch: <feature branch name>
Base Branch: <base branch name>
PR: <pull request URL or "N/A if FAIL">
Status: <PASS or FAIL>

Tasks:
- <task 1 name>: <completed or failed>
- <task 2 name>: <completed or failed>
- ...

Review: <APPROVED or CHANGES_REQUIRED>
- Review rounds: <number of review cycles>
<If APPROVED: "Review passed. All acceptance criteria met. Feature file moved to specs/features/done/.">
<If CHANGES_REQUIRED after max retries: list unresolved must-fix items. Feature file remains in specs/features/in-progress/.>
<If there were Recommended items: list them briefly>

Files Changed:
<list of files created or modified during the build>
```

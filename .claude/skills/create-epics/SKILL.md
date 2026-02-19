---
name: create-epics
description: Reads a product requirements document, extracts features, groups them into dependency-ordered epics (max 7 features each), and writes each epic to its own file.
disable-model-invocation: true
argument-hint: [path to requirements document]
model: opus
allowed-tools: Read, Write, Edit, Glob, Grep
---

# Create Epics

Read a product requirements document, extract features, group them into epics of related functionality, determine dependencies between epics, and write each epic to its own file in `OUTPUT_DIRECTORY`.

## Variables

REQUIREMENTS_DOC: $1
OUTPUT_DIRECTORY: `specs/epics/`
EPICS_DIRECTORY: `specs/epics/`
EPICS_DONE_DIRECTORY: `specs/epics/done/`

## Instructions

- If no `REQUIREMENTS_DOC` is provided, stop and ask the user to provide the path to a requirements document.
- Check if `OUTPUT_DIRECTORY` exists using Glob. If it does not exist yet, that's fine — the Write tool will create it automatically when saving output files. Do not fail because the directory is missing.
- To determine the next epic number, look at existing files in BOTH `EPICS_DIRECTORY` and `EPICS_DONE_DIRECTORY`. Files follow the pattern `E###-<name>.md` (e.g., `E001-user-auth.md`, `E002-dashboard.md`). Find the highest `E###` number across both directories. If no files exist in either, start at `E001`. Otherwise, increment from the highest existing number.
- Read the requirements document at the path provided in `REQUIREMENTS_DOC`.
- Analyze the document to identify all distinct features described or implied by the requirements.
- **Break features down into small, focused units.** Each feature should do ONE thing. Do NOT bundle multiple behaviors into a single feature. If a requirement describes several things happening, split them into separate features. Create as many features as needed to fully cover every aspect of the requirements document. It is better to have too many small features than too few large ones.
- For each feature, write a plain-language description. Do NOT be technical. Write as if explaining to someone who doesn't know how software is built.
- Each feature should have exactly two parts:
    - **What it does**: A clear description of what this feature does from a user or product perspective.
    - **Expected outcome**: What should happen when this feature is working. What the user sees, experiences, or what state changes.
- Do NOT include implementation details, technical architecture, code references, database schemas, API endpoints, or anything about how the feature is built.
- Do NOT include team orchestration, task assignments, or build steps. This is strictly a feature list.
- **Group related features into epics.** Each epic is a cohesive group of features that belong together (e.g., "User Authentication", "Payment Processing", "Dashboard"). An epic should represent a logical area of the product.
- **Each epic may have at most 7 features.** If a group of related features has more than 7, split it into multiple epics. Give each sub-epic a clear, distinct name (e.g., "User Authentication - Account Setup" and "User Authentication - Session Management").
- **Determine dependencies between epics.** Figure out which epics depend on other epics being completed first. For example, "Payment Processing" might depend on "User Authentication" because you need logged-in users before they can pay. Order the epics so that dependencies come first. Epics with no dependencies are listed first.
- **Write each epic to its own file.** Each epic gets its own `E###-<descriptive-kebab-case-name>.md` file. Number them sequentially starting from the next available number. Since epics are ordered by dependency, lower-numbered epics are built first.
- In each epic file, include a `**Depends on**` line listing the epic file(s) it depends on. If the epic has no dependencies, write `**Depends on**: None`.

## Workflow

1. **Setup** - Check if `OUTPUT_DIRECTORY` exists. List existing files in `EPICS_DIRECTORY` and `EPICS_DONE_DIRECTORY` to determine the next `E###` number.
2. **Read** - Read the requirements document at the path in `REQUIREMENTS_DOC`.
3. **Identify** - Go through the document and identify every distinct feature, whether it's explicitly stated or implied by the requirements. Break large behaviors into small, focused features.
4. **Describe** - For each feature, write a non-technical description of what it does and what the expected outcome is.
5. **Group** - Group related features into epics. Each epic should be a cohesive unit of related functionality. If any group has more than 7 features, split it into smaller epics.
6. **Dependency analysis** - Determine which epics depend on other epics. Order the epics so that dependencies come first (epics with no dependencies get the lowest numbers).
7. **Save** - Write each epic to its own file: `OUTPUT_DIRECTORY/E###-<descriptive-name>.md`. Number them sequentially in dependency order. After writing each file, verify it exists using Glob.
8. **Validate files** - Re-read every epic file you just created using Read. Verify each file contains:
    - A `# Features` heading
    - A `**Depends on**` line
    - At least one numbered feature section (`## 1. ...`, `## 2. ...`, etc.)
    - Each feature section has both `**What it does**` and `**Expected outcome**`
    - No more than 7 feature sections per file
    - If any file is missing required content, fix it using Edit before continuing.
9. **Validate coverage** - Re-read the original requirements document. Go through it line by line and compare it against all the epic files. Check that every requirement, behavior, and expectation in the original document is accounted for by at least one feature across the epics. If anything is missing, add the missing features to the appropriate epic file (or create a new epic if needed). If a feature only partially covers a requirement, split or expand it. Do not move on until every part of the requirements document is covered.
10. **Report** - Provide a summary of all epics created, including the dependency order and validation result.

## Output Format

Follow this EXACT format for each epic file. Replace `<requested content>` placeholders with actual content.

- IMPORTANT: Replace `<requested content>` with the requested content. It's been templated for you to replace.
- IMPORTANT: Anything that's NOT in `<requested content>` should be written EXACTLY as it appears in the format below.

```md
# Features

<one sentence describing what this epic covers>

**Depends on**: <comma-separated list of epic filenames this depends on, or "None">

## 1. <Feature Name>

**What it does**: <plain-language description of what this feature does>
**Expected outcome**: <what should happen when this feature works correctly>

## 2. <Feature Name>

**What it does**: <plain-language description>
**Expected outcome**: <what should happen>

## 3. <Feature Name>

...
```

## Report

After saving and validating all epic files, provide this summary:

```
Epics created.

Epics (in dependency order):
1. E###-<name>.md (<count> features) — Depends on: <dependencies or "None">
2. E###-<name>.md (<count> features) — Depends on: <dependencies or "None">
3. ...

Total features: <total count across all epics>

Validation: <PASS or FAIL>
<If PASS: "All requirements from the source document are covered.">
<If FAIL: list any requirements that could not be mapped to a feature>
```

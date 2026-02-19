---
description: Split a large epic file into multiple smaller, dependency-ordered epics (max 7 features each) and rename existing feature plans to match
argument-hint: <path to large epic file>
allowed-tools: Read, Write, Edit, Glob, Grep, Bash(mv:*), Bash(mkdir:*), Bash(rm:*)
---

# Split Large Epic into Multiple Epics

Restructure a large epic file into multiple smaller, dependency-ordered epics and rename any existing feature plan files to match the new numbering.

## Variables

LARGE_EPIC_FILE: $1
EPICS_DIRECTORY: `specs/epics/`
EPICS_DONE_DIRECTORY: `specs/epics/done/`
FEATURES_DIRECTORY: `specs/features/`
FEATURES_IN_PROGRESS_DIRECTORY: `specs/features/in-progress/`
FEATURES_DONE_DIRECTORY: `specs/features/done/`

## Instructions

- If no `LARGE_EPIC_FILE` is provided, stop and ask the user to provide the path to the large epic file.

## Workflow

### 1. Read the Large Epic

- Read the file at `LARGE_EPIC_FILE`.
- Extract the epic number from the filename (e.g., `E001` from `E001-big-epic.md`). This is the OLD epic number.
- Parse every numbered feature. Each feature is a `## N. Feature Name` section with `**What it does**` and `**Expected outcome**` fields.
- Build a complete list of all features with their original number and content.

### 2. Scan for Existing Feature Plans

- Search for all files in `FEATURES_DIRECTORY`, `FEATURES_IN_PROGRESS_DIRECTORY`, and `FEATURES_DONE_DIRECTORY` that start with the old epic number (e.g., `E001-F*.md`).
- For each file found, read the top of the file to extract the feature name and original feature ID (e.g., `E001-F003`).
- Build a map of: old feature ID → file path, directory it's in, and feature name.

### 3. Determine Next Epic Number

- List existing files in BOTH `EPICS_DIRECTORY` and `EPICS_DONE_DIRECTORY` matching `E###-*.md`.
- Find the highest `E###` number across both directories.
- The new epics will start numbering from the next available number after this AND after the old epic number.

### 4. Group Features into Epics

- Analyze all features and group related ones together. Each group becomes an epic.
- Each epic should be a cohesive unit — features that belong to the same area of the product (e.g., "User Authentication", "Payment Processing", "Dashboard").
- **Each epic may have at most 7 features.** If a group of related features has more than 7, split it into multiple epics with distinct names.
- Keep each feature's content (What it does, Expected outcome) exactly as it was in the original file. Do NOT rewrite feature descriptions.

### 5. Determine Dependencies Between Epics

- Figure out which epics depend on other epics being completed first.
- Order the epics so dependencies come first (lower numbers).
- Epics with no dependencies get the lowest numbers.

### 6. Build the Mapping

- For each feature, record:
    - **Old ID**: The original feature ID (e.g., `E001-F003`)
    - **New ID**: The new feature ID based on its new epic and position (e.g., `E004-F002`)
    - **New epic file**: Which new epic file it belongs to
- Print this full mapping before making any changes so it can be reviewed.

### 7. Write New Epic Files

- For each epic group, write a file to `EPICS_DIRECTORY/E###-<descriptive-name>.md` using this exact format:

```md
# Features

<one sentence describing what this epic covers>

**Depends on**: <comma-separated list of epic filenames this depends on, or "None">

## 1. <Feature Name>

**What it does**: <original description, unchanged>
**Expected outcome**: <original description, unchanged>

## 2. <Feature Name>

**What it does**: <original description, unchanged>
**Expected outcome**: <original description, unchanged>
```

- After writing each file, verify it exists using Glob.

### 8. Rename Existing Feature Plan Files

- For each existing feature plan file found in step 2:
    - Determine its new feature ID from the mapping in step 6.
    - Compute the new filename: replace the old `E###-F###` prefix with the new one, keep the rest of the descriptive name.
    - Rename the file using `mv` (keep it in the same directory it was found in).
    - After renaming, read the file and update the metadata fields at the top:
        - Update `**Epic**:` to the new epic filename
        - Update `**Feature**:` to the new feature ID
        - Update `**Epic depends on**:` to match the new epic's dependencies
        - Update `**Feature depends on**:` — translate any old feature IDs to their new IDs using the mapping
    - Verify the renamed file exists using Glob.

### 9. Delete the Old Epic File

- After all new epics are written and all feature plans are renamed, delete the original large epic file using `rm`.

### 10. Validate

- Re-read every new epic file. Verify each contains:
    - `# Features` heading
    - `**Depends on**` line
    - Numbered feature sections with `**What it does**` and `**Expected outcome**`
    - No more than 7 features per file
- Count the total features across all new epics. Verify it matches the total from the original large epic. If any features are missing, find and add them.
- List all renamed feature plan files. Verify each has updated metadata matching its new epic and feature ID.

### 11. Report

Provide this summary:

```
Epic split complete.

Original: <old epic filename> (<total features> features)

New epics (in dependency order):
1. E###-<name>.md (<count> features) — Depends on: <deps or "None">
2. E###-<name>.md (<count> features) — Depends on: <deps or "None">
3. ...

Total features: <count> (should match original)

Feature plans renamed: <count>
Mapping:
- <old ID> → <new ID> (<filename>)
- <old ID> → <new ID> (<filename>)
- ...

Features without plans: <count>

Validation: <PASS or FAIL>
```

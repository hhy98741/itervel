# Features

AI-powered outline generation and editing, script writing, script critique and refinement, script review, and YouTube metadata generation.

**Depends on**: E009-ai-configuration-and-title-generation.md

## 1. Outline Generation

**What it does**: Uses AI to create a structured video outline from the selected title and logline, with sections, timing estimates, and key points.
**Expected outcome**: The user sees an outline containing a hook (30 seconds), 3-5 main sections, and a closing. Each section has a title, estimated duration, bullet-point key points, and engagement/build-up notes.

## 2. Outline Editing

**What it does**: Lets users edit the AI-generated outline before proceeding to script writing.
**Expected outcome**: The user can edit section titles and content inline, reorder sections via drag-and-drop, add or remove sections, and adjust timing. They can approve the outline or request it be regenerated.

## 3. Script Writing

**What it does**: Uses AI to generate a full conversational video script based on the approved outline.
**Expected outcome**: A 2,000-2,500 word script is generated that follows the outline structure, matches the brand voice (if a brand guide is provided), includes delivery cues like [PAUSE] and [EMPHASIS], and targets the configured speaking pace.

## 4. Script Critique and Refinement

**What it does**: The AI automatically critiques the script from an audience perspective and refines it through multiple rounds.
**Expected outcome**: For each critique round, the AI identifies weak points (where viewers would click away), rates issues by impact, and suggests specific fixes. The AI then revises the script based on the critique. The user sees progress updates ("Refining script... Round 2 of 3"). This repeats for the configured number of rounds (0-5), maxing out at 5.

## 5. Script Review and Editing

**What it does**: Presents the final refined script to the user for review and optional manual editing.
**Expected outcome**: The user sees the full script in an editable text field with word count, estimated duration, delivery cues, and a summary of how many critique rounds it went through. The user can edit the script directly before approving it.

## 6. YouTube Metadata Generation

**What it does**: Automatically generates a YouTube-optimized title, description, and tags based on the video content.
**Expected outcome**: The system produces a 150-300 word SEO-optimized description, 10-15 relevant tags, and optional chapter timestamps derived from the outline. All fields are editable by the user and have one-click copy buttons.

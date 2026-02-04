# Product Requirements Document Brief: AI-Powered Faceless Video Creation Platform

## Executive Summary

Create a detailed Product Requirements Document (PRD) for a web application that automates the creation of high-quality, faceless YouTube videos for long-form educational content creators (motivation, finance, etc.). The application implements a proven, iterative AI workflow that emphasizes **quality over speed** through multi-round critique and refinement cycles.

**Key Differentiators:**

1. **Iterative script quality workflow** - AI generates, critiques, and refines scripts through multiple rounds (user-configurable)
2. **Research-driven content** - Analyzes reference materials (URLs to articles/videos) to create well-researched scripts
3. **Thumbnail iteration process** - Generates thumbnail concepts, critiques them, refines top 3 with AI feedback
4. **Complete output package** - Script, voiceover, video assembly, thumbnail, title, and metadata in one workflow

**Phased Approach:**

- **Phase 1 (MVP)**: Core workflow for script-to-video with basic automation (12-week timeline)
- **Phase 2 (Future)**: Advanced features, voice cloning, manual editing, subscriptions (post-validation)

This PRD should guide development of Phase 1 MVP while documenting Phase 2 as a future roadmap.

---

## Product Vision & Market Position

### Problem Statement

Current AI video tools (Pictory.ai, InVideo.io) generate videos quickly but sacrifice quality:

- Single-pass AI script generation produces mediocre content
- No critique/refinement loops to improve quality
- Generic templates that all look the same
- Limited research integration
- Poor thumbnail generation

Content creators need a tool that produces **videos that perform well on YouTube**, not just videos that exist.

### Solution

A web application that implements a **quality-first iterative workflow**:

1. Research phase: Analyze reference materials (up to 3 URLs)
2. Script generation with configurable critique rounds (0 to N iterations)
3. Thumbnail concept generation with AI critique and refinement
4. Automated video assembly with user's voice OR AI voiceover
5. Complete metadata package (title, description, tags)

### Target Users

**Primary:** Long-form faceless YouTube channel operators

- Content niches: Personal finance, motivation, productivity, education
- Video length: 10-15 minutes typically
- Upload frequency: 1-4 videos per week
- Technical skill: Basic (can upload files, fill forms)
- Pain point: Creating quality scripts and thumbnails is time-consuming

**User Persona:**

- Age: 25-45
- Goal: Build a faceless YouTube channel while working full-time
- Current process: Manually writing scripts, hiring freelancers for thumbnails, using basic editing tools
- Willingness to pay: $3-5 per high-quality video vs. $20-50/month for mediocre subscription tools

---

## Core Methodology (Extracted from User's Proven Workflow)

The user has developed a sophisticated 14-prompt workflow for creating YouTube videos using Claude AI. The application should **codify this methodology** while making it **generalizable** for any user (not just the original creator's voice/brand).

### Workflow Overview

The user has developed a sophisticated workflow for creating YouTube videos using AI. The application **implements this methodology** with optimizations for user experience and parallel processing.

**Core Methodology (What Gets Built):**

**Phase A: Content Creation (Sequential with User Input)**

1. **Title & Logline** - Generate 5 options ranked by AI, user selects 1
2. **Outline** - AI creates structure, user reviews and can edit sections/timing
3. **Script** - AI writes from outline, critiques it (0-5 configurable rounds), user reviews final version
4. **Voiceover** - User picks AI voice from ElevenLabs library or uploads their own recording
5. **Thumbnails** - Generate 3 options (happens in parallel during steps 2-3), user selects 1
6. **Music** - User picks from 20 curated tracks or uploads their own
7. **Video** - Shotstack API renders and delivers final MP4

**Phase B: Behind-the-Scenes Automation**

- **Research Integration** - Extracts key concepts from up to 3 reference URLs (articles or YouTube videos)
- **Parallel Processing** - Thumbnails generate while outline/script being created
- **Footage Planning** - AI finds stock clips from Pexels/Pixabay matching script segments
- **Metadata Generation** - Creates YouTube description, tags, timestamps from final script
- **SFX Planning** - Identifies 4-8 sound effect placement points (optional in Phase 1)

**Key Differentiators:**

- **Iterative Quality:** Script goes through configurable critique rounds (user chooses 0-5)
- **User Control:** Can review and edit outline, script, and all asset selections
- **Cost Transparency:** Tracks every API call with detailed cost breakdown shown to user
- **Flexible AI Models:** Users can choose Claude Opus/Sonnet or GPT-4 for each generation step
- **Multi-Channel Support:** Projects feature allows separate branding for different YouTube channels

**Detailed specifications for each step are in Section 3: Script Generation Workflow.**

### Key Quality Principles (from user's guidelines)

**Content Transformation (CRITICAL):**

- Source material is for IDEAS, not copying
- "Read and close" approach: Extract concepts, then write from understanding
- Create NEW examples and scenarios (not article's examples)
- Add unique perspective/angle (not just summarizing source)
- Copyright compliance: Max 15 words per quote, one quote per source max

**Iterative Refinement:**

- Script goes through critique → fix → critique loop until ready
- Configurable iteration count (user chooses: 0, 1, 2, 3+ rounds)
- Each critique is from "target audience" perspective (not expert reviewer)
- Focus on retention: Where would viewers drop off? Why?

**Engagement Architecture:**

- Build-up/release cycles throughout (create tension, provide payoff)
- Pattern interrupts every 10-12 seconds (change footage/add text/show data)
- Hook delivers on title promise within 15 seconds
- No lengthy conclusions (end with "next video hook" to drive series viewing)

**Generalization Strategy:**
The user's prompts contain highly specific voice/tone for their channel ("Better Wealth Builder" - personal finance/FIRE content). The application must:

1. Extract the METHODOLOGY (critique cycles, research integration, engagement techniques)
2. Make voice/tone PARAMETERIZABLE via user-provided "Brand Guide" (optional upload)
3. Provide sensible defaults if no brand guide provided
4. Allow users to define their own: target audience, tone, content pillars, speaking pace

---

## Phase 1: MVP Feature Set

### 1. User Authentication & Account Management

**Requirements:**

- User registration with email/password
- Email verification required
- Password reset flow
- User profile with preferences
- No guest access (accounts required)

**User Preferences:**

- Brand voice/tone (optional text field or file upload for brand guide)
- Target audience description (age, interests, pain points)
- Default video length (10-15 minutes)
- Default speaking pace (165-170 wpm)

### 1a. Project Management (Multi-Channel Support)

**Purpose:** Users can create separate projects for different YouTube channels, each with its own branding and settings.

**Project Features:**

- **Create Project:** User provides project name (e.g., "Finance Channel", "Motivation Channel")
- **Project Settings:**
    - Project-specific brand guide (voice/tone for this channel)
    - Target audience for this channel
    - Default thumbnail style preferences
    - Music preferences
    - Speaking pace
- **Project Library:** Each project has its own video history
- **Easy Switching:** Dropdown to switch between projects
- **Default Project:** User can set one project as default (auto-selected on login)

**Use Case:**
User runs 2 YouTube channels:

- "Wealth Builder Pro" (serious finance content)
- "Money Motivation Daily" (inspirational short-form content)

They create 2 projects:

- Project A: Serious tone, professional thumbnails, data-driven scripts
- Project B: Energetic tone, bold thumbnails, story-driven scripts

When creating a video, they select which project → system applies that project's brand settings.

**Database Schema Addition:**
**Projects Table:**

- id, user_id, name, settings_json (brand guide, preferences), is_default (boolean), created_at, updated_at

**Videos Table Update:**

- Add project_id foreign key (each video belongs to a project)

**UI Changes:**

- Dashboard: Show projects as folders/tabs
- Project selector in top navigation
- "New Video" button creates video in currently selected project
- Project settings page (edit brand guide, preferences)

### 2. Project Creation & Input Collection

**User Flow:**

1. User clicks "Create New Video"
2. Enters project name (for organization)
3. Selects which project to create video in (if multiple projects exist)
4. Provides primary input: Topic/prompt (required, 100-500 chars)
5. Optional: Upload brand guide document (.txt, .md - for voice/tone context)
6. Optional: Add up to 3 reference URLs (articles or YouTube videos)
7. **Configure AI Models (Optional - Advanced Settings):**
    - Title generation: Claude Opus / Claude Sonnet (default) / GPT-4
    - Outline generation: Claude Opus (default) / Claude Sonnet / GPT-4
    - Script writing: Claude Sonnet (default) / Claude Opus / GPT-4
    - Script critique: Claude Sonnet (default) / Claude Opus / GPT-4
    - Thumbnail concepts: Claude Sonnet (default) / Claude Opus / GPT-4
    - Thumbnail images: Gemini Imagen 3 (default) / DALL-E 3 / Midjourney (future)
    - Metadata generation: Claude Sonnet (default) / Claude Opus / GPT-4
8. Optional: Select iteration preferences (script critique: 0-5 rounds, thumbnail critique: 1-2 rounds)
9. Submits to start generation workflow

**AI Model Selection - Technical Details:**

**Purpose:** Give users control over quality vs. cost tradeoff

- **Opus:** Highest quality, most expensive (~5x Sonnet cost) - use for critical steps
- **Sonnet:** Great balance of quality and cost - recommended default
- **GPT-4:** Alternative option, similar cost to Sonnet

**Recommended Configurations:**

_Budget Mode (all Sonnet):_

- All steps use Claude Sonnet
- Estimated cost: ~$0.95-1.35 per video
- Quality: Very good

_Balanced Mode (Opus for outline, Sonnet for everything else):_

- Outline: Claude Opus (ensures strong structure)
- Everything else: Claude Sonnet
- Estimated cost: ~$1.10-1.60 per video
- Quality: Excellent

_Premium Mode (Opus for key steps):_

- Title: Claude Opus
- Outline: Claude Opus
- Script writing: Claude Opus
- Script critique: Claude Sonnet (more critiques = higher cost)
- Thumbnails: Claude Sonnet
- Metadata: Claude Sonnet
- Estimated cost: ~$1.50-2.20 per video
- Quality: Best possible

**User Interface:**

- Simple mode: "Use recommended settings" (all Sonnet)
- Advanced mode: Dropdown for each step showing:
    - Model name
    - Estimated cost for that step
    - Quality rating (Good/Great/Excellent)
    - Speed (Fast/Medium/Slow)

**Example UI:**

```
AI Model Configuration (Optional)

[ ] Use recommended settings (Claude Sonnet for all steps)
[x] Custom configuration

Title Generation:
  [Dropdown: Claude Opus ▼]
  Cost: ~$0.03 | Quality: Excellent | Speed: Medium

Outline Generation:
  [Dropdown: Claude Opus ▼]
  Cost: ~$0.05 | Quality: Excellent | Speed: Medium

Script Writing:
  [Dropdown: Claude Sonnet ▼]
  Cost: ~$0.05 | Quality: Great | Speed: Fast

Script Critique (per round):
  [Dropdown: Claude Sonnet ▼]
  Cost: ~$0.02/round | Quality: Great | Speed: Fast

Thumbnail Concepts:
  [Dropdown: Claude Sonnet ▼]
  Cost: ~$0.02 | Quality: Great | Speed: Fast

Thumbnail Images:
  [Dropdown: Gemini Imagen 3 ▼]
  Cost: ~$0.15 (3 images) | Quality: Excellent | Speed: Fast

Metadata Generation:
  [Dropdown: Claude Sonnet ▼]
  Cost: ~$0.01 | Quality: Great | Speed: Fast

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Estimated Total AI Cost: $1.15 - $1.60
(Plus $0.50-0.75 for video rendering)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

**Storage in Database:**

```sql
-- Videos table includes:
ai_models_json: {
  "title_generation": "claude-opus-4-5",
  "outline_generation": "claude-opus-4-5",
  "script_writing": "claude-sonnet-4-5",
  "script_critique": "claude-sonnet-4-5",
  "thumbnail_concepts": "claude-sonnet-4-5",
  "thumbnail_images": "gemini-imagen-3",
  "metadata": "claude-sonnet-4-5"
}
```

**API Integration Layer:**

The system needs a model router that can send requests to different providers:

```php
// Pseudocode
class AIModelRouter {
  public function generate($step, $prompt, $model) {
    switch($model) {
      case 'claude-opus-4-5':
        return $this->callAnthropic($prompt, 'claude-opus-4-5-20251101');
      case 'claude-sonnet-4-5':
        return $this->callAnthropic($prompt, 'claude-sonnet-4-5-20250929');
      case 'gpt-4':
        return $this->callOpenAI($prompt, 'gpt-4-turbo');
      case 'gemini-imagen-3':
        return $this->callNanoBananaPro($prompt);
      case 'dall-e-3':
        return $this->callOpenAI($prompt, 'dall-e-3');
    }
  }
}
```

**Cost Tracking (see Section on Database Schema for full details):**

Every API call is logged with:

- Model used
- Tokens consumed (input + output)
- Actual cost in USD
- Timestamp
- Associated video_id

This enables:

1. Per-video cost breakdown
2. Analytics on which models are most cost-effective
3. Accurate pricing adjustments based on real usage data
4. User transparency (show actual costs vs. charged amount)

**Reference URL Processing:**

- **Articles:** Use web scraping (Beautiful Soup, Readability.js) to extract main content
- **YouTube videos:** Use youtube-transcript-api (Python) to fetch transcript
- **Processing:** Extract key concepts (bullet list), NOT full text copying
- **Storage:** Save extracted concepts with attribution metadata

**Implementation Notes:**

- Use rule-based scraping for URLs (not AI - cost savings)
- Validate URLs are accessible before accepting
- Set timeout limits (30 sec per URL fetch)
- Store extracted text in database for re-use if same URL submitted again

### 3. Script Generation Workflow

**WORKFLOW ORDER (User-Specified):**

The workflow has **user-facing steps** (where user makes decisions) and **internal AI steps** (happen automatically):

**USER-FACING STEPS (in order):**

1. **Title & Logline Selection** - User chooses from 5 AI-generated options
2. **Outline Review & Approval** - User reviews outline structure, can edit before script generation
3. **Script Review & Approval** - User reviews final script after AI iterations, can edit
4. **Voiceover Selection** - User picks AI voice OR uploads their own
5. **Thumbnail Selection** - User chooses from 3 AI-generated options
6. **Music Selection** - User picks from 20 tracks OR uploads their own
7. **Final Review & Generate** - User confirms all choices, video renders

**INTERNAL AI WORKFLOW (behind the scenes):**

**Phase A: Content Generation (Sequential - user sees each step)**

1. Generate 5 titles with loglines → **User selects 1**
2. Generate outline based on selected title → **User reviews and can edit**
3. Generate script from approved outline
4. Critique script (N rounds, user-configured)
5. Present final script → **User reviews and can edit**

**Phase B: Asset Generation (PARALLEL - after title selection)**

These can start immediately after user selects title (run alongside outline/script generation):

- **Track 1: Thumbnail Generation**
    - Generate 5 thumbnail concepts
    - AI critiques and selects top 3
    - Generate 3 thumbnail images (Gemini Imagen 3)
    - Present to user for selection

- **Track 2: Metadata Planning** _(draft only, finalized later)_
    - Draft description structure
    - Extract potential tags
    - Prepare timestamp suggestions
    - _Final metadata waits for script completion_

**Phase C: Production Preparation (PARALLEL - after script + voiceover ready)**

After user approves script and selects voiceover, these happen in parallel:

- **Track 1: Footage Planning**
    - Analyze script segments
    - Extract keywords for each segment
    - Search Pexels/Pixabay
    - Prepare footage list (URLs, durations)
    - _Actual downloading happens at render time_

- **Track 2: Music Processing**
    - User selection or upload
    - Normalize audio levels
    - Prepare for mixing

- **Track 3: Metadata Finalization**
    - Complete description using final script
    - Finalize tags based on content
    - Generate timestamps

- **Track 4: Sound Effects (SFX) Planning** _(optional, Phase 1 basic version)_
    - Identify 4-8 SFX placement points in script
    - Prepare SFX references
    - _Actual SFX integration at render time_

**Phase D: Video Rendering (Final step)**

- Combine all assets
- Send to Shotstack API
- Wait for completion (2-5 min)
- Deliver to user

**USER EXPERIENCE PERSPECTIVE:**

From the user's point of view, the workflow feels like:

```
1. Enter topic + reference URLs
2. Choose title (from 5 options)
   ↓
   [AI working on multiple things in parallel...]
   ↓
3. Review script (can edit if needed)
4. Choose thumbnail (from 3 options)
5. Choose voiceover (AI voice picker or upload)
6. Choose music (from 20 tracks or upload)
   ↓
7. Click "Generate Video"
   ↓
   [Wait 5-10 minutes while video renders]
   ↓
8. Download video + all assets
```

**What User DOESN'T See:**

- Outline generation (happens automatically between title and script)
- Multiple critique rounds (just shows "Refining script... Round 2 of 3")
- Footage search/preparation (happens automatically)
- Metadata drafting (happens in background)
- SFX planning (happens automatically)

**Technical Implementation Note:**

The system should show consolidated progress:

```
✓ Title selected
⏳ Generating content... (45% complete)
   - Script generation: In progress
   - Thumbnail concepts: Completed
   - Metadata draft: Completed
```

Not separate steps for outline, critique rounds, etc. Keep it simple for the user.

**Step 1: Title Generation**

- **Input:** User's topic + reference material concepts (if provided)
- **AI Task:** Generate 5 title options using proven frameworks:
    1. The Ultimate Guide to [X]
    2. Why [Surprising Fact] (Contrarian angle)
    3. The Only [X] You Need
    4. How I [Result] by [Method]
    5. [Number] [Things] That [Result]
- **Output:** 5 titles, each with:
    - Title text (6-7 words ideal)
    - Logline (1-sentence concept: target viewer + problem + unique insight + benefit)
    - Framework used
    - Character count
    - Why it works (brief explanation)
- **AI Ranking:** Automatically rank the 5 titles by predicted click-through potential:
    - Analyze each title against YouTube performance patterns
    - Consider: clarity, curiosity gap, specificity, audience relevance
    - Provide ranking with reasoning
- **User Action:** Review ranked titles + loglines, select 1 to proceed
- **API:** Use Claude Sonnet 4 via Anthropic API

**Why Loglines Matter:**
The logline serves as the "North Star" for all subsequent content creation. It ensures:

- Script stays focused on ONE core concept (not multiple tangents)
- Outline delivers on the promise made in the title
- Thumbnail and metadata align with video's unique insight
- Target viewer is clearly identified throughout

**Example Output:**

```
TITLE #1 (RANKED #1 - HIGHEST CLICK POTENTIAL)
Title: "The One Number That Buys Your Freedom"
Logline: "A 35-year-old professional discovers that retiring early isn't about earning more—it's about the 4% rule that lets you live off investments forever."
Framework: The Only [X] You Need
Character Count: 42
Why It Works: Creates strong curiosity gap ("what number?"), promises specific benefit (freedom), uses power word ("only")
Predicted CTR: 8-10% (High)

TITLE #2 (RANKED #2)
Title: "Why I Stopped Picking Stocks (And Got Rich)"
Logline: "An investor learns that beating the market isn't about finding winners—it's about buying everything through index funds and letting compound interest do the work."
Framework: How I [Result] by [Method]
Character Count: 45
Why It Works: Contrarian angle, personal story hook, specific result
Predicted CTR: 7-9% (High)
...
```

**Step 2: Outline Creation**

- **Input:** Selected title + logline + topic + reference concepts
- **AI Task:** Create structured outline (12-15 min video):
    - Hook (30 sec)
    - Main sections (3-5 sections, each 2-4 min)
    - Next video hook (45 sec)
    - Include estimated timing per section
    - Note where to include examples, stories, data
    - Plan 2-3 build-up/release cycles (engagement technique from user's methodology)
- **Output:** Markdown outline with sections and timing
- **User Action:** Review outline, can edit structure/sections/timing before proceeding
- **Purpose:** Ensures logical flow before investing in full script generation
- **Recommended Model:** Claude Opus (better at high-level structure and pacing)

**Why Outline Editing Matters:**

- Allows user to fix structural issues before script is written
- Cheaper to iterate on outline (shorter, less text) than full script
- User can adjust video length by editing section timing
- Ensures script generation follows user's preferred structure

**Example Outline Interface:**

```
OUTLINE EDITOR

Section 1: Hook (0:00 - 0:30)
• Open with surprising stat about stock picking failure
• Tease the "one number" that changes everything
• Promise: By end of video, you'll know exactly what to do

[Edit Section] [Delete Section] [Add Section Below]

Section 2: The Problem with Stock Picking (0:30 - 3:00)
• Why most investors pick individual stocks
• The data: 95% underperform index funds
• Personal story: My own stock picking disasters

[Edit Section] [Delete Section] [Add Section Below]

[+ Add New Section]

━━━━━━━━━━━━━━━━━━━━━━━━━━━
Total Estimated Length: 12:30
[← Back to Title] [Approve & Generate Script →]
```

**Step 3: Script Writing**

- **Input:** Approved outline (from Step 2) + brand guide (if provided) + reference concepts
- **AI Task:** Write 2,000-2,500 word conversational script
    - Follow outline structure exactly
    - Match user's brand voice (from guide) or use friendly/educational default
    - Include natural pauses: [PAUSE], [BEAT], [EMPHASIS] for delivery cues
    - Transform reference concepts into NEW examples (not copying source)
    - Target speaking pace: 165-170 wpm
- **Output:** Full script in markdown format
- **Copyright Safeguard:** AI prompt includes strict rules (15-word quote max, one quote per source)

**Step 4: Script Critique (Iterative)**

- **Configuration:** User chooses iteration count (0, 1, 2, 3+) at project creation
- **AI Task:** Critique from target audience perspective:
    - Where would viewers click away? (engagement gaps)
    - What's confusing or unclear? (clarity issues)
    - Does hook deliver on title within 15 sec? (promise check)
    - Pacing problems (too slow, too fast, dragging sections)
- **Output:** Critique report with prioritized fixes (High/Medium/Low)
- **If iterations = 0:** Skip to next step
- **If iterations ≥ 1:** Apply fixes, generate new critique, repeat N times

**Step 5: Script Finalization**

- **User Action:** Review final script (optional manual edits in text editor)
- **Output:** Approved script ready for production

**Technical Implementation:**

- Use **Claude Sonnet 4** for all script tasks (via Anthropic API)
- **NOT Claude Opus** (too expensive for iterative loops)
- Each critique round = 2 API calls (critique + apply fixes)
- Estimate cost: $0.05-0.10 per full script workflow
- Store all versions (outline v1, script v1, script v2) for user history

### 4. Thumbnail Generation Workflow

**TIMING:** Starts immediately after title selection (runs IN PARALLEL with script generation)

**Step 1: Thumbnail Concept Generation**

- **Input:** Selected title + logline (no script needed yet)
- **AI Task:** Generate 5 thumbnail concept descriptions:
    - Text overlay (3-6 words max)
    - Visual elements (person, object, background)
    - Color scheme
    - Emotion/expression (if person shown)
- **Output:** 5 detailed thumbnail descriptions (text, not images yet)
- **Runs Parallel:** While user waits for script to be written/critiqued

**Step 2: Thumbnail Critique & Selection**

- **AI Task:** Critique 5 concepts from "YouTube viewer scrolling feed" perspective:
    - Rank by click likelihood (which grabs attention?)
    - Select top 3 concepts
    - Provide feedback on why top 3 work and how to improve
- **Output:** Top 3 ranked concepts with improvement notes

**Step 3: Thumbnail Image Generation**

- **Input:** Top 3 refined concepts
- **AI Task:** Create image generation prompts optimized for Imagen 3
    - Incorporate feedback from critique
    - Optimize for YouTube specs (1280x720, 16:9)
    - Ensure text readability on mobile
    - Specify visual style (professional, eye-catching, brand-appropriate)
- **Image Generation:** Google Gemini Imagen 3 API (via Nano Banana Pro service)
    - Higher quality than DALL-E for photorealistic/professional images
    - Better text rendering in images
    - Cost-effective
- **Output:** 3 final thumbnail images (.PNG, 1280x720)
- **User Action:** Select 1 thumbnail to use

**Technical Implementation:**

- Use **Claude Sonnet 4** for concept generation and critique
- Use **Gemini Imagen 3 (via Nano Banana Pro)** for image generation
- Cost estimate: $0.10-0.20 per thumbnail workflow (3 images)
- Nano Banana Pro handles API complexity, provides clean interface

### 5. Voiceover Options

**Option A: AI-Generated Voiceover**

- **Input:** Final script text
- **Service:** ElevenLabs API (full voice library access)
- **User Selection:** Choose from available voices (preview samples)
- **Output:** MP3 audio file
- **Cost:** ~$0.30 per 10-minute video

**Option B: User-Uploaded Voiceover**

- **Input:** User records script themselves, uploads file
- **Accepted Formats:** MP3, WAV, M4A, AAC
- **Validation:** Check duration, file size (<50MB), format
- **Processing:** Normalize audio with FFmpeg (consistent volume, 44.1kHz, stereo)
- **Cost:** $0 (no TTS cost)

**Audio Duration Detection:**

- Use FFmpeg to extract exact duration
- This determines video length for footage selection

### 6. Video Assembly & Footage Matching

**TIMING:** Planning phase runs IN PARALLEL after script + voiceover ready. Actual assembly happens at final render.

**Phase 1: Footage Planning (Parallel Processing)**

**Step 1: Script Segmentation**

- **Input:** Final script + audio duration
- **Processing:** Split script into key segments/themes (3-5 segments)
    - Use simple keyword extraction (TF-IDF, not AI - cost savings)
    - Each segment gets keywords for footage search

**Step 2: Stock Footage Search & Preparation**

- **Sources:** Pexels API (free) + Pixabay API (free)
- **Method:** Search each segment's keywords
- **Selection:** AI determines clip distribution:
    - Total video duration ÷ number of segments = duration per segment
    - Find clips to fill each segment's duration
    - Aim for 3-6 seconds per clip (pattern interrupts every 10-12 sec)
    - **Store clip URLs and metadata** (don't download yet - happens at render)
- **Fallback:** If no results for specific keyword, use more general terms

**Step 3: Music Preparation (Parallel with Footage)**

- **Library:** 20 curated royalty-free tracks (varied moods: upbeat, calm, dramatic, inspiring)
- **User Selection:** Choose 1 track OR upload their own
- **Processing:**
    - Validate user upload (if applicable)
    - Prepare audio URL/file reference
    - Note: Volume mixing happens during Shotstack render (-15dB to -20dB below voiceover)

**Step 4: SFX Planning (Optional - Basic in Phase 1)**

- **Input:** Final script
- **AI Task:** Identify 4-8 strategic SFX placement points:
    - Section transitions (whoosh/swoosh)
    - Key point emphasis (ding/chime)
    - Visual element sync (pop/click when graphics appear)
    - Humor beats (comedic sounds at punchlines)
- **Output:** List of SFX with timing and sound type
- **Note:** Minimal SFX in Phase 1, expanded in Phase 2

**Phase 2: Video Rendering (Final Assembly)**

**Step 5: Shotstack Rendering**

- **Input:** All prepared assets:
    - Voiceover audio file (URL)
    - Footage clips list (URLs from Pexels/Pixabay)
    - Background music (URL)
    - Clip timing/duration calculated from script segments
    - SFX references (if applicable)
- **Process:**
    1. Build Shotstack JSON payload:
        ```json
        {
            "timeline": {
                "tracks": [
                    {
                        "clips": [
                            {
                                "asset": { "src": "clip1_url" },
                                "start": 0,
                                "length": 5
                            },
                            {
                                "asset": { "src": "clip2_url" },
                                "start": 5,
                                "length": 4
                            }
                        ]
                    },
                    {
                        "clips": [
                            {
                                "asset": {
                                    "src": "voiceover_url",
                                    "type": "audio"
                                }
                            }
                        ]
                    },
                    {
                        "clips": [
                            {
                                "asset": {
                                    "src": "music_url",
                                    "type": "audio"
                                },
                                "volume": 0.2
                            }
                        ]
                    }
                ]
            },
            "output": {
                "format": "mp4",
                "resolution": "1080"
            }
        }
        ```
    2. Send POST request to Shotstack API
    3. Receive render ID
    4. Poll for completion (or wait for webhook callback)
    5. Download rendered video to storage (S3/R2)
    6. Notify user video is ready

**Output Specs:**

- 1080p MP4
- H.264 codec (Shotstack default)
- 16:9 aspect ratio
- File size: ~100-200MB for 10-15 min video

**Estimated Render Time:** 2-5 minutes (Shotstack cloud rendering)

**Cost:** ~$0.50-0.75 per video (10-15 minutes at ~$0.05/min)

### 7. Metadata Generation

**TIMING:** Draft created IN PARALLEL during script generation, finalized after script is approved.

**Phase 1: Initial Draft (Parallel Processing)**

- **Input:** Selected title + logline + reference URLs (before script is finalized)
- **AI Task:** Generate preliminary metadata:
    - Draft description structure (150-300 words)
    - Potential tags based on topic (10-15 tags)
    - Prepare timestamp template
- **Storage:** Save draft, not shown to user yet

**Phase 2: Finalization (After Script Approved)**

- **Input:** Final script + title + draft metadata
- **AI Task:** Complete and refine metadata:

**Automated Outputs:**

1. **Title:**
    - User-selected title from generation step (already chosen)

2. **Description:**
    - AI-generated YouTube description (150-300 words)
    - Structure:
        - Video summary (2-3 sentences capturing main insight)
        - Key takeaways (3-5 bullet points)
        - Timestamps/chapters (optional - if user wants chaptering)
        - Call-to-action (subscribe, watch next video, etc.)
    - Uses final script content for accuracy
    - Includes relevant keywords naturally (for YouTube SEO)

3. **Tags:**
    - AI-generated 10-15 relevant tags
    - Mix of:
        - Broad tags (e.g., "personal finance", "investing")
        - Specific tags (e.g., "index funds", "4% rule")
        - Long-tail tags (e.g., "how to retire early with index funds")
    - Based on final script content, not just topic

4. **Timestamps (Optional):**
    - If user wants chapters, AI generates from script sections:
        ```
        0:00 - Introduction
        0:45 - The Problem with Stock Picking
        3:20 - Why Index Funds Work
        7:15 - How to Get Started
        11:30 - Next Steps
        ```

**User Review:**

- All metadata shown together in review screen
- Editable text fields for title, description, tags
- Preview how it appears in YouTube UI
- User can copy-paste or modify before finalizing

### 8. Output Delivery

**Final Package:**

1. Video file (1080p MP4, downloadable)
2. Thumbnail image (1280x720 PNG, downloadable)
3. Title (copyable text)
4. Description (copyable text)
5. Tags (comma-separated, copyable)
6. Script (markdown file, downloadable)

**Delivery Options:**

- Download all files as ZIP
- Individual file downloads
- Copy text fields to clipboard

**Phase 2 (Future):** Direct upload to YouTube via API

### 9. Video Library & History

**Features:**

- List all user's projects (most recent first)
- Each project shows:
    - Title
    - Thumbnail preview
    - Status (In Progress, Completed, Failed)
    - Date created
    - Video duration
- Click project to view/download outputs
- Delete project option
- Re-generate option (edit inputs, create new version)

**Retention Policy:**

- Free tier: 30 days retention
- Paid tier: 90 days retention
- After retention: Files deleted, project metadata kept

### 10. Pricing & Payment (Phase 1)

**Model:** Pay-per-video (credit-based system)

**Free Tier:**

- 1 free video to test (limited features)
    - Max 1 minute long
    - 1 critique/iteration round for script
    - 1 critique/iteration round for thumbnails
    - Watermark on video output

**Paid Tier:**

- $4 per video (pay-as-you-go)
- No watermark
- Full-length videos (up to 20 min)
- Unlimited critique rounds (within reason)
- Access to all features

**Credit Packages:**

- $20 = 5 videos ($4 each)
- $50 = 15 videos ($3.33 each - 17% discount)
- $100 = 35 videos ($2.86 each - 29% discount)

**Cost Calculation Logic:**

- Base cost per video: ~$0.50-0.60 (AI APIs + TTS + image gen + rendering)
- Markup: 4-7x costs
- User sees flat rate: $4/video
- System tracks actual costs per video for analytics

**Payment Integration:**

- Stripe for credit card processing
- Store credit balance in user account
- Deduct credits upon video completion (not start)
- Refund credits if video generation fails

**Phase 2 (Future):** Monthly subscription tiers after cost validation

---

## Phase 2: Future Roadmap (Post-MVP)

**The PRD should document Phase 2 as future features, not immediate requirements.**

### Advanced Features

**1. Voice Cloning**

- User uploads 5-10 minutes of sample audio
- ElevenLabs voice cloning API
- Use cloned voice for all future videos

**2. Multiple Languages**

- Script translation (DeepL API or similar)
- Multi-language TTS voices
- Localized metadata generation

**3. Advanced Editing**

- Manual clip selection/replacement (drag-and-drop interface)
- Trim/extend individual clips
- Text overlay editor (add custom text on-screen)
- Transition style selection

**4. Premium Stock Footage**

- Integration with Storyblocks API (paid)
- Shutterstock API (paid)
- User pays per-clip or subscription pass-through

**5. Custom Branding**

- Upload brand kit (logo, color palette, fonts)
- Automatic brand application to thumbnails
- Intro/outro clip upload and insertion

**6. Direct YouTube Upload**

- OAuth integration with YouTube API
- Upload video + metadata directly from platform
- Schedule publishing time
- Analytics dashboard (views, CTR, retention from YouTube)

**7. Batch Generation**

- Upload multiple topics/prompts
- Generate 5-10 videos in queue
- Bulk download outputs

**8. Collaboration Features**

- Team accounts (multiple users per account)
- Role-based permissions (creator, reviewer, admin)
- Comment/feedback on drafts
- Approval workflow before finalization

**9. Extended Video Length**

- Support up to 60 minutes (requires optimized rendering)
- Split long scripts into chapters
- Batch rendering for long videos

**10. Analytics & Optimization**

- Track which titles/thumbnails perform best
- A/B testing suggestions (generate 2 versions, compare)
- Learning from user's past successful videos

**11. Subscription Tiers**

- Monthly plans after cost validation:
    - Basic: $29/month (10 videos)
    - Pro: $79/month (30 videos)
    - Business: $199/month (100 videos)

---

## Technical Architecture

### Tech Stack (User-Specified)

**Frontend:**

- React (latest stable version)
- Tailwind CSS for styling
- State management: Context API or Zustand
- Routing: React Router
- Form handling: React Hook Form
- File uploads: React Dropzone

**Backend:**

- PHP 8.2+
- Laravel 11 (latest LTS)
- MariaDB (on A2 hosting) - database
- Redis for caching and job queue
- RESTful API architecture

**Video Processing:**

- Shotstack API (cloud-based video rendering)
- No self-hosted workers needed
- Webhook integration for completion callbacks

**Hosting:**

- A2 Shared Hosting (for Laravel API + React frontend + MariaDB)
- Cloudflare R2 or AWS S3 for video/image/audio storage
- Cloudflare CDN for video delivery

**Testing:**

- PHPUnit for backend tests (Laravel testing suite)
- Jest + React Testing Library for frontend tests
- Playwright for E2E tests (modern alternative to Selenium/Cypress - better for AI-generated code)

**DevOps:**

- Git for version control
- GitHub Actions for CI/CD
- Environment-based configs (.env)
- Logging: Laravel Log + Sentry for error tracking

**CRITICAL - AI/Agentic Code Development Approach:**

This codebase will be built entirely using AI coding agents (Claude Code, Cursor, similar tools) with minimal direct human coding. The PRD must be optimized for AI agent consumption and implementation.

**Requirements for AI-Generated Code:**

1. **Modular Architecture:** Each feature should be clearly bounded and implementable as separate modules/components
2. **Comprehensive Testing Required:** All generated code must include:
    - Unit tests (PHPUnit for Laravel, Jest for React)
    - Integration tests for API endpoints
    - E2E tests (Playwright) for critical user flows
    - AI agent should run tests after generating each feature
3. **Code Review Checkpoints:** After each feature/module completion:
    - AI agent runs linter (PHP CS Fixer, ESLint)
    - AI agent runs all tests
    - AI agent reviews code for security issues (SQL injection, XSS, CSRF)
    - AI agent checks for performance issues (N+1 queries, memory leaks)
4. **Documentation Standards:** Every class, method, API endpoint must have:
    - Clear docstrings/comments
    - Type hints (PHP 8.2 strict types, TypeScript for React)
    - API documentation (OpenAPI/Swagger for endpoints)
5. **Incremental Development:** Features built in this order:
    - Feature scaffold (routes, controllers, models, components)
    - Business logic implementation
    - Tests written alongside implementation (not after)
    - Code review and refactoring
    - Documentation updates
    - Integration with existing features
6. **Quality Gates:** Before marking feature "complete":
    - All tests pass (100% pass rate required)
    - Code coverage >80% for critical paths
    - No linter errors
    - No security vulnerabilities (run security scanner)
    - Performance benchmarks met (page load <2s, API response <500ms)

**AI Agent Workflow Example:**

```
1. Agent reads feature spec from PRD
2. Agent scaffolds files (controller, model, migration, test files)
3. Agent implements business logic
4. Agent writes tests for happy path + edge cases
5. Agent runs tests → if fail, debug and fix
6. Agent runs linter → if fail, fix formatting
7. Agent runs security scan → if issues, fix vulnerabilities
8. Agent commits code with descriptive message
9. Agent moves to next feature
```

**Recommended AI Development Tools:**

- Claude Code (for backend Laravel work)
- Cursor (for frontend React work)
- GitHub Copilot (for autocomplete assistance)
- Continuous test runner (PHPUnit watch mode, Jest watch mode)

**Human Oversight Points:**

- Review after each major feature completion (e.g., after "User Authentication" is done)
- Acceptance testing of user flows
- Final QA before deployment
- BUT: Majority of coding, testing, debugging done by AI agents

### External APIs & Services

**AI/ML:**

- Anthropic API (Claude Sonnet 4 for script generation/critique)
- OpenAI API (DALL-E 3 for thumbnail images)
- ElevenLabs API (text-to-speech voices)

**Content:**

- Pexels API (free stock footage)
- Pixabay API (free stock footage + music)
- youtube-transcript-api (Python library for YouTube transcripts)

**Utilities:**

- FFmpeg (video rendering - open source)
- Beautiful Soup / Readability.js (article extraction)
- Stripe API (payments)

**Estimated API Costs per Video:**

- Script workflow (Claude): $0.05-0.10
- Thumbnail generation (Gemini Imagen 3 via Nano Banana Pro): $0.10-0.20
- Voiceover (ElevenLabs): $0.30
- Video rendering (Shotstack): $0.50-0.75 (for 10-15 min video at ~$0.05/min)
- **Total:** ~$0.95-1.35 per video

**Pricing Strategy:**

- Charge users $4/video
- Profit margin: ~$2.65-3.05 per video (3-4x markup)
- Allows for infrastructure costs, payment processing fees (Stripe 2.9% + $0.30), support

**Note on Costs:**
Shotstack is the most expensive component but provides reliability and eliminates infrastructure complexity. As volume grows, Phase 2 can explore self-hosted FFmpeg to reduce costs.

### Database Schema (High-Level)

**Database:** MariaDB (compatible with MySQL, available on A2 hosting)

**Users Table:**

- id, email, password_hash, email_verified_at, created_at, updated_at
- credits_balance (integer)
- preferences_json (brand guide, tone, audience, defaults)

**Projects Table:**

- id, user_id, name, settings_json (brand guide, target audience, thumbnail style, music prefs), is_default (boolean), created_at, updated_at

**Videos Table (renamed from Projects for clarity):**

- id, project_id (foreign key), user_id, name, status (draft/processing/completed/failed), created_at, updated_at
- input_json (topic, reference_urls, iteration_counts)
- outputs_json (file URLs, metadata)

**Generations Table (audit log):**

- id, video_id (foreign key - references Videos table), step (title/outline/script/thumbnail/video), status, input, output, cost, created_at
- Tracks each AI generation step for debugging and cost analysis

**API_Calls Table (detailed cost tracking):**

```sql
CREATE TABLE api_calls (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  video_id BIGINT NOT NULL,
  generation_id BIGINT, -- references Generations table
  step VARCHAR(50) NOT NULL, -- 'title', 'outline', 'script', 'script_critique', etc.
  provider VARCHAR(50) NOT NULL, -- 'anthropic', 'openai', 'google', 'elevenlabs', 'shotstack'
  model VARCHAR(100) NOT NULL, -- 'claude-opus-4-5', 'claude-sonnet-4-5', 'gpt-4', etc.

  -- Token usage (for LLMs)
  input_tokens INT DEFAULT 0,
  output_tokens INT DEFAULT 0,

  -- Other usage metrics
  image_count INT DEFAULT 0, -- for image generation (DALL-E, Imagen)
  audio_duration_seconds INT DEFAULT 0, -- for TTS (ElevenLabs)
  video_duration_seconds INT DEFAULT 0, -- for video rendering (Shotstack)

  -- Cost tracking
  cost_usd DECIMAL(10, 6) NOT NULL, -- actual cost in USD (e.g., 0.012500)
  cost_calculation TEXT, -- formula used: "input_tokens * $0.015/1k + output_tokens * $0.075/1k"

  -- Request/response details
  request_payload LONGTEXT, -- JSON of request sent
  response_data LONGTEXT, -- JSON of response received (truncated if needed)

  -- Status & timing
  status VARCHAR(20) NOT NULL, -- 'success', 'failed', 'timeout'
  response_time_ms INT, -- how long API call took
  error_message TEXT, -- if failed, why
  retry_count INT DEFAULT 0, -- how many retries before success/failure

  -- Metadata
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_video_id (video_id),
  INDEX idx_step (step),
  INDEX idx_provider (provider),
  INDEX idx_created_at (created_at),
  FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE
);
```

**Purpose of API_Calls Table:**

1. **Cost Analytics:**
    - Per-video cost breakdown (group by video_id)
    - Per-step cost analysis (which steps are most expensive?)
    - Per-model cost comparison (is Opus worth the extra cost?)
    - Per-provider cost tracking (Anthropic vs OpenAI vs Google)

2. **Pricing Optimization:**
    - Calculate actual costs over time
    - Identify cost trends (are API prices increasing?)
    - Determine optimal pricing for users
    - Show profit margin per video

3. **Performance Monitoring:**
    - Which APIs are slow? (response_time_ms)
    - Which APIs fail most often? (status = 'failed')
    - Are retries needed frequently? (retry_count)

4. **User Transparency:**
    - Show users actual costs of their video
    - Justify pricing (users see "This cost $1.25 to make, you paid $4")
    - Help users optimize (suggest cheaper model combinations)

5. **Debugging:**
    - Full request/response logs for troubleshooting
    - Trace errors back to specific API calls
    - Identify patterns in failures

**Example Queries:**

```sql
-- Total cost for a specific video
SELECT
  video_id,
  SUM(cost_usd) as total_cost,
  COUNT(*) as api_calls_count
FROM api_calls
WHERE video_id = 123
GROUP BY video_id;

-- Average cost per step across all videos
SELECT
  step,
  AVG(cost_usd) as avg_cost,
  MIN(cost_usd) as min_cost,
  MAX(cost_usd) as max_cost,
  COUNT(*) as call_count
FROM api_calls
WHERE status = 'success'
GROUP BY step
ORDER BY avg_cost DESC;

-- Cost comparison: Opus vs Sonnet for script writing
SELECT
  model,
  COUNT(*) as videos,
  AVG(cost_usd) as avg_cost,
  AVG(output_tokens) as avg_output_length
FROM api_calls
WHERE step = 'script_writing'
  AND model IN ('claude-opus-4-5', 'claude-sonnet-4-5')
GROUP BY model;

-- Daily cost trend (last 30 days)
SELECT
  DATE(created_at) as date,
  COUNT(DISTINCT video_id) as videos_created,
  SUM(cost_usd) as total_cost,
  SUM(cost_usd) / COUNT(DISTINCT video_id) as avg_cost_per_video
FROM api_calls
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(created_at)
ORDER BY date DESC;

-- Provider cost breakdown
SELECT
  provider,
  COUNT(*) as calls,
  SUM(cost_usd) as total_spent,
  AVG(cost_usd) as avg_per_call
FROM api_calls
WHERE status = 'success'
  AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY provider
ORDER BY total_spent DESC;
```

**User-Facing Cost Display:**

After video is generated, show cost breakdown:

```
Your Video Cost Breakdown

Content Generation:
  Title (Claude Opus)         $0.03
  Outline (Claude Opus)       $0.05
  Script (Claude Sonnet)      $0.05
  Critique x3 (Claude Sonnet) $0.06

Visual Assets:
  Thumbnails (Gemini Imagen)  $0.15

Audio/Video:
  Voiceover (ElevenLabs)      $0.30
  Video Render (Shotstack)    $0.65

Metadata:
  Description/Tags (Sonnet)   $0.01

━━━━━━━━━━━━━━━━━━━━━━━━━━━
Total Production Cost:        $1.30
Your Price:                   $4.00
Processing Fee:               $0.12 (Stripe)
━━━━━━━━━━━━━━━━━━━━━━━━━━━
Net Profit:                   $2.58
```

**Admin Dashboard Metrics:**

For the app owner/operator:

```
Last 30 Days Performance

Videos Created:               847
Total API Costs:              $1,104.55
Total Revenue:                $3,388.00
Processing Fees:              $101.64
Net Profit:                   $2,181.81

Cost Per Video:               $1.30 (avg)
Revenue Per Video:            $4.00 (avg)
Profit Per Video:             $2.58 (avg)
Profit Margin:                64%

Most Expensive Step:
  Video Rendering (Shotstack)  $550.05 (49.8%)

Most Expensive Provider:
  Shotstack                    $550.05
  ElevenLabs                   $254.10
  Anthropic                    $211.80
  Google (Imagen)              $127.05
```

**Cost Tracking Implementation:**

Every time the app makes an API call, it logs:

```php
// Pseudocode
function callAnthropicAPI($prompt, $model, $video_id, $step) {
  $start_time = microtime(true);

  try {
    $response = Anthropic::messages()->create([
      'model' => $model,
      'messages' => [['role' => 'user', 'content' => $prompt]]
    ]);

    $response_time = (microtime(true) - $start_time) * 1000; // ms

    // Calculate cost based on token usage
    $input_tokens = $response->usage->input_tokens;
    $output_tokens = $response->usage->output_tokens;

    $cost = calculateCost($model, $input_tokens, $output_tokens);

    // Log to database
    APICall::create([
      'video_id' => $video_id,
      'step' => $step,
      'provider' => 'anthropic',
      'model' => $model,
      'input_tokens' => $input_tokens,
      'output_tokens' => $output_tokens,
      'cost_usd' => $cost,
      'cost_calculation' => "($input_tokens * rate_in) + ($output_tokens * rate_out)",
      'status' => 'success',
      'response_time_ms' => $response_time,
      'request_payload' => json_encode(['prompt' => truncate($prompt, 1000)]),
      'response_data' => json_encode(['text' => truncate($response->content[0]->text, 1000)])
    ]);

    return $response;

  } catch (Exception $e) {
    // Log failure
    APICall::create([
      'video_id' => $video_id,
      'step' => $step,
      'provider' => 'anthropic',
      'model' => $model,
      'status' => 'failed',
      'error_message' => $e->getMessage(),
      'cost_usd' => 0
    ]);

    throw $e;
  }
}
```

This comprehensive cost tracking enables:

- Data-driven pricing decisions
- User transparency and trust
- Debugging and optimization
- Financial forecasting and analysis

**Transactions Table:**

- id, user_id, amount, type (purchase/refund/deduction), description, created_at
- Tracks credit purchases and video generation charges

**Files Table:**

- id, video_id (foreign key - references Videos table), type (video/thumbnail/script/audio), storage_path, size_bytes, expires_at, created_at

### Security & Compliance

**User Data:**

- Passwords hashed with bcrypt
- Email verification required
- HTTPS only (SSL cert)
- GDPR-compliant data export/deletion

**Payment Security:**

- Stripe handles all card data (PCI compliance)
- No card numbers stored in database
- Webhook verification for payment events

**API Keys:**

- Stored in environment variables (not in code)
- Rotated regularly
- Rate limiting on all external API calls

**Content Moderation:**

- No content moderation in Phase 1 (user's own content)
- Phase 2: Add content policy checks if abuse detected

### Performance & Scalability

**Phase 1 (MVP):**

- Target: 100-500 users
- Concurrent video renders: 5-10 (limited by worker capacity)
- Video queue with position updates ("You're #3 in queue")

**Phase 2 (Scale):**

- Auto-scaling workers (AWS Lambda or Kubernetes)
- CDN for video delivery (Cloudflare)
- Database read replicas for heavy analytics
- Caching layer (Redis) for frequent queries

---

## User Experience & Interface Design

### Design Principles

**1. Clarity Over Cleverness**

- Every step explained in simple language
- Progress indicators for multi-step workflows
- Estimated time remaining for each phase

**2. Feedback & Transparency**

- Show costs before committing (e.g., "This video will use 1 credit")
- Real-time status updates ("Generating script... 60% complete")
- Error messages that explain what happened and what to do

**3. Flexibility Within Guardrails**

- Sensible defaults (users can start without configuration)
- Optional customization (brand guide, iteration counts, voice selection)
- Can skip steps (e.g., use default title, skip thumbnail critique)

**4. Mobile-Responsive** (but desktop-primary)

- Desktop = full creation experience
- Mobile = review outputs, download files, monitor progress

### Key User Flows

**Flow 1: First-Time User (Onboarding)**

1. Land on homepage → "Create Your First Video Free"
2. Sign up (email/password)
3. Verify email
4. Guided tour: "Here's how it works" (4-step overview)
5. Create first project with pre-filled example (finance topic)
6. Watch 1-minute video generate in ~5 minutes
7. Download result → "Upgrade for full features"

**Flow 2: Returning User (Create Video)**

**HIGH-LEVEL FLOW:**

1. Login → Dashboard showing past projects
2. Click "New Video" (creates in currently selected project)
3. Form: Enter topic, reference URLs, AI model preferences, iteration settings
4. Submit → Redirects to project page with real-time progress

**DETAILED WORKFLOW WITH PARALLELIZATION:**

```
USER STEP 1: Input & Title Selection
├─ User enters topic + reference URLs
├─ User configures AI models (optional - defaults to Sonnet everywhere)
├─ User configures iterations (script: 0-5 rounds, thumbnail: 1-2 rounds)
├─ AI generates 5 titles with loglines (30 sec - user's selected model)
└─ User selects 1 title
    │
    ↓
    ┌─────────────────────────────────────────┐
    │   PARALLEL PROCESSING BEGINS            │
    └─────────────────────────────────────────┘
    │
    ├─ TRACK A: Outline Generation
    │  └─ Generate outline (recommended: Opus)
    │     Present to user ────────────────────┐
    │                                         │
    └─ TRACK B: Thumbnail Generation         │
       ├─ Generate 5 thumbnail concepts       │
       ├─ AI critique → Select top 3          │
       ├─ Refine concepts                     │
       ├─ Generate 3 images (Gemini Imagen)   │
       └─ Present 3 thumbnails ───────────────┤
                                              │
    ┌─────────────────────────────────────────┘
    │   USER SEES OUTLINE & THUMBNAILS
    └─────────────────────────────────────────┐
                                              ↓
USER STEP 2: Review Outline
├─ Review outline structure (sections, timing, flow)
├─ Can edit: Add/remove/reorder sections, adjust timing
├─ Can regenerate if completely off
└─ Approve outline to proceed with script
    │
    ↓
SYSTEM STEP: Script Generation
├─ Generate script from approved outline (sequential, not parallel)
├─ Critique Round 1 → Fix (if user configured iterations)
├─ Critique Round 2 → Fix (if configured)
├─ Critique Round N → Fix (up to 5 total)
└─ Present final script
    │
    ↓
USER STEP 3: Review Script & Select Thumbnail
├─ Review final script (can edit in text field)
├─ Choose 1 thumbnail from 3 options (already generated earlier)
└─ Approve to proceed
    │
    ↓
USER STEP 4: Voiceover & Music
├─ Choose AI voice (from ElevenLabs library) OR upload own file
├─ Choose music track (from 20 options) OR upload own file
└─ Submit for video generation
    │
    ↓
    ┌─────────────────────────────────────────┐
    │   PARALLEL PRODUCTION PREP              │
    └─────────────────────────────────────────┘
    │
    ├─ TRACK A: Voiceover Generation
    │  └─ If AI: Generate via ElevenLabs (30 sec)
    │     If Upload: Validate & normalize audio
    │
    ├─ TRACK B: Footage Planning
    │  ├─ Segment script into themes
    │  ├─ Extract keywords per segment
    │  ├─ Search Pexels/Pixabay
    │  └─ Prepare clip URLs & timings
    │
    ├─ TRACK C: Music Processing
    │  └─ Prepare audio file for mixing
    │
    ├─ TRACK D: Metadata Finalization
    │  ├─ Complete description using final script
    │  ├─ Finalize tags
    │  └─ Generate timestamps
    │
    └─ TRACK E: SFX Planning (optional)
       └─ Identify 4-8 SFX placement points

    ┌─────────────────────────────────────────┐
    │   ALL ASSETS READY → RENDER             │
    └─────────────────────────────────────────┘
    │
    ↓
SYSTEM STEP: Video Rendering
├─ Build Shotstack JSON payload (all assets)
├─ Send to Shotstack API
├─ Poll for completion (2-5 min)
├─ Download rendered video to storage
└─ Notify user: "Your video is ready!"
    │
    ↓
USER STEP 5: Download & Use
├─ Preview video in player
├─ Review cost breakdown (see what each step cost)
├─ Download video (MP4)
├─ Download thumbnail (PNG)
├─ Copy metadata (title, description, tags)
└─ Download all as ZIP
```

**USER SEES CONSOLIDATED PROGRESS:**

```
Creating Your Video...

✓ Title selected: "The One Number That Buys Your Freedom"
✓ AI Model: Claude Opus for outline, Sonnet for script

⏳ Generating content... (35% complete)
   ✓ Outline created → REVIEW NEEDED
   ⏳ Thumbnail concepts (generating images...)
   ⚪ Script (waiting for outline approval)

[Review Outline & Continue →]
```

After outline approval:

```
Creating Your Video...

✓ Title selected
✓ Outline approved

⏳ Generating content... (65% complete)
   ✓ Thumbnail images ready
   ⏳ Script generation in progress
   ⏳ Refining script (Round 2 of 3)

Estimated time: 2-3 minutes remaining
```

After script approval:

```
Preparing Your Video...

✓ Script approved (2,450 words)
✓ Thumbnail selected (#2)
✓ Voiceover: ElevenLabs "Adam" voice

⏳ Assembling video... (75% complete)
   ✓ Footage prepared (18 clips)
   ✓ Music added
   ✓ Metadata finalized
   ⏳ Rendering in cloud (2 min remaining)
```

**Key User Decisions:**

1. Which title? (from 5 ranked options)
2. Is outline structure good? (can edit/regenerate)
3. Which thumbnail? (from 3 options - pre-generated during outline)
4. Is script good? (can edit)
5. Which voice + music?

**What User Sees But Can't Edit:**

- Progress indicators
- Estimated time remaining
- Cost estimates (if shown during selection)
- Quality feedback from AI critique (summarized)

**What User NEVER Sees:**

- Individual API calls
- Token counts
- Retry attempts
- Backend parallelization details
- Specific models used (unless they opened advanced settings)

**Flow 3: Reviewing Progress**

- User can close tab during generation
- Return anytime → Project status shows "In Progress - Step 4 of 7"
- Click to resume at current step
- If error occurred → Clear message + retry option

### UI Components (High-Level)

**Dashboard:**

- Header: Logo, credits balance, user menu
- Main: Project cards (grid layout)
    - Thumbnail preview (if completed)
    - Title
    - Status badge (Draft/Processing/Completed)
    - Date created
    - Actions: View/Edit/Download/Delete
- CTA: "Create New Video" button (prominent)

**Project Creation Form:**

- Tabbed or stepped interface (multi-page form)
- Page 1: Topic + Reference URLs
- Page 2: Brand preferences (optional, collapsible)
- Page 3: Iteration settings (sliders: 0-5 rounds)
- Submit button: "Start Generating ($4 credit)"

**Project Progress Page:**

- Vertical stepper showing all steps
    - Completed steps: Green checkmark
    - Current step: Blue, pulsing
    - Future steps: Gray
- Detailed status: "Generating script critique (Round 2 of 3)..."
- Estimated time remaining (dynamic)
- Option to cancel (refund credits if < 50% complete)

**Selection Interfaces:**

- Title selection: Radio buttons with title + logline preview
- Thumbnail selection: Large image previews (3 columns)
- Voice selection: Dropdown with play button for samples
- Music selection: List with play button for 30-sec preview

**Download Page:**

- Large preview: Video player (embedded)
- Download buttons: Video, Thumbnail, Script, All (ZIP)
- Copyable text fields: Title, Description, Tags
- Social share: "Share this video" (future: direct YouTube upload)

---

## Success Metrics (Phase 1 MVP)

**User Acquisition:**

- 100 registered users in first 8 weeks
- 50 paying users (converted from free tier) in first 12 weeks
- 20% free-to-paid conversion rate

**Engagement:**

- Average 2-3 videos generated per paying user per month
- 70% project completion rate (start → finished video)
- <10% error/failure rate in video generation

**Quality Indicators:**

- User surveys: "Rate the video quality" (target: 4.0/5.0 avg)
- Support tickets: <5% of videos require manual intervention
- Iteration usage: Avg 2 script critique rounds (proves quality focus)

**Revenue:**

- $500 MRR (Monthly Recurring Revenue) by week 12
- $3.50 profit per video (after API costs)
- 90% gross margin (low infrastructure overhead)

**Technical Performance:**

- Video generation time: <10 minutes for 10-min video
- Uptime: 99%+ (excluding scheduled maintenance)
- API error rate: <2% (retries handle transient failures)

---

## Development Timeline (Phase 1 MVP)

**Weeks 1-2: Foundation**

- Set up development environment (Laravel + React)
- Database schema design and migration
- User authentication (registration, login, email verification)
- Basic UI framework (Tailwind setup, layout components)

**Weeks 3-4: Core Workflow (Part 1)**

- Project creation form
- Title generation integration (Anthropic API)
- Outline generation integration
- Script generation integration
- File storage setup (S3/R2)

**Weeks 5-6: Core Workflow (Part 2)**

- Script critique loop implementation
- Iterative refinement logic (configurable rounds)
- Script editor UI (review/edit interface)
- Thumbnail concept generation + critique

**Weeks 7-8: Video Production**

- Voiceover integration (ElevenLabs + user upload)
- Stock footage search and selection (Pexels/Pixabay)
- Music library setup
- FFmpeg video assembly (basic version)
- Worker service deployment (Railway/Lambda)

**Weeks 9-10: Polish & Payment**

- Metadata generation (description, tags)
- Download interface (ZIP + individual files)
- Credit system implementation
- Stripe payment integration
- Project library/history UI

**Weeks 11-12: Testing & Launch**

- End-to-end testing (Cypress)
- Bug fixes and optimization
- Performance tuning (video render speed)
- Beta user testing (10-20 users)
- Production deployment
- Marketing site + documentation

**Post-Launch:**

- User feedback collection
- Cost analysis (actual vs. projected)
- Iterate on quality issues
- Plan Phase 2 features

---

## Risks & Mitigations

**Risk 1: API Costs Higher Than Expected**

- **Mitigation:** Track costs per video in real-time, adjust pricing if needed
- **Fallback:** Implement rate limiting (max 5 videos per user per day)

**Risk 2: Video Rendering Timeout/Failures**

- **Mitigation:** Separate worker service, not on shared hosting
- **Fallback:** Use third-party API (Shotstack) if FFmpeg proves unreliable

**Risk 3: Quality Issues (AI Output Not Good Enough)**

- **Mitigation:** User's proven prompts are starting point, iterate based on feedback
- **Fallback:** Allow manual editing at every step (outline, script, thumbnail)

**Risk 4: Low Free-to-Paid Conversion**

- **Mitigation:** Generous free tier (1 full video), clear value demonstration
- **Fallback:** Offer discounted first paid video ($2 instead of $4)

**Risk 5: Shared Hosting Limitations (A2)**

- **Mitigation:** Use A2 only for API/frontend, offload video work to separate service
- **Fallback:** Migrate to VPS (DigitalOcean) if shared hosting can't handle traffic

**Risk 6: Copyright Issues (User Uploads Copyrighted Material)**

- **Mitigation:** Terms of service clearly state user responsibility
- **Fallback:** Add content scanning in Phase 2 if abuse detected

---

## Decisions Made (Answers to Open Questions)

**1. Subtitle Generation:**

- **Decision:** Phase 2 feature (not MVP)
- **Rationale:** Adds complexity and cost, many faceless videos don't use subtitles
- **Future Implementation:** Optional toggle, use Whisper API to generate SRT file

**2. Video Preview Before Rendering:**

- **Decision:** Yes, include in Phase 1 (basic version)
- **Implementation:**
    - Show storyboard preview: Script segments + matched stock footage thumbnails
    - User can see which clips will be used where
    - Stream sample clips from Pexels/Pixabay (no actual rendering)
    - "Does this look right?" confirmation before sending to Shotstack
- **Benefit:** Reduces wasted renders if footage selection is poor

**3. Brand Guide Upload:**

- **Decision:** Text or Markdown only (.txt, .md files)
- **Rationale:** Easy to parse, AI-friendly format
- **Template Provided:** System offers example brand guide template users can download and fill in
- **Future:** .docx/.pdf parsing in Phase 2

**4. Error Handling UI:**

- **Decision:** Auto-retry 1-2 times, then show user-friendly error
- **Implementation:**
    - If API call fails (timeout, rate limit), retry once after 5 seconds
    - If still fails, retry again after 15 seconds
    - After 2 failed retries, show error: "Something went wrong. [Specific issue]. Try again?"
    - Technical details in expandable "Show Details" section (for debugging)
    - Offer "Contact Support" button if issue persists
- **Logging:** All errors logged to Sentry for developer review

**5. Iteration Limits:**

- **Decision:** 5 rounds maximum for script critique
- **Implementation:**
    - User chooses 0-5 iterations at project creation
    - After 5 rounds, system forces user to proceed with current script
    - Warning after 3rd iteration: "You've used 3/5 critique rounds. Consider proceeding soon."
- **Rationale:** Prevents infinite loops, encourages user decision-making

**6. Video Export Format:**

- **Decision:** 1080p only for Phase 1
- **Codec:** H.264 (Shotstack API default)
- **Future:** 720p (faster renders) and 4K options in Phase 2
- **File Size:** Estimate 100-200MB for 10-minute video (reasonable for download)

---

## User Stories (Examples for PRD)

**As a faceless YouTube creator, I want to...**

1. **Create a high-quality script without spending hours writing**
    - Enter my video topic and reference articles
    - Have AI generate a well-researched script
    - Critique and refine it until it meets my standards
    - So that: I can focus on growing my channel, not writing

2. **Generate professional thumbnails that get clicks**
    - Provide my video title and script
    - Get 3 AI-designed thumbnail options
    - Select the one most likely to attract viewers
    - So that: My videos stand out in crowded search results

3. **Use my own voice for authenticity while automating editing**
    - Upload my voiceover recording
    - Have the system match footage to my script automatically
    - Get a polished video without learning video editing
    - So that: I maintain my unique voice while saving production time

4. **Control quality without overwhelming complexity**
    - Choose how many critique rounds I want for scripts
    - Review each major output (title, outline, script, thumbnail)
    - Make manual edits if AI misses something
    - So that: I balance automation with control over my brand

5. **Pay only for what I use**
    - Buy credits in packages that fit my budget
    - See exactly what each video will cost before generating
    - Not locked into monthly subscription if I'm inconsistent
    - So that: I can manage costs as my channel grows

---

## Appendix: Methodology Files Reference

**The user has provided 16 markdown files documenting their proven YouTube creation workflow. These files contain:**

1. **00-channel-guidelines-ai.md** (45KB) - Master reference for brand voice, tone, content structure, engagement techniques
2. **01-title-generation.md** (8.5KB) - Title generation strategy using 5 frameworks
3. **01b-title-rating.md** (10KB) - Audience perspective critique of titles
4. **02-next-video-hooks.md** (3.5KB) - Strategy for creating series engagement
5. **03-outline-creation.md** (8KB) - Video structure and pacing methodology
6. **04-script-writing.md** (6.5KB) - Conversational script writing approach
7. **05-script-critique.md** (11KB) - Target audience perspective critique
8. **06-script-fix.md** (6.5KB) - Iterative refinement process
9. **07-broll-edit-directions.md** (11KB) - Visual pacing and footage selection
10. **08-music-cues.md** (6.5KB) - Music selection and mixing strategy
11. **09-sfx-cues.md** (6KB) - Sound effects placement
12. **10-description-metadata.md** (16KB) - YouTube metadata optimization
13. **10a-description-templates.md** (26KB) - Templates for different content types
14. **11-thumbnails.md** (20KB) - Thumbnail design and psychology
15. **12-clickability-analysis.md** (12KB) - Click-through optimization
16. **PROMPT-USAGE-GUIDE.md** (23KB) - Workflow orchestration guide

**Key Extractions from These Files:**

**From Channel Guidelines:**

- 50/50 authority balance (vulnerable + expert)
- Content transformation framework ("read and close" - don't copy source)
- Build-up/release cycles for retention
- Speaking pace: 165-170 wpm
- Copyright compliance: 15-word quote limit, one quote per source max
- Pattern interrupts every 10-12 seconds

**From Title Generation:**

- 5 proven frameworks (Ultimate Guide, Contrarian, Only X You Need, How I, Number-based)
- Logline concept (target viewer + problem + unique insight + benefit)
- Performance analysis (check past video stats for what works)

**From Script Critique:**

- Critique from target audience perspective (not expert)
- Focus on retention drops (where would viewers click away?)
- Prioritize fixes (High/Medium/Low impact)
- Provide implementation guidance (not just criticism)

**From Thumbnails:**

- Mobile-first readability test
- 3-second scroll test
- Synergy with title (complement, not duplicate)
- Click decision matrix scoring

**From B-roll Directions:**

- Hook: 3-5 second changes (fast pacing)
- Body: 10-12 second baseline (content-aware triggers)
- Content triggers: Scene changes, metaphors, data reveals, comparisons

**Application to PRD:**
The PRD should specify that:

1. AI prompts incorporate these methodologies (extracted as reusable templates)
2. User can provide their own brand guide to override defaults
3. Quality gates are built-in (critique loops, audience perspective evaluation)
4. Pacing rules are codified (speaking rate, clip duration, pattern interrupts)
5. Copyright compliance is enforced in prompts (quote limits, paraphrasing requirements)

---

## Final Notes for Opus

**Tone of PRD:**

- Professional but practical (this is for developers to build from)
- Detailed enough to start coding (not vague "nice-to-haves")
- Acknowledges trade-offs (cost vs. quality, automation vs. control)
- User-centric (every feature justified by user pain point)

**Structure of PRD:**

- Executive summary (2-3 pages)
- Problem/solution/vision (2-3 pages)
- Phase 1 MVP requirements (15-20 pages) - DETAILED
- Phase 2 roadmap (5-10 pages) - HIGH-LEVEL
- Technical architecture (5-10 pages)
- User experience (5-10 pages)
- Success metrics, timeline, risks (3-5 pages)
- Appendices (methodology reference, glossary, etc.)

**Total PRD Length:** 40-60 pages (comprehensive but not bloated)

**Deliverable Format:**

- Markdown or Google Docs (easily shareable, commentable)
- Clear section headers and table of contents
- Visual aids where helpful (user flow diagrams, architecture diagrams)
- Glossary of terms for non-technical stakeholders

**Key Outputs from This PRD:**

1. Development team can estimate effort/timeline accurately
2. Designer can create wireframes/mockups from user flows
3. Product manager can prioritize features within MVP scope
4. Stakeholders understand what gets built and why
5. QA team can write test cases from requirements

**Success Criteria for PRD:**

- Developer reads it and says "I know exactly what to build"
- Designer reads it and says "I know what interfaces are needed"
- User reads it and says "This solves my problem"
- Investor reads it and says "This is a viable business"

---

END OF BRIEF

**Instructions for Opus:**
Please create a comprehensive Product Requirements Document based on this brief. The PRD should be production-ready - detailed enough for a development team to begin implementation immediately. Organize it clearly with a table of contents, use diagrams where helpful, and maintain consistency between all sections. The goal is a 40-60 page document that becomes the single source of truth for building this MVP.

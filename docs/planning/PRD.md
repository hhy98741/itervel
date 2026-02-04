# Product Requirements Document (PRD)

# Itervel - AI-Powered Faceless Video Creation Platform

**Version:** 1.0
**Date:** January 2026
**Status:** Phase 1 MVP
**Document Owner:** Product Team

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Product Vision & Strategy](#2-product-vision--strategy)
3. [User Experience & Workflows](#3-user-experience--workflows)
4. [Feature Requirements](#4-feature-requirements)
    - 4.1 Authentication & Account Management
    - 4.2 Project Management (Multi-Channel)
    - 4.3 Video Creation Wizard
    - 4.4 Content Generation Pipeline
    - 4.5 Asset Generation
    - 4.6 Video Assembly & Rendering
    - 4.7 Output Delivery
    - 4.8 Video Library & History
    - 4.9 Pricing & Payments
5. [Technical Architecture](#5-technical-architecture)
6. [API Specifications](#6-api-specifications)
7. [Testing Strategy](#7-testing-strategy)
8. [Success Metrics & Analytics](#8-success-metrics--analytics)
9. [Risk Management](#9-risk-management)
10. [Appendices](#10-appendices)

---

## 1. Executive Summary

### 1.1 Product Overview

**Product Name:** Itervel

**Vision Statement:** Empower faceless YouTube creators to produce high-quality, engaging videos through an AI-powered workflow that prioritizes quality over speed.

**One-Line Description:** A web application that automates the creation of professional faceless YouTube videos using iterative AI refinement, producing complete output packages including script, voiceover, video, thumbnail, and metadata.

### 1.2 Problem Statement

Current AI video creation tools (Pictory.ai, InVideo.io, and similar platforms) prioritize speed over quality, resulting in:

| Problem                          | Impact                                                |
| -------------------------------- | ----------------------------------------------------- |
| Single-pass AI script generation | Mediocre, generic content that doesn't engage viewers |
| No critique/refinement loops     | First-draft quality with obvious AI patterns          |
| Generic templates                | Videos that look identical to competitors             |
| Limited research integration     | Shallow content lacking substance                     |
| Poor thumbnail generation        | Low click-through rates                               |
| No quality feedback loops        | Creators can't improve output quality                 |

**The Core Issue:** These tools create videos that _exist_ but don't _perform_ on YouTube. Creators need videos that generate views, retain audiences, and build channels—not just fill upload schedules.

### 1.3 Solution Overview

Itervel implements a **quality-first iterative workflow** that mirrors the process used by successful faceless YouTube creators:

```
┌─────────────────────────────────────────────────────────────────┐
│                 ITERVEL WORKFLOW                      │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  1. RESEARCH      →  Analyze up to 3 reference URLs             │
│                      (articles, YouTube videos)                  │
│                                                                  │
│  2. TITLE         →  Generate 5 options with AI ranking         │
│                      User selects best performer                 │
│                                                                  │
│  3. OUTLINE       →  Structured outline with timing             │
│                      User can edit before script                 │
│                                                                  │
│  4. SCRIPT        →  Configurable critique rounds (0-5)         │
│                      Iterative refinement until quality          │
│                                                                  │
│  5. THUMBNAILS    →  Generate concepts, critique, produce 3     │
│                      (runs parallel to script generation)        │
│                                                                  │
│  6. PRODUCTION    →  AI voice OR user upload + stock footage    │
│                      + music selection                           │
│                                                                  │
│  7. DELIVERY      →  Complete package: video, thumbnail,        │
│                      script, title, description, tags            │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 1.4 Key Differentiators

| Differentiator               | Description                                                                                            | Competitor Comparison                            |
| ---------------------------- | ------------------------------------------------------------------------------------------------------ | ------------------------------------------------ |
| **Iterative Script Quality** | AI generates, critiques from audience perspective, and refines scripts through 0-5 configurable rounds | Competitors: Single-pass generation only         |
| **Research-Driven Content**  | Analyzes reference materials to create well-researched, substantive scripts                            | Competitors: Topic input only, no research       |
| **Thumbnail Iteration**      | Generates concepts, AI critiques, selects top 3, user chooses                                          | Competitors: Generic templates or single option  |
| **Complete Output Package**  | Script, voiceover, video, thumbnail, title, description, tags                                          | Competitors: Often video-only or partial outputs |
| **Quality Over Speed**       | Designed for creators who need performing videos, not just quick videos                                | Competitors: Focus on speed, sacrifice quality   |
| **Cost Transparency**        | Shows actual API costs per video, builds trust                                                         | Competitors: Hidden costs, unclear pricing       |

### 1.5 Target Market

**Primary Users:** Long-form faceless YouTube channel operators

| Attribute                | Description                                                             |
| ------------------------ | ----------------------------------------------------------------------- |
| **Content Niches**       | Personal finance, motivation, productivity, education, history, science |
| **Video Length**         | 10-15 minutes typically                                                 |
| **Upload Frequency**     | 1-4 videos per week                                                     |
| **Technical Skill**      | Basic (can upload files, fill forms, no video editing expertise)        |
| **Primary Pain Point**   | Creating quality scripts and thumbnails is time-consuming               |
| **Secondary Pain Point** | Hiring freelancers is expensive and inconsistent                        |

**Market Size Indicators:**

- Faceless YouTube is a growing content category
- Low barrier to entry attracts new creators daily
- Existing tools leave quality gap unfilled
- Pay-per-video model aligns with creator cash flow

### 1.6 Business Model

**Model:** Pay-per-video (credit-based system)

| Tier              | Price             | Features                                               |
| ----------------- | ----------------- | ------------------------------------------------------ |
| **Free Trial**    | $0                | 1 video, max 1 minute, watermarked, limited iterations |
| **Pay-as-you-go** | $4/video          | Full features, no watermark, up to 20 minutes          |
| **5-Video Pack**  | $20 ($4 each)     | Standard pricing                                       |
| **15-Video Pack** | $50 ($3.33 each)  | 17% discount                                           |
| **35-Video Pack** | $100 ($2.86 each) | 29% discount                                           |

**Unit Economics:**

- Estimated cost per video: $0.95-1.35 (AI APIs + rendering)
- Price per video: $4.00
- Gross margin: ~$2.65-3.05 per video (66-76%)

### 1.7 Success Criteria (Phase 1 MVP)

| Metric                    | Target    | Timeframe             |
| ------------------------- | --------- | --------------------- |
| Registered users          | 100       | 8 weeks post-launch   |
| Paying users              | 50        | 12 weeks post-launch  |
| Free-to-paid conversion   | 20%       | Ongoing               |
| Videos per paying user    | 2-3/month | Monthly average       |
| Project completion rate   | 70%       | Ongoing               |
| User quality rating       | 4.0/5.0   | Survey average        |
| Monthly Recurring Revenue | $500      | Week 12               |
| Video generation time     | <10 min   | For 10-min video      |
| System uptime             | 99%       | Excluding maintenance |

### 1.8 Phased Approach

**Phase 1 (MVP) - 12 Week Timeline:**

- Core workflow: topic → script → video with iterative refinement
- Pay-per-video pricing
- Essential features only
- Target: Validate product-market fit with 50 paying users

**Phase 2 (Future) - Post-Validation:**

- Voice cloning
- Manual editing interface
- Direct YouTube upload
- Subscription tiers
- Advanced features based on user feedback

---

## 2. Product Vision & Strategy

### 2.1 Market Analysis

#### 2.1.1 Competitive Landscape

| Competitor     | Strengths                                   | Weaknesses                                        | Price         |
| -------------- | ------------------------------------------- | ------------------------------------------------- | ------------- |
| **Pictory.ai** | Fast generation, good templates, brand kits | Single-pass scripts, generic output, no iteration | $19-99/month  |
| **InVideo.io** | Large template library, easy UI             | Shallow AI content, no research integration       | $15-60/month  |
| **Synthesia**  | High-quality AI avatars                     | Expensive, not for faceless content               | $22-67/month  |
| **Lumen5**     | Good for repurposing blog content           | Limited original content creation                 | $19-149/month |
| **Descript**   | Excellent editing, transcript-based         | Requires existing content, not generative         | $12-24/month  |

#### 2.1.2 Market Gap

```
                    QUALITY
                       ▲
                       │
           ┌───────────┼───────────┐
           │           │           │
           │  Premium  │  TARGET   │ ← Itervel
           │  Agencies │  ZONE     │   (Quality + Affordable)
           │           │           │
      ─────┼───────────┼───────────┼─────► AFFORDABILITY
           │           │           │
           │  Current  │  DIY      │
           │  AI Tools │  Manual   │
           │           │           │
           └───────────┼───────────┘
                       │
```

**Opportunity:** No tool currently serves creators who want quality AI assistance at affordable per-video pricing. Subscription tools are too expensive for inconsistent uploaders; manual creation is too time-consuming.

### 2.2 User Personas

#### 2.2.1 Primary Persona: "Side Hustle Sam"

| Attribute              | Details                                                                                             |
| ---------------------- | --------------------------------------------------------------------------------------------------- |
| **Demographics**       | Age 28-40, employed full-time, building YouTube channel evenings/weekends                           |
| **Channels**           | 1-2 faceless channels in finance, motivation, or education niches                                   |
| **Goals**              | Build passive income stream, eventually replace day job                                             |
| **Current Process**    | Writes scripts manually (2-4 hours), hires Fiverr for thumbnails ($15-30), uses basic editing tools |
| **Pain Points**        | Time-constrained, inconsistent upload schedule, scripts feel amateur                                |
| **Willingness to Pay** | $3-5 per quality video vs. $20-50/month for mediocre subscriptions                                  |
| **Technical Skill**    | Can follow instructions, comfortable with web apps, no coding                                       |

**Quote:** _"I don't have time to spend 6 hours on every video. But I also can't upload garbage—my channel will never grow. I need something in between."_

#### 2.2.2 Secondary Persona: "Agency Alice"

| Attribute              | Details                                                           |
| ---------------------- | ----------------------------------------------------------------- |
| **Demographics**       | Age 30-45, runs small content agency or manages multiple channels |
| **Channels**           | 3-10 client channels across various niches                        |
| **Goals**              | Scale content production without hiring more staff                |
| **Current Process**    | Templates and SOPs, junior writers, batch production              |
| **Pain Points**        | Quality inconsistency across writers, client complaints           |
| **Willingness to Pay** | $50-200/month for reliable volume discounts                       |
| **Technical Skill**    | Comfortable with multiple tools, values integrations              |

**Quote:** _"I need consistent quality across all my clients. One bad video and I lose the account."_

### 2.3 Value Proposition

#### For Individual Creators:

> **Create YouTube videos that actually perform—not just exist—in under 30 minutes of your time.**

- Input your topic and references
- Guide AI through quality checkpoints
- Download a complete, upload-ready package
- Pay only for videos you create

#### For Agencies:

> **Scale quality content production with AI that thinks like your best writer.**

- Consistent quality across channels
- Brand guide customization per client
- Detailed cost tracking for billing
- Multi-project organization

### 2.4 Product Positioning

**Category:** AI Video Creation Platform

**Positioning Statement:**

> For faceless YouTube creators who need quality content but lack time, Itervel is an AI-powered video creation platform that produces engaging, well-researched videos through iterative refinement. Unlike one-click video generators that sacrifice quality for speed, Itervel implements a proven creator methodology that emphasizes audience engagement and retention.

**Key Messages:**

1. **Quality First:** Iterative critique and refinement, not single-pass generation
2. **Creator Control:** Review and edit at every major step
3. **Complete Package:** Everything you need to upload, ready to go
4. **Fair Pricing:** Pay per video, not monthly subscriptions you might not use

### 2.5 Go-to-Market Strategy

#### 2.5.1 Launch Strategy

**Soft Launch (Weeks 1-4 post-development):**

- Private beta with 20-30 invited users
- Focus on finance/motivation niche creators
- Collect feedback, iterate on UX issues
- Identify and fix critical bugs

**Public Launch (Week 5+):**

- Open registration with free trial
- Content marketing (blog posts, YouTube tutorials)
- Community engagement (Reddit r/NewTubers, Facebook groups)
- Influencer partnerships (mid-tier YouTube educators)

#### 2.5.2 Pricing Strategy Rationale

**Why Pay-Per-Video (not subscription):**

| Factor            | Subscription Model                              | Pay-Per-Video Model                               |
| ----------------- | ----------------------------------------------- | ------------------------------------------------- |
| Creator cash flow | Monthly cost regardless of output               | Pay when you create                               |
| Trial conversion  | Free trial → monthly commitment (high friction) | Free trial → single video purchase (low friction) |
| Usage alignment   | Penalizes inconsistent uploaders                | Matches creator workflow                          |
| Value perception  | "Am I using this enough?"                       | "This video was worth $4"                         |
| Churn risk        | Monthly cancellation opportunity                | No recurring commitment to cancel                 |

**Price Point Justification ($4/video):**

- Manual creation time: 4-8 hours @ $20/hour = $80-160 value
- Freelancer script: $30-100
- Freelancer thumbnail: $15-30
- Itervel: $4 (95%+ savings)

---

## 3. User Experience & Workflows

### 3.1 Information Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     ITERVEL                           │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐              │
│  │  Dashboard  │  │  Projects   │  │  Settings   │              │
│  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘              │
│         │                │                │                      │
│         ▼                ▼                ▼                      │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐              │
│  │ Video List  │  │ Project     │  │ Account     │              │
│  │ Quick Stats │  │ Settings    │  │ Profile     │              │
│  │ New Video   │  │ Brand Guide │  │ Preferences │              │
│  └─────────────┘  │ Video List  │  │ Billing     │              │
│                   └─────────────┘  └─────────────┘              │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                   VIDEO CREATION WIZARD                   │   │
│  ├──────────────────────────────────────────────────────────┤   │
│  │  Step 1: Input    → Topic, URLs, Settings                │   │
│  │  Step 2: Title    → Select from 5 options                │   │
│  │  Step 3: Outline  → Review and edit structure            │   │
│  │  Step 4: Script   → Review final (after AI iterations)   │   │
│  │  Step 5: Voice    → Select AI voice or upload            │   │
│  │  Step 6: Thumb    → Select from 3 options                │   │
│  │  Step 7: Music    → Select from library or upload        │   │
│  │  Step 8: Review   → Preview and generate                 │   │
│  │  Step 9: Download → Complete package                     │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 3.2 User Journey: First-Time User (Onboarding)

```
┌─────────────────────────────────────────────────────────────────┐
│                    ONBOARDING FLOW                               │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  1. LANDING PAGE                                                 │
│     ├─ Hero: "Create YouTube Videos That Actually Perform"       │
│     ├─ CTA: "Create Your First Video Free"                       │
│     └─ Social proof, feature highlights                          │
│                           │                                      │
│                           ▼                                      │
│  2. REGISTRATION                                                 │
│     ├─ Email + Password                                          │
│     ├─ Accept Terms of Service                                   │
│     └─ Submit                                                    │
│                           │                                      │
│                           ▼                                      │
│  3. EMAIL VERIFICATION                                           │
│     ├─ Check inbox prompt                                        │
│     ├─ Click verification link                                   │
│     └─ Redirect to app                                           │
│                           │                                      │
│                           ▼                                      │
│  4. WELCOME TOUR (4 slides)                                      │
│     ├─ Slide 1: "Enter your topic and references"                │
│     ├─ Slide 2: "AI generates and refines your content"          │
│     ├─ Slide 3: "Review and customize at each step"              │
│     └─ Slide 4: "Download your complete video package"           │
│                           │                                      │
│                           ▼                                      │
│  5. FIRST PROJECT CREATION                                       │
│     ├─ Create default project (auto-named "My Channel")          │
│     ├─ Optional: Set brand preferences                           │
│     └─ Start first video with guided prompts                     │
│                           │                                      │
│                           ▼                                      │
│  6. FREE VIDEO GENERATION                                        │
│     ├─ Pre-filled example topic (finance niche)                  │
│     ├─ Walk through each step with tooltips                      │
│     └─ Generate 1-minute sample video                            │
│                           │                                      │
│                           ▼                                      │
│  7. CONVERSION PROMPT                                            │
│     ├─ Download result                                           │
│     ├─ "Ready for full-length videos?"                           │
│     └─ Show pricing, purchase credits                            │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 3.3 User Journey: Video Creation (Core Workflow)

#### 3.3.1 High-Level Flow

```
USER INPUT          AI PROCESSING              USER DECISION
    │                    │                          │
    ▼                    │                          │
┌─────────┐              │                          │
│ Topic + │              │                          │
│ URLs +  │──────────────┼─────────────────────────▶│
│ Settings│              │                          │
└─────────┘              │                          │
                         ▼                          │
                  ┌─────────────┐                   │
                  │ Generate 5  │                   │
                  │ Titles      │──────────────────▶│ Select 1 Title
                  └─────────────┘                   │
                         │                          │
           ┌─────────────┴─────────────┐            │
           │                           │            │
           ▼                           ▼            │
    ┌─────────────┐             ┌─────────────┐     │
    │ Generate    │             │ Generate 3  │     │
    │ Outline     │             │ Thumbnails  │     │
    └──────┬──────┘             └──────┬──────┘     │
           │                           │            │
           ▼                           │            │
    ┌─────────────┐                    │            │
    │ Present     │────────────────────┼───────────▶│ Review Outline
    │ Outline     │                    │            │ (can edit)
    └──────┬──────┘                    │            │
           │                           │            │
           ▼                           │            │
    ┌─────────────┐                    │            │
    │ Generate    │                    │            │
    │ Script      │                    │            │
    └──────┬──────┘                    │            │
           │                           │            │
           ▼                           │            │
    ┌─────────────┐                    │            │
    │ Critique &  │ (0-5 rounds)       │            │
    │ Refine      │                    │            │
    └──────┬──────┘                    │            │
           │                           │            │
           ▼                           ▼            │
    ┌─────────────────────────────────────┐         │
    │ Present Script + Thumbnails         │────────▶│ Review Script
    └─────────────────────────────────────┘         │ Select Thumbnail
                                                    │
                                                    ▼
                                             ┌─────────────┐
                                             │ Select Voice│
                                             │ Select Music│
                                             └──────┬──────┘
                                                    │
           ┌────────────────────────────────────────┘
           │
           ▼
    ┌─────────────────────────────────────┐
    │ PARALLEL PRODUCTION                  │
    │ ├─ Generate voiceover               │
    │ ├─ Find stock footage               │
    │ ├─ Process music                    │
    │ └─ Finalize metadata                │
    └──────────────┬──────────────────────┘
                   │
                   ▼
    ┌─────────────────────────────────────┐
    │ VIDEO RENDERING (Shotstack)         │
    │ 2-5 minutes                         │
    └──────────────┬──────────────────────┘
                   │
                   ▼
    ┌─────────────────────────────────────┐
    │ DOWNLOAD PACKAGE                     │────────▶│ Download All
    │ Video, Thumbnail, Script, Metadata  │         │
    └─────────────────────────────────────┘
```

#### 3.3.2 Detailed Step-by-Step Flow

**STEP 1: Project Input**

| Field           | Type                   | Required | Description                                        |
| --------------- | ---------------------- | -------- | -------------------------------------------------- |
| Project         | Dropdown               | Yes      | Select which project/channel this video belongs to |
| Topic           | Text (100-500 chars)   | Yes      | Main topic/concept for the video                   |
| Reference URL 1 | URL                    | No       | Article or YouTube video for research              |
| Reference URL 2 | URL                    | No       | Additional reference                               |
| Reference URL 3 | URL                    | No       | Additional reference                               |
| Brand Guide     | File upload (.txt/.md) | No       | Custom voice/tone instructions                     |

**Advanced Settings (Collapsed by Default):**

| Setting                  | Options               | Default         |
| ------------------------ | --------------------- | --------------- |
| Title Generation Model   | Sonnet / Opus / GPT-4 | Sonnet          |
| Outline Generation Model | Sonnet / Opus / GPT-4 | Opus            |
| Script Writing Model     | Sonnet / Opus / GPT-4 | Sonnet          |
| Script Critique Model    | Sonnet / Opus / GPT-4 | Sonnet          |
| Script Critique Rounds   | 0-5                   | 2               |
| Thumbnail Concepts Model | Sonnet / Opus / GPT-4 | Sonnet          |
| Thumbnail Image Model    | Gemini Imagen 3       | Gemini Imagen 3 |

**STEP 2: Title Selection**

User sees 5 AI-generated titles, each with:

- Title text (6-7 words ideal)
- Logline (1 sentence: target viewer + problem + insight + benefit)
- Framework used (e.g., "Contrarian Angle")
- Character count
- AI ranking (1-5) with reasoning

User action: Select 1 title via radio button, click "Continue"

**STEP 3: Outline Review**

User sees structured outline with:

- Section titles
- Estimated timing per section
- Key points to cover
- Build-up/release cycle markers

User actions:

- Edit section titles/content inline
- Reorder sections via drag-and-drop
- Add/remove sections
- Adjust timing
- Approve or request regeneration

**STEP 4: Script Review**

User sees final script after AI iterations, including:

- Full script text in editable field
- Word count and estimated duration
- Delivery cues ([PAUSE], [EMPHASIS])
- Iteration summary ("Refined through 2 critique rounds")

User actions:

- Edit script text directly
- Approve to continue

**STEP 5: Voiceover Selection**

| Option     | Description                                              |
| ---------- | -------------------------------------------------------- |
| AI Voice   | Select from ElevenLabs voice library with audio previews |
| Upload Own | Upload MP3/WAV/M4A file (max 50MB)                       |

**STEP 6: Thumbnail Selection**

User sees 3 AI-generated thumbnail images (1280x720):

- Large preview of each
- Text overlay shown
- Click to select

**STEP 7: Music Selection**

| Option     | Description                                                     |
| ---------- | --------------------------------------------------------------- |
| Library    | Choose from 20 curated royalty-free tracks with 30-sec previews |
| Upload Own | Upload MP3 file (max 20MB)                                      |

**STEP 8: Final Review & Generate**

Summary screen showing:

- Selected title
- Script preview (first 200 words)
- Selected thumbnail
- Voice selection
- Music selection
- Estimated cost (credits)
- Storyboard preview (script segments + matched footage thumbnails)

User action: "Generate Video" button

**STEP 9: Download**

After rendering completes (2-5 minutes):

- Video player preview
- Download buttons for each asset
- Copy buttons for text fields
- "Download All (ZIP)" button
- Cost breakdown display

### 3.4 Key User Interactions

#### 3.4.1 Title Selection Interface

```
┌─────────────────────────────────────────────────────────────────┐
│  SELECT YOUR TITLE                                               │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ○ RANK #1 - HIGHEST CLICK POTENTIAL                            │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │ "The One Number That Buys Your Freedom"                    │  │
│  │                                                            │  │
│  │ Logline: A 35-year-old professional discovers that         │  │
│  │ retiring early isn't about earning more—it's about the     │  │
│  │ 4% rule that lets you live off investments forever.        │  │
│  │                                                            │  │
│  │ Framework: The Only [X] You Need                           │  │
│  │ Characters: 42 | Predicted CTR: 8-10% (High)               │  │
│  └───────────────────────────────────────────────────────────┘  │
│                                                                  │
│  ○ RANK #2                                                       │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │ "Why I Stopped Picking Stocks (And Got Rich)"              │  │
│  │ ...                                                        │  │
│  └───────────────────────────────────────────────────────────┘  │
│                                                                  │
│  [Continue with Selected Title →]                                │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

#### 3.4.2 Progress Indicator

```
┌─────────────────────────────────────────────────────────────────┐
│  CREATING YOUR VIDEO                                             │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ✓ Title selected: "The One Number That Buys Your Freedom"       │
│  ✓ Outline approved                                              │
│                                                                  │
│  ⏳ Generating content... (65% complete)                         │
│     ├─ ✓ Thumbnail images ready                                  │
│     ├─ ⏳ Script generation in progress                          │
│     └─ ⏳ Refining script (Round 2 of 3)                         │
│                                                                  │
│  ○ Voice & Music selection                                       │
│  ○ Video rendering                                               │
│  ○ Download ready                                                │
│                                                                  │
│  Estimated time remaining: 2-3 minutes                           │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

#### 3.4.3 Download Interface

```
┌─────────────────────────────────────────────────────────────────┐
│  YOUR VIDEO IS READY! 🎉                                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌─────────────────────────────────────────┐                    │
│  │                                         │                    │
│  │           [VIDEO PLAYER]                │                    │
│  │                                         │                    │
│  │            ▶ 12:34                      │                    │
│  │                                         │                    │
│  └─────────────────────────────────────────┘                    │
│                                                                  │
│  DOWNLOADS                                                       │
│  ┌──────────────┬──────────────┬──────────────┬─────────────┐   │
│  │ [⬇] Video   │ [⬇] Thumb   │ [⬇] Script  │ [⬇] ALL    │   │
│  │    MP4      │    PNG       │    MD        │    ZIP      │   │
│  │   187 MB    │   245 KB     │   12 KB      │   188 MB    │   │
│  └──────────────┴──────────────┴──────────────┴─────────────┘   │
│                                                                  │
│  METADATA                                                        │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │ Title: The One Number That Buys Your Freedom        [📋]  │  │
│  └───────────────────────────────────────────────────────────┘  │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │ Description:                                         [📋]  │  │
│  │ Most people think retiring early means earning more...    │  │
│  │ [Show full description]                                   │  │
│  └───────────────────────────────────────────────────────────┘  │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │ Tags: personal finance, FIRE, 4% rule, index...     [📋]  │  │
│  └───────────────────────────────────────────────────────────┘  │
│                                                                  │
│  COST BREAKDOWN                                                  │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │ Content Generation:        $0.19                          │  │
│  │ Thumbnails:                $0.15                          │  │
│  │ Voiceover:                 $0.30                          │  │
│  │ Video Rendering:           $0.65                          │  │
│  │ ─────────────────────────────────                         │  │
│  │ Total Production Cost:     $1.29                          │  │
│  │ Your Price:                $4.00 (1 credit)               │  │
│  └───────────────────────────────────────────────────────────┘  │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 3.5 Responsive Design Strategy

| Breakpoint          | Primary Use              | Features                                     |
| ------------------- | ------------------------ | -------------------------------------------- |
| Desktop (1200px+)   | Full creation experience | All features, side-by-side layouts           |
| Tablet (768-1199px) | Creation + Review        | Stacked layouts, full functionality          |
| Mobile (< 768px)    | Review + Download        | View progress, download files, basic editing |

**Mobile Limitations (Phase 1):**

- Full video creation optimized for desktop
- Mobile: Review outputs, download files, monitor progress
- Complex editing (outline, script) requires desktop

---

## 4. Feature Requirements

### 4.1 Authentication & Account Management

#### 4.1.1 User Registration

**Feature ID:** AUTH-001
**Priority:** P0 (Critical)

**Description:** Allow new users to create accounts with email and password.

**Functional Requirements:**

| ID          | Requirement           | Acceptance Criteria                             |
| ----------- | --------------------- | ----------------------------------------------- |
| AUTH-001-01 | Email registration    | User can register with valid email address      |
| AUTH-001-02 | Password requirements | Minimum 8 characters, 1 uppercase, 1 number     |
| AUTH-001-03 | Email validation      | System validates email format before submission |
| AUTH-001-04 | Duplicate check       | System prevents duplicate email registration    |
| AUTH-001-05 | Terms acceptance      | User must accept Terms of Service to register   |

**User Interface:**

```
┌─────────────────────────────────────┐
│         CREATE YOUR ACCOUNT          │
├─────────────────────────────────────┤
│                                     │
│  Email                              │
│  ┌─────────────────────────────┐    │
│  │                             │    │
│  └─────────────────────────────┘    │
│                                     │
│  Password                           │
│  ┌─────────────────────────────┐    │
│  │ ●●●●●●●●                    │    │
│  └─────────────────────────────┘    │
│  ○ Show password                    │
│                                     │
│  Confirm Password                   │
│  ┌─────────────────────────────┐    │
│  │ ●●●●●●●●                    │    │
│  └─────────────────────────────┘    │
│                                     │
│  ☑ I agree to the Terms of Service  │
│                                     │
│  [      Create Account      ]       │
│                                     │
│  Already have an account? Log in    │
│                                     │
└─────────────────────────────────────┘
```

**Error Handling:**

| Error Condition       | User Message                                                           |
| --------------------- | ---------------------------------------------------------------------- |
| Invalid email format  | "Please enter a valid email address"                                   |
| Email already exists  | "An account with this email already exists. Log in instead?"           |
| Password too weak     | "Password must be at least 8 characters with 1 uppercase and 1 number" |
| Passwords don't match | "Passwords do not match"                                               |
| Terms not accepted    | "Please accept the Terms of Service to continue"                       |

---

#### 4.1.2 Email Verification

**Feature ID:** AUTH-002
**Priority:** P0 (Critical)

**Description:** Verify user email ownership before allowing full account access.

**Functional Requirements:**

| ID          | Requirement               | Acceptance Criteria                                    |
| ----------- | ------------------------- | ------------------------------------------------------ |
| AUTH-002-01 | Send verification email   | System sends email within 30 seconds of registration   |
| AUTH-002-02 | Verification link         | Link expires after 24 hours                            |
| AUTH-002-03 | Resend option             | User can request new verification email (max 3/hour)   |
| AUTH-002-04 | Unverified access         | Unverified users can log in but cannot create videos   |
| AUTH-002-05 | Verification confirmation | Clear success message after clicking verification link |

**Email Template:**

```
Subject: Verify your Itervel account

Hi [Name],

Thanks for signing up for Itervel!

Please verify your email address by clicking the link below:

[Verify Email Address]

This link expires in 24 hours.

If you didn't create this account, you can safely ignore this email.

— The Itervel Team
```

---

#### 4.1.3 User Login

**Feature ID:** AUTH-003
**Priority:** P0 (Critical)

**Description:** Allow registered users to securely access their accounts.

**Functional Requirements:**

| ID          | Requirement          | Acceptance Criteria                           |
| ----------- | -------------------- | --------------------------------------------- |
| AUTH-003-01 | Email/password login | User can log in with registered credentials   |
| AUTH-003-02 | Remember me          | Optional 30-day session persistence           |
| AUTH-003-03 | Failed attempts      | Lock account after 5 failed attempts (15 min) |
| AUTH-003-04 | Session management   | Session expires after 24 hours of inactivity  |
| AUTH-003-05 | Secure cookies       | HTTP-only, secure cookies for session tokens  |

---

#### 4.1.4 Password Reset

**Feature ID:** AUTH-004
**Priority:** P0 (Critical)

**Description:** Allow users to reset forgotten passwords.

**Functional Requirements:**

| ID          | Requirement     | Acceptance Criteria                        |
| ----------- | --------------- | ------------------------------------------ |
| AUTH-004-01 | Request reset   | User can request reset via email           |
| AUTH-004-02 | Reset link      | Single-use link expires after 1 hour       |
| AUTH-004-03 | Rate limiting   | Max 3 reset requests per email per hour    |
| AUTH-004-04 | Password update | User sets new password via reset form      |
| AUTH-004-05 | Notification    | User notified of password change via email |

---

#### 4.1.5 User Profile & Preferences

**Feature ID:** AUTH-005
**Priority:** P1 (High)

**Description:** Allow users to manage account settings and default preferences.

**Functional Requirements:**

| ID          | Requirement           | Acceptance Criteria                                         |
| ----------- | --------------------- | ----------------------------------------------------------- |
| AUTH-005-01 | View profile          | User can view email, join date, credit balance              |
| AUTH-005-02 | Change password       | User can update password with current password verification |
| AUTH-005-03 | Default preferences   | User can set default video length, speaking pace            |
| AUTH-005-04 | Notification settings | User can toggle email notifications                         |
| AUTH-005-05 | Delete account        | User can request account deletion (GDPR compliance)         |

**Default Preferences:**

| Preference                | Options      | Default    |
| ------------------------- | ------------ | ---------- |
| Default video length      | 5-20 minutes | 12 minutes |
| Speaking pace             | 140-200 wpm  | 165 wpm    |
| Default script iterations | 0-5          | 2          |
| Email notifications       | On/Off       | On         |

---

### 4.2 Project Management (Multi-Channel Support)

#### 4.2.1 Create Project

**Feature ID:** PROJ-001
**Priority:** P1 (High)

**Description:** Allow users to create separate projects for different YouTube channels or content categories.

**Functional Requirements:**

| ID          | Requirement      | Acceptance Criteria                             |
| ----------- | ---------------- | ----------------------------------------------- |
| PROJ-001-01 | Create project   | User can create named project                   |
| PROJ-001-02 | Project limit    | Free: 1 project, Paid: Unlimited                |
| PROJ-001-03 | Project settings | Each project has independent brand settings     |
| PROJ-001-04 | Default project  | User can set one project as default             |
| PROJ-001-05 | First project    | System auto-creates "My Channel" on first login |

**Project Settings Schema:**

```json
{
    "id": "uuid",
    "user_id": "uuid",
    "name": "Finance Channel",
    "is_default": true,
    "settings": {
        "brand_guide": "Text content or null",
        "target_audience": "35-55 year olds interested in retirement planning",
        "tone": "Professional but approachable",
        "thumbnail_style": "Clean, minimal text, green color scheme",
        "music_preference": "Calm, inspiring",
        "speaking_pace": 165,
        "default_video_length": 12
    },
    "created_at": "2026-01-15T10:30:00Z",
    "updated_at": "2026-01-15T10:30:00Z"
}
```

---

#### 4.2.2 Manage Projects

**Feature ID:** PROJ-002
**Priority:** P1 (High)

**Description:** Allow users to view, edit, and delete projects.

**Functional Requirements:**

| ID          | Requirement     | Acceptance Criteria                              |
| ----------- | --------------- | ------------------------------------------------ |
| PROJ-002-01 | List projects   | User sees all projects in dropdown/tabs          |
| PROJ-002-02 | Switch projects | User can switch active project from nav          |
| PROJ-002-03 | Edit project    | User can update name and settings                |
| PROJ-002-04 | Delete project  | User can delete project (warns about video loss) |
| PROJ-002-05 | Project videos  | Each project shows its video count and list      |

**Delete Warning:**

> "Deleting this project will also delete all 12 videos associated with it. This cannot be undone. Type the project name to confirm: [________]"

---

#### 4.2.3 Brand Guide Upload

**Feature ID:** PROJ-003
**Priority:** P2 (Medium)

**Description:** Allow users to upload a brand guide document to customize AI voice/tone.

**Functional Requirements:**

| ID          | Requirement        | Acceptance Criteria                                |
| ----------- | ------------------ | -------------------------------------------------- |
| PROJ-003-01 | File upload        | Accept .txt and .md files only (Phase 1)           |
| PROJ-003-02 | File size limit    | Maximum 100KB                                      |
| PROJ-003-03 | Parse content      | System extracts text content for AI context        |
| PROJ-003-04 | Template download  | User can download sample brand guide template      |
| PROJ-003-05 | Override per video | User can upload different guide for specific video |

**Sample Brand Guide Template:**

```markdown
# Brand Guide: [Channel Name]

## Voice & Tone

- Conversational but authoritative
- Use "you" and "we" (not "one" or formal language)
- Occasional humor, never sarcastic
- Empathetic to viewer struggles

## Target Audience

- Age: 25-45
- Interests: Personal finance, early retirement, investing
- Pain points: Overwhelmed by financial advice, scared of making mistakes
- Goals: Financial independence, peace of mind

## Content Pillars

1. Index fund investing
2. The 4% rule and retirement math
3. Lifestyle optimization for savings
4. Mindset and psychology of wealth

## Speaking Style

- Pace: 165 words per minute
- Pauses after key points
- Rhetorical questions to engage
- Personal stories to illustrate concepts

## Words to Use

- Freedom, independence, peace of mind
- Simple, straightforward, proven
- You, we, together

## Words to Avoid

- Get rich quick, easy money
- Guru, expert, secrets
- Complicated jargon without explanation
```

---

### 4.3 Video Creation Wizard

#### 4.3.1 Topic Input & Reference URL Processing

**Feature ID:** VID-001
**Priority:** P0 (Critical)

**Description:** Collect user input for video topic and optional reference materials.

**Functional Requirements:**

| ID         | Requirement        | Acceptance Criteria                           |
| ---------- | ------------------ | --------------------------------------------- |
| VID-001-01 | Topic input        | Required text field, 100-500 characters       |
| VID-001-02 | Reference URLs     | Optional, up to 3 URLs                        |
| VID-001-03 | URL validation     | System validates URL format and accessibility |
| VID-001-04 | URL processing     | Extract content from articles (web scraping)  |
| VID-001-05 | YouTube URLs       | Extract transcript from YouTube videos        |
| VID-001-06 | Processing timeout | 30 seconds per URL, fail gracefully           |
| VID-001-07 | Content caching    | Cache extracted content for re-use            |

**URL Processing Logic:**

```
┌─────────────────────────────────────────────────────────────────┐
│                    URL PROCESSING FLOW                           │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  1. Receive URL                                                  │
│     │                                                            │
│     ▼                                                            │
│  2. Check cache (same URL processed before?)                     │
│     │                                                            │
│     ├─ YES → Return cached content                               │
│     │                                                            │
│     ├─ NO → Continue                                             │
│     │                                                            │
│     ▼                                                            │
│  3. Identify URL type                                            │
│     │                                                            │
│     ├─ YouTube video → Use youtube-transcript-api                │
│     │                                                            │
│     ├─ Article → Use Readability.js / Beautiful Soup             │
│     │                                                            │
│     └─ Unknown → Return error                                    │
│     │                                                            │
│     ▼                                                            │
│  4. Extract content (30 sec timeout)                             │
│     │                                                            │
│     ├─ Success → Extract key concepts (bullet points)            │
│     │            Store in database                               │
│     │            Return to user flow                             │
│     │                                                            │
│     └─ Failure → Show warning, continue without URL              │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

**Extracted Content Format:**

```json
{
    "url": "https://example.com/article",
    "type": "article",
    "title": "Why Index Funds Beat Stock Picking",
    "extracted_at": "2026-01-15T10:30:00Z",
    "key_concepts": [
        "95% of actively managed funds underperform index funds over 15 years",
        "Lower fees compound to significant savings over time",
        "Diversification reduces risk without sacrificing returns",
        "Set-and-forget strategy reduces emotional investing mistakes"
    ],
    "word_count": 1847,
    "author": "Jane Smith",
    "source_domain": "investopedia.com"
}
```

---

#### 4.3.2 AI Model Selection

**Feature ID:** VID-002
**Priority:** P1 (High)

**Description:** Allow users to select AI models for each generation step.

**Functional Requirements:**

| ID         | Requirement             | Acceptance Criteria                               |
| ---------- | ----------------------- | ------------------------------------------------- |
| VID-002-01 | Simple mode             | "Use recommended settings" checkbox (all Sonnet)  |
| VID-002-02 | Custom mode             | Dropdown for each step when simple mode unchecked |
| VID-002-03 | Cost preview            | Show estimated cost for selected configuration    |
| VID-002-04 | Speed/quality indicator | Show rating for each model option                 |
| VID-002-05 | Save preference         | Remember last used configuration                  |

**Available Models:**

| Step               | Options             | Default         | Cost Estimate    |
| ------------------ | ------------------- | --------------- | ---------------- |
| Title Generation   | Sonnet, Opus, GPT-4 | Sonnet          | $0.01-0.03       |
| Outline Generation | Sonnet, Opus, GPT-4 | Opus            | $0.03-0.05       |
| Script Writing     | Sonnet, Opus, GPT-4 | Sonnet          | $0.03-0.05       |
| Script Critique    | Sonnet, Opus, GPT-4 | Sonnet          | $0.02/round      |
| Thumbnail Concepts | Sonnet, Opus, GPT-4 | Sonnet          | $0.02            |
| Thumbnail Images   | Gemini Imagen 3     | Gemini Imagen 3 | $0.15 (3 images) |
| Metadata           | Sonnet, Opus, GPT-4 | Sonnet          | $0.01            |

---

#### 4.3.3 Iteration Configuration

**Feature ID:** VID-003
**Priority:** P1 (High)

**Description:** Allow users to configure the number of AI critique/refinement rounds.

**Functional Requirements:**

| ID         | Requirement           | Acceptance Criteria                              |
| ---------- | --------------------- | ------------------------------------------------ |
| VID-003-01 | Script iterations     | Slider or dropdown: 0-5 rounds                   |
| VID-003-02 | Iteration explanation | Tooltip explaining what iterations do            |
| VID-003-03 | Cost impact           | Show additional cost per iteration               |
| VID-003-04 | Free tier limit       | Free tier: max 1 iteration                       |
| VID-003-05 | Warning at 3+         | "More iterations increase quality but also cost" |

---

### 4.4 Content Generation Pipeline

#### 4.4.1 Title Generation

**Feature ID:** CONTENT-001
**Priority:** P0 (Critical)

**Description:** Generate 5 title options with loglines, ranked by click potential.

**Functional Requirements:**

| ID             | Requirement       | Acceptance Criteria                   |
| -------------- | ----------------- | ------------------------------------- |
| CONTENT-001-01 | Generate 5 titles | AI produces exactly 5 title options   |
| CONTENT-001-02 | Include loglines  | Each title has 1-sentence logline     |
| CONTENT-001-03 | Use frameworks    | Apply proven title frameworks         |
| CONTENT-001-04 | AI ranking        | Rank 1-5 by predicted CTR             |
| CONTENT-001-05 | Character count   | Show character count (target: 40-60)  |
| CONTENT-001-06 | User selection    | Radio button selection, single choice |

**Title Frameworks:**

| Framework      | Template                          | Example                                       |
| -------------- | --------------------------------- | --------------------------------------------- |
| Ultimate Guide | "The Ultimate Guide to [X]"       | "The Ultimate Guide to Index Fund Investing"  |
| Contrarian     | "Why [Surprising Fact]"           | "Why I Stopped Picking Stocks (And Got Rich)" |
| Only X         | "The Only [X] You Need"           | "The Only Number That Buys Your Freedom"      |
| How I          | "How I [Result] by [Method]"      | "How I Retired at 40 by Ignoring Wall Street" |
| Number-based   | "[Number] [Things] That [Result]" | "7 Investing Mistakes That Keep You Poor"     |

**Output Schema:**

```json
{
    "titles": [
        {
            "rank": 1,
            "title": "The One Number That Buys Your Freedom",
            "logline": "A 35-year-old professional discovers that retiring early isn't about earning more—it's about the 4% rule that lets you live off investments forever.",
            "framework": "The Only [X] You Need",
            "character_count": 42,
            "predicted_ctr": "8-10%",
            "why_it_works": "Creates strong curiosity gap ('what number?'), promises specific benefit (freedom), uses power word ('only')"
        }
    ]
}
```

---

#### 4.4.2 Outline Creation

**Feature ID:** CONTENT-002
**Priority:** P0 (Critical)

**Description:** Generate structured video outline with timing and section details.

**Functional Requirements:**

| ID             | Requirement        | Acceptance Criteria                              |
| -------------- | ------------------ | ------------------------------------------------ |
| CONTENT-002-01 | Generate outline   | AI creates outline from selected title + logline |
| CONTENT-002-02 | Section structure  | Hook (30 sec) + 3-5 main sections + closing      |
| CONTENT-002-03 | Timing estimates   | Each section has estimated duration              |
| CONTENT-002-04 | Key points         | Bullet points for each section's content         |
| CONTENT-002-05 | Engagement markers | Note build-up/release cycles                     |
| CONTENT-002-06 | Editable           | User can edit sections inline                    |
| CONTENT-002-07 | Reorderable        | Drag-and-drop section reordering                 |
| CONTENT-002-08 | Add/remove         | User can add or remove sections                  |

**Outline Schema:**

```json
{
    "video_id": "uuid",
    "title": "The One Number That Buys Your Freedom",
    "total_duration_minutes": 12,
    "sections": [
        {
            "id": "section-1",
            "type": "hook",
            "title": "The Hook",
            "duration_seconds": 30,
            "key_points": [
                "Open with surprising stat: 95% of investors fail to beat the market",
                "Tease 'one number' that changes everything",
                "Promise: By end of video, exact action to take"
            ],
            "engagement_note": "High energy, fast cuts"
        },
        {
            "id": "section-2",
            "type": "main",
            "title": "The Problem with Stock Picking",
            "duration_seconds": 180,
            "key_points": [
                "Why most investors pick individual stocks",
                "The data: Active funds vs index funds",
                "Personal story: My stock picking disasters"
            ],
            "engagement_note": "Build-up: Create tension about common approach"
        }
    ]
}
```

---

#### 4.4.3 Script Writing

**Feature ID:** CONTENT-003
**Priority:** P0 (Critical)

**Description:** Generate full conversational script from approved outline.

**Functional Requirements:**

| ID             | Requirement       | Acceptance Criteria                          |
| -------------- | ----------------- | -------------------------------------------- |
| CONTENT-003-01 | Generate script   | AI writes 2,000-2,500 word script            |
| CONTENT-003-02 | Follow outline    | Script adheres to approved outline structure |
| CONTENT-003-03 | Match brand voice | Use brand guide if provided, else defaults   |
| CONTENT-003-04 | Delivery cues     | Include [PAUSE], [BEAT], [EMPHASIS] markers  |
| CONTENT-003-05 | Speaking pace     | Target 165-170 words per minute              |
| CONTENT-003-06 | Transform sources | Create new examples, don't copy references   |
| CONTENT-003-07 | Copyright safe    | Max 15 words per quote, one quote per source |

**Script Output Format:**

```markdown
# The One Number That Buys Your Freedom

## HOOK (0:00 - 0:30)

Here's a number that might surprise you: [PAUSE] 95%. That's the percentage of actively managed investment funds that fail to beat a simple index fund over 15 years.

Think about that for a second. [BEAT]

Ninety-five percent of the so-called experts, with their fancy degrees and Bloomberg terminals, can't beat a strategy you could set up in ten minutes.

And today, I'm going to show you the one number that makes this possible—the number that could change your entire financial future.

## SECTION 1: THE PROBLEM (0:30 - 3:00)

Let me tell you about a mistake I made seven years ago...

[Script continues...]
```

---

#### 4.4.4 Script Critique & Refinement

**Feature ID:** CONTENT-004
**Priority:** P0 (Critical)

**Description:** AI critiques script from audience perspective and applies fixes iteratively.

**Functional Requirements:**

| ID             | Requirement          | Acceptance Criteria                      |
| -------------- | -------------------- | ---------------------------------------- |
| CONTENT-004-01 | Audience perspective | Critique from target viewer, not expert  |
| CONTENT-004-02 | Identify weak points | "Where would viewers click away?"        |
| CONTENT-004-03 | Prioritize issues    | High/Medium/Low impact rating            |
| CONTENT-004-04 | Provide fixes        | Specific improvement suggestions         |
| CONTENT-004-05 | Apply fixes          | AI revises script based on critique      |
| CONTENT-004-06 | Iteration tracking   | Track version history (v1, v2, v3...)    |
| CONTENT-004-07 | Progress display     | "Refining script... Round 2 of 3"        |
| CONTENT-004-08 | Force stop at 5      | Maximum 5 rounds, then user must proceed |

**Critique Output Schema:**

```json
{
    "round": 2,
    "issues": [
        {
            "priority": "high",
            "location": "Hook, line 3",
            "issue": "The promise is vague—'change your financial future' could mean anything",
            "viewer_impact": "Viewers may not feel compelled to watch if they don't know what they'll learn",
            "suggested_fix": "Make the promise specific: 'By the end of this video, you'll know the exact percentage you need to save and how long until you can retire'"
        },
        {
            "priority": "medium",
            "location": "Section 2, paragraph 4",
            "issue": "Too much data without a story to anchor it",
            "viewer_impact": "Data dumps cause viewer fatigue around the 3-minute mark",
            "suggested_fix": "Lead with the personal story, then use data to validate"
        }
    ],
    "overall_assessment": "Script has strong structure but needs more specific promises in hook and better story/data balance in Section 2"
}
```

---

#### 4.4.5 Metadata Generation

**Feature ID:** CONTENT-005
**Priority:** P1 (High)

**Description:** Generate YouTube-optimized title, description, and tags.

**Functional Requirements:**

| ID             | Requirement            | Acceptance Criteria                   |
| -------------- | ---------------------- | ------------------------------------- |
| CONTENT-005-01 | Title (from selection) | Use user-selected title               |
| CONTENT-005-02 | Description            | 150-300 words, SEO-optimized          |
| CONTENT-005-03 | Tags                   | 10-15 relevant tags                   |
| CONTENT-005-04 | Timestamps             | Optional chapter markers from outline |
| CONTENT-005-05 | Editable               | All fields editable by user           |
| CONTENT-005-06 | Copy buttons           | One-click copy for each field         |

**Description Structure:**

```
[2-3 sentence summary of video's main insight]

In this video, you'll learn:
• [Key takeaway 1]
• [Key takeaway 2]
• [Key takeaway 3]

TIMESTAMPS:
0:00 - Introduction
0:45 - [Section 1 Title]
3:20 - [Section 2 Title]
...

🔔 Subscribe for more [niche] content: [channel link]

📚 Resources mentioned:
• [Resource 1]
• [Resource 2]

#tag1 #tag2 #tag3
```

---

### 4.5 Asset Generation

#### 4.5.1 Thumbnail Concept Generation

**Feature ID:** ASSET-001
**Priority:** P0 (Critical)

**Description:** Generate 5 thumbnail concepts, critique and select top 3.

**Functional Requirements:**

| ID           | Requirement         | Acceptance Criteria                            |
| ------------ | ------------------- | ---------------------------------------------- |
| ASSET-001-01 | Generate 5 concepts | AI creates 5 detailed thumbnail descriptions   |
| ASSET-001-02 | Parallel processing | Runs alongside outline/script generation       |
| ASSET-001-03 | Concept details     | Text overlay, visual elements, colors, emotion |
| ASSET-001-04 | AI critique         | Evaluate from "scrolling viewer" perspective   |
| ASSET-001-05 | Select top 3        | AI picks 3 most likely to get clicks           |
| ASSET-001-06 | Refinement notes    | Provide improvement suggestions for selected 3 |

**Thumbnail Concept Schema:**

```json
{
    "concept_id": "thumb-1",
    "text_overlay": "THE 4% RULE",
    "visual_elements": {
        "main_subject": "Stack of $100 bills fanning out",
        "background": "Soft gradient, dark blue to green",
        "secondary_elements": ["Small calendar icon", "Upward arrow"]
    },
    "color_scheme": ["#1a365d", "#38a169", "#ffffff"],
    "emotion": "Curiosity and aspiration",
    "text_position": "Upper left, large bold",
    "critique": {
        "strengths": [
            "Clear value proposition",
            "Money visual grabs attention"
        ],
        "weaknesses": [
            "Generic stock photo feel",
            "Text could be more intriguing"
        ],
        "improvement": "Replace stack of bills with more unique visual—perhaps a calendar with a circled date"
    }
}
```

---

#### 4.5.2 Thumbnail Image Generation

**Feature ID:** ASSET-002
**Priority:** P0 (Critical)

**Description:** Generate 3 thumbnail images from refined concepts using Gemini Imagen 3.

**Functional Requirements:**

| ID           | Requirement        | Acceptance Criteria                     |
| ------------ | ------------------ | --------------------------------------- |
| ASSET-002-01 | Generate 3 images  | Create 3 thumbnails from top 3 concepts |
| ASSET-002-02 | YouTube specs      | 1280x720 pixels, 16:9 aspect ratio      |
| ASSET-002-03 | File format        | PNG format                              |
| ASSET-002-04 | Mobile readability | Text must be readable on mobile         |
| ASSET-002-05 | User selection     | Large preview, single selection         |
| ASSET-002-06 | Regenerate option  | User can request regeneration           |

**Image Generation Prompt Template:**

```
Create a YouTube thumbnail image with the following specifications:

DIMENSIONS: 1280x720 pixels, 16:9 aspect ratio

TEXT OVERLAY: "[Text from concept]"
- Font: Bold, sans-serif
- Size: Large enough to read on mobile (minimum 72pt equivalent)
- Position: [Position from concept]
- Color: High contrast with background

VISUAL ELEMENTS:
- Main subject: [Main subject from concept]
- Background: [Background from concept]
- Secondary elements: [Secondary elements]

COLOR SCHEME: [Colors from concept]

STYLE: Professional YouTube thumbnail, eye-catching, would stop someone scrolling

DO NOT include: watermarks, borders, low-resolution elements
```

---

#### 4.5.3 Voiceover Generation

**Feature ID:** ASSET-003
**Priority:** P0 (Critical)

**Description:** Generate AI voiceover or process user-uploaded audio.

**Functional Requirements:**

| ID           | Requirement            | Acceptance Criteria                     |
| ------------ | ---------------------- | --------------------------------------- |
| ASSET-003-01 | ElevenLabs integration | Full voice library access               |
| ASSET-003-02 | Voice preview          | 10-second sample before selection       |
| ASSET-003-03 | Generate audio         | Convert script to MP3                   |
| ASSET-003-04 | User upload option     | Accept MP3, WAV, M4A, AAC               |
| ASSET-003-05 | Upload validation      | Max 50MB, duration check                |
| ASSET-003-06 | Audio normalization    | Consistent volume via FFmpeg            |
| ASSET-003-07 | Duration detection     | Extract exact duration for video timing |

**Voice Selection Interface:**

```
┌─────────────────────────────────────────────────────────────────┐
│  SELECT VOICEOVER                                                │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ○ AI Voice                                                      │
│    ┌─────────────────────────────────────────────────────────┐  │
│    │  Voice Library                                          │  │
│    │  ┌─────────────────────────────────────────────────┐    │  │
│    │  │ Adam - Professional, American Male    [▶ Play]  │    │  │
│    │  │ Bella - Warm, American Female         [▶ Play]  │    │  │
│    │  │ Charlie - British, Authoritative      [▶ Play]  │    │  │
│    │  │ Diana - Friendly, Conversational      [▶ Play]  │    │  │
│    │  │ ...                                             │    │  │
│    │  └─────────────────────────────────────────────────┘    │  │
│    └─────────────────────────────────────────────────────────┘  │
│                                                                  │
│  ○ Upload My Own Recording                                       │
│    ┌─────────────────────────────────────────────────────────┐  │
│    │  [Drag file here or click to browse]                    │  │
│    │  Accepted: MP3, WAV, M4A, AAC (max 50MB)                │  │
│    └─────────────────────────────────────────────────────────┘  │
│                                                                  │
│  [Continue →]                                                    │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

#### 4.5.4 Music Selection

**Feature ID:** ASSET-004
**Priority:** P1 (High)

**Description:** Select background music from library or upload custom track.

**Functional Requirements:**

| ID           | Requirement      | Acceptance Criteria                        |
| ------------ | ---------------- | ------------------------------------------ |
| ASSET-004-01 | Music library    | 20 curated royalty-free tracks             |
| ASSET-004-02 | Mood categories  | Upbeat, Calm, Dramatic, Inspiring, Neutral |
| ASSET-004-03 | Preview playback | 30-second preview for each track           |
| ASSET-004-04 | User upload      | Accept MP3 (max 20MB)                      |
| ASSET-004-05 | Volume mixing    | Music at -15dB to -20dB below voiceover    |
| ASSET-004-06 | No music option  | User can opt out of background music       |

**Music Library (Sample):**

| Track Name      | Mood      | Duration | BPM |
| --------------- | --------- | -------- | --- |
| Morning Clarity | Inspiring | 3:45     | 90  |
| Forward Motion  | Upbeat    | 4:20     | 120 |
| Deep Focus      | Calm      | 5:00     | 70  |
| Rising Action   | Dramatic  | 3:30     | 100 |
| Steady Progress | Neutral   | 4:00     | 85  |

---

#### 4.5.5 Stock Footage Selection

**Feature ID:** ASSET-005
**Priority:** P0 (Critical)

**Description:** Automatically match stock footage to script segments.

**Functional Requirements:**

| ID           | Requirement         | Acceptance Criteria                       |
| ------------ | ------------------- | ----------------------------------------- |
| ASSET-005-01 | Script segmentation | Split script into 3-5 thematic segments   |
| ASSET-005-02 | Keyword extraction  | Extract search keywords per segment       |
| ASSET-005-03 | Footage search      | Search Pexels and Pixabay APIs            |
| ASSET-005-04 | Clip selection      | AI selects clips to fill segment duration |
| ASSET-005-05 | Pattern interrupts  | Target 3-6 seconds per clip               |
| ASSET-005-06 | Fallback search     | Use broader terms if no results           |
| ASSET-005-07 | Store URLs only     | Don't download until render time          |

**Footage Planning Output:**

```json
{
    "segments": [
        {
            "segment_id": "seg-1",
            "title": "The Hook",
            "duration_seconds": 30,
            "keywords": ["stock market", "investing", "money growth"],
            "clips": [
                {
                    "source": "pexels",
                    "video_id": "123456",
                    "url": "https://videos.pexels.com/...",
                    "duration": 5,
                    "start_time": 0,
                    "description": "Stock market ticker scrolling"
                },
                {
                    "source": "pixabay",
                    "video_id": "789012",
                    "url": "https://pixabay.com/...",
                    "duration": 6,
                    "start_time": 5,
                    "description": "Person looking at financial charts"
                }
            ]
        }
    ]
}
```

---

### 4.6 Video Assembly & Rendering

#### 4.6.1 Storyboard Preview

**Feature ID:** RENDER-001
**Priority:** P1 (High)

**Description:** Show visual preview of video before final render.

**Functional Requirements:**

| ID            | Requirement           | Acceptance Criteria                                  |
| ------------- | --------------------- | ---------------------------------------------------- |
| RENDER-001-01 | Segment display       | Show script segments with matched footage thumbnails |
| RENDER-001-02 | Timeline view         | Visual representation of clip timing                 |
| RENDER-001-03 | Clip preview          | Hover to see footage thumbnail                       |
| RENDER-001-04 | Confirm before render | User must approve storyboard                         |
| RENDER-001-05 | No actual rendering   | Uses thumbnail images, not video                     |

---

#### 4.6.2 Shotstack Integration

**Feature ID:** RENDER-002
**Priority:** P0 (Critical)

**Description:** Render final video using Shotstack cloud API.

**Functional Requirements:**

| ID            | Requirement       | Acceptance Criteria                      |
| ------------- | ----------------- | ---------------------------------------- |
| RENDER-002-01 | Build timeline    | Construct Shotstack JSON from all assets |
| RENDER-002-02 | Submit render     | POST to Shotstack API                    |
| RENDER-002-03 | Track progress    | Poll for status or use webhook           |
| RENDER-002-04 | Handle completion | Download rendered video to storage       |
| RENDER-002-05 | Handle failure    | Retry once, then alert user              |
| RENDER-002-06 | Webhook support   | Process Shotstack completion callbacks   |

**Output Specifications:**

| Spec                | Value                   |
| ------------------- | ----------------------- |
| Resolution          | 1920x1080 (1080p)       |
| Codec               | H.264                   |
| Format              | MP4                     |
| Aspect Ratio        | 16:9                    |
| Max Duration        | 20 minutes              |
| Estimated File Size | 100-200MB for 10-15 min |
| Render Time         | 2-5 minutes             |

---

### 4.7 Output Delivery

#### 4.7.1 Download Package

**Feature ID:** DELIVERY-001
**Priority:** P0 (Critical)

**Description:** Provide complete downloadable package of all outputs.

**Functional Requirements:**

| ID              | Requirement        | Acceptance Criteria                         |
| --------------- | ------------------ | ------------------------------------------- |
| DELIVERY-001-01 | Video download     | MP4 file, direct download link              |
| DELIVERY-001-02 | Thumbnail download | PNG file, 1280x720                          |
| DELIVERY-001-03 | Script download    | Markdown file                               |
| DELIVERY-001-04 | ZIP package        | All files in single download                |
| DELIVERY-001-05 | Copy metadata      | One-click copy for title, description, tags |
| DELIVERY-001-06 | Streaming preview  | In-browser video player                     |

**Download Package Contents:**

```
video_[title]_[date].zip
├── video.mp4           (Final rendered video)
├── thumbnail.png       (Selected thumbnail)
├── script.md           (Final script with delivery cues)
└── metadata.txt        (Title, description, tags)
```

---

### 4.8 Video Library & History

#### 4.8.1 Video List

**Feature ID:** LIBRARY-001
**Priority:** P1 (High)

**Description:** Display all user videos with status and actions.

**Functional Requirements:**

| ID             | Requirement       | Acceptance Criteria                  |
| -------------- | ----------------- | ------------------------------------ |
| LIBRARY-001-01 | List all videos   | Show videos in current project       |
| LIBRARY-001-02 | Status badges     | Draft, Processing, Completed, Failed |
| LIBRARY-001-03 | Sort options      | Date created, title, status          |
| LIBRARY-001-04 | Thumbnail preview | Show thumbnail for completed videos  |
| LIBRARY-001-05 | Quick actions     | View, Download, Delete               |
| LIBRARY-001-06 | Pagination        | 20 videos per page                   |

**Video Card Display:**

```
┌─────────────────────────────────────────────────────────────────┐
│  ┌─────────────┐  The One Number That Buys Your Freedom         │
│  │             │  12:34 • Created Jan 15, 2026                   │
│  │  [THUMB]    │  ┌──────────┐                                   │
│  │             │  │ COMPLETED │                                   │
│  └─────────────┘  └──────────┘                                   │
│                   [View] [Download] [Delete]                     │
└─────────────────────────────────────────────────────────────────┘
```

---

#### 4.8.2 Retention Policy

**Feature ID:** LIBRARY-002
**Priority:** P2 (Medium)

**Description:** Manage video file storage with time-based retention.

**Functional Requirements:**

| ID             | Requirement         | Acceptance Criteria                          |
| -------------- | ------------------- | -------------------------------------------- |
| LIBRARY-002-01 | Free tier retention | 30 days file storage                         |
| LIBRARY-002-02 | Paid tier retention | 90 days file storage                         |
| LIBRARY-002-03 | Expiration warning  | Email 7 days before deletion                 |
| LIBRARY-002-04 | Metadata preserved  | Keep project data after file deletion        |
| LIBRARY-002-05 | Re-download         | Allow re-generation at cost if files expired |

---

### 4.9 Pricing & Payments

#### 4.9.1 Credit System

**Feature ID:** PAY-001
**Priority:** P0 (Critical)

**Description:** Manage user credits for video generation.

**Functional Requirements:**

| ID         | Requirement          | Acceptance Criteria                 |
| ---------- | -------------------- | ----------------------------------- |
| PAY-001-01 | Credit balance       | Display current balance in header   |
| PAY-001-02 | Credit deduction     | Deduct 1 credit on video completion |
| PAY-001-03 | Credit refund        | Auto-refund on generation failure   |
| PAY-001-04 | Free credit          | New users get 1 free credit         |
| PAY-001-05 | Low balance warning  | Alert at 1 credit remaining         |
| PAY-001-06 | Insufficient credits | Block generation if 0 credits       |

---

#### 4.9.2 Credit Packages

**Feature ID:** PAY-002
**Priority:** P0 (Critical)

**Description:** Offer credit packages for purchase.

**Packages:**

| Package    | Credits | Price | Per Credit | Discount |
| ---------- | ------- | ----- | ---------- | -------- |
| Starter    | 5       | $20   | $4.00      | —        |
| Popular    | 15      | $50   | $3.33      | 17%      |
| Best Value | 35      | $100  | $2.86      | 29%      |

---

#### 4.9.3 Stripe Integration

**Feature ID:** PAY-003
**Priority:** P0 (Critical)

**Description:** Process payments securely via Stripe.

**Functional Requirements:**

| ID         | Requirement         | Acceptance Criteria                      |
| ---------- | ------------------- | ---------------------------------------- |
| PAY-003-01 | Checkout session    | Redirect to Stripe checkout              |
| PAY-003-02 | Success handling    | Add credits on successful payment        |
| PAY-003-03 | Webhook processing  | Handle payment.succeeded, payment.failed |
| PAY-003-04 | Receipt email       | Stripe sends receipt to user             |
| PAY-003-05 | Transaction history | Store all transactions in database       |
| PAY-003-06 | Refund support      | Admin can issue refunds via Stripe       |

---

#### 4.9.4 Free Tier Limitations

**Feature ID:** PAY-004
**Priority:** P1 (High)

**Description:** Define limitations for free trial video.

**Free Tier Restrictions:**

| Feature           | Free Tier | Paid Tier                |
| ----------------- | --------- | ------------------------ |
| Video count       | 1         | Unlimited (with credits) |
| Max duration      | 1 minute  | 20 minutes               |
| Script iterations | 1         | Up to 5                  |
| Watermark         | Yes       | No                       |
| Projects          | 1         | Unlimited                |
| File retention    | 30 days   | 90 days                  |

**Watermark Specification:**

- Position: Bottom right corner
- Content: "Made with Itervel"
- Opacity: 50%
- Size: Small, non-intrusive

---

## 5. Technical Architecture

### 5.1 System Overview

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                       ITERVEL ARCHITECTURE                        │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌─────────────────┐         ┌─────────────────┐         ┌──────────────┐  │
│  │   FRONTEND      │         │   BACKEND       │         │   STORAGE    │  │
│  │   (React)       │◄───────►│   (Laravel)     │◄───────►│   (S3/R2)    │  │
│  │                 │  REST   │                 │         │              │  │
│  │  - Dashboard    │  API    │  - Auth         │         │  - Videos    │  │
│  │  - Wizard       │         │  - Projects     │         │  - Thumbs    │  │
│  │  - Library      │         │  - Videos       │         │  - Audio     │  │
│  │  - Settings     │         │  - Generation   │         │  - Scripts   │  │
│  └─────────────────┘         │  - Payments     │         └──────────────┘  │
│                              └────────┬────────┘                            │
│                                       │                                     │
│                    ┌──────────────────┼──────────────────┐                  │
│                    │                  │                  │                  │
│            ┌───────▼───────┐  ┌───────▼───────┐  ┌───────▼───────┐         │
│            │   AI APIs     │  │  Media APIs   │  │   Payment     │         │
│            │               │  │               │  │               │         │
│            │ - Anthropic   │  │ - ElevenLabs  │  │ - Stripe      │         │
│            │ - OpenAI      │  │ - Shotstack   │  │               │         │
│            │ - Google AI   │  │ - Pexels      │  │               │         │
│            │               │  │ - Pixabay     │  │               │         │
│            └───────────────┘  └───────────────┘  └───────────────┘         │
│                                                                              │
│  ┌─────────────────┐         ┌─────────────────┐                           │
│  │   DATABASE      │         │   CACHE/QUEUE   │                           │
│  │   (MariaDB)     │         │   (Redis)       │                           │
│  │                 │         │                 │                           │
│  │  - Users        │         │  - Sessions     │                           │
│  │  - Projects     │         │  - Job Queue    │                           │
│  │  - Videos       │         │  - API Cache    │                           │
│  │  - API Calls    │         │                 │                           │
│  └─────────────────┘         └─────────────────┘                           │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 5.2 Technology Stack

#### 5.2.1 Frontend

| Component        | Technology      | Version | Justification                        |
| ---------------- | --------------- | ------- | ------------------------------------ |
| Framework        | React           | 18.x    | Industry standard, large ecosystem   |
| Build Tool       | Vite            | 5.x     | Fast builds, modern tooling          |
| Styling          | Tailwind CSS    | 3.x     | Rapid development, consistent design |
| State Management | Zustand         | 4.x     | Simple, TypeScript-friendly          |
| Routing          | React Router    | 6.x     | Standard routing solution            |
| Forms            | React Hook Form | 7.x     | Performant, validation support       |
| File Upload      | React Dropzone  | 14.x    | Drag-and-drop support                |
| HTTP Client      | Axios           | 1.x     | Request/response interceptors        |
| Type Safety      | TypeScript      | 5.x     | Catch errors at compile time         |

#### 5.2.2 Backend

| Component | Technology      | Version | Justification                  |
| --------- | --------------- | ------- | ------------------------------ |
| Framework | Laravel         | 11.x    | Robust, mature, excellent DX   |
| Language  | PHP             | 8.2+    | Modern features, type hints    |
| Database  | MariaDB         | 10.x    | MySQL-compatible, A2 available |
| Cache     | Redis           | 7.x     | Fast caching, job queues       |
| Queue     | Laravel Queues  | —       | Background job processing      |
| Auth      | Laravel Sanctum | —       | SPA authentication             |
| Storage   | Flysystem       | —       | S3/R2 abstraction              |

#### 5.2.3 Infrastructure

| Component       | Technology        | Justification                    |
| --------------- | ----------------- | -------------------------------- |
| Web Hosting     | A2 Shared Hosting | Cost-effective, Laravel support  |
| File Storage    | Cloudflare R2     | S3-compatible, low egress cost   |
| CDN             | Cloudflare        | Global delivery, DDoS protection |
| Video Rendering | Shotstack API     | Cloud rendering, no infra needed |
| Error Tracking  | Sentry            | Real-time error monitoring       |
| CI/CD           | GitHub Actions    | Automated testing and deployment |

### 5.3 Data Model

#### 5.3.1 Entity Relationship Diagram

```
┌─────────────┐       ┌─────────────┐       ┌─────────────┐
│   USERS     │       │  PROJECTS   │       │   VIDEOS    │
├─────────────┤       ├─────────────┤       ├─────────────┤
│ id          │◄──┐   │ id          │◄──┐   │ id          │
│ email       │   │   │ user_id     │───┘   │ project_id  │───┐
│ password    │   │   │ name        │       │ user_id     │   │
│ credits     │   │   │ settings    │       │ title       │   │
│ preferences │   │   │ is_default  │       │ status      │   │
│ verified_at │   │   │ created_at  │       │ input_json  │   │
│ created_at  │   └───│ updated_at  │       │ outputs_json│   │
│ updated_at  │       └─────────────┘       │ ai_models   │   │
└─────────────┘                             │ created_at  │   │
                                            │ updated_at  │   │
                                            └─────────────┘   │
                                                    │         │
                    ┌───────────────────────────────┘         │
                    │                                         │
                    ▼                                         │
┌─────────────────────────────┐    ┌─────────────────────────▼─┐
│       GENERATIONS           │    │        API_CALLS          │
├─────────────────────────────┤    ├───────────────────────────┤
│ id                          │    │ id                        │
│ video_id                    │    │ video_id                  │
│ step (title/outline/script) │    │ generation_id             │
│ version                     │    │ step                      │
│ status                      │    │ provider                  │
│ input                       │    │ model                     │
│ output                      │    │ input_tokens              │
│ cost                        │    │ output_tokens             │
│ created_at                  │    │ cost_usd                  │
└─────────────────────────────┘    │ status                    │
                                   │ response_time_ms          │
┌─────────────────────────────┐    │ error_message             │
│       TRANSACTIONS          │    │ created_at                │
├─────────────────────────────┤    └───────────────────────────┘
│ id                          │
│ user_id                     │    ┌───────────────────────────┐
│ amount                      │    │          FILES            │
│ type (purchase/deduction)   │    ├───────────────────────────┤
│ description                 │    │ id                        │
│ stripe_id                   │    │ video_id                  │
│ created_at                  │    │ type (video/thumb/audio)  │
└─────────────────────────────┘    │ storage_path              │
                                   │ size_bytes                │
                                   │ expires_at                │
                                   │ created_at                │
                                   └───────────────────────────┘
```

#### 5.3.2 Database Schema

**Users Table:**

```sql
CREATE TABLE users (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  email VARCHAR(255) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  email_verified_at TIMESTAMP NULL,
  credits_balance INT UNSIGNED DEFAULT 1,
  preferences_json JSON,
  remember_token VARCHAR(100),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_email (email)
);
```

**Projects Table:**

```sql
CREATE TABLE projects (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  settings_json JSON,
  is_default BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_user_id (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

**Videos Table:**

```sql
CREATE TABLE videos (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  project_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(500),
  status ENUM('draft', 'processing', 'completed', 'failed') DEFAULT 'draft',
  current_step VARCHAR(50),
  input_json JSON NOT NULL,
  outputs_json JSON,
  ai_models_json JSON,
  total_cost_usd DECIMAL(10, 4) DEFAULT 0,
  error_message TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_project_id (project_id),
  INDEX idx_user_id (user_id),
  INDEX idx_status (status),
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

**Generations Table:**

```sql
CREATE TABLE generations (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  video_id BIGINT UNSIGNED NOT NULL,
  step VARCHAR(50) NOT NULL,
  version INT UNSIGNED DEFAULT 1,
  status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
  input_data LONGTEXT,
  output_data LONGTEXT,
  cost_usd DECIMAL(10, 6) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_video_id (video_id),
  INDEX idx_step (step),
  FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE
);
```

**API_Calls Table:**

```sql
CREATE TABLE api_calls (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  video_id BIGINT UNSIGNED NOT NULL,
  generation_id BIGINT UNSIGNED,
  step VARCHAR(50) NOT NULL,
  provider VARCHAR(50) NOT NULL,
  model VARCHAR(100) NOT NULL,
  input_tokens INT UNSIGNED DEFAULT 0,
  output_tokens INT UNSIGNED DEFAULT 0,
  image_count INT UNSIGNED DEFAULT 0,
  audio_duration_seconds INT UNSIGNED DEFAULT 0,
  video_duration_seconds INT UNSIGNED DEFAULT 0,
  cost_usd DECIMAL(10, 6) NOT NULL,
  cost_calculation TEXT,
  request_payload LONGTEXT,
  response_data LONGTEXT,
  status ENUM('success', 'failed', 'timeout') NOT NULL,
  response_time_ms INT UNSIGNED,
  error_message TEXT,
  retry_count INT UNSIGNED DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_video_id (video_id),
  INDEX idx_step (step),
  INDEX idx_provider (provider),
  INDEX idx_created_at (created_at),
  FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE
);
```

**Transactions Table:**

```sql
CREATE TABLE transactions (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  amount_cents INT NOT NULL,
  credits INT NOT NULL,
  type ENUM('purchase', 'refund', 'deduction', 'bonus') NOT NULL,
  description VARCHAR(500),
  stripe_payment_id VARCHAR(255),
  stripe_session_id VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_user_id (user_id),
  INDEX idx_stripe_payment_id (stripe_payment_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

**Files Table:**

```sql
CREATE TABLE files (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  video_id BIGINT UNSIGNED NOT NULL,
  type ENUM('video', 'thumbnail', 'audio', 'script', 'package') NOT NULL,
  storage_path VARCHAR(500) NOT NULL,
  original_filename VARCHAR(255),
  mime_type VARCHAR(100),
  size_bytes BIGINT UNSIGNED,
  expires_at TIMESTAMP,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_video_id (video_id),
  INDEX idx_expires_at (expires_at),
  FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE
);
```

### 5.4 External Service Integration

#### 5.4.1 AI Services

| Service            | Provider                 | Purpose                     | API Type |
| ------------------ | ------------------------ | --------------------------- | -------- |
| Claude Opus/Sonnet | Anthropic                | Text generation             | REST     |
| GPT-4              | OpenAI                   | Alternative text generation | REST     |
| Gemini Imagen 3    | Google (via Nano Banana) | Image generation            | REST     |

**Anthropic Integration:**

```php
// Pseudocode for AI Model Router
class AIModelRouter
{
    public function generateText(string $step, string $prompt, string $model, int $videoId): array
    {
        $startTime = microtime(true);

        try {
            $response = match($model) {
                'claude-opus-4-5' => $this->callAnthropic($prompt, 'claude-opus-4-5-20251101'),
                'claude-sonnet-4-5' => $this->callAnthropic($prompt, 'claude-sonnet-4-5-20250929'),
                'gpt-4' => $this->callOpenAI($prompt, 'gpt-4-turbo'),
                default => throw new InvalidModelException($model),
            };

            $this->logApiCall($videoId, $step, $model, $response, microtime(true) - $startTime);

            return $response;
        } catch (Exception $e) {
            $this->logApiFailure($videoId, $step, $model, $e->getMessage());
            throw $e;
        }
    }
}
```

#### 5.4.2 Media Services

| Service         | Provider   | Purpose              | Cost          |
| --------------- | ---------- | -------------------- | ------------- |
| Text-to-Speech  | ElevenLabs | Voiceover generation | ~$0.30/10 min |
| Video Rendering | Shotstack  | Video assembly       | ~$0.05/min    |
| Stock Footage   | Pexels     | Free video clips     | Free          |
| Stock Footage   | Pixabay    | Free video clips     | Free          |

#### 5.4.3 Payment Service

| Service            | Provider        | Purpose                |
| ------------------ | --------------- | ---------------------- |
| Payment Processing | Stripe          | Credit card payments   |
| Checkout           | Stripe Checkout | Hosted payment page    |
| Webhooks           | Stripe Webhooks | Payment event handling |

### 5.5 Security Requirements

#### 5.5.1 Authentication Security

| Requirement        | Implementation                     |
| ------------------ | ---------------------------------- |
| Password Storage   | bcrypt hashing (cost factor 12)    |
| Session Management | HTTP-only, secure cookies          |
| CSRF Protection    | Laravel CSRF tokens                |
| Rate Limiting      | 60 requests/minute per IP          |
| Account Lockout    | 5 failed attempts → 15 min lockout |

#### 5.5.2 Data Security

| Requirement           | Implementation                       |
| --------------------- | ------------------------------------ |
| Encryption in Transit | TLS 1.3 (HTTPS only)                 |
| Encryption at Rest    | S3/R2 server-side encryption         |
| API Keys              | Environment variables, never in code |
| PCI Compliance        | Stripe handles all card data         |
| Input Validation      | Server-side validation on all inputs |

#### 5.5.3 API Security

| Requirement        | Implementation                      |
| ------------------ | ----------------------------------- |
| Authentication     | Laravel Sanctum tokens              |
| Authorization      | Policy-based access control         |
| Input Sanitization | Laravel request validation          |
| SQL Injection      | Eloquent ORM, parameterized queries |
| XSS Prevention     | React auto-escaping, CSP headers    |

### 5.6 Performance Requirements

| Metric            | Target       | Measurement                    |
| ----------------- | ------------ | ------------------------------ |
| Page Load Time    | < 2 seconds  | Time to First Contentful Paint |
| API Response Time | < 500ms      | p95 response time              |
| Video Render Time | < 10 minutes | For 10-minute video            |
| Concurrent Users  | 100          | Simultaneous active sessions   |
| Database Queries  | < 50ms       | p95 query time                 |
| Uptime            | 99%          | Monthly availability           |

### 5.7 AI-Generated Code Quality Standards

This codebase will be built using AI coding agents. The following standards ensure quality:

#### 5.7.1 Modular Architecture Requirements

- Each feature implemented as separate module/service
- Clear boundaries between components
- Dependency injection for testability
- Interface-driven design

#### 5.7.2 Testing Requirements

| Test Type         | Coverage Target     | Tools              |
| ----------------- | ------------------- | ------------------ |
| Unit Tests        | 80% critical paths  | PHPUnit, Jest      |
| Integration Tests | All API endpoints   | Laravel Test Suite |
| E2E Tests         | Critical user flows | Playwright         |
| Security Scans    | Every deployment    | SAST tools         |

#### 5.7.3 Code Quality Gates

Before feature completion:

- [ ] All tests pass (100% pass rate)
- [ ] Code coverage > 80% for critical paths
- [ ] No linter errors (PHP CS Fixer, ESLint)
- [ ] No security vulnerabilities
- [ ] Performance benchmarks met
- [ ] Documentation complete

---

## 6. API Specifications

### 6.1 Internal REST API

#### 6.1.1 Authentication Endpoints

| Method | Endpoint                         | Description               |
| ------ | -------------------------------- | ------------------------- |
| POST   | `/api/auth/register`             | Create new account        |
| POST   | `/api/auth/login`                | Authenticate user         |
| POST   | `/api/auth/logout`               | End session               |
| POST   | `/api/auth/forgot-password`      | Request password reset    |
| POST   | `/api/auth/reset-password`       | Reset password with token |
| GET    | `/api/auth/verify-email/{token}` | Verify email address      |
| GET    | `/api/auth/user`                 | Get current user          |

**Register Request:**

```json
POST /api/auth/register
{
  "email": "user@example.com",
  "password": "SecurePass123",
  "password_confirmation": "SecurePass123",
  "accept_terms": true
}
```

**Register Response:**

```json
{
    "success": true,
    "data": {
        "user": {
            "id": "uuid",
            "email": "user@example.com",
            "credits_balance": 1,
            "email_verified": false
        },
        "token": "jwt-token-here"
    },
    "message": "Registration successful. Please verify your email."
}
```

---

#### 6.1.2 Project Endpoints

| Method | Endpoint                         | Description            |
| ------ | -------------------------------- | ---------------------- |
| GET    | `/api/projects`                  | List all user projects |
| POST   | `/api/projects`                  | Create new project     |
| GET    | `/api/projects/{id}`             | Get project details    |
| PUT    | `/api/projects/{id}`             | Update project         |
| DELETE | `/api/projects/{id}`             | Delete project         |
| POST   | `/api/projects/{id}/set-default` | Set as default project |

**Create Project Request:**

```json
POST /api/projects
{
  "name": "Finance Channel",
  "settings": {
    "target_audience": "30-50 year olds interested in retirement",
    "tone": "Professional but approachable",
    "speaking_pace": 165
  }
}
```

---

#### 6.1.3 Video Endpoints

| Method | Endpoint                            | Description                    |
| ------ | ----------------------------------- | ------------------------------ |
| GET    | `/api/videos`                       | List videos in current project |
| POST   | `/api/videos`                       | Start new video creation       |
| GET    | `/api/videos/{id}`                  | Get video details and status   |
| DELETE | `/api/videos/{id}`                  | Delete video                   |
| POST   | `/api/videos/{id}/select-title`     | Select title option            |
| PUT    | `/api/videos/{id}/outline`          | Update outline                 |
| POST   | `/api/videos/{id}/approve-outline`  | Approve outline                |
| PUT    | `/api/videos/{id}/script`           | Update script                  |
| POST   | `/api/videos/{id}/approve-script`   | Approve script                 |
| POST   | `/api/videos/{id}/select-thumbnail` | Select thumbnail               |
| POST   | `/api/videos/{id}/select-voice`     | Select voice option            |
| POST   | `/api/videos/{id}/select-music`     | Select music track             |
| POST   | `/api/videos/{id}/generate`         | Start final rendering          |
| GET    | `/api/videos/{id}/download`         | Get download URLs              |

**Create Video Request:**

```json
POST /api/videos
{
  "project_id": "uuid",
  "topic": "The 4% rule for retirement explained simply",
  "reference_urls": [
    "https://www.investopedia.com/terms/f/four-percent-rule.asp",
    "https://www.youtube.com/watch?v=example"
  ],
  "ai_models": {
    "title_generation": "claude-sonnet-4-5",
    "outline_generation": "claude-opus-4-5",
    "script_writing": "claude-sonnet-4-5",
    "script_critique": "claude-sonnet-4-5"
  },
  "settings": {
    "script_iterations": 2,
    "video_length_minutes": 12
  }
}
```

**Video Status Response:**

```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "title": "The One Number That Buys Your Freedom",
    "status": "processing",
    "current_step": "script_critique",
    "progress": {
      "titles": "completed",
      "outline": "completed",
      "script": "processing",
      "script_critique_round": 2,
      "script_critique_total": 3,
      "thumbnails": "completed",
      "voiceover": "pending",
      "video": "pending"
    },
    "outputs": {
      "titles": [...],
      "selected_title_index": 0,
      "outline": {...},
      "thumbnails": [...]
    },
    "estimated_time_remaining": "2-3 minutes",
    "total_cost_usd": 0.45
  }
}
```

---

#### 6.1.4 Payment Endpoints

| Method | Endpoint                 | Description                    |
| ------ | ------------------------ | ------------------------------ |
| GET    | `/api/payments/balance`  | Get credit balance             |
| POST   | `/api/payments/checkout` | Create Stripe checkout session |
| GET    | `/api/payments/history`  | Get transaction history        |
| POST   | `/api/webhooks/stripe`   | Stripe webhook handler         |

**Checkout Request:**

```json
POST /api/payments/checkout
{
  "package": "15_credits",
  "success_url": "https://app.Itervel.com/payment/success",
  "cancel_url": "https://app.Itervel.com/payment/cancel"
}
```

**Checkout Response:**

```json
{
    "success": true,
    "data": {
        "checkout_url": "https://checkout.stripe.com/...",
        "session_id": "cs_..."
    }
}
```

---

### 6.2 External API Integration Specs

#### 6.2.1 Anthropic API

**Request Format:**

```json
POST https://api.anthropic.com/v1/messages
Headers:
  x-api-key: {API_KEY}
  anthropic-version: 2023-06-01
  content-type: application/json

{
  "model": "claude-sonnet-4-5-20250929",
  "max_tokens": 4096,
  "messages": [
    {
      "role": "user",
      "content": "Generate 5 YouTube title options for a video about..."
    }
  ]
}
```

#### 6.2.2 ElevenLabs API

**Request Format:**

```json
POST https://api.elevenlabs.io/v1/text-to-speech/{voice_id}
Headers:
  xi-api-key: {API_KEY}
  content-type: application/json

{
  "text": "Script text here...",
  "model_id": "eleven_monolingual_v1",
  "voice_settings": {
    "stability": 0.5,
    "similarity_boost": 0.75
  }
}
```

#### 6.2.3 Shotstack API

**Request Format:**

```json
POST https://api.shotstack.io/v1/render
Headers:
  x-api-key: {API_KEY}
  content-type: application/json

{
  "timeline": {
    "tracks": [
      {
        "clips": [
          {
            "asset": {
              "type": "video",
              "src": "https://videos.pexels.com/..."
            },
            "start": 0,
            "length": 5
          }
        ]
      },
      {
        "clips": [
          {
            "asset": {
              "type": "audio",
              "src": "https://storage.example.com/voiceover.mp3"
            },
            "start": 0
          }
        ]
      },
      {
        "clips": [
          {
            "asset": {
              "type": "audio",
              "src": "https://storage.example.com/music.mp3",
              "volume": 0.2
            },
            "start": 0
          }
        ]
      }
    ]
  },
  "output": {
    "format": "mp4",
    "resolution": "hd"
  }
}
```

### 6.3 Webhook Specifications

#### 6.3.1 Shotstack Completion Webhook

**Endpoint:** `POST /api/webhooks/shotstack`

**Payload:**

```json
{
    "type": "render",
    "action": "completed",
    "id": "render-uuid",
    "status": "done",
    "url": "https://cdn.shotstack.io/render-uuid.mp4",
    "data": {
        "duration": 754.5
    }
}
```

**Processing:**

1. Verify webhook signature
2. Find video by render ID
3. Download video to storage
4. Update video status to "completed"
5. Notify user (email if configured)

#### 6.3.2 Stripe Payment Webhook

**Endpoint:** `POST /api/webhooks/stripe`

**Events Handled:**

| Event                           | Action                      |
| ------------------------------- | --------------------------- |
| `checkout.session.completed`    | Add credits to user account |
| `payment_intent.payment_failed` | Log failure, notify user    |
| `charge.refunded`               | Deduct credits, log refund  |

---

## 7. Testing Strategy

### 7.1 Unit Testing

#### 7.1.1 Backend (PHPUnit)

**Coverage Targets:**

| Component               | Target | Priority |
| ----------------------- | ------ | -------- |
| Authentication          | 90%    | Critical |
| Video Generation Logic  | 85%    | Critical |
| Payment Processing      | 90%    | Critical |
| API Response Formatting | 80%    | High     |
| Utility Functions       | 75%    | Medium   |

**Example Test Cases:**

```php
// tests/Unit/Services/TitleGenerationServiceTest.php
class TitleGenerationServiceTest extends TestCase
{
    /** @test */
    public function it_generates_exactly_five_titles(): void
    {
        $service = new TitleGenerationService($this->mockAIClient);
        $result = $service->generate('4% retirement rule', []);

        $this->assertCount(5, $result['titles']);
    }

    /** @test */
    public function each_title_has_required_fields(): void
    {
        $service = new TitleGenerationService($this->mockAIClient);
        $result = $service->generate('4% retirement rule', []);

        foreach ($result['titles'] as $title) {
            $this->assertArrayHasKey('title', $title);
            $this->assertArrayHasKey('logline', $title);
            $this->assertArrayHasKey('framework', $title);
            $this->assertArrayHasKey('rank', $title);
        }
    }

    /** @test */
    public function it_handles_ai_api_failure_gracefully(): void
    {
        $this->mockAIClient->shouldThrow(new AIServiceException());

        $this->expectException(TitleGenerationException::class);

        $service = new TitleGenerationService($this->mockAIClient);
        $service->generate('topic', []);
    }
}
```

#### 7.1.2 Frontend (Jest)

**Coverage Targets:**

| Component         | Target | Priority |
| ----------------- | ------ | -------- |
| Form Validation   | 85%    | Critical |
| State Management  | 80%    | High     |
| Utility Functions | 90%    | High     |
| UI Components     | 70%    | Medium   |

### 7.2 Integration Testing

#### 7.2.1 API Endpoint Tests

```php
// tests/Feature/VideoCreationTest.php
class VideoCreationTest extends TestCase
{
    /** @test */
    public function authenticated_user_can_create_video(): void
    {
        $user = User::factory()->withCredits(5)->create();

        $response = $this->actingAs($user)
            ->postJson('/api/videos', [
                'project_id' => $user->defaultProject->id,
                'topic' => 'The 4% retirement rule explained',
                'settings' => ['script_iterations' => 2]
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'status', 'current_step']
            ]);
    }

    /** @test */
    public function user_without_credits_cannot_create_video(): void
    {
        $user = User::factory()->withCredits(0)->create();

        $response = $this->actingAs($user)
            ->postJson('/api/videos', [...]);

        $response->assertStatus(402)
            ->assertJson(['message' => 'Insufficient credits']);
    }
}
```

### 7.3 End-to-End Testing (Playwright)

#### 7.3.1 Critical User Flows

| Flow                 | Steps                                             | Priority |
| -------------------- | ------------------------------------------------- | -------- |
| User Registration    | Register → Verify Email → Login                   | Critical |
| First Video Creation | Input → Title Select → Approve → Download         | Critical |
| Credit Purchase      | Select Package → Stripe Checkout → Verify Credits | Critical |
| Project Management   | Create → Edit → Switch → Delete                   | High     |

**Example Playwright Test:**

```typescript
// tests/e2e/video-creation.spec.ts
import { test, expect } from '@playwright/test';

test('user can create a video from start to finish', async ({ page }) => {
    // Login
    await page.goto('/login');
    await page.fill('[data-testid="email"]', 'test@example.com');
    await page.fill('[data-testid="password"]', 'password123');
    await page.click('[data-testid="login-button"]');

    // Start video creation
    await page.click('[data-testid="new-video-button"]');
    await page.fill('[data-testid="topic-input"]', 'The 4% retirement rule');
    await page.click('[data-testid="continue-button"]');

    // Select title
    await expect(page.locator('[data-testid="title-option"]')).toHaveCount(5);
    await page.click('[data-testid="title-option"]:first-child');
    await page.click('[data-testid="select-title-button"]');

    // Wait for processing and verify completion
    await expect(page.locator('[data-testid="video-status"]')).toHaveText(
        'Completed',
        { timeout: 600000 },
    ); // 10 min timeout

    // Verify download available
    await expect(
        page.locator('[data-testid="download-video-button"]'),
    ).toBeVisible();
});
```

### 7.4 Performance Testing

#### 7.4.1 Load Testing Scenarios

| Scenario    | Concurrent Users | Duration | Success Criteria       |
| ----------- | ---------------- | -------- | ---------------------- |
| Normal Load | 50               | 30 min   | p95 < 500ms, 0% errors |
| Peak Load   | 100              | 15 min   | p95 < 1s, < 1% errors  |
| Stress Test | 200              | 5 min    | Graceful degradation   |

### 7.5 Security Testing

#### 7.5.1 Security Checklist

| Test                    | Tool            | Frequency |
| ----------------------- | --------------- | --------- |
| SQL Injection           | Automated SAST  | Every PR  |
| XSS Vulnerabilities     | Automated SAST  | Every PR  |
| Authentication Bypass   | Manual pen test | Monthly   |
| CSRF Protection         | Automated       | Every PR  |
| Sensitive Data Exposure | Manual review   | Weekly    |

### 7.6 User Acceptance Testing (UAT)

#### 7.6.1 UAT Criteria

| Criterion            | Acceptance Test                                        |
| -------------------- | ------------------------------------------------------ |
| Video Quality        | 10 test videos reviewed, all meet "good" threshold     |
| Script Quality       | Scripts pass human review for coherence and engagement |
| Thumbnail Quality    | Thumbnails are professional and brand-appropriate      |
| Workflow Clarity     | New users complete first video without support         |
| Download Reliability | All files download correctly, play in standard players |

---

## 8. Success Metrics & Analytics

### 8.1 User Acquisition Metrics

| Metric           | Target | Timeframe | Measurement                 |
| ---------------- | ------ | --------- | --------------------------- |
| Registered Users | 100    | 8 weeks   | Total registrations         |
| Verified Users   | 80     | 8 weeks   | Email verified accounts     |
| Active Users     | 50     | 12 weeks  | Users with ≥1 video/month   |
| Paying Users     | 50     | 12 weeks  | Users who purchased credits |
| Conversion Rate  | 20%    | Ongoing   | Paying / Registered         |

### 8.2 Engagement Metrics

| Metric                  | Target    | Measurement                  |
| ----------------------- | --------- | ---------------------------- |
| Videos per User         | 2-3/month | Average for paying users     |
| Project Completion Rate | 70%       | Started → Completed videos   |
| Return Rate             | 60%       | Users who create 2+ videos   |
| Session Duration        | 15+ min   | Average session length       |
| Feature Adoption        | Track     | AI model customization usage |

### 8.3 Quality Metrics

| Metric            | Target       | Measurement                    |
| ----------------- | ------------ | ------------------------------ |
| User Satisfaction | 4.0/5.0      | Post-video survey rating       |
| Error Rate        | < 10%        | Videos that fail to complete   |
| Support Tickets   | < 5%         | Videos requiring intervention  |
| Iteration Usage   | Avg 2 rounds | Script critique rounds used    |
| Regeneration Rate | < 20%        | Users who request regeneration |

### 8.4 Revenue Metrics

| Metric                    | Target    | Timeframe           |
| ------------------------- | --------- | ------------------- |
| Monthly Recurring Revenue | $500      | Week 12             |
| Average Revenue Per User  | $10/month | Paying users        |
| Cost Per Video            | $1.30     | Production cost     |
| Gross Margin Per Video    | $2.70     | Revenue - Cost      |
| Customer Lifetime Value   | $40+      | 4+ months retention |

### 8.5 Technical Metrics

| Metric                | Target   | Measurement          |
| --------------------- | -------- | -------------------- |
| Video Generation Time | < 10 min | For 10-minute video  |
| API Response Time     | < 500ms  | p95 latency          |
| System Uptime         | 99%      | Monthly availability |
| Error Rate            | < 2%     | API errors           |
| Page Load Time        | < 2s     | Time to interactive  |

### 8.6 Analytics Implementation

#### 8.6.1 Events to Track

| Event                | Properties                   | Purpose        |
| -------------------- | ---------------------------- | -------------- |
| `user_registered`    | source, timestamp            | Acquisition    |
| `user_verified`      | time_to_verify               | Onboarding     |
| `video_started`      | project_id, has_references   | Engagement     |
| `title_selected`     | selected_rank, total_options | Quality        |
| `video_completed`    | duration, total_time, cost   | Success        |
| `video_failed`       | error_type, step             | Error tracking |
| `credits_purchased`  | package, amount              | Revenue        |
| `download_initiated` | file_type                    | Delivery       |

#### 8.6.2 Dashboard Metrics

**Admin Dashboard Shows:**

```
┌─────────────────────────────────────────────────────────────────┐
│  ITERVEL ADMIN DASHBOARD                                   │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  TODAY                    THIS WEEK              THIS MONTH      │
│  ┌─────────────────┐     ┌─────────────────┐    ┌─────────────┐ │
│  │ New Users: 5    │     │ New Users: 23   │    │ Users: 87   │ │
│  │ Videos: 12      │     │ Videos: 67      │    │ Videos: 234 │ │
│  │ Revenue: $48    │     │ Revenue: $268   │    │ Rev: $936   │ │
│  │ API Cost: $15   │     │ API Cost: $87   │    │ Cost: $304  │ │
│  └─────────────────┘     └─────────────────┘    └─────────────┘ │
│                                                                  │
│  CONVERSION FUNNEL                                               │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ Registered (100) → Verified (80) → First Video (65) →       ││
│  │ Paid (50) → Repeat (35)                                     ││
│  └─────────────────────────────────────────────────────────────┘│
│                                                                  │
│  TOP ERRORS (LAST 7 DAYS)                                        │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ 1. Shotstack timeout (5 occurrences)                        ││
│  │ 2. ElevenLabs rate limit (3 occurrences)                    ││
│  │ 3. URL extraction failed (2 occurrences)                    ││
│  └─────────────────────────────────────────────────────────────┘│
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## 9. Risk Management

### 9.1 Technical Risks

#### Risk 1: API Costs Higher Than Expected

| Aspect          | Details                                             |
| --------------- | --------------------------------------------------- |
| **Likelihood**  | Medium                                              |
| **Impact**      | High                                                |
| **Description** | AI API costs may exceed estimates, reducing margins |
| **Mitigation**  | Real-time cost tracking, per-video cost caps        |
| **Contingency** | Adjust pricing, implement rate limiting             |
| **Monitoring**  | Daily cost reports, alerts at thresholds            |

#### Risk 2: Video Rendering Failures

| Aspect          | Details                                              |
| --------------- | ---------------------------------------------------- |
| **Likelihood**  | Medium                                               |
| **Impact**      | High                                                 |
| **Description** | Shotstack API may timeout or fail for complex videos |
| **Mitigation**  | Auto-retry (2 attempts), timeout handling            |
| **Contingency** | Manual re-trigger option, credit refund              |
| **Monitoring**  | Render success rate tracking                         |

#### Risk 3: AI Output Quality Issues

| Aspect          | Details                                             |
| --------------- | --------------------------------------------------- |
| **Likelihood**  | Medium                                              |
| **Impact**      | Medium                                              |
| **Description** | AI-generated content may not meet user expectations |
| **Mitigation**  | Iterative refinement, user editing at each step     |
| **Contingency** | Regeneration option, human review for complaints    |
| **Monitoring**  | User satisfaction surveys, regeneration rate        |

### 9.2 Business Risks

#### Risk 4: Low Free-to-Paid Conversion

| Aspect          | Details                                                 |
| --------------- | ------------------------------------------------------- |
| **Likelihood**  | Medium                                                  |
| **Impact**      | High                                                    |
| **Description** | Users may not convert after free trial                  |
| **Mitigation**  | Generous free tier (full workflow), value demonstration |
| **Contingency** | Discounted first purchase, extended trial               |
| **Monitoring**  | Conversion funnel analytics                             |

#### Risk 5: Shared Hosting Limitations

| Aspect          | Details                                                      |
| --------------- | ------------------------------------------------------------ |
| **Likelihood**  | Low                                                          |
| **Impact**      | Medium                                                       |
| **Description** | A2 shared hosting may not handle traffic spikes              |
| **Mitigation**  | Use A2 only for API, offload heavy work to external services |
| **Contingency** | Migrate to VPS if needed                                     |
| **Monitoring**  | Response time monitoring, error rate tracking                |

### 9.3 Legal/Compliance Risks

#### Risk 6: Copyright Concerns

| Aspect          | Details                                                     |
| --------------- | ----------------------------------------------------------- |
| **Likelihood**  | Low                                                         |
| **Impact**      | Medium                                                      |
| **Description** | AI may inadvertently generate copyrighted content           |
| **Mitigation**  | Strict prompts (15-word quote limit), source transformation |
| **Contingency** | Content review process, ToS user responsibility             |
| **Monitoring**  | Spot checks of generated content                            |

#### Risk 7: User Data Privacy

| Aspect          | Details                                                   |
| --------------- | --------------------------------------------------------- |
| **Likelihood**  | Low                                                       |
| **Impact**      | High                                                      |
| **Description** | Data breach or GDPR non-compliance                        |
| **Mitigation**  | Encryption, minimal data collection, clear privacy policy |
| **Contingency** | Incident response plan, user notification                 |
| **Monitoring**  | Security scans, access logging                            |

### 9.4 Risk Matrix Summary

```
                    IMPACT
                    Low    Medium    High
              ┌─────────┬─────────┬─────────┐
         High │         │         │         │
              ├─────────┼─────────┼─────────┤
LIKELIHOOD Med│         │ R3, R6  │ R1, R2, │
              │         │         │ R4      │
              ├─────────┼─────────┼─────────┤
         Low  │         │ R5      │ R7      │
              └─────────┴─────────┴─────────┘

R1: API Costs          R5: Hosting Limits
R2: Render Failures    R6: Copyright
R3: Quality Issues     R7: Data Privacy
R4: Low Conversion
```

---

## 10. Appendices

### 10.1 Glossary

| Term                  | Definition                                                                            |
| --------------------- | ------------------------------------------------------------------------------------- |
| **Faceless Video**    | YouTube video without on-camera presenter, using B-roll, stock footage, and voiceover |
| **B-roll**            | Supplementary footage intercut with main content to add visual interest               |
| **Logline**           | One-sentence summary capturing video's target viewer, problem, insight, and benefit   |
| **CTR**               | Click-Through Rate - percentage of impressions that result in clicks                  |
| **Pattern Interrupt** | Visual or audio change that recaptures viewer attention                               |
| **Build-up/Release**  | Engagement technique creating tension then providing payoff                           |
| **Critique Round**    | AI review of content from audience perspective with suggested improvements            |
| **Iteration**         | One cycle of critique and refinement                                                  |
| **Credit**            | Unit of payment; 1 credit = 1 video generation                                        |

### 10.2 API Cost Estimates

#### 10.2.1 Per-Video Cost Breakdown (Default Configuration)

| Step                  | Provider   | Model           | Est. Cost |
| --------------------- | ---------- | --------------- | --------- |
| Title Generation      | Anthropic  | Claude Sonnet   | $0.02     |
| Outline Generation    | Anthropic  | Claude Opus     | $0.05     |
| Script Writing        | Anthropic  | Claude Sonnet   | $0.05     |
| Script Critique (×2)  | Anthropic  | Claude Sonnet   | $0.04     |
| Script Revision (×2)  | Anthropic  | Claude Sonnet   | $0.04     |
| Thumbnail Concepts    | Anthropic  | Claude Sonnet   | $0.02     |
| Thumbnail Images (×3) | Google     | Gemini Imagen 3 | $0.15     |
| Metadata Generation   | Anthropic  | Claude Sonnet   | $0.01     |
| Voiceover (10 min)    | ElevenLabs | Standard        | $0.30     |
| Video Render (10 min) | Shotstack  | Standard        | $0.50     |
| **Total**             |            |                 | **$1.18** |

#### 10.2.2 Model Pricing Reference

| Model             | Input (per 1M tokens) | Output (per 1M tokens) |
| ----------------- | --------------------- | ---------------------- |
| Claude Opus 4.5   | $15.00                | $75.00                 |
| Claude Sonnet 4.5 | $3.00                 | $15.00                 |
| GPT-4 Turbo       | $10.00                | $30.00                 |

### 10.3 Pricing Model Breakdown

#### 10.3.1 Unit Economics

| Item                      | Amount |
| ------------------------- | ------ |
| Average Production Cost   | $1.30  |
| User Price                | $4.00  |
| Gross Profit              | $2.70  |
| Stripe Fee (2.9% + $0.30) | $0.42  |
| Net Profit                | $2.28  |
| Net Margin                | 57%    |

#### 10.3.2 Package Economics

| Package    | Price | Credits | Revenue | Est. Cost | Gross Profit |
| ---------- | ----- | ------- | ------- | --------- | ------------ |
| 5 Credits  | $20   | 5       | $20     | $6.50     | $13.50       |
| 15 Credits | $50   | 15      | $50     | $19.50    | $30.50       |
| 35 Credits | $100  | 35      | $100    | $45.50    | $54.50       |

### 10.4 Phase 2 Roadmap Summary

**Features Planned for Phase 2 (Post-Validation):**

| Feature               | Description                             | Priority |
| --------------------- | --------------------------------------- | -------- |
| Voice Cloning         | Clone user's voice from sample audio    | High     |
| Direct YouTube Upload | OAuth integration, scheduled publishing | High     |
| Subtitle Generation   | Auto-generate SRT files with Whisper    | Medium   |
| Manual Editing        | Drag-and-drop clip replacement          | Medium   |
| Multiple Languages    | Script translation, multi-language TTS  | Medium   |
| Batch Generation      | Queue multiple videos at once           | Medium   |
| Premium Stock Footage | Storyblocks/Shutterstock integration    | Low      |
| Team Accounts         | Multi-user with role permissions        | Low      |
| Subscription Tiers    | Monthly plans for high-volume users     | Medium   |
| Analytics Dashboard   | Track video performance from YouTube    | Low      |

### 10.5 Development Timeline

| Week  | Phase                | Deliverables                                             |
| ----- | -------------------- | -------------------------------------------------------- |
| 1-2   | Foundation           | Dev environment, DB schema, auth, basic UI               |
| 3-4   | Core Workflow Part 1 | Project creation, title/outline generation, file storage |
| 5-6   | Core Workflow Part 2 | Script critique loop, thumbnail generation               |
| 7-8   | Video Production     | Voiceover, stock footage, music, Shotstack integration   |
| 9-10  | Polish & Payment     | Metadata, downloads, credits, Stripe integration         |
| 11-12 | Testing & Launch     | E2E testing, bug fixes, beta testing, deployment         |

### 10.6 Compliance Requirements

#### 10.6.1 GDPR Compliance

| Requirement       | Implementation               |
| ----------------- | ---------------------------- |
| Right to Access   | Export user data endpoint    |
| Right to Deletion | Delete account with all data |
| Data Minimization | Collect only necessary data  |
| Privacy Policy    | Clear policy on data usage   |
| Cookie Consent    | Cookie banner for EU users   |

#### 10.6.2 Content Policy

| Policy                 | Enforcement                    |
| ---------------------- | ------------------------------ |
| No explicit content    | AI prompt restrictions         |
| No hate speech         | ToS, user responsibility       |
| Copyright compliance   | 15-word quote limit in prompts |
| User content ownership | User retains rights to outputs |

### 10.7 Support & Documentation

#### 10.7.1 User Documentation

| Document              | Purpose                      |
| --------------------- | ---------------------------- |
| Getting Started Guide | First-time user onboarding   |
| Feature Documentation | How each feature works       |
| FAQ                   | Common questions and answers |
| Video Tutorials       | Visual walkthroughs          |
| Brand Guide Template  | Downloadable template        |

#### 10.7.2 Support Channels

| Channel        | Response Time | Availability   |
| -------------- | ------------- | -------------- |
| In-app Help    | Self-service  | 24/7           |
| Email Support  | < 24 hours    | Business hours |
| Knowledge Base | Self-service  | 24/7           |

---

## Document History

| Version | Date         | Author       | Changes     |
| ------- | ------------ | ------------ | ----------- |
| 1.0     | January 2026 | Product Team | Initial PRD |

---

**END OF DOCUMENT**

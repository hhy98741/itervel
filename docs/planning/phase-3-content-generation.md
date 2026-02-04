# Phase 3: Content Generation Pipeline

## AI-Powered Title, Script, and Metadata Creation

**Duration:** 2 weeks (Week 5-6)  
**Features:** 9 features (3.1-3.9)  
**Checkpoint:** User can generate all text content with AI

---

## 🎯 CONTEXT: What Came Before

**Completed Phases:**

- ✅ Phase 1: Foundation (Auth, database, UI) - Complete
- ✅ Phase 2: Projects & Input (Projects, video creation started) - Complete

**What Exists:**

- ✅ User authentication and profile management
- ✅ Project CRUD operations
- ✅ Brand guide uploads
- ✅ Video model with topic and reference URLs
- ✅ Reference URL processing
- ✅ AI model selection configuration

**DO NOT Rebuild:**

- Authentication features already exist
- Project management already exists
- Video input form already exists
- Reference processing already exists
- Don't recreate Phase 1-2 features

---

## 📋 PHASE 3 GOAL

Build AI-powered content generation:

1. ✅ AI model router (unified API for Claude/GPT)
2. ✅ Title generation (3-5 options)
3. ✅ Outline creation
4. ✅ Script writing
5. ✅ Script critique & refinement
6. ✅ Metadata generation (description, tags)

**After this phase:** Users can generate complete video scripts and metadata using AI.

---

## 🔨 Features in This Phase

### Feature 3.1: AI Model Router & API Integration

**Purpose:** Unified interface for Claude Sonnet 4, Claude Opus 4.5, GPT-4, GPT-4o

**Key Components:**

- Service: AIModelRouter (route to appropriate provider)
- Services: AnthropicService, OpenAIService
- Cost tracking: Calculate and log costs in api_calls table
- Rate limiting: Respect provider limits

**Model Configuration:**

```php
[
    'claude-sonnet-4' => ['provider' => 'anthropic', 'model' => 'claude-sonnet-4-20250514'],
    'claude-opus-4.5' => ['provider' => 'anthropic', 'model' => 'claude-opus-4-5-20251101'],
    'gpt-4' => ['provider' => 'openai', 'model' => 'gpt-4-0125-preview'],
    'gpt-4o' => ['provider' => 'openai', 'model' => 'gpt-4o']
]
```

**Files to Create:** ~8 files (services, config, tests)  
**Dependencies:** Requires 2.6 (Model selection)  
**Time:** 1 day | **Complexity:** High

---

### Feature 3.2: Title Generation

**Purpose:** Generate 3-5 YouTube title options using AI

**Key Components:**

- Service: TitleGenerationService
- Controller: GenerationController@generateTitles
- Prompt: SEO-optimized, attention-grabbing, under 70 characters
- Store: In generations table with step='title', version=1-5

**Title Generation Prompt:**

```
Topic: [user topic]
Reference Content: [processed references]
Brand Guide: [brand guide if exists]
Target Audience: [from project settings]

Generate 5 YouTube titles that are:
- Under 70 characters
- SEO-optimized
- Attention-grabbing
- Accurate to content
```

**Output in generations table:**

```json
{
    "video_id": 1,
    "step": "title",
    "version": 1,
    "output_data": {
        "title": "How to Train Your Puppy in 30 Days",
        "reasoning": "SEO-friendly, clear benefit, timeframe..."
    },
    "cost_usd": 0.0012
}
```

**Files to Create:** ~10 files  
**Dependencies:** Requires 3.1  
**Time:** 1 day | **Complexity:** Medium

---

### Feature 3.3: Outline Creation

**Purpose:** Create structured video outline based on selected title

**Key Components:**

- Service: OutlineGenerationService
- Prompt: Create sections with timestamps, key points
- Input: Selected title, references, brand guide

**Outline Structure:**

```json
{
  "sections": [
    {
      "timestamp": "0:00-0:30",
      "title": "Hook",
      "key_points": ["Attention grabber", "Problem statement"]
    },
    {
      "timestamp": "0:30-2:00",
      "title": "Introduction",
      "key_points": [...]
    },
    ...
  ],
  "total_duration": "12:00"
}
```

**Files to Create:** ~8 files  
**Dependencies:** Requires 3.2 (Title selected)  
**Time:** 1 day | **Complexity:** Medium

---

### Feature 3.4: Script Writing

**Purpose:** Generate full video script based on outline

**Key Components:**

- Service: ScriptGenerationService
- Prompt: Natural speaking tone, specified pace (WPM), target length
- Multiple iterations (if configured)

**Script Format:**

```json
{
  "script": "Welcome to today's video where we'll...",
  "word_count": 1850,
  "estimated_duration": "12:15",
  "speaking_pace": 165,
  "sections": [
    {
      "section_title": "Hook",
      "text": "...",
      "timestamp_start": "0:00"
    },
    ...
  ]
}
```

**Files to Create:** ~10 files  
**Dependencies:** Requires 3.3 (Outline)  
**Time:** 1.5 days | **Complexity:** High

---

### Feature 3.5: Script Critique & Refinement

**Purpose:** AI critique and iterative improvement of script

**Key Components:**

- Service: ScriptCritiqueService
- Critique dimensions: Engagement, clarity, pacing, accuracy, SEO
- Refinement: Apply critique to improve script
- Iterations: Configurable (default 2)

**Critique Format:**

```json
{
  "overall_score": 8.5,
  "dimensions": {
    "engagement": { "score": 9, "feedback": "..." },
    "clarity": { "score": 8, "feedback": "..." },
    "pacing": { "score": 8, "feedback": "..." },
    "accuracy": { "score": 9, "feedback": "..." },
    "seo": { "score": 8, "feedback": "..." }
  },
  "improvement_suggestions": [...]
}
```

**Iteration Flow:**

1. Generate script v1
2. Critique v1
3. Refine to v2
4. Critique v2
5. Refine to v3 (if iterations > 2)
6. User selects best version

**Files to Create:** ~12 files  
**Dependencies:** Requires 3.4  
**Time:** 1.5 days | **Complexity:** High

---

### Feature 3.6: Metadata Generation

**Purpose:** Generate YouTube description, tags, hashtags

**Key Components:**

- Service: MetadataGenerationService
- Generate based on: Title, script, references

**Metadata Format:**

```json
{
  "description": "In this video, we'll cover...\n\nTimestamps:\n0:00 Intro\n...",
  "tags": ["puppy training", "dog training", "pets", ...],
  "hashtags": ["#puppytraining", "#dogtraining"],
  "seo_keywords": ["how to train puppy", ...]
}
```

**Files to Create:** ~8 files  
**Dependencies:** Requires 3.4 (Script)  
**Time:** 0.5 days | **Complexity:** Low

---

### Feature 3.7: Generation Progress Tracking

**Purpose:** Real-time progress updates for content generation

**Key Components:**

- Backend: Broadcasting progress events
- Frontend: ProgressTracker component with real-time updates
- WebSocket/Polling: Progress updates

**Progress Events:**

```json
{
    "step": "title",
    "status": "processing",
    "progress": 20,
    "message": "Generating title options..."
}
```

**Files to Create:** ~6 files  
**Dependencies:** Requires 3.1-3.6  
**Time:** 1 day | **Complexity:** Medium

---

### Feature 3.8: Content Selection Interface

**Purpose:** UI for reviewing and selecting from AI-generated options

**Key Components:**

- Frontend: TitleSelector, ScriptSelector, MetadataReview
- Compare versions side-by-side
- Save selections to video.outputs_json

**Files to Create:** ~8 files  
**Dependencies:** Requires 3.2-3.6  
**Time:** 1 day | **Complexity:** Medium

---

### Feature 3.9: Content Regeneration

**Purpose:** Allow users to regenerate any step if unsatisfied

**Key Components:**

- Controller: GenerationController@regenerate
- Keep previous versions in generations table
- Track regeneration count (rate limit to 5 per step)

**Files to Create:** ~5 files  
**Dependencies:** Requires 3.8  
**Time:** 0.5 days | **Complexity:** Low

---

## ✅ CHECKPOINT 3: STOP HERE

**After completing all 9 features above, STOP and wait for review.**

### What You've Built:

- ✅ AI model router (unified API)
- ✅ Title generation (multiple options)
- ✅ Outline creation
- ✅ Script writing with critique
- ✅ Iterative refinement
- ✅ Metadata generation
- ✅ Progress tracking
- ✅ Selection interface
- ✅ Regeneration capability

### Test This Phase:

```bash
# Run all tests
vendor/bin/phpunit
npm test

# Self-review checklist
- AI router works with Claude and GPT models
- Titles generate correctly (3-5 options)
- Outline creates logical structure
- Script generates with proper pacing
- Critique provides useful feedback
- Refinement improves script
- Metadata generates (description, tags)
- Progress tracking shows real-time updates
- User can select and regenerate content
- Cost tracking logs all API calls
- All tests passing (100%)
```

### Integration Point:

```bash
# Push feature branch
git push origin feature/phase-3-content-generation

# Create PR: feature/phase-3-content-generation → develop
# Wait for review before Phase 4
```

---

## 🚫 DO NOT BUILD (Next Phase Preview)

**Phase 4: Asset Generation (STOP - Don't start yet!)**

Phase 4 will add:

- Thumbnail generation (AI images)
- Thumbnail critique & selection
- Voiceover generation (TTS)
- Background music selection
- Stock footage search & download

**Why stop here:**

- Phase 3 content generation must work perfectly
- Phase 4 depends on having title and script
- Human review ensures AI outputs are high quality
- Integration point for testing content pipeline

**DO NOT BUILD:**

- Thumbnail generation
- Image AI integration
- Voiceover (TTS)
- Music selection
- Stock footage

**These are Phase 4 features - build them in the next session!**

---

## 📊 Phase 3 Summary

**Features:** 9  
**Files Created:** ~75  
**Tests:** ~60  
**Time:** 2 weeks

**Result:** Content generation complete! Users can generate titles, scripts, and metadata with AI. Ready for Phase 4! ✅

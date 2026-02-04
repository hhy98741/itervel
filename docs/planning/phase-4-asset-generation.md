# Phase 4: Asset Generation

## Thumbnails, Voiceovers, Music, Stock Footage

**Duration:** 2 weeks (Week 7-8)  
**Features:** 5 features (4.1-4.5)  
**Checkpoint:** User can generate all video assets (thumbnails, audio, footage)

---

## 🎯 CONTEXT: What Came Before

**Completed Phases:**

- ✅ Phase 1: Foundation (Auth, database, UI) - Complete
- ✅ Phase 2: Projects & Input (Projects, video creation) - Complete
- ✅ Phase 3: Content Generation (Titles, scripts, metadata) - Complete

**What Exists:**

- ✅ User authentication and profile management
- ✅ Project CRUD operations
- ✅ Video creation with topic input and references
- ✅ AI-powered title generation
- ✅ Script writing with critique and refinement
- ✅ Metadata generation (description, tags)
- ✅ AI model router (Claude, GPT)

**DO NOT Rebuild:**

- Authentication features (Phase 1)
- Project management (Phase 2)
- Video input and reference processing (Phase 2)
- Content generation features (Phase 3)
- AI model router already exists
- Don't recreate Phase 1-3 features

---

## 📋 PHASE 4 GOAL

Build asset generation pipeline:

1. ✅ Thumbnail generation (AI images)
2. ✅ Thumbnail critique & selection
3. ✅ Voiceover generation (TTS)
4. ✅ Background music selection
5. ✅ Stock footage search & download

**After this phase:** Users can generate all assets needed for video production.

---

## 🔨 Features in This Phase

### Feature 4.1: Thumbnail Generation

**Purpose:** Generate YouTube thumbnail images using AI (Gemini Imagen 3)

**Key Components:**

- Service: ThumbnailGenerationService
- API: Google Gemini Imagen 3 via Nano Banana Pro
- Generate: 3-5 thumbnail options based on title
- Store: Images in Cloudflare R2, references in files table

**Thumbnail Prompt Generation:**

```
Based on video title: "[title]"
Create a YouTube thumbnail that is:
- Eye-catching and clickable
- Relevant to content
- No text overlay (will add separately)
- 1920x1080 resolution
- Professional quality

Style: [from project settings]
Target Audience: [from project settings]
```

**Generated Thumbnails:**

```json
{
  "thumbnails": [
    {
      "id": 1,
      "url": "https://r2.../thumbnail-v1.jpg",
      "prompt": "...",
      "generation_cost": 0.04
    },
    ...
  ]
}
```

**Files to Create:**

```
Backend:
├── app/Services/ThumbnailGenerationService.php
├── app/Services/ImageAIService.php (Gemini integration)
├── app/Http/Controllers/Api/ThumbnailController.php
└── app/Jobs/GenerateThumbnailJob.php

Frontend:
├── resources/js/components/thumbnails/ThumbnailGenerator.tsx
├── resources/js/components/thumbnails/ThumbnailGrid.tsx
├── resources/js/api/thumbnailApi.ts

Tests:
├── tests/Unit/Services/ThumbnailGenerationServiceTest.php
└── tests/Feature/ThumbnailGenerationTest.php
```

**Dependencies:** Requires 3.2 (Title selected)  
**Time:** 1.5 days | **Complexity:** High

---

### Feature 4.2: Thumbnail Critique & Selection

**Purpose:** AI critique of thumbnails for clickability and relevance

**Key Components:**

- Service: ThumbnailCritiqueService
- Critique: Analyze for eye appeal, relevance, clarity
- Scoring: Rate each thumbnail 1-10
- Regeneration: Option to generate more thumbnails

**Critique Format:**

```json
{
    "thumbnail_id": 1,
    "overall_score": 8.5,
    "criteria": {
        "eye_appeal": { "score": 9, "feedback": "..." },
        "relevance": { "score": 8, "feedback": "..." },
        "clarity": { "score": 9, "feedback": "..." },
        "composition": { "score": 8, "feedback": "..." }
    },
    "recommendation": "strong|good|regenerate"
}
```

**Files to Create:** ~8 files  
**Dependencies:** Requires 4.1  
**Time:** 1 day | **Complexity:** Medium

---

### Feature 4.3: Voiceover Generation

**Purpose:** Generate voiceover audio from script using TTS (ElevenLabs)

**Key Components:**

- Service: VoiceoverGenerationService
- API: ElevenLabs TTS
- Voice selection: 10+ voices (male/female, various accents)
- Processing: Split long scripts, concatenate audio files
- Storage: MP3 files in Cloudflare R2

**Voice Options:**

```json
{
  "voices": [
    {"id": "adam", "name": "Adam", "gender": "male", "accent": "american"},
    {"id": "rachel", "name": "Rachel", "gender": "female", "accent": "american"},
    {"id": "george", "name": "George", "gender": "male", "accent": "british"},
    ...
  ]
}
```

**Voiceover Process:**

1. Select voice from project settings or per-video
2. Split script into chunks (< 5000 chars per API call)
3. Generate audio for each chunk
4. Concatenate chunks into single MP3
5. Calculate duration, verify timing
6. Store in R2

**Files to Create:**

```
Backend:
├── app/Services/VoiceoverGenerationService.php
├── app/Services/ElevenLabsService.php
├── app/Services/AudioProcessingService.php
├── app/Http/Controllers/Api/VoiceoverController.php
└── app/Jobs/GenerateVoiceoverJob.php

Frontend:
├── resources/js/components/voiceover/VoiceSelector.tsx
├── resources/js/components/voiceover/AudioPlayer.tsx
├── resources/js/api/voiceoverApi.ts

Tests:
└── tests/Feature/VoiceoverGenerationTest.php
```

**Dependencies:** Requires 3.4 (Script selected)  
**Time:** 1.5 days | **Complexity:** High

---

### Feature 4.4: Background Music Selection

**Purpose:** Select background music from library or upload custom

**Key Components:**

- Music library: Curated royalty-free tracks
- Categories: Upbeat, Calm, Dramatic, Inspiring, etc.
- Upload: Custom music (MP3, max 50MB)
- Preview: Audio player with waveform

**Music Library Schema:**

```json
{
  "tracks": [
    {
      "id": 1,
      "title": "Uplifting Corporate",
      "category": "upbeat",
      "duration": 180,
      "url": "https://r2.../music-1.mp3",
      "license": "royalty-free"
    },
    ...
  ]
}
```

**Files to Create:**

```
Backend:
├── app/Models/MusicTrack.php
├── app/Http/Controllers/Api/MusicController.php
├── app/Services/MusicUploadService.php
└── database/seeders/MusicLibrarySeeder.php

Frontend:
├── resources/js/components/music/MusicSelector.tsx
├── resources/js/components/music/MusicPlayer.tsx
├── resources/js/components/music/MusicUpload.tsx
├── resources/js/api/musicApi.ts

Tests:
└── tests/Feature/MusicSelectionTest.php
```

**Dependencies:** None (can start early)  
**Time:** 1 day | **Complexity:** Low

---

### Feature 4.5: Stock Footage Search & Download

**Purpose:** Search and download stock video clips (Pexels, Pixabay)

**Key Components:**

- Service: StockFootageService
- APIs: Pexels API, Pixabay API
- Search: Based on script keywords, AI-suggested queries
- Selection: User picks relevant clips
- Storage: Download to R2 for use in video

**Search Flow:**

1. Extract keywords from script (AI-assisted)
2. Search Pexels + Pixabay
3. Present results with previews
4. User selects clips (or auto-select top 5)
5. Download selected clips to R2
6. Store references in files table

**Footage Schema:**

```json
{
  "clips": [
    {
      "id": 1,
      "source": "pexels",
      "source_id": 123456,
      "url": "https://r2.../clip-1.mp4",
      "thumbnail": "...",
      "duration": 15,
      "keywords": ["puppy", "training", "outdoor"],
      "selected": true
    },
    ...
  ]
}
```

**Files to Create:**

```
Backend:
├── app/Services/StockFootageService.php
├── app/Services/PexelsService.php
├── app/Services/PixabayService.php
├── app/Http/Controllers/Api/StockFootageController.php
└── app/Jobs/DownloadFootageJob.php

Frontend:
├── resources/js/components/footage/FootageSearch.tsx
├── resources/js/components/footage/FootageGrid.tsx
├── resources/js/components/footage/FootagePreview.tsx
├── resources/js/api/footageApi.ts

Tests:
└── tests/Feature/StockFootageTest.php
```

**Dependencies:** Requires 3.4 (Script for keywords)  
**Time:** 1.5 days | **Complexity:** Medium

---

## ✅ CHECKPOINT 4: STOP HERE

**After completing all 5 features above, STOP and wait for review.**

### What You've Built:

- ✅ Thumbnail generation (AI images)
- ✅ Thumbnail critique & selection
- ✅ Voiceover generation (TTS)
- ✅ Background music selection
- ✅ Stock footage search & download

### Test This Phase:

```bash
# Run all tests
vendor/bin/phpunit
npm test

# Self-review checklist
- Thumbnails generate correctly (3-5 options)
- Thumbnail critique provides useful feedback
- Voiceover generates from script (ElevenLabs)
- Audio quality is good, timing is correct
- Music library is accessible
- Custom music uploads work
- Stock footage searches return relevant results
- Clips download correctly to R2
- All files stored with proper references
- Cost tracking logs all API calls
- All tests passing (100%)
```

### Integration Point:

```bash
# Push feature branch
git push origin feature/phase-4-asset-generation

# Create PR: feature/phase-4-asset-generation → develop
# Wait for review before Phase 5
```

---

## 🚫 DO NOT BUILD (Next Phase Preview)

**Phase 5: Video Production & Delivery (STOP - Don't start yet!)**

Phase 5 will add:

- Storyboard creation (timeline planning)
- Video assembly (combine assets)
- Shotstack video rendering
- Download & packaging
- Video library & history
- Credit system
- Payment integration (Stripe)

**Why stop here:**

- Phase 4 asset generation must work perfectly
- Phase 5 depends on having all assets (thumbnails, audio, footage)
- Human review ensures assets are high quality
- Integration point for testing asset pipeline

**DO NOT BUILD:**

- Storyboard creation
- Video assembly
- Shotstack integration
- Download system
- Video library
- Payment features

**These are Phase 5 features - build them in the next session!**

---

## 📊 Phase 4 Summary

**Features:** 5  
**Files Created:** ~45  
**Tests:** ~35  
**Time:** 2 weeks

**Result:** Asset generation complete! Users can generate thumbnails, voiceovers, music, and footage. Ready for Phase 5! ✅

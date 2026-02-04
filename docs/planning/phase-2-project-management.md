# Phase 2: Project Management & Video Input

## Profile, Projects, Video Creation Initiation

**Duration:** 2 weeks (Week 3-4)  
**Features:** 7 features (2.1-2.7)  
**Checkpoint:** User can manage projects, configure settings, input video topics

---

## 🎯 CONTEXT: What Came Before

**Completed Phases:**

- ✅ Phase 1: Foundation - Complete

**What Exists:**

- ✅ Database (all tables created)
- ✅ User authentication (register, verify, login, reset)
- ✅ Frontend UI framework (React components)
- ✅ Dashboard page

**DO NOT Rebuild:**

- User model, AuthController, AuthService already exist
- All database migrations already run
- UI components (Button, Input, Modal, etc.) already exist
- Don't recreate authentication features

---

## 📋 PHASE 2 GOAL

Build project management and video creation initiation:

1. ✅ User profile management
2. ✅ Project CRUD operations
3. ✅ Brand guide uploads
4. ✅ Video creation workflow (input stage)
5. ✅ Reference URL processing
6. ✅ AI model selection
7. ✅ Iteration configuration

**After this phase:** Users can create projects and start the video creation process.

---

## 🔨 Features in This Phase

**Note:** Feature details extracted from original development-plan.md lines 722-1158.
For complete details, see those lines. Key information summarized below for focus:

### Feature 2.1: User Profile & Preferences

**Purpose:** Manage account settings and default preferences

**Key Components:**

- Backend: ProfileController, Update/Password/Preferences Requests
- Frontend: ProfilePage, PreferencesPage, ProfileForm, PasswordChangeForm
- Routes: GET/PUT /api/profile, PUT /api/profile/password, /preferences, DELETE /api/profile

**Files to Create:** ~13 files (controllers, requests, pages, components, tests)

**Dependencies:** Requires 1.4 (Login)  
**Time:** 1 day | **Complexity:** Low

---

### Feature 2.2: Create Project

**Purpose:** Create separate projects for different YouTube channels

**Key Components:**

- Backend: Project model, ProjectController@store, CreateProjectRequest, ProjectService
- Frontend: CreateProjectModal, ProjectForm
- Auto-create default project on first login

**Project Settings Schema:**

```json
{
    "brand_guide": null,
    "target_audience": "",
    "tone": "",
    "thumbnail_style": "",
    "music_preference": "",
    "speaking_pace": 165,
    "default_video_length": 12
}
```

**Files to Create:** ~12 files  
**Dependencies:** Requires 1.4 (Login) | Blocks 2.3, Video Creation  
**Time:** 1 day | **Complexity:** Medium

---

### Feature 2.3: Manage Projects

**Purpose:** View, edit, switch, and delete projects

**Key Components:**

- Backend: ProjectController (index, show, update, destroy, setDefault)
- Frontend: ProjectsPage, ProjectList, ProjectCard, EditProjectModal, ProjectSwitcher
- Store: useProjectStore (Zustand for project state)

**Files to Create:** ~10 files  
**Dependencies:** Requires 2.2  
**Time:** 1 day | **Complexity:** Medium

---

### Feature 2.4: Brand Guide Upload

**Purpose:** Upload brand guidelines (PDF/DOCX) for AI context

**Key Components:**

- Backend: BrandGuideController (upload, show, destroy), FileService (R2 storage)
- Frontend: BrandGuideUpload component with drag-and-drop
- Storage: Cloudflare R2

**File Processing:**

- Accept: PDF, DOCX (max 10MB)
- Extract text content
- Store in R2, reference in project settings

**Files to Create:** ~8 files  
**Dependencies:** Requires 2.2  
**Time:** 1 day | **Complexity:** Medium

---

### Feature 2.5: Topic Input & Reference URL Processing

**Purpose:** Allow users to input video topics and reference URLs

**Key Components:**

- Backend: Video model, VideoController@store, ReferenceProcessorService
- Frontend: TopicInputForm, ReferenceUrlInput
- URL Processing: Extract title, description, transcript (YouTube), article text

**Video Schema (input_json):**

```json
{
    "topic": "How to train a puppy",
    "reference_urls": ["https://youtube.com/...", "https://article.com/..."],
    "processed_references": [
        {
            "url": "...",
            "title": "...",
            "content": "...",
            "type": "youtube|article"
        }
    ]
}
```

**Files to Create:** ~12 files  
**Dependencies:** Requires 2.3  
**Time:** 1.5 days | **Complexity:** High

---

### Feature 2.6: AI Model Selection

**Purpose:** Choose AI models for each step of generation

**Key Components:**

- Backend: ModelConfigurationService
- Frontend: ModelSelectionModal, ModelConfigForm
- Supported: Claude Sonnet 4, Claude Opus 4.5, GPT-4, GPT-4o

**Model Configuration (ai_models_json):**

```json
{
    "title": "claude-sonnet-4",
    "outline": "claude-sonnet-4",
    "script": "claude-opus-4.5",
    "critique": "gpt-4o",
    "metadata": "claude-sonnet-4"
}
```

**Files to Create:** ~8 files  
**Dependencies:** Requires 2.5  
**Time:** 0.5 days | **Complexity:** Low

---

### Feature 2.7: Iteration Configuration

**Purpose:** Configure how many iterations for script/thumbnail generation

**Key Components:**

- Backend: Store in video.input_json
- Frontend: IterationConfigForm

**Configuration:**

```json
{
    "script_iterations": 2,
    "thumbnail_iterations": 3,
    "auto_select_best": true
}
```

**Files to Create:** ~4 files  
**Dependencies:** Requires 2.6  
**Time:** 0.5 days | **Complexity:** Low

---

## ✅ CHECKPOINT 2: STOP HERE

**After completing all 7 features above, STOP and wait for review.**

### What You've Built:

- ✅ User profile management
- ✅ Project CRUD operations
- ✅ Brand guide uploads
- ✅ Video creation initiation
- ✅ Reference URL processing
- ✅ AI model selection
- ✅ Iteration configuration

### Test This Phase:

```bash
# Run all tests
vendor/bin/phpunit
npm test

# Self-review checklist
- User can update profile/password
- User can create/edit/delete projects
- User can upload brand guide (PDF/DOCX)
- User can input topic + reference URLs
- URLs are processed correctly (YouTube, articles)
- User can select AI models
- User can configure iterations
- All tests passing (100%)
```

### Integration Point:

```bash
# Push feature branch
git push origin feature/phase-2-project-management

# Create PR: feature/phase-2-project-management → develop
# Wait for review before Phase 3
```

---

## 🚫 DO NOT BUILD (Next Phase Preview)

**Phase 3: Content Generation Pipeline (STOP - Don't start yet!)**

Phase 3 will add:

- AI model router
- Title generation (3-5 options)
- Outline creation
- Script writing
- Script critique & refinement
- Metadata generation (description, tags)

**Why stop here:**

- Phase 2 setup must be solid (projects, video creation flow)
- Phase 3 depends on video input working
- Human review ensures video creation flow works
- Integration point for testing

**DO NOT BUILD:**

- AI model router
- Title generation
- Outline creation
- Script writing
- Critique system
- Metadata generation

**These are Phase 3 features - build them in the next session!**

---

## 📊 Phase 2 Summary

**Features:** 7  
**Files Created:** ~70  
**Tests:** ~50  
**Time:** 2 weeks

**Result:** Project management complete! Users can create projects and start video creation. Ready for Phase 3! ✅

# Autonomous Development Session - Initial Prompt

**Copy and paste this into Claude Code when starting your first development session:**

---

You are an autonomous software development agent. Your task is to build a faceless video creation platform by implementing features sequentially from the attached development plan.

## PROGRESS TRACKING (CRITICAL)

After completing each checkpoint, you MUST create/update a progress file:

**File:** `docs/progress/DEVELOPMENT_PROGRESS.md` (create in docs/progress/ directory)

This file tracks what's been built and enables seamless continuation in new chat sessions.

**On first run:** If `docs/progress/` directory doesn't exist, create it first:

```bash
mkdir -p docs/progress
```

## GIT BRANCHING STRATEGY (CRITICAL)

**IMPORTANT: Use proper git workflow with feature branches**

### **Branch Structure:**

- `main` - Production branch (deployed to live site)
- `develop` - Development branch (integration branch)
- `feature/phase-X-DESCRIPTION` - Feature branches for each checkpoint

### **Before Starting Work:**

```bash
# 1. Make sure you're starting from develop branch
git checkout develop

# 2. Pull latest changes (in case there are any)
git pull origin develop

# 3. Create feature branch for this phase
git checkout -b feature/phase-1-foundation

# Branch naming convention:
# - feature/phase-1-foundation
# - feature/phase-2-core-video
# - feature/phase-3-video-generation
# etc.
```

### **During Development:**

```bash
# Commit regularly as you complete each feature
git add .
git commit -m "feat: add database setup (Feature 1.1)"

git add .
git commit -m "feat: add user authentication (Feature 1.2)"

# etc.
```

### **At Checkpoint (Before Pausing):**

```bash
# Push feature branch to remote
git push origin feature/phase-1-foundation

# DO NOT merge to develop yet - user will review first
```

### **Output to User:**

```
🎯 CHECKPOINT 1 Complete

Branch: feature/phase-1-foundation
Status: Pushed to remote, ready for review

To merge after review:
1. Create PR: feature/phase-1-foundation → develop
2. Review code changes
3. Merge PR
4. Continue with next phase
```

## AUTONOMOUS OPERATION RULES

**Auto-Continue Mode:**

- Build features in exact order from the development plan (1.1 → 1.2 → 1.3 → etc.)
- **If development plan identifies parallel tracks**, build Track A features, then Track B features (cannot truly parallelize in single agent)
- After completing each feature, automatically proceed to the next WITHOUT waiting for approval
- Only stop at designated CHECKPOINTS

**Handling Parallel Tracks:**
If the development plan shows:

```
Parallel Track A: Features 2.1, 2.2
Parallel Track B: Features 2.3, 2.4
```

Build in this order: 2.1 → 2.2 → 2.3 → 2.4 (sequential within single agent)

**Note to User:** True parallelization requires multiple AI agents running simultaneously. A single agent builds "parallel" tracks sequentially but in optimal dependency order.

**Checkpoints (Pause for Review):**

1. After Foundation Phase (Features 1.1-1.3) - **CHECKPOINT 1**
2. After Core Video Creation (Features 2.1-2.4) - **CHECKPOINT 2**
3. After Video Generation (Features 3.1-3.5) - **CHECKPOINT 3**
4. After Video Assembly (Features 4.1-4.3) - **CHECKPOINT 4**
5. After Polish Phase (Features 5.1-5.4) - **CHECKPOINT 5**

**Within Each Phase:**
Build all features automatically without stopping.

## FEATURE BUILD LOOP

For each feature:

1. Read feature spec from development plan
2. Create all necessary files (migrations, models, controllers, services, components)
3. Write implementation following PRD specifications
4. Write tests (PHPUnit for backend, Jest for frontend, Playwright for E2E)
5. Run tests - if they fail, debug and fix until they pass (max 3 attempts)
6. Run linter - if errors, fix until clean
7. Run security scan if applicable (PHP Security Checker)
8. Commit with descriptive message (conventional commits format)
9. Log completion to console
10. Update progress tracking
11. AUTOMATICALLY move to next feature

## QUALITY GATES (Must Pass Before Next Feature)

- ✅ All tests pass (100% pass rate required)
- ✅ No linter errors (PHP CS Fixer, ESLint)
- ✅ No security vulnerabilities
- ✅ Code committed to git
- ✅ Feature documented in progress file

## CHECKPOINT PROCEDURE (Self-Review Before Pausing)

When reaching a checkpoint, perform self-review BEFORE marking complete:

### **1. Review All Changes:**

```bash
# Get all changes since last checkpoint
git diff develop..HEAD
```

### **2. Security Check:**

Look for:

- ❌ SQL injection (raw queries with user input)
- ❌ XSS vulnerabilities (unescaped output)
- ❌ Missing authentication/authorization
- ❌ Unvalidated file uploads
- ❌ Missing input validation
- ❌ Exposed sensitive data

**Fix immediately if found.**

### **3. Code Quality Check:**

Look for:

- ❌ Code duplication (extract to service/helper)
- ❌ Missing error handling
- ❌ Inconsistent patterns
- ❌ Complex functions (refactor if >50 lines)
- ❌ Missing validation

**Fix immediately if found.**

### **4. Test Coverage Check:**

Verify:

- ✅ All features have tests
- ✅ Happy path tested
- ✅ Error cases tested
- ✅ Edge cases covered

**Add missing tests if found.**

### **5. Performance Check:**

Look for:

- ❌ N+1 query problems
- ❌ Missing database indexes
- ❌ No rate limiting on expensive endpoints

**Fix immediately if found.**

### **6. Fix All Issues:**

```bash
# After fixing issues:
vendor/bin/phpunit  # All tests must pass
npm test
npm run lint

git add .
git commit -m "fix: address self-review issues"
```

### **7. Push and Document:**

```bash
# Push feature branch
git push origin feature/phase-X-name

# Update docs/progress/DEVELOPMENT_PROGRESS.md
# Document self-review results
```

### **8. Output Checkpoint Summary:**

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎯 CHECKPOINT X: [Phase Name] - COMPLETE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Features Built: [list]
Branch: feature/phase-X-name
Status: Pushed to remote

Self-Review Results:
✅ Security check: Passed (0 issues)
✅ Code quality: Passed (0 issues)
✅ Test coverage: 100% (X/X tests)
✅ Performance: Passed (0 issues)

[Or if issues found and fixed:]
⚠️ Self-review found 3 issues - ALL FIXED:
1. Fixed SQL injection in VideoController
2. Added rate limiting to API endpoints
3. Added missing validation tests

Total: X files, X/X tests passing, X commits
Time: ~X minutes

📝 Updated: docs/progress/DEVELOPMENT_PROGRESS.md

Next Phase: [Phase Name]
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
⏸️  PAUSED FOR REVIEW
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

To continue:
1. Review code in feature/phase-X-name branch
2. Test features manually
3. Merge PR: feature/phase-X-name → develop
4. Start new chat with continuation prompt
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

## STOPPING CONDITIONS (Only Stop If)

- Reached checkpoint (planned pause point)
- Encounter error you cannot fix after 3 attempts
- Need external resource (API key, credentials, service setup)
- Hit rate limit on external API
- User explicitly says "pause"

## PROGRESS TRACKING FILE FORMAT

At each checkpoint, create/update `docs/progress/DEVELOPMENT_PROGRESS.md`:

```markdown
# Development Progress Tracker

**Last Updated:** [Date/Time]
**Current Phase:** [Phase Name]
**Next Feature:** [Feature Number]

---

## Completed Phases

### ✅ Phase 1: Foundation (CHECKPOINT 1)

**Status:** Complete
**Completed:** [Date]
**Duration:** ~[X] minutes
**Branch:** feature/phase-1-foundation
**Branch Status:** Merged to develop

**Features Built:**

- ✅ 1.1: Database Setup
    - Files: migrations/create_users_table.php, migrations/create_projects_table.php, migrations/create_videos_table.php, migrations/create_api_calls_table.php
    - Tests: 8/8 passing
    - Commit: [hash] "feat: add core database schema"
- ✅ 1.2: User Authentication
    - Files: Controllers/AuthController.php, Models/User.php, routes/auth.php, middleware/Authenticate.php, components/Auth/Login.jsx, components/Auth/Register.jsx
    - Tests: 15/15 passing (12 backend, 3 frontend)
    - Commit: [hash] "feat: implement user authentication with JWT"
- ✅ 1.3: Project Management
    - Files: Controllers/ProjectController.php, Models/Project.php, routes/projects.php, components/Projects/ProjectList.jsx, components/Projects/ProjectForm.jsx
    - Tests: 12/12 passing
    - Commit: [hash] "feat: add project CRUD and multi-channel support"

**Summary:**

- Total files created: 47
- Total tests: 35/35 passing (100%)
- Total commits: 3
- Issues encountered: None
- Time elapsed: ~45 minutes

**Learnings/Notes:**

- Used Laravel Sanctum for API authentication
- Project settings stored as JSON in projects.settings_json field
- Default project feature working correctly

---

### 🔄 Phase 2: Core Video Creation (CHECKPOINT 2)

**Status:** Not Started
**Next Feature:** 2.1

---

## Environment Setup

**Database:**

- MariaDB configured
- All migrations run successfully
- Seeded with test user: test@example.com / password

**Dependencies Installed:**

- Laravel 11.x
- React 18.x
- Tailwind CSS 3.x
- PHPUnit 10.x
- Jest 29.x
- Playwright 1.x

**API Keys Needed (Not Yet Configured):**

- [ ] Anthropic API Key (for Claude)
- [ ] OpenAI API Key (for GPT-4)
- [ ] Google API Key (for Gemini Imagen 3 via Nano Banana Pro)
- [ ] ElevenLabs API Key (for TTS)
- [ ] Shotstack API Key (for video rendering)
- [ ] Pexels API Key (for stock footage)
- [ ] Pixabay API Key (for stock footage)

**Git Status:**

- Repository: [repo URL or "local only"]
- Branch: feature/phase-1-foundation
- Last commit: [hash] [message]
- Clean working directory: Yes/No

---

## Next Steps

1. **User Review:** Test Foundation features manually
    - [ ] Register new user
    - [ ] Login/logout
    - [ ] Create/edit/delete projects
    - [ ] Switch between projects

2. **Ready for Phase 2:** Core Video Creation
    - Feature 2.1: Video Input Form
    - Feature 2.2: Reference URL Processing
    - Feature 2.3: AI Model Router
    - Feature 2.4: Cost Tracking Setup

3. **To Continue:** Use continuation prompt with this progress file
```

## CONSOLE OUTPUT FORMAT

After each feature completion:

```
✅ Feature X.X: [Feature Name] - COMPLETE

Files Created:
- [list of files]

Tests:
- Backend: X/X passing
- Frontend: X/X passing
- E2E: X/X passing

Commit: [hash] "[message]"

Quality Checks:
- ✅ Tests passing
- ✅ Linter clean
- ✅ Security scan passed
- ✅ Committed to git

Time: ~X minutes

Moving to Feature X.X+1...
```

## ERROR HANDLING

If you encounter an error you cannot fix after 3 attempts:

1. Document the error in docs/progress/DEVELOPMENT_PROGRESS.md under "Blockers"
2. Commit current work (even if incomplete)
3. Output clear error message with troubleshooting suggestions
4. Wait for user intervention

## STARTING POINT

**Current Status:** Starting fresh
**Next Feature:** 1.1 (Database Setup)
**Checkpoint Target:** CHECKPOINT 1 (after Feature 1.3)

**Files to Create:**

- docs/progress/DEVELOPMENT_PROGRESS.md (will be created at first checkpoint)

---

## 🚀 BEGIN AUTONOMOUS DEVELOPMENT

**FIRST: Read the planning documents in your project:**

```bash
# Read these files to understand what to build:
cat docs/planning/PRD.md                      # WHAT to build (requirements)
cat docs/planning/phase-1-foundation.md       # HOW to build Phase 1

# Check if progress file exists (might not on first run):
cat docs/progress/DEVELOPMENT_PROGRESS.md 2>/dev/null || echo "No progress file yet - will create one"
```

**Why phase-1-foundation.md instead of full development-plan.md:**

- Focused on Phase 1 features only (~500 lines vs 3,493 lines)
- Includes context (what came before: nothing, fresh start)
- Includes guardrails (what NOT to build: Phase 2+ features)
- 50% less context, 100% relevant information

**THEN: Start building**

Build Features 1.1 → 1.2 → 1.3 → 1.4 → 1.5 → 1.6 → 1.7 automatically.

Stop at CHECKPOINT 1, create `docs/progress/DEVELOPMENT_PROGRESS.md`, and wait for review.

**All planning documents are in `docs/planning/` directory.**

Start now with Feature 1.1: Development Environment & Database Schema.

# Autonomous Development Quick Start Guide

This guide shows you how to use the autonomous development system to build your faceless video platform with minimal intervention.

---

## 📁 Files You Have

**Planning & Requirements:**

1. **prompt-1-create-prd.md** - Step 1: Give to Opus to create PRD (WHAT to build)
2. **prompt-2-create-dev-plan.md** - Step 2: Give to Opus to create Development Plan (HOW to build)
3. **prd-brief-for-opus.md** - Your brief (input for Step 1)

**Development:** 4. **autonomous-dev-prompt-initial.md** - Step 3: Give to Claude Code for first build session 5. **autonomous-dev-prompt-continuation.md** - Step 4+: Give to Claude Code for continuation sessions 6. **DEVELOPMENT_PROGRESS_TEMPLATE.md** - Template (Claude creates actual file)

---

## 🚀 Step-by-Step Workflow

### **Step 0.1: Setup Project Structure** (if not already done)

First, set up your project directories:

```bash
cd ~/projects/itervel-platform

# Create documentation directories
mkdir -p docs/planning
mkdir -p docs/progress
mkdir -p docs/guides

# Move your brief to planning folder (if not already there)
mv prd-brief-for-opus.md docs/planning/ 2>/dev/null || true

# Create develop branch (integration branch)
git checkout -b develop
git push origin develop

# Set develop as default branch on GitHub
# (GitHub: Settings > Branches > Default branch > develop)
```

---

### **Step 0.2: Create PRD with Opus (WHAT to build)**

**What you're creating:** Product Requirements Document that defines WHAT the application should do.

```bash
# Make sure you're in project directory
cd ~/projects/itervel-platform

# Make sure brief exists
ls docs/planning/prd-brief-for-opus.md

# Run Opus
claude-code  # or your Opus access method

# Paste entire contents of:
prompt-1-create-prd.md
```

**What Opus does:**

- Reads: `docs/planning/prd-brief-for-opus.md` (your brief)
- Thinks: "What should this product do? Why? For whom?"
- Writes: `docs/planning/PRD.md` (30-40 pages)

**PRD includes:**

- Executive summary & product vision
- User personas & journeys
- Feature specifications (WHAT each feature does)
- User stories & functional requirements
- Data & API specifications
- Testing strategy (WHAT to test)
- Success metrics

**Verify:**

```bash
ls docs/planning/PRD.md  # Should exist
```

---

### **Step 0.3: Create Development Plan with Opus (HOW to build)**

**What you're creating:** Development Plan that defines HOW to build the application in optimal order.

```bash
# Same or new Opus session
claude-code

# Paste entire contents of:
prompt-2-create-dev-plan.md
```

**What Opus does:**

- Reads: `docs/planning/PRD.md` (from Step 0.2)
- Thinks: "How should we build this? What order? What dependencies?"
- Writes: `docs/planning/development-plan.md` (15-25 pages)

**Development Plan includes:**

- Feature breakdown (how to organize builds)
- Build order (Feature 1.1 → 1.2 → 1.3)
- Dependencies (X must be built before Y)
- Parallel tracks (what can build simultaneously)
- Timeline (realistic week-by-week)
- Files to create for each feature
- Testing requirements per feature
- Checkpoints (when to pause for review)
- Dependency diagrams (ASCII art)

**Verify:**

```bash
ls docs/planning/development-plan.md  # Should exist
```

**Why two steps?**

- ✅ **PRD = WHAT** (product requirements, user needs) - Product Manager mindset
- ✅ **Dev Plan = HOW** (implementation order, dependencies) - Technical Architect mindset
- ✅ Clearer focus, better quality, more flexible

---

### **Step 1: First Session (Foundation Phase)**

**Open Claude Code (or Cursor):**

**What to do:**

1. Make sure you're in the project directory:
    ```bash
    cd ~/projects/itervel-platform
    ```
2. Start Docker and enter container:
    ```bash
    docker compose up -d
    docker compose exec app bash
    ```
3. Inside container, run Claude Code:
    ```bash
    claude-code
    ```
4. Copy entire contents of `autonomous-dev-prompt-initial.md`
5. Paste into Claude Code
6. Press Enter

**Claude will automatically read:**

- `docs/planning/PRD.md`
- `docs/planning/development-plan.md`
- Will create `docs/progress/DEVELOPMENT_PROGRESS.md`

**No need to attach files - they're in the project directory!**

**What happens:**

```
Claude:
🔄 Starting Feature 1.1: Database Setup
[Works for ~5 minutes]
✅ Feature 1.1 Complete - 8 tests passing

🔄 Starting Feature 1.2: User Authentication
[Works for ~15 minutes]
✅ Feature 1.2 Complete - 15 tests passing

🔄 Starting Feature 1.3: Project Management
[Works for ~12 minutes]
✅ Feature 1.3 Complete - 12 tests passing

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎯 CHECKPOINT 1: Foundation Complete
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Features Built:
✅ 1.1: Database Setup (8 tests)
✅ 1.2: User Authentication (15 tests)
✅ 1.3: Project Management (12 tests)

Total: 47 files, 35/35 tests passing, 3 commits
Time: ~45 minutes

📝 Created: DEVELOPMENT_PROGRESS.md

Next Phase: Core Video Creation (2.1-2.4)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
⏸️  PAUSED FOR REVIEW
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

**Your turn:**

1. Look at `docs/progress/DEVELOPMENT_PROGRESS.md` (created by Claude)
2. Test the features manually:

    ```bash
    # Start Laravel
    php artisan serve --host=0.0.0.0

    # In browser, go to http://localhost:8000
    # Try to register, login, create a project
    ```

3. Review code on GitHub or locally:
    ```bash
    git checkout feature/phase-1-foundation
    git diff develop..feature/phase-1-foundation
    ```
4. Create Pull Request on GitHub:
    - Go to your repo on GitHub
    - Click "Compare & pull request"
    - Base: `develop` ← Compare: `feature/phase-1-foundation`
    - Title: "Phase 1: Foundation (Features 1.1-1.3)"
    - Review changes, merge
5. Pull latest develop:
    ```bash
    git checkout develop
    git pull origin develop
    # Now has Foundation code integrated
    ```
6. Review the code in `app/`, `database/`, `resources/`
7. Check git commits: `git log`

**If everything looks good, proceed to Step 2.**

---

### **Step 2: Continue to Next Phase**

**Open NEW Claude Code chat** (important - fresh context)

**What to do:**

1. Enter container:

    ```bash
    docker compose exec app bash
    claude-code
    ```

2. Copy entire contents of `autonomous-dev-prompt-continuation.md`

3. Paste into Claude Code

4. Press Enter

**That's it! Claude will automatically:**

- Read `docs/progress/DEVELOPMENT_PROGRESS.md`
- Figure out where you left off
- Determine next features to build
- Identify target checkpoint
- Continue development

**No manual editing needed!** Claude extracts everything from the progress file.

**What happens:**

```
Claude:
📖 Reading docs/progress/DEVELOPMENT_PROGRESS.md...
✅ Last checkpoint: CHECKPOINT 1 (Foundation Complete)
✅ Confirmed: Features 1.1-1.3 complete
📋 Next features to build: 2.1, 2.2, 2.3, 2.4
🎯 Target checkpoint: CHECKPOINT 2

🔄 Starting Feature 2.1: Video Input Form
[Works for ~20 minutes]
✅ Feature 2.1 Complete - 18 tests passing

🔄 Starting Feature 2.2: Reference URL Processing
[Works for ~15 minutes]
✅ Feature 2.2 Complete - 14 tests passing

🔄 Starting Feature 2.3: AI Model Router
[Works for ~18 minutes]
✅ Feature 2.3 Complete - 10 tests passing

🔄 Starting Feature 2.4: Cost Tracking Setup
[Works for ~10 minutes]
✅ Feature 2.4 Complete - 8 tests passing

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎯 CHECKPOINT 2: Core Video Creation Complete
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Session Summary:
- Started from: Feature 2.1
- Completed: Features 2.1-2.4
- Tests: 50/50 passing
- Commits: 4
- Time: ~75 minutes

Cumulative Progress:
- Total features: 7/20 complete (35%)

📝 Updated: docs/progress/DEVELOPMENT_PROGRESS.md

Next Phase: Video Generation (3.1-3.5)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
⏸️  PAUSED FOR REVIEW
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

**Your turn:**

1. Check updated `docs/progress/DEVELOPMENT_PROGRESS.md`
2. Test Phase 2 features
3. Review code
4. When ready, go to Step 3

---

### **Step 3: Repeat for Remaining Phases**

**For each subsequent phase:**

1. Open NEW Claude Code chat (fresh context)
2. Enter container: `docker compose exec app bash && claude-code`
3. Copy and paste `autonomous-dev-prompt-continuation.md`
4. Press Enter
5. Claude automatically figures out where to resume
6. Review at checkpoint
7. Repeat

**Zero manual editing - Claude reads the progress file and knows what to do!**

**You'll have 5 total sessions:**

- Session 1: Phase 1 (Features 1.1-1.3) - ~45 min
- Session 2: Phase 2 (Features 2.1-2.4) - ~75 min
- Session 3: Phase 3 (Features 3.1-3.5) - ~2-3 hours
- Session 4: Phase 4 (Features 4.1-4.3) - ~90 min
- Session 5: Phase 5 (Features 5.1-5.4) - ~2 hours

**Total: ~8-10 hours of autonomous building over 5 sessions**

---

## 🎯 What You Do at Each Checkpoint

### **Your Review Checklist:**

**1. Check DEVELOPMENT_PROGRESS.md**

```bash
cat DEVELOPMENT_PROGRESS.md
# Verify it shows completed features correctly
```

**2. Test Features Manually**

```bash
# Start app
php artisan serve

# Run frontend
npm run dev

# Test in browser
# - Try the new features
# - Make sure they work end-to-end
```

**3. Review Code Quality**

```bash
# Check what files were created
git status

# Review recent commits
git log --oneline -10

# Look at a few files
cat app/Http/Controllers/ProjectController.php
```

**4. Run Tests**

```bash
# Backend tests
vendor/bin/phpunit

# Frontend tests
npm test

# Should see X/X passing (100%)
```

**5. Check for Issues**

- Any error messages in console?
- Any failing tests?
- Any warnings from linter?
- Any blockers mentioned in DEVELOPMENT_PROGRESS.md?

**6. Decision Time**

- ✅ Everything good? → Continue to next phase
- ⚠️ Minor issues? → Note them, continue anyway
- ❌ Blocking issues? → Fix manually, then continue

---

## 📝 Understanding DEVELOPMENT_PROGRESS.md

This file is your **session-to-session memory**. It contains:

### **What's Been Built:**

```markdown
✅ 1.1: Database Setup

- Files: migrations/create_users_table.php, ...
- Tests: 8/8 passing
- Commit: abc123 "feat: add core database schema"
```

### **What's Next:**

```markdown
**Current Phase:** Core Video Creation
**Next Feature:** 2.1
```

### **Environment Status:**

```markdown
**API Keys Needed:**

- [x] Database configured
- [ ] Anthropic API key (needed for Phase 3)
```

### **Blockers (if any):**

```markdown
❌ **Blocker 1:** Cannot connect to Shotstack

- Feature affected: 4.3
- Resolution: Need API key
```

**This file lets Claude pick up exactly where it left off in a new chat.**

---

## 🚨 Troubleshooting

### **Problem: Claude doesn't read DEVELOPMENT_PROGRESS.md**

**Solution:** In continuation prompt, emphasize:

```
CRITICAL: Read DEVELOPMENT_PROGRESS.md FIRST before starting.
Verify what features are complete before beginning new work.
```

### **Problem: Claude recreates files that already exist**

**Solution:** In continuation prompt, add:

```
Before creating any file, check if it already exists:
- ls app/Models/
- ls database/migrations/
- cat routes/api.php

Only create NEW files. Modify existing files if needed.
```

### **Problem: Tests are failing**

**Solution:** Let Claude try to fix (it will auto-retry 3 times). If still failing:

1. Check what the error is
2. Fix manually or give Claude specific guidance
3. Update DEVELOPMENT_PROGRESS.md with the fix
4. Continue

### **Problem: Claude seems confused about context**

**Solution:** Your DEVELOPMENT_PROGRESS.md might be out of sync. Manually update it:

```markdown
# Add a note:

**Context Note for Next Session:**

- Feature 2.2 is actually complete, ignore what progress file says
- Files exist in app/Services/ for URL processing
- Database has `videos` table with all required columns
```

---

## ✅ Success Criteria

**You know it's working when:**

✅ Claude builds 3-4 features in a row automatically
✅ DEVELOPMENT_PROGRESS.md gets updated after each checkpoint  
✅ You can start a new chat, attach progress file, and Claude picks up where it left off
✅ All tests passing at each checkpoint
✅ Git history shows clean, logical commits
✅ You're spending time reviewing, not coding

---

## 📊 Expected Timeline

**Week 1:**

- Session 1 (Foundation): ~1 hour
- Review & test: ~30 min
- **Total: ~1.5 hours**

**Week 2:**

- Session 2 (Core Video): ~1.5 hours
- Review & test: ~30 min
- **Total: ~2 hours**

**Week 3:**

- Session 3 (Video Generation): ~3 hours
- Review & test: ~1 hour
- **Total: ~4 hours**

**Week 4:**

- Session 4 (Video Assembly): ~2 hours
- Review & test: ~1 hour
- **Total: ~3 hours**

**Week 5:**

- Session 5 (Polish): ~2 hours
- Review & test: ~1 hour
- **Total: ~3 hours**

**Week 6:**

- Integration testing: ~4 hours
- Bug fixes: ~4 hours
- **Total: ~8 hours**

**Grand Total: ~21.5 hours of your active time over 6 weeks**

(Compared to ~200+ hours if coding manually!)

---

## 🎉 Final Notes

**This system works because:**

- ✅ Progress file creates continuity across sessions
- ✅ Checkpoints prevent runaway errors
- ✅ Quality gates ensure clean code
- ✅ Clear prompts guide autonomous behavior

**Your job is to:**

- ⚙️ Set up each session (5 min)
- ☕ Review at checkpoints (30-60 min)
- 🐛 Fix blockers when they occur (varies)
- ✅ Test and approve (30-60 min per phase)

**Claude's job is to:**

- 🤖 Write all the code
- ✅ Write all the tests
- 🔍 Run quality checks
- 📝 Track progress
- 🚀 Build features autonomously

**Ready to start? Go to Step 0 and get your development plan from Opus!**

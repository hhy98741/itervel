# Autonomous Development Session - Continuation Prompt

**Copy and paste this into Claude Code when continuing from a checkpoint:**

---

## 🎯 FIRST: Select Which Phase You're Working On

**Before starting, tell me which phase to work on by uncommenting ONE line below:**

```bash
# Uncomment the phase you want to build:

PHASE_FILE="phase-1-foundation.md"
# PHASE_FILE="phase-2-project-management.md"
# PHASE_FILE="phase-3-content-generation.md"
# PHASE_FILE="phase-4-asset-generation.md"
# PHASE_FILE="phase-5-video-production.md"
# PHASE_FILE="phase-6-testing-launch.md"
```

**Or let me auto-detect from progress file (recommended):**

```bash
PHASE_FILE="auto"  # I'll read progress file and determine current phase
```

---

You are an autonomous software development agent continuing work on a faceless video creation platform.

## CONTEXT RESTORATION

**I'm continuing autonomous development from a previous session.**

**Required Files (Read from project directories):**

```bash
# Read progress file to see what's done:
cat docs/progress/DEVELOPMENT_PROGRESS.md

# Determine which phase file to read:
if [ "$PHASE_FILE" = "auto" ]; then
  # Auto-detect from progress file
  # If progress shows "Phase 1 complete, Phase 2 next" → use phase-2-project-management.md
  # If progress shows "Phase 2 complete, Phase 3 next" → use phase-3-content-generation.md
  # etc.
  echo "Auto-detecting phase from progress file..."
  # Read the appropriate phase file based on progress
else
  # Use the specified phase file
  cat docs/planning/$PHASE_FILE
fi

# Optional - reference PRD if needed for detailed requirements:
cat docs/planning/PRD.md
```

**Auto-Detection Logic:**

- Look for "Next Feature: X.Y" in progress file
- Extract phase number (X) from feature number
- Map to phase file:
    - Feature 1.X → phase-1-foundation.md
    - Feature 2.X → phase-2-project-management.md
    - Feature 3.X → phase-3-content-generation.md
    - Feature 4.X → phase-4-asset-generation.md
    - Feature 5.X → phase-5-video-production.md
    - Feature 6.X → phase-6-testing-launch.md

**Why phase-specific files instead of full development-plan.md:**

- Focused on current phase only (~400-500 lines vs 3,493 lines)
- Includes context (what phases are complete, what not to rebuild)
- Includes guardrails (what NOT to build ahead)
- 50-87% less context, 100% relevant information

**IMPORTANT: Read `docs/progress/DEVELOPMENT_PROGRESS.md` FIRST and extract:**

- What checkpoint was last completed (e.g., "CHECKPOINT 1 Complete")
- What features are marked as complete (✅)
- What is the "Next Feature" listed
- What is the "Current Phase"
- What branch was last used (e.g., "feature/phase-1-foundation")
- Any blockers or issues

**From this information, automatically determine:**

1. Which features to build next
2. Which checkpoint to target
3. Where to resume
4. What branch name to use

**Example:**
If progress file shows:

```
✅ Phase 1: Foundation - COMPLETE (CHECKPOINT 1)
Branch: feature/phase-1-foundation (merged to develop)

🔄 Phase 2: Core Video - NOT STARTED
  ⚪ 2.1: Next
  ⚪ 2.2: Pending
  ⚪ 2.3: Pending
  ⚪ 2.4: Pending
Next Feature: 2.1
Target: CHECKPOINT 2
```

Then you should:

- Read `phase-2-project-management.md` (auto-detected from "Feature 2.1")
- Checkout develop branch
- Create new branch: feature/phase-2-project-management
- Resume with Feature 2.1
- Continue through 2.7
- Self-review at checkpoint
- Push branch
- Stop at CHECKPOINT 2

**Phase File Mapping:**

- Phase 1 (Features 1.1-1.7) → `phase-1-foundation.md`
- Phase 2 (Features 2.1-2.7) → `phase-2-project-management.md`
- Phase 3 (Features 3.1-3.9) → `phase-3-content-generation.md`
- Phase 4 (Features 4.1-4.5) → `phase-4-asset-generation.md`
- Phase 5 (Features 5.1-5.8) → `phase-5-video-production.md`
- Phase 6 (Features 6.1-6.4) → `phase-6-testing-launch.md`

**Do NOT require the user to manually specify checkpoint numbers - figure it out from the progress file!**

**All planning documents are in `docs/planning/` directory.**
**Progress tracking is in `docs/progress/` directory.**

## GIT BRANCHING STRATEGY

**Before Starting Work:**

```bash
# 1. Checkout develop branch
git checkout develop

# 2. Pull latest changes
git pull origin develop

# 3. Create new feature branch for this phase
# Extract phase name from progress file
git checkout -b feature/phase-X-DESCRIPTION

# Example branch names:
# - feature/phase-2-core-video
# - feature/phase-3-video-generation
# - feature/phase-4-video-assembly
# etc.
```

**During Development:**

```bash
# Commit regularly after each feature
git add .
git commit -m "feat: implement feature X.X"
```

**At Checkpoint:**

```bash
# Push feature branch
git push origin feature/phase-X-DESCRIPTION

# DO NOT merge - user will review and merge via PR
```

## RESUMING AUTONOMOUS OPERATION

**Current Status:**

- Last completed: Feature [X.X]
- Next to build: Feature [X.X]
- Target checkpoint: CHECKPOINT [X]

**Auto-Continue Mode:**

- Build features in exact order from the development plan
- Start with next incomplete feature from DEVELOPMENT_PROGRESS.md
- After completing each feature, automatically proceed to the next
- Stop at next checkpoint

**Checkpoints (Pause Points):**

1. After Foundation Phase (Features 1.1-1.3) - CHECKPOINT 1
2. After Core Video Creation (Features 2.1-2.4) - CHECKPOINT 2
3. After Video Generation (Features 3.1-3.5) - CHECKPOINT 3
4. After Video Assembly (Features 4.1-4.3) - CHECKPOINT 4
5. After Polish Phase (Features 5.1-5.4) - CHECKPOINT 5

## FEATURE BUILD LOOP

For each feature:

1. Read feature spec from current phase file (e.g., phase-2-project-management.md)
2. Check DEVELOPMENT_PROGRESS.md to see what's already built (avoid recreating files)
3. Create new files needed for this feature
4. Write implementation
5. Write tests
6. Run tests - if fail, debug and fix (max 3 attempts)
7. Run linter - if errors, fix
8. Commit with descriptive message
9. Update DEVELOPMENT_PROGRESS.md with completion details
10. AUTOMATICALLY move to next feature

## QUALITY GATES

- ✅ All tests pass (100% pass rate)
- ✅ No linter errors
- ✅ No security vulnerabilities
- ✅ Code committed to git
- ✅ DEVELOPMENT_PROGRESS.md updated

## CHECKPOINT PROCEDURE (Self-Review Before Pausing)

When reaching a checkpoint, perform comprehensive self-review:

### **1. Review All Changes:**

```bash
# Get all changes in current feature branch
git diff develop..HEAD
```

### **2. Security Audit:**

Check for:

- ❌ SQL injection vulnerabilities (raw queries with user input)
- ❌ XSS vulnerabilities (unescaped output)
- ❌ Missing CSRF protection
- ❌ Authentication/authorization bypass
- ❌ Unvalidated file uploads
- ❌ Exposed sensitive data
- ❌ Missing rate limiting

**Fix immediately if found.**

### **3. Code Quality Check:**

Look for:

- ❌ Code duplication (extract to service/helper)
- ❌ Missing error handling
- ❌ Inconsistent patterns
- ❌ Functions >50 lines (refactor)
- ❌ Missing input validation
- ❌ Poor naming conventions

**Fix immediately if found.**

### **4. Test Coverage:**

Verify:

- ✅ All features have tests
- ✅ Happy path covered
- ✅ Error cases covered
- ✅ Edge cases covered
- ✅ 100% pass rate

**Add missing tests if found.**

### **5. Performance Check:**

Look for:

- ❌ N+1 query problems
- ❌ Missing database indexes
- ❌ No caching on expensive operations
- ❌ No rate limiting on expensive endpoints

**Fix immediately if found.**

### **6. Fix Issues & Re-test:**

```bash
# After fixing issues:
vendor/bin/phpunit
npm test
npm run lint

git add .
git commit -m "fix: address self-review issues

- Fixed [issue 1]
- Fixed [issue 2]
- Added [missing tests]"
```

### **7. Push Branch:**

```bash
git push origin feature/phase-X-name
```

### **8. Update Progress File:**

Document self-review results in `docs/progress/DEVELOPMENT_PROGRESS.md`

## PROGRESS TRACKING

**Update DEVELOPMENT_PROGRESS.md after each feature:**

- Mark feature as complete (✅)
- Add files created
- Add test results
- Add commit hash
- Update "Next Feature" field
- Add any notes/learnings

**At checkpoint, update:**

- Mark phase as complete
- Add phase summary (files, tests, commits, time)
- Update "Current Phase" and "Next Feature" fields
- Document any blockers
- Add review checklist

## INTEGRATION WITH EXISTING CODE

**CRITICAL:** Since previous features exist:

- Don't recreate existing files
- Import/use existing models, controllers, services
- Add to existing routes (don't overwrite)
- Build on top of existing database schema
- Reuse existing React components where appropriate

**Check before creating:**

```bash
# Example checks you should do
ls app/Models/User.php  # Does User model exist?
ls database/migrations/  # What migrations are there?
cat routes/api.php  # What routes are defined?
```

## CONSOLE OUTPUT FORMAT

After each feature:

```
✅ Feature X.X: [Feature Name] - COMPLETE

Files Created:
- [new files only, not existing]

Files Modified:
- [existing files that were updated]

Tests:
- Backend: X/X passing
- Frontend: X/X passing

Commit: [hash] "[message]"

Progress File: Updated DEVELOPMENT_PROGRESS.md

Moving to Feature X.X+1...
```

At checkpoint:

```
[Running self-review on feature/phase-X-name...]
Checking security... ✅ Passed
Checking code quality... ✅ Passed
Checking test coverage... ✅ 100%
Checking performance... ✅ Passed

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎯 CHECKPOINT X: [Phase Name] - COMPLETE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Branch: feature/phase-X-name
Status: Pushed to remote, ready for review

Session Summary:
- Started from: Feature X.X
- Completed through: Feature X.X
- Features built this session: [list]
- Total tests passing: X/X (100%)
- Total commits this session: X
- Time elapsed: ~X minutes

Self-Review: ✅ Passed
- Security: 0 issues
- Code quality: 0 issues
- Test coverage: 100%
- Performance: 0 issues

[Or if issues found and fixed:]
Self-Review: ⚠️ Found 3 issues - ALL FIXED ✅
- Fixed SQL injection in VideoController
- Added rate limiting to API endpoints
- Added missing validation tests
- Commit: xyz789 "fix: address self-review issues"

Cumulative Progress:
- Total features complete: X/XX
- Overall completion: XX%

📝 DEVELOPMENT_PROGRESS.md updated with full details

Next Phase: [Phase Name]
Next Feature: [Feature Number]

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
⏸️  PAUSED FOR REVIEW
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

To continue:
1. Review code in feature/phase-X-name branch
2. Test features manually
3. Create PR: feature/phase-X-name → develop
4. Merge PR after approval
5. Start new chat with continuation prompt
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

## ERROR HANDLING

If you encounter errors:

1. Attempt to fix (max 3 tries)
2. If still failing, document in `docs/progress/DEVELOPMENT_PROGRESS.md` under "Blockers"
3. Commit current work
4. Ask user for help with specific error details

---

## 🔄 RESUME AUTONOMOUS DEVELOPMENT

**Read the planning and progress files to determine where to resume:**

```bash
cat docs/progress/DEVELOPMENT_PROGRESS.md   # See where we left off, what's next
# Based on progress, read appropriate phase file:
# Phase 2: cat docs/planning/phase-2-project-management.md
# Phase 3: cat docs/planning/phase-3-content-generation.md
# Phase 4: cat docs/planning/phase-4-asset-generation.md
# Phase 5: cat docs/planning/phase-5-video-production.md
# Phase 6: cat docs/planning/phase-6-testing-launch.md
```

**Based on `docs/progress/DEVELOPMENT_PROGRESS.md`, automatically:**

1. Identify which phase to work on
2. Read the corresponding phase file
3. Identify the next feature to build
4. Determine which checkpoint to target
5. Build all features until that checkpoint
6. Update the progress file
7. Pause for review

**The phase files include:**

- ✅ Context (what phases are complete, what not to rebuild)
- ✅ Detailed feature specs (what to build)
- ✅ Guardrails (what NOT to build ahead)

**No manual input needed - extract everything from the progress file!**

Begin autonomous development now.

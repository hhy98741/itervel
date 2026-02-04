# Git Workflow for AI-Driven Development

## Feature Branches + Self-Review + Pull Requests

This guide explains the git branching strategy for autonomous development with Claude Code.

---

## 🌳 Branch Structure

```
main (production)
  ↑
  merge after thorough testing
  ↑
develop (integration)
  ↑
  merge via PR after each checkpoint
  ↑
feature/phase-X-name (work happens here)
```

### **Branch Purposes:**

**`main`**

- Production-ready code only
- Deployed to live site (A2 hosting)
- Never commit directly to main
- Only merge from develop after full testing

**`develop`**

- Integration branch
- Where all feature branches merge
- Should always be stable
- Can be deployed to staging (optional)

**`feature/phase-X-name`**

- Where Claude builds features
- One branch per checkpoint/phase
- Merged to develop via PR after review
- Deleted after merge

---

## 🔄 Complete Workflow

### **Initial Setup (One Time)**

```bash
cd ~/projects/itervel-platform

# Create develop branch
git checkout -b develop
git push origin develop

# Set develop as default branch in GitHub
# (Settings > Branches > Default branch > develop)
```

### **Session 1: Foundation Phase (Features 1.1-1.3)**

**Claude does this automatically:**

```bash
# 1. Start from develop
git checkout develop
git pull origin develop

# 2. Create feature branch
git checkout -b feature/phase-1-foundation

# 3. Build features
# [Claude builds 1.1, commits]
git add .
git commit -m "feat: add database setup"

# [Claude builds 1.2, commits]
git add .
git commit -m "feat: implement user authentication"

# [Claude builds 1.3, commits]
git add .
git commit -m "feat: add project management"

# 4. Self-review
git diff develop..HEAD  # Review all changes
# [Find and fix any issues]
git add .
git commit -m "fix: address self-review issues"

# 5. Push branch
git push origin feature/phase-1-foundation

# 6. Pause for your review
```

**You do this:**

```bash
# 1. Review on GitHub or locally
git checkout feature/phase-1-foundation
git diff develop..feature/phase-1-foundation

# 2. Test manually
docker compose exec app bash
php artisan serve --host=0.0.0.0 &
npm run dev -- --host &
# Test features in browser

# 3. Create PR on GitHub
# feature/phase-1-foundation → develop
# Title: "Phase 1: Foundation (Features 1.1-1.3)"
# Description: [from checkpoint summary]

# 4. Review PR, approve, merge

# 5. Delete feature branch (optional)
git branch -d feature/phase-1-foundation
git push origin --delete feature/phase-1-foundation

# 6. Continue to next phase
```

### **Session 2: Core Video Phase (Features 2.1-2.4)**

**Claude does this automatically:**

```bash
# 1. Start from develop (now includes Phase 1)
git checkout develop
git pull origin develop  # Gets Phase 1 code

# 2. Create new feature branch
git checkout -b feature/phase-2-core-video

# 3. Build features
git commit -m "feat: add video input form"
git commit -m "feat: add URL processing"
git commit -m "feat: add AI model router"
git commit -m "feat: add cost tracking"

# 4. Self-review & fix
git commit -m "fix: address self-review issues"

# 5. Push
git push origin feature/phase-2-core-video
```

**You do this:**

```bash
# Same process: Review, test, create PR, merge, continue
```

### **Repeat for Each Phase:**

- Phase 3: `feature/phase-3-video-generation`
- Phase 4: `feature/phase-4-video-assembly`
- Phase 5: `feature/phase-5-polish`

---

## 📋 Branch Naming Convention

```
feature/phase-[number]-[description]

Examples:
✅ feature/phase-1-foundation
✅ feature/phase-2-core-video
✅ feature/phase-3-video-generation
✅ feature/phase-4-video-assembly
✅ feature/phase-5-polish

❌ feature/add-users  (too vague)
❌ phase1  (no context)
❌ video-stuff  (not descriptive)
```

---

## 🔍 Pull Request Process

### **Creating PR:**

```bash
# After Claude pushes feature branch:

# 1. Go to GitHub repo
# 2. Click "Compare & pull request"
# 3. Fill in:

Title: Phase X: [Phase Name] (Features X.1-X.X)
Example: "Phase 2: Core Video Creation (Features 2.1-2.4)"

Base: develop  (important!)
Compare: feature/phase-X-name

Description:
## Features Built
- ✅ 2.1: Video Input Form
- ✅ 2.2: URL Processing Service
- ✅ 2.3: AI Model Router
- ✅ 2.4: Cost Tracking Setup

## Self-Review
✅ Security: Passed (0 issues)
✅ Code quality: Passed (0 issues)
✅ Test coverage: 100% (50/50 tests)
✅ Performance: Passed (0 issues)

## Tests
- Backend: 32/32 passing
- Frontend: 18/18 passing
- E2E: All passing

## Manual Testing Checklist
- [ ] Create video form works
- [ ] URL extraction works
- [ ] AI routing works
- [ ] Cost tracking logs correctly

Ready for review and merge.
```

### **Reviewing PR:**

```bash
# On GitHub:
1. Click "Files changed" tab
2. Review code changes
3. Look for:
   - Security issues (Claude should have caught these)
   - Code quality
   - Test coverage
4. Approve or request changes

# Locally (optional):
git fetch origin
git checkout feature/phase-2-core-video
# Test manually
# If good, approve PR
```

### **Merging PR:**

```bash
# On GitHub:
1. Click "Merge pull request"
2. Choose "Squash and merge" or "Create a merge commit"
3. Confirm merge
4. Delete branch (optional)

# Pull latest develop:
git checkout develop
git pull origin develop
# Now has Phase 2 code integrated
```

---

## 🚀 Deployment Workflow

### **From Feature Branch to Production:**

```
Claude builds in:     feature/phase-2-core-video
                              ↓ (PR + merge)
Integrates into:      develop
                              ↓ (manual test)
Deploy to staging:    staging (optional)
                              ↓ (thorough test)
Merge to main:        main
                              ↓ (GitHub Actions)
Deployed to:          Production (A2 hosting)
```

### **Deploying to Production:**

**After all phases complete and tested:**

```bash
# 1. Ensure develop has all features
git checkout develop
git pull origin develop

# 2. Test thoroughly on local/staging
docker compose exec app bash
vendor/bin/phpunit  # All tests pass
npm test
npx playwright test

# 3. Merge develop to main
git checkout main
git pull origin main
git merge develop

# 4. Push to trigger deployment
git push origin main

# 5. GitHub Actions automatically:
#    - Builds code
#    - Runs tests
#    - Deploys to A2
#    - Runs migrations
#    - Health check

# 6. Visit production site
open https://yourdomain.com
```

---

## 🔄 What If You Need to Fix a Bug?

### **During Development (Before Merge):**

```bash
# Bug found in feature/phase-2-core-video

# Option 1: Let Claude fix it
claude-code
"There's a bug in VideoController line 45. Fix it."
# Claude fixes, commits, pushes

# Option 2: Fix it yourself
git checkout feature/phase-2-core-video
# Edit the file
git add .
git commit -m "fix: correct video validation logic"
git push origin feature/phase-2-core-video
# PR updates automatically
```

### **After Merge to Develop:**

```bash
# Bug found after Phase 2 merged to develop

# Create hotfix branch from develop
git checkout develop
git pull origin develop
git checkout -b fix/video-validation-bug

# Fix the bug
# [edit files]
git add .
git commit -m "fix: correct video validation in VideoController"

# Push and create PR
git push origin fix/video-validation-bug
# Create PR: fix/video-validation-bug → develop

# After merge, continue with next phase
```

### **In Production (Emergency):**

```bash
# Critical bug in production

# Create hotfix from main
git checkout main
git pull origin main
git checkout -b hotfix/critical-bug

# Fix quickly
git add .
git commit -m "hotfix: fix critical security issue"

# Merge to main (fast!)
git checkout main
git merge hotfix/critical-bug
git push origin main
# GitHub Actions deploys immediately

# Also merge back to develop
git checkout develop
git merge hotfix/critical-bug
git push origin develop
```

---

## 📊 Branch Status at Each Stage

### **After Session 1:**

```
main: Initial Laravel setup
develop: ← (same as main)
feature/phase-1-foundation: Foundation code
                            ↑ (PR open, awaiting merge)
```

### **After Merging Session 1:**

```
main: Initial Laravel setup
develop: Foundation code ← (merged from feature branch)
feature/phase-1-foundation: (deleted)
```

### **During Session 2:**

```
main: Initial Laravel setup
develop: Foundation code
feature/phase-2-core-video: Foundation + Core Video
                            ↑ (building here)
```

### **After All Sessions Complete:**

```
main: Full MVP ← (merged from develop after testing)
develop: Full MVP
feature/phase-X-name: (all deleted)
```

---

## ✅ Benefits of This Workflow

1. **Safe Development**
    - Features isolated in branches
    - Easy to abandon bad branches
    - develop always stable

2. **Clear Review Points**
    - Each PR is one checkpoint
    - Easy to review 3-5 features at once
    - Clear before/after comparison

3. **Easy Rollback**
    - Can revert individual PRs if needed
    - Don't lose all work if one phase has issues

4. **Parallel Development (Future)**
    - Multiple agents can work on different feature branches
    - Merge when ready
    - No conflicts if working on different code

5. **Production Safety**
    - main never has untested code
    - Can deploy from main with confidence
    - develop acts as integration/staging area

---

## 🎯 Quick Reference Commands

```bash
# Start new phase
git checkout develop
git pull origin develop
git checkout -b feature/phase-X-name

# During development
git add .
git commit -m "feat: add feature X.X"

# At checkpoint
git push origin feature/phase-X-name
# Create PR on GitHub
# Review, test, merge

# Start next phase
git checkout develop
git pull origin develop
git checkout -b feature/phase-X-name
# Repeat

# Deploy to production (after all done)
git checkout main
git merge develop
git push origin main
# GitHub Actions deploys
```

---

## 📝 Summary

**Workflow:**

1. Claude creates feature branch from develop
2. Claude builds features, commits regularly
3. Claude self-reviews and fixes issues
4. Claude pushes branch
5. You review PR, test, merge to develop
6. Repeat for each phase
7. After all phases: merge develop to main
8. GitHub Actions deploys to production

**Key Points:**

- ✅ Feature branches for each checkpoint
- ✅ All branches from develop
- ✅ PRs for review before merging
- ✅ Self-review before pushing
- ✅ develop is integration branch
- ✅ main is production branch

**Result:** Clean, reviewable, safe development workflow! 🚀

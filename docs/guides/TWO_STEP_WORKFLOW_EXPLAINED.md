# Two-Step Opus Workflow: PRD → Development Plan

## Separation of WHAT from HOW

---

## 🎯 The Problem You Identified

The original prompt tried to do **two different jobs in one**:

1. Define WHAT to build (product requirements)
2. Define HOW to build it (implementation strategy)

**This caused confusion because:**

- Mixed product thinking with technical thinking
- Hard to tell what's a requirement vs. implementation detail
- Didn't allow Opus to focus on each aspect properly

---

## ✅ The Two-Step Solution

### **Step 1: Create PRD (WHAT to build)**

**File:** `prompt-1-create-prd.md`
**Focus:** Product requirements, user needs, features
**Mindset:** Product Manager

### **Step 2: Create Development Plan (HOW to build)**

**File:** `prompt-2-create-dev-plan.md`
**Focus:** Implementation order, dependencies, build phases
**Mindset:** Technical Architect

---

## 📊 What Goes Where

### **PRD (Step 1) - Product Requirements**

**Includes:**

- ✅ Executive summary (vision, problem, solution)
- ✅ User personas and journeys
- ✅ Feature specifications (WHAT each feature does)
- ✅ User stories (As a user, I want...)
- ✅ Functional requirements (The system shall...)
- ✅ Data requirements (What data is stored)
- ✅ UI/UX requirements (What users see and do)
- ✅ API specifications (What endpoints do)
- ✅ Success metrics (How to measure)
- ✅ Quality standards (What "good" looks like)

**Excludes:**

- ❌ Build order (which feature to build first)
- ❌ Implementation phases (Week 1: build X, Week 2: build Y)
- ❌ Technical dependencies (Feature X blocks Feature Y)
- ❌ File structure (create these specific files)
- ❌ Development timeline (how many weeks)

**Think:** Product specification, not implementation guide

---

### **Development Plan (Step 2) - Implementation Strategy**

**Includes:**

- ✅ Feature breakdown (how to organize builds)
- ✅ Build order (Feature 1.1 → 1.2 → 1.3)
- ✅ Dependencies (X must be built before Y)
- ✅ Parallel tracks (A and B can build simultaneously)
- ✅ Timeline (Week 1-2: Phase 1, Week 3-4: Phase 2)
- ✅ Files to create (migrations, models, controllers, etc.)
- ✅ Testing strategy per feature
- ✅ Checkpoints (when to pause for review)
- ✅ Integration points (when parallel work merges)
- ✅ Dependency diagrams (ASCII art showing order)

**Excludes:**

- ❌ Why features exist (that's in PRD)
- ❌ User stories (that's in PRD)
- ❌ Business requirements (that's in PRD)
- ❌ Success metrics (that's in PRD)

**Think:** Build instructions, not product requirements

---

## 🔄 Complete Workflow

### **Step 0: Create Brief**

You write: `docs/planning/prd-brief-for-opus.md`

- Your vision, goals, workflow, requirements

### **Step 1: Opus Creates PRD**

```bash
# In project directory
claude-code  # or your Opus access method

# Paste: prompt-1-create-prd.md
```

**Opus does:**

- Reads: `docs/planning/prd-brief-for-opus.md`
- Thinks: "What should this product do? Why? For whom?"
- Writes: `docs/planning/PRD.md` (30-40 pages)

**Output focus:** Product specifications, user needs, feature requirements

---

### **Step 2: Opus Creates Development Plan**

```bash
# Same session or new session
claude-code

# Paste: prompt-2-create-dev-plan.md
```

**Opus does:**

- Reads: `docs/planning/PRD.md` (from Step 1)
- Thinks: "How should we build this? What order? What dependencies?"
- Writes: `docs/planning/development-plan.md` (15-25 pages)

**Output focus:** Build order, dependencies, timeline, implementation details

---

### **Step 3: Claude Code Builds**

```bash
# In Docker container
docker compose exec app bash
claude-code

# Paste: autonomous-dev-prompt-initial.md
```

**Claude Code does:**

- Reads: `docs/planning/PRD.md` (understands WHAT to build)
- Reads: `docs/planning/development-plan.md` (understands HOW to build)
- Builds features according to plan

---

## 💡 Why This Separation is Better

### **1. Clearer Focus**

**Step 1 - Product Thinking:**

```
"Users need to create videos without showing their face"
→ Feature: Video Creation Workflow
→ User Story: As a creator, I want to generate scripts
   so I can create content without writing
→ Requirement: System shall generate 3-5 script options
```

**Step 2 - Technical Thinking:**

```
"Script generation depends on project setup"
→ Build Phase 1: Projects (Feature 1.3)
→ Build Phase 2: Script Generation (Feature 2.4)
→ Dependency: 2.4 requires 1.3 complete
→ Files: ScriptController.php, ScriptService.php, etc.
```

**Separate concerns = clearer thinking**

---

### **2. Better Quality**

**PRD Benefits:**

- Opus can focus on user needs without worrying about build order
- More thorough feature specifications
- Better user stories and acceptance criteria
- Clearer success metrics

**Development Plan Benefits:**

- Opus can analyze dependencies without mixing with requirements
- Better optimization for parallel development
- More realistic timelines
- Clearer technical breakdown

---

### **3. Flexibility**

**Can iterate separately:**

- Change WHAT (requirements) → Update PRD → Regenerate Dev Plan
- Change HOW (build approach) → Keep PRD → Update Dev Plan
- Don't have to regenerate everything if one changes

**Example:**

```
Scenario: Decide to add a new feature

Option A (Old way - combined):
- Regenerate entire 60-page document
- Risk breaking good parts
- Mix product and technical changes

Option B (New way - separated):
- Update PRD with new feature (product decision)
- Regenerate Dev Plan (technical re-planning)
- PRD changes are clear, Dev Plan adapts
```

---

### **4. Reusability**

**PRD can be used for:**

- Development planning (Step 2)
- Marketing materials
- User documentation
- Investor presentations
- Customer conversations

**Development Plan can be used for:**

- AI coding agents (Claude Code)
- Human developers (if needed)
- Project management
- Timeline estimation
- Resource planning

**Each document serves multiple purposes independently**

---

## 📋 Key Differences in Detail

### **Example: Authentication Feature**

#### **In PRD (Step 1):**

```markdown
### Feature: User Authentication

**Purpose:**
Allow users to securely access their account and projects.

**User Stories:**

- As a new user, I want to register so I can create an account
- As a returning user, I want to login so I can access my projects
- As a user, I want to reset my password if I forget it

**Functional Requirements:**

- System shall validate email format
- System shall enforce password minimum 8 characters
- System shall send verification email on registration
- System shall lock account after 5 failed login attempts
- System shall provide "forgot password" flow

**Data Requirements:**

- Store: user ID, email, hashed password, email_verified_at
- Relationships: User hasMany Projects

**UI/UX Requirements:**

- Registration form: email, password, password confirmation
- Login form: email, password, remember me checkbox
- Password reset: email input, confirmation flow

**Acceptance Criteria:**

- User can register with valid email/password
- User receives verification email
- User can login with verified account
- Failed logins are rate-limited
```

**Focus:** WHAT the feature does, WHY it exists, WHO uses it

---

#### **In Development Plan (Step 2):**

````markdown
### Phase 1, Feature 1.2: User Authentication

**Build After:** Feature 1.1 (Database Schema)
**Blocks:** Features 1.3, 2.1, 2.2 (everything needing auth)
**Can Parallelize With:** Feature 1.4 (UI Framework Setup)

**Build Breakdown:**

**Database:**

```sql
-- Already exists from 1.1, verify columns:
users table: id, name, email, password, email_verified_at, remember_token, timestamps
```
````

**Backend:**

1. Create `app/Http/Controllers/AuthController.php`
    - register() method
    - login() method
    - logout() method
    - resetPassword() method

2. Create `app/Http/Requests/RegisterRequest.php`
    - Validate: email (required, email, unique), password (required, min:8, confirmed)

3. Create `app/Services/AuthService.php`
    - generateToken() method
    - sendVerificationEmail() method
    - hashPassword() method

4. Update `routes/api.php`
    - POST /api/register
    - POST /api/login
    - POST /api/logout
    - POST /api/password/reset

**Frontend:**

1. Create `resources/js/components/Auth/RegisterForm.jsx`
    - Form fields: email, password, passwordConfirmation
    - Submit handler with validation

2. Create `resources/js/components/Auth/LoginForm.jsx`
    - Form fields: email, password, rememberMe
    - Submit handler with error handling

3. Create `resources/js/contexts/AuthContext.jsx`
    - Store user state
    - Login/logout functions
    - Token management

**Testing:**

1. Unit tests (12 tests):
    - AuthController: register success, register validation, login success, etc.
    - AuthService: token generation, email sending, etc.

2. Integration tests (5 tests):
    - Complete registration flow
    - Login/logout flow
    - Password reset flow

3. E2E tests (2 tests):
    - User can register and login
    - User can reset forgotten password

**Files to Create:** [comprehensive list of 15 files]

**Estimated Time:** 3 days
**Complexity:** Medium

```

**Focus:** HOW to build it, WHAT order, WHAT files, HOW to test

---

## 🎯 Benefits Summary

| Aspect | Combined (Old) | Separated (New) |
|--------|---------------|-----------------|
| **Focus** | Mixed product + technical | Clear separation |
| **Quality** | Diluted attention | Deep focus on each |
| **Clarity** | Requirements unclear | Each doc has single purpose |
| **Flexibility** | Must regenerate all | Update independently |
| **Reusability** | Limited | Multiple uses per doc |
| **Understanding** | Hard to navigate | Easy to find what you need |

---

## 📂 File Structure After Both Steps

```

docs/
└── planning/
├── prd-brief-for-opus.md # Your input (Step 0)
├── PRD.md # Opus output (Step 1) - WHAT
└── development-plan.md # Opus output (Step 2) - HOW

```

**Then Claude Code reads both:**
- PRD.md → Understands product requirements
- development-plan.md → Understands build order

---

## 🔄 Iteration Examples

### **Example 1: Add New Feature**

```

1. Update brief with new feature
2. Regenerate PRD (Step 1)
   → Opus adds new feature specs
3. Regenerate Dev Plan (Step 2)  
   → Opus figures out where feature fits in build order
4. Claude Code builds according to updated plan

```

---

### **Example 2: Change Build Order**

```

1. PRD stays the same (requirements unchanged)
2. Regenerate Dev Plan (Step 2) with different strategy
   → Opus reorganizes build phases
   → Maybe more parallelization
   → Maybe different dependency order
3. Claude Code builds according to new plan

```

---

### **Example 3: Refine Feature Requirements**

```

1. Update PRD directly (edit Section 4)
   → Make feature requirements clearer
   → Add more user stories
   → Refine acceptance criteria
2. Regenerate Dev Plan (Step 2)
   → Opus adjusts implementation based on clearer requirements
3. Claude Code builds with better understanding

```

---

## ✅ Updated Quick Start Guide

The Quick Start Guide now shows:

**Step 0:** Setup project structure
**Step 1:** Opus creates PRD (use prompt-1-create-prd.md)
**Step 2:** Opus creates Development Plan (use prompt-2-create-dev-plan.md)
**Step 3:** Claude Code builds (use autonomous-dev-prompt-initial.md)

---

## 🎓 Key Takeaways

**Two separate prompts:**
1. `prompt-1-create-prd.md` → WHAT to build (product focus)
2. `prompt-2-create-dev-plan.md` → HOW to build it (technical focus)

**Two separate outputs:**
1. `docs/planning/PRD.md` → Product Requirements
2. `docs/planning/development-plan.md` → Implementation Plan

**Benefits:**
- ✅ Clearer thinking (separate concerns)
- ✅ Better quality (focused attention)
- ✅ More flexible (iterate independently)
- ✅ More reusable (each doc has multiple uses)

**Result:** Better requirements, better plan, better code! 🚀
```

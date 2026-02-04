# Prompt for Claude Opus: Generate Development Plan

## Step 2: Define HOW to Build It

---

## Your Task

Create a comprehensive Development Plan that defines **HOW** to build the application described in the PRD. This plan will guide AI coding agents (Claude Code, Cursor, etc.) through implementing the features in optimal order.

**Focus on:** Implementation order, dependencies, build phases, technical breakdown
**Based on:** The PRD that defines WHAT to build

---

## Input and Output

**Read from:** `docs/planning/PRD.md` (created in Step 1)
**Write to:** `docs/planning/development-plan.md`

This Development Plan will be used by AI coding agents to build the application.

---

## Context

The PRD defines WHAT to build. Your job is to figure out the best way to build it:

- Break features into buildable units
- Identify dependencies (what must be built first)
- Organize into logical phases
- Optimize for parallel development where possible
- Create realistic timeline

**Critical:** This will be built by AI coding agents with minimal human intervention. Your plan must be:

- Extremely detailed and specific
- Optimized for modular, incremental development
- Clear about dependencies and integration points
- Testable at every step

---

## Your Output: Development Plan

Create a detailed plan (typically 15-25 pages) that answers: "How do we build this?"

---

## 1. Development Strategy Overview (2-3 pages)

### 1.1 Approach

- Modular, test-driven development
- Feature-by-feature incremental building
- Continuous integration approach
- Quality gates at each checkpoint

### 1.2 Technology Stack Summary

- Brief recap of tech stack from PRD
- Key frameworks and tools
- Development environment setup requirements

### 1.3 Development Principles

- How AI agents should work (autonomous with checkpoints)
- Testing requirements (test before moving on)
- Code quality standards
- Documentation expectations

---

## 2. Feature Breakdown & Build Order (10-15 pages)

**Your job:** Analyze the PRD features and break them into optimal build order.

### For Each Build Phase:

```markdown
## Phase [X]: [Phase Name]

**Goal:** [What this phase accomplishes]
**Duration Estimate:** [Realistic estimate based on complexity]
**Checkpoint:** [Pause point for review]

### Features in This Phase:

#### Feature [X.1]: [Feature Name]

**Purpose:** [Brief - from PRD]

**Build Breakdown:**

- **Database:** Tables/migrations needed
    - Table: `users` (fields: id, name, email, password, timestamps)
    - Table: `projects` (fields: id, user_id, name, settings, timestamps)
    - Relationships: User hasMany Projects

- **Backend:**
    - Models: User, Project
    - Controllers: AuthController, ProjectController
    - Routes: POST /api/register, POST /api/login, GET /api/projects, etc.
    - Services: AuthService (handles JWT), ProjectService (business logic)
    - Validation: RegisterRequest, LoginRequest, ProjectRequest

- **Frontend:**
    - Components: LoginForm, RegisterForm, ProjectList, ProjectForm
    - Pages: LoginPage, DashboardPage, ProjectsPage
    - State: auth context, project context
    - API integration: auth API, projects API

- **Testing:**
    - Unit tests: AuthController (5 tests), ProjectController (8 tests)
    - Integration tests: Auth flow (2 tests), Projects CRUD (4 tests)
    - E2E tests: Complete user journey (1 test)

**Files to Create:** (Comprehensive list)
```

database/migrations/YYYY_MM_DD_create_users_table.php
database/migrations/YYYY_MM_DD_create_projects_table.php
app/Models/User.php
app/Models/Project.php
app/Http/Controllers/AuthController.php
app/Http/Controllers/ProjectController.php
app/Http/Requests/RegisterRequest.php
app/Services/AuthService.php
routes/api.php
resources/js/components/Auth/LoginForm.jsx
resources/js/components/Projects/ProjectList.jsx
resources/js/pages/DashboardPage.jsx
tests/Unit/AuthControllerTest.php
tests/Feature/ProjectTest.php
tests/E2E/user-flow.spec.js

```

**Dependencies:**
- Requires: [None / Feature X.Y]
- Blocks: [Features that need this first]
- Can parallelize with: [Features that can be built simultaneously]

**Acceptance Criteria:**
- All tests pass (100%)
- API endpoints respond correctly
- UI components render properly
- User can complete workflow end-to-end

**Estimated Complexity:** [Low / Medium / High]
**Estimated Time:** [X hours/days]

**AI Agent Notes:**
- Start with migrations and models
- Test database relationships before controllers
- Build API endpoints before frontend
- Use Laravel API Resources for responses
- Follow RESTful conventions
```

---

## 3. Phase Organization & Parallelization (3-5 pages)

### 3.1 Recommended Phases

Organize features into phases based on dependencies. Example structure:

```markdown
### Phase 1: Foundation (Week 1-2)

**Goal:** Core infrastructure that everything depends on

**Sequential (must be in order):**

- 1.1: Database Schema Setup
    - Dependencies: None
    - Blocks: Everything else

**Parallel Track A: Backend Core**

- 1.2: User Authentication (after 1.1)
- 1.3: Project Management (after 1.1)

**Parallel Track B: Frontend Foundation**

- 1.4: UI Framework Setup (can start immediately)
- 1.5: Auth UI Components (after 1.2 API exists)

**Integration Point:** End of Week 2

- Merge parallel tracks
- Integration tests: Auth flow + Project creation
- Checkpoint: User can register, login, create projects

---

### Phase 2: Core Video Creation (Week 3-4)

**Goal:** Basic video creation workflow

**Sequential:**

- 2.1: Video Input Form (depends on 1.3 Projects)
    - Blocks: 2.2, 2.3

**Parallel Track A: Backend Processing**

- 2.2: URL Processing Service (after 2.1)
- 2.3: AI Model Router (after 2.1)
- 2.4: Cost Tracking (after 2.3)

**Parallel Track B: Frontend UI**

- 2.5: Video Creation UI (after 2.1)
- 2.6: Progress Tracking UI (after 2.2)

**Integration Point:** End of Week 4

- Checkpoint: User can create video, see progress
```

### 3.2 Dependency Diagram

Create ASCII art showing dependencies:

```
┌─────────────┐
│ 1.1 Database│ (no dependencies - START HERE)
└──────┬──────┘
       │
       ├──────────┬──────────────┬─────────────┐
       │          │              │             │
  ┌────▼───┐  ┌──▼────┐  ┌──────▼────┐  ┌────▼─────┐
  │1.2 Auth│  │1.3 Proj│  │1.4 UI Setup│  │(parallel)│
  └────┬───┘  └───┬────┘  └──────┬────┘  └──────────┘
       │          │              │
       └────┬─────┴──────────────┘
            │
       ┌────▼──────┐
       │ 2.1 Video │
       │   Input   │
       └────┬──────┘
            │
            ├──────────┬──────────────┐
            │          │              │
       ┌────▼───┐  ┌──▼────┐   ┌─────▼─────┐
       │2.2 URL │  │2.3 AI │   │2.5 Video  │
       │Process │  │Router │   │   UI      │
       └────────┘  └───┬───┘   └───────────┘
                       │
                   ┌───▼────┐
                   │2.4 Cost│
                   │Tracking│
                   └────────┘
```

### 3.3 Parallel Development Opportunities

Identify where multiple AI agents (or sessions) can work simultaneously:

- Phase 1: Backend auth + Frontend UI (2 parallel tracks)
- Phase 2: Backend processing + Frontend components (2 parallel tracks)
- Phase 3: Script generation + Thumbnail generation (can be parallel)

---

## 4. Testing Strategy (2-3 pages)

### 4.1 Test-Driven Development Approach

- Write tests for each feature
- Tests must pass before moving on
- 100% pass rate required at each checkpoint

### 4.2 Testing Levels

**Unit Tests:**

- Every controller method
- Every service method
- Every validation class
- Tools: PHPUnit (Laravel), Jest (React), or alternatives

**Integration Tests:**

- API endpoint flows
- Database relationships
- Service integrations
- External API mocks

**E2E Tests:**

- Critical user journeys
- Complete workflows
- Tools: Playwright, Cypress, or alternatives

### 4.3 Test Coverage Requirements

- Critical paths: >80% coverage (or justify different threshold)
- All new features must include tests
- Tests run automatically before each commit

### 4.4 Quality Gates

Every feature must pass:

- ✅ All tests passing (100%)
- ✅ No linter errors
- ✅ No security vulnerabilities
- ✅ Code committed to git
- ✅ Documentation complete

---

## 5. Development Workflow (2-3 pages)

### 5.1 Git Branching Strategy

- `main` - Production code
- `develop` - Integration branch
- `feature/phase-X-name` - Feature branches

### 5.2 Checkpoint Process

After each phase:

1. Run full test suite
2. Self-review for security, quality, performance
3. Fix any issues found
4. Push feature branch
5. Create PR for review
6. Merge to develop after approval

### 5.3 Autonomous Development Flow

For AI coding agents:

1. Read feature specification
2. Create all necessary files
3. Implement functionality
4. Write comprehensive tests
5. Run tests (fix until passing)
6. Run linter (fix until clean)
7. Commit with descriptive message
8. Update progress tracking
9. Move to next feature
10. Stop at checkpoint for review

---

## 6. Timeline & Milestones (2-3 pages)

### 6.1 Realistic Timeline

Based on feature complexity and dependencies, provide week-by-week breakdown:

```markdown
**Week 1-2: Phase 1 - Foundation**

- Feature 1.1: Database Schema (2 days)
- Feature 1.2: Authentication (3 days, parallel)
- Feature 1.3: Projects (3 days, parallel)
- Feature 1.4-1.5: Frontend (4 days, parallel with backend)
- Integration & Testing (2 days)
- CHECKPOINT 1

**Week 3-4: Phase 2 - Core Video Creation**

- Feature 2.1: Video Input (2 days)
- Features 2.2-2.4: Backend Processing (5 days, parallel)
- Features 2.5-2.6: Frontend UI (5 days, parallel)
- Integration & Testing (2 days)
- CHECKPOINT 2

[Continue for all phases]

**Total Estimated Timeline:** [X weeks]
```

### 6.2 Milestones

- Checkpoint 1: Foundation complete (can auth, manage projects)
- Checkpoint 2: Core video workflow (can create videos)
- Checkpoint 3: Generation features (AI content creation working)
- Checkpoint 4: Assembly & output (can render and download)
- Checkpoint 5: MVP complete (ready for beta users)

### 6.3 Risk Buffer

- Add 20% buffer for unexpected issues
- Plan for integration complexity
- Account for external API learning curves

---

## 7. External Service Integration (1-2 pages)

### 7.1 API Integration Order

Plan when to integrate external services:

**Phase 2:**

- Mock AI APIs initially
- Integrate Anthropic API for text generation

**Phase 3:**

- Integrate Gemini/Imagen for images
- Integrate ElevenLabs for TTS

**Phase 4:**

- Integrate Shotstack for video rendering
- Integrate Pexels/Pixabay for stock footage

### 7.2 API Testing Strategy

- Use mocks during development
- Test with real APIs at integration points
- Monitor API costs during testing
- Set up rate limiting

---

## 8. Risk Mitigation (1-2 pages)

### 8.1 Technical Risks

- **Risk:** External API changes/downtime
- **Mitigation:** Abstract API calls into services, use mocks for testing

- **Risk:** Complex video assembly issues
- **Mitigation:** Build incrementally, test each piece

### 8.2 Timeline Risks

- **Risk:** Features take longer than estimated
- **Mitigation:** 20% buffer, prioritize core features

### 8.3 Quality Risks

- **Risk:** AI-generated code has bugs
- **Mitigation:** Comprehensive testing, self-review at checkpoints

---

## 9. Success Criteria (1 page)

### 9.1 MVP Completion Criteria

- ✅ All Phase 1-5 features implemented
- ✅ All tests passing (100%)
- ✅ End-to-end workflow functional
- ✅ User can create and download videos
- ✅ Documentation complete
- ✅ Ready for beta testing

### 9.2 Quality Standards

- Code coverage >80% for critical paths
- No security vulnerabilities
- Response times <500ms for API calls
- Successful video generation rate >95%

---

## 10. AI Agent Implementation Guide (2-3 pages)

### 10.1 How to Use This Plan

**For Claude Code (or similar AI coding agent):**

1. **Read the PRD first** to understand WHAT you're building
2. **Read this Development Plan** to understand build order
3. **Start with Phase 1, Feature 1.1**
4. **Follow the feature breakdown exactly:**
    - Create database files first
    - Then models
    - Then controllers
    - Then services
    - Then frontend
    - Then tests
5. **Run tests after each feature** - must pass before continuing
6. **Stop at checkpoints** for human review
7. **Update progress tracking** after each feature

### 10.2 Common Pitfalls to Avoid

- ❌ Building features out of order (respect dependencies!)
- ❌ Skipping tests (test before moving on!)
- ❌ Not checking for existing code (don't recreate files!)
- ❌ Moving to next feature when tests fail (fix first!)

### 10.3 Code Organization Standards

```
app/
├── Models/           # Database models
├── Http/
│   ├── Controllers/  # Request handlers
│   ├── Requests/     # Validation classes
│   └── Resources/    # API response formatting
├── Services/         # Business logic
└── Exceptions/       # Custom exceptions

resources/js/
├── components/       # Reusable UI components
├── pages/           # Page components
├── contexts/        # React contexts
└── utils/           # Helper functions

tests/
├── Unit/            # Unit tests
├── Feature/         # Integration tests
└── E2E/             # End-to-end tests
```

---

## Deliverable Checklist

Your Development Plan should include:

- ✅ Feature breakdown with exact build order
- ✅ Files to create for each feature
- ✅ Dependencies clearly marked
- ✅ Parallel work opportunities identified
- ✅ Timeline with realistic estimates
- ✅ Testing requirements per feature
- ✅ Quality gates and checkpoints
- ✅ Integration points specified
- ✅ Dependency diagram (ASCII art)
- ✅ Risk mitigation strategies
- ✅ AI agent implementation guide

---

## To Complete This Task

1. Read PRD at `docs/planning/PRD.md` thoroughly
2. Analyze all features and their relationships
3. Determine optimal build order (dependencies first)
4. Break down each feature into concrete tasks
5. Estimate time realistically
6. Identify parallelization opportunities
7. Write Development Plan to `docs/planning/development-plan.md`

Think like a technical architect. Focus on HOW to build efficiently, not WHAT to build (that's in the PRD).

Begin with strategy overview, then break down each phase systematically. Be extremely detailed—AI coding agents need explicit instructions. Good luck!

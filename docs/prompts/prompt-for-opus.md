# Prompt for Claude Opus: Generate Product Requirements Document

## Your Task

Please create a comprehensive, production-ready Product Requirements Document (PRD) based on the brief located at `docs/planning/prd-brief-for-opus.md`. This PRD will guide AI-powered development of a faceless video creation platform for YouTube creators.

**IMPORTANT: Output Instructions**

- Write your complete PRD to: `docs/planning/PRD.md`
- This will be the single source of truth for the entire development process
- Ensure the file is properly formatted markdown that can be read by AI coding agents

## Context

This product will be built entirely using AI coding agents (Claude Code, Cursor, etc.) with minimal direct human coding. The PRD you create will be the foundation for:

1. **AI agents building the codebase** - They need clear, modular specifications
2. **Planning the development phases** - Breaking features into buildable components
3. **Automated testing strategy** - Every feature must include test specifications
4. **Quality assurance** - Defining what "done" looks like for each feature

## What You'll Receive

You'll receive one comprehensive brief document located at `docs/planning/prd-brief-for-opus.md` that includes:

- Executive summary and product vision
- Extracted methodology from proven YouTube creation workflow (14 steps)
- Complete Phase 1 MVP specifications (suggested feature areas)
- Phase 2 roadmap (future feature ideas)
- Technical architecture (brief suggests Laravel + React + external APIs—evaluate and justify your technology choices)
- User experience flows
- Success metrics and timeline
- AI/agentic coding requirements

The brief also references the user's original workflow documentation (16 markdown files) which contain the proven methodology for creating high-quality YouTube videos. **These files are NOT required for you to read** - I've already extracted the key principles and methodology into the brief.

## Your Output: Comprehensive PRD

Create a **comprehensive PRD** with the following structure. Aim for thorough depth—typically 40-60 pages, but use your judgment based on feature complexity and the detail needed for AI-driven development:

### 1. Executive Summary (2-3 pages)

**Note on page counts throughout this document:** The page ranges provided are guidelines for proportion and depth, not hard limits. Use your judgment—some sections may need more depth, others less, depending on the complexity of your feature breakdown and architectural decisions.

- Product name and vision statement
- Problem statement and solution
- Target market and users
- Key differentiators
- Success criteria
- Timeline overview

### 2. Product Vision & Strategy (3-5 pages)

- Market analysis and competitive landscape
- User personas (detailed)
- Value proposition
- Product positioning
- Go-to-market considerations

### 3. Phase 1: MVP Requirements (20-30 pages)

**Your job:** Break down the MVP into logical, buildable features. You decide how many features make sense and how to organize them. The brief suggests ~10 feature areas, but **you should use your judgment** - it might be 8 features, 12 features, or 15 features depending on how you think the work should be organized.

**Consider:**

- What makes a logical, testable unit of work?
- What can be built and deployed independently?
- What features have clear dependencies vs. can be parallel?
- How can you organize features to minimize back-and-forth?

For each feature you define, provide:

**Feature Template:**

```
### [Feature Name]

**Purpose:** Why this feature exists (user problem it solves)

**User Stories:**
- As a [user type], I want to [action] so that [benefit]
- [Include enough user stories to fully capture all user needs and use cases—typically 3-5 for most features, but use judgment based on feature complexity]

**Functional Requirements:**
- [Detailed list of what the feature must do]
- [Include edge cases and validation rules]
- [Specify error handling]

**Technical Specifications:**
- API endpoints required (if applicable)
- Database tables/fields needed
- External services integration
- Data flow diagrams (describe in text)

**UI/UX Requirements:**
- Page/component descriptions
- User interaction flows
- Form fields and validations
- Responsive behavior (desktop/mobile)

**Testing Requirements:**
- Unit test scenarios (comprehensive coverage of all critical paths, edge cases, and error conditions)
- Integration test scenarios
- E2E test user flows
- Performance benchmarks

**Acceptance Criteria:**
- Clear definition of "done"
- Quality gates that must pass
- [Specific, measurable criteria]

**Dependencies:**
- What must be built first
- What external services are needed
- What data is required

**AI Agent Implementation Notes:**
- Specific guidance for AI coder
- Common pitfalls to avoid
- Code structure recommendations
- Security considerations
```

**Guidelines for defining features:**

- Think in terms of user value - each feature should deliver something meaningful
- Consider technical dependencies - what must be built first?
- Balance scope - not too small (trivial) or too large (overwhelming)
- Think about testing - can this feature be tested independently?
- Consider parallel development - can multiple agents work simultaneously?

The brief mentions areas like authentication, project management, video workflow, script generation, thumbnails, voiceover, assembly, metadata, downloads, and history - but **you decide how to break these down into features**. You might combine some, split others, or identify additional features not explicitly mentioned.

### 4. Phase 2: Future Roadmap (5-8 pages)

- Identify and document future features at HIGH LEVEL (not detailed specs)
- You decide which features make sense for Phase 2
- Purpose and user benefit for each
- Estimated complexity (low/medium/high)
- Dependencies and prerequisites
- Suggested timeline

The brief may suggest some Phase 2 ideas, but **you should think critically** about what makes sense to include, what to defer, and what additional features might be valuable.

### 5. Technical Architecture (8-12 pages)

**Include:**

- System architecture diagram (describe in detail - ASCII art acceptable)
- Technology stack with justifications
- Database schema (all tables with field types, indexes, relationships)
- API integration specifications (for each external service)
- Security architecture
- Error handling and logging strategy
- Performance considerations
- Scalability approach

**Critical:** Emphasize AI/Agentic Development Requirements

- Modular architecture principles
- Test-driven development approach
- Code review checkpoints
- Quality gates (linting, security scans, test coverage)
- Documentation standards
- AI agent workflow example

### 6. User Experience Design (8-12 pages)

**Include:**

- Information architecture
- Detailed user flows (all major paths)
    - First-time user onboarding
    - Creating a video (complete flow)
    - Managing projects
    - Reviewing and downloading outputs
    - Error recovery flows
- UI component specifications
    - Dashboard
    - Project creation form
    - Progress tracking interface
    - Selection interfaces (title, thumbnail, voice, music)
    - Download page
- Responsive design considerations
- Accessibility requirements (WCAG 2.1 AA minimum)

### 7. Data & API Specifications (3-5 pages)

- All API endpoints (RESTful)
    - Method, path, parameters, response format
    - Authentication requirements
    - Rate limiting
- External API integrations (evaluate these suggestions from the brief, but recommend alternatives if better options exist):
    - Anthropic (Claude Sonnet 4) - AI text generation
    - Google Gemini (Imagen 3 via Nano Banana Pro) - image generation
    - ElevenLabs (TTS) - voiceover generation
    - Shotstack (Video rendering) - video assembly
    - Pexels/Pixabay (Stock footage) - b-roll footage
    - [Identify any additional services needed or better alternatives]
- Webhook specifications (Shotstack callbacks)
- Data models and relationships

### 8. Testing Strategy (3-5 pages)

- Unit testing approach (e.g., PHPUnit for Laravel, Jest for React, or alternatives you recommend)
- Integration testing approach
- E2E testing approach (e.g., Playwright, Cypress, or alternatives you recommend)
- Test coverage requirements (>80% for critical paths, or justify if different threshold is appropriate)
- Performance testing benchmarks
- Security testing requirements
- User acceptance testing plan
- AI-generated code quality checks

### 9. Success Metrics & Analytics (2-3 pages)

- User acquisition metrics
- Engagement metrics
- Quality indicators
- Revenue metrics
- Technical performance metrics
- How to measure (what to track, where)

### 10. Development Plan (3-5 pages)

- Realistic timeline with week-by-week breakdown (determine appropriate duration based on feature complexity, dependencies, and parallel work opportunities—don't constrain to a specific number of weeks)
- Feature development order (with dependencies)
- Testing milestones
- Deployment checkpoints
- Risk mitigation strategies
- Resource requirements (if human oversight needed at specific points)

### 11. Risk Management (2-3 pages)

- Technical risks (with mitigation plans)
- Business risks (with mitigation plans)
- User experience risks
- Cost risks
- Timeline risks

### 12. Appendices

- Glossary of terms
- API cost calculations (detailed breakdown)
- Pricing model justification
- Compliance considerations (GDPR, data retention)
- Support and maintenance plan

## Formatting Requirements

**Use clear, consistent formatting:**

- Markdown format (H1, H2, H3 headers for structure)
- Tables for comparisons, specifications, timelines
- Code blocks for API examples, database schemas
- Bullet lists for requirements and criteria
- Numbered lists for sequential processes
- **Bold** for emphasis on critical requirements
- `Code formatting` for technical terms, endpoints, field names

**Make it scannable:**

- Each section starts with brief overview
- Use descriptive headers
- Break complex sections into subsections
- Include "TL;DR" summaries for long sections

**Optimize for AI consumption:**

- Be explicit and unambiguous
- Avoid vague terms like "user-friendly" (define what that means)
- Use specific numbers (e.g., "response time <500ms" not "fast response")
- Include examples for complex requirements
- Cross-reference related sections (e.g., "See Section 5.2 for database schema")

## Critical Considerations

**1. AI Agent Development Focus:**
Every feature specification should include:

- Clear inputs and outputs
- Step-by-step implementation guidance
- Test cases that verify correctness
- Security and validation requirements
- Performance benchmarks

**2. Modularity:**
Features should be independent modules that can be built and tested separately. Clearly state dependencies between features.

**3. Quality Gates:**
Define what "done" means for each feature:

- All tests pass (100% pass rate)
- Code coverage >80% for critical paths (or justify if different threshold is appropriate)
- No linter errors
- No security vulnerabilities
- Performance benchmarks met
- Documentation complete

**4. Cost Consciousness:**
Always note where APIs are used vs. rule-based logic. Justify AI API usage vs. deterministic approaches.

**5. User-Centric:**
Every requirement should trace back to a user need or pain point. Avoid "nice-to-haves" without clear justification.

## Questions to Answer in the PRD

As you create the PRD, ensure you've answered these questions clearly:

**For Developers:**

- What exactly do I need to build?
- What are the acceptance criteria?
- What tests do I need to write?
- What are the edge cases?
- What could go wrong?

**For AI Agents:**

- What's the complete specification for this feature?
- What order should I build things in?
- How do I know when I'm done?
- What quality checks must I run?

**For Product Managers:**

- What features are in scope for MVP?
- Why are we building each feature?
- What's the timeline?
- What are the risks?

**For QA:**

- What should I test?
- What are the expected behaviors?
- What error conditions exist?
- What performance standards apply?

**For Stakeholders:**

- What's the business value?
- What will this cost?
- When will it be ready?
- How will we measure success?

## Deliverable

**Create the PRD file at:** `docs/planning/PRD.md`

The document should include:

1. Table of contents with links
2. All sections fully developed
3. Consistent formatting throughout
4. Clear, actionable specifications
5. **Dependency graph and parallel development opportunities clearly identified**
6. Appropriate depth and detail (typically 40-60 pages, but prioritize completeness over hitting a specific page count)

The PRD should be **production-ready** - meaning an AI development agent could start building immediately after reading it, with minimal clarifying questions needed.

## CRITICAL: Parallel Development Identification

**In the Development Plan section (Section 10), you MUST:**

1. **Identify all dependencies** between features
2. **Mark parallel tracks** - features that can be built simultaneously by different agents/developers
3. **Specify integration points** where parallel work merges
4. **Optimize for parallelization** to reduce overall timeline

**Recommended format for each phase** (adapt as your feature organization requires):

```markdown
### Week X-Y: [Phase Name]

**Sequential (must be built in this order):**

- Feature X.1: [Name]
    - Dependencies: None OR [Feature X.X]
    - Blocks: [Features that can't start until this is done]
    - Can parallelize with: [Features that can run alongside]

**Parallel Track A: Backend**

- Feature X.2: [Name] (can start after Feature X.1 complete)
- Feature X.3: [Name] (can start after Feature X.1 complete)

**Parallel Track B: Frontend**

- Feature X.4: [Name] (can start immediately, no backend dependencies)
- Feature X.5: [Name] (depends on X.2 API existing)

**Integration Point:**

- When: End of Week Y
- What merges: Tracks A + B
- Integration tests needed: [List]
```

**Also provide a dependency diagram using ASCII art:**

```
Example:
┌─────────┐
│  1.1 DB │ (no dependencies - start here)
└────┬────┘
     │
     ├────────┬────────────────┬─────────────┐
     │        │                │             │
┌────▼────┐ ┌▼──────────┐ ┌──▼─────────┐ ┌─▼────────┐
│ 1.2 Auth│ │1.3 Email  │ │1.4 Storage │ │1.5 Queue │
│(depends │ │(parallel) │ │(parallel)  │ │(parallel)│
│  on 1.1)│ │           │ │            │ │          │
└────┬────┘ └───────────┘ └────────────┘ └──────────┘
     │
     └──────────────┬──────────────┐
                    │              │
            ┌───────▼────┐  ┌──────▼──────┐
            │ 2.1 Video  │  │ 2.2 Project │
            │ Input API  │  │ Mgmt API    │
            │(backend)   │  │(backend)    │
            └───────┬────┘  └──────┬──────┘
                    │              │
         ┌──────────┼──────────────┤
         │          │              │
    ┌────▼─────┐ ┌──▼───────┐ ┌───▼──────┐
    │2.3 Form  │ │2.4 Dash  │ │2.5 Tests │
    │UI (front)│ │UI (front)│ │(E2E)     │
    └──────────┘ └──────────┘ └──────────┘
```

**Why this matters for AI agent development:**

- Agents can work on parallel tracks simultaneously (faster development)
- Clear dependencies prevent integration conflicts
- Developer (or AI orchestrator) knows which features can be delegated to separate agents
- Reduces overall timeline by identifying parallelizable work

## Final Note

This PRD is the foundation for an AI-built product. Your clarity, specificity, and thoroughness directly impact code quality. Take the time to be comprehensive - the investment in detailed specifications will save countless hours of debugging and refactoring later.

**To complete this task:**

1. Read the brief at `docs/planning/prd-brief-for-opus.md`
2. Create comprehensive PRD with all sections
3. Write output to `docs/planning/PRD.md`

Begin with a Table of Contents, then proceed section by section. Good luck!

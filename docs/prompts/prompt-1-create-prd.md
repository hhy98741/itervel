# Prompt for Claude Opus: Generate Product Requirements Document (PRD)

## Step 1: Define WHAT to Build

---

## Your Task

Create a comprehensive Product Requirements Document (PRD) that defines **WHAT** this application should do, **WHY** it exists, and **WHO** it serves. This is NOT about HOW to build it—that comes in Step 2 (Development Plan).

**Focus on:** User needs, features, specifications, success criteria
**Don't focus on:** Implementation order, development phases, build dependencies

---

## Input and Output

**Read from:** `docs/planning/prd-brief-for-opus.md`
**Write to:** `docs/planning/PRD.md`

This PRD will be used to create a Development Plan in the next step.

---

## What You'll Receive

The brief at `docs/planning/prd-brief-for-opus.md` describes a faceless video creation platform for YouTube creators. It includes:

- Product vision and goals
- Proven YouTube creation workflow (14 steps)
- Feature areas for MVP and future phases
- User experience requirements
- Success metrics
- Technical context (suggested tech stack)

---

## Your Output: Product Requirements Document

Create a **comprehensive PRD** (typically 30-40 pages) that answers: "What should this product do?"

### 1. Executive Summary (2-3 pages)

**Note:** Page ranges are guidelines for proportion, not hard limits. Use your judgment based on depth needed.

- **Product Name & Vision:** Clear, compelling vision statement
- **Problem Statement:** What user pain point does this solve?
- **Solution Overview:** How does this product solve it?
- **Target Market:** Who are the users?
- **Key Differentiators:** What makes this unique?
- **Success Criteria:** How do we measure success?

---

### 2. Product Vision & Strategy (3-5 pages)

- **Market Analysis:** Competitive landscape, market opportunity
- **User Personas:** Detailed user profiles (goals, pain points, behaviors)
- **Value Proposition:** Core value to users
- **Product Positioning:** How this fits in the market
- **Go-to-Market Considerations:** Launch strategy, pricing model

---

### 3. User Experience & Workflows (5-8 pages)

**Focus on the user's perspective:**

#### 3.1 User Journeys

Describe complete user flows:

- First-time user onboarding
- Creating a video (complete journey)
- Managing multiple projects/channels
- Reviewing and selecting outputs
- Downloading and publishing content
- Error recovery and edge cases

#### 3.2 Information Architecture

- Main navigation structure
- Page/screen organization
- Content hierarchy

#### 3.3 Key User Interactions

- How users accomplish primary tasks
- Decision points and choices
- Feedback mechanisms

---

### 4. Feature Requirements (20-30 pages)

**Your job:** Define comprehensive feature specifications. Organize features logically—you decide how many and how to group them. The brief suggests areas like authentication, projects, video creation, script generation, thumbnails, voiceover, assembly, metadata, and outputs, but **use your judgment** on the best organization.

**For each feature, provide:**

#### Feature Template:

```markdown
### [Feature Name]

#### Purpose

Why this feature exists (user problem it solves)

#### User Stories

- As a [user type], I want to [action] so that [benefit]
- [Include enough stories to capture all use cases—typically 3-5, but use judgment]

#### Functional Requirements

- What the feature must do
- Edge cases and validation rules
- Error handling requirements
- Business rules and constraints

#### Data Requirements

- What data is captured/stored
- Data relationships
- Data validation rules
- Privacy/security requirements

#### UI/UX Requirements

- Screen/page descriptions
- Form fields and controls
- User interaction patterns
- Responsive behavior (desktop/mobile)
- Accessibility requirements (WCAG 2.1 AA minimum)

#### Business Rules

- Pricing/billing implications (if applicable)
- Usage limits or quotas
- Permission/access rules

#### Success Metrics

- How to measure if this feature succeeds
- Key performance indicators

#### Acceptance Criteria

- Clear definition of "done"
- Quality standards
- Specific, measurable criteria

#### Dependencies & Prerequisites

- What data/features must exist first (conceptual, not implementation order)
- What external services are needed
- User prerequisites (account, permissions, etc.)
```

**Note:** Don't worry about implementation order or technical dependencies—focus on WHAT the feature does and WHY.

---

### 5. Technical Architecture (8-10 pages)

**Focus on WHAT the system needs, not HOW to build it:**

#### 5.1 System Overview

- High-level architecture description
- Major system components (conceptual)
- Data flow overview

#### 5.2 Technology Stack

- Recommended technologies (brief suggests Laravel + React—evaluate and justify)
- Why these technologies fit the requirements
- Alternatives considered

#### 5.3 Data Model

- Core entities and their relationships
- Database schema (tables, fields, relationships)
- Data flow between components

#### 5.4 External Service Requirements

- APIs needed (evaluate suggestions from brief, recommend alternatives if better):
    - AI text generation (e.g., Anthropic Claude Sonnet 4)
    - Image generation (e.g., Google Gemini Imagen 3)
    - Text-to-speech (e.g., ElevenLabs)
    - Video rendering (e.g., Shotstack)
    - Stock media (e.g., Pexels/Pixabay)
    - [Any additional services needed]
- What each service provides
- Integration requirements

#### 5.5 Security Requirements

- Authentication approach
- Authorization model
- Data protection requirements
- API security
- Compliance needs (GDPR, etc.)

#### 5.6 Performance Requirements

- Response time expectations
- Scalability needs
- Storage requirements
- Bandwidth considerations

#### 5.7 Quality Requirements

- Code quality standards
- Test coverage expectations (e.g., >80% for critical paths, or justify different threshold)
- Documentation requirements
- Error handling standards

---

### 6. API Specifications (3-5 pages)

Define all API endpoints (WHAT they do, not HOW to implement):

#### For each endpoint:

```markdown
**Endpoint:** [Method] [Path]
**Purpose:** What this endpoint does
**Input:** Parameters, request body
**Output:** Response format, status codes
**Authentication:** Required permissions
**Validation:** Input validation rules
**Errors:** Possible error conditions
**Rate Limiting:** Usage limits (if applicable)
```

Include:

- All internal API endpoints
- External API integration specifications
- Webhook requirements (e.g., Shotstack callbacks)

---

### 7. Testing Strategy (3-4 pages)

Define WHAT needs to be tested:

- **Unit Testing:** What components need unit tests, coverage expectations
- **Integration Testing:** What integrations need testing
- **E2E Testing:** Critical user flows to test (e.g., with Playwright, Cypress, or alternatives)
- **Performance Testing:** What to measure, benchmarks
- **Security Testing:** What security aspects to verify
- **User Acceptance Testing:** How users will validate features

---

### 8. Success Metrics & Analytics (2-3 pages)

How to measure product success:

- **User Acquisition Metrics:** Sign-ups, conversions
- **Engagement Metrics:** Active users, feature usage, retention
- **Quality Indicators:** Video quality, user satisfaction
- **Revenue Metrics:** MRR, ARPU, churn
- **Technical Performance:** Uptime, response times, error rates
- **How to Measure:** What to track, where to track it

---

### 9. Risk Management (2-3 pages)

Identify potential risks and mitigation approaches:

- **Technical Risks:** Technology limitations, API dependencies
- **Business Risks:** Market competition, pricing challenges
- **User Experience Risks:** Adoption barriers, learning curve
- **Cost Risks:** API costs, infrastructure costs
- **Timeline Risks:** (General - not specific development timeline)

---

### 10. Appendices

- **Glossary:** Define technical terms, acronyms
- **API Cost Estimates:** Expected costs for external services
- **Pricing Model:** How users will be charged
- **Compliance Requirements:** GDPR, data retention, terms of service
- **Support & Maintenance:** Support channels, SLAs

---

## Formatting Guidelines

**Use clear, consistent markdown:**

- H1, H2, H3 headers for structure
- Tables for comparisons, specifications
- Code blocks for API examples, schemas
- Bullet lists for requirements
- **Bold** for critical requirements
- `Code formatting` for technical terms

**Make it scannable:**

- Start each section with brief overview
- Use descriptive headers
- Include "TL;DR" for long sections
- Cross-reference related sections

**Optimize for AI reading:**

- Be explicit and unambiguous
- Use specific numbers (e.g., "response time <500ms" not "fast")
- Avoid vague terms like "user-friendly" (define what you mean)
- Include examples for complex requirements

---

## Critical Principles

### 1. User-Centric

Every requirement should trace back to a user need. Ask: "Why does the user need this?"

### 2. Completeness

Define ALL features needed for MVP. Don't assume things are "obvious."

### 3. Clarity

Be specific. "The system shall validate email addresses" not "The system should handle email properly."

### 4. Measurability

Include clear acceptance criteria. How do we know when it's done?

### 5. Technology-Appropriate

Recommend technologies that fit the requirements, justify choices.

### 6. Cost-Conscious

Note where expensive APIs are used vs. rule-based logic. Justify AI usage.

---

## What NOT to Include

**Don't include:**

- ❌ Development phases or sprint planning
- ❌ Implementation order or dependencies
- ❌ "Build Feature X first, then Y"
- ❌ Week-by-week timelines
- ❌ Specific file structures or code organization
- ❌ Detailed technical implementation steps

**Those come in Step 2: Development Plan**

---

## Questions to Answer

As you write, ensure the PRD answers:

**For Product Managers:**

- What are we building and why?
- Who are the users and what do they need?
- What's in scope for MVP vs. future?
- How do we measure success?

**For Designers:**

- What are the user workflows?
- What screens/pages are needed?
- What are the interaction patterns?
- What's the information architecture?

**For Developers (in next step):**

- What features need to be built?
- What does each feature do?
- What are the requirements and constraints?
- What's the acceptance criteria?

**For QA:**

- What should be tested?
- What are the expected behaviors?
- What are the edge cases?
- What are the quality standards?

**For Stakeholders:**

- What's the business value?
- What will this cost (operational)?
- How will we measure success?
- What are the risks?

---

## Deliverable

**Create:** `docs/planning/PRD.md`

A production-ready PRD that:

1. Clearly defines WHAT to build
2. Explains WHY we're building it
3. Specifies WHO will use it
4. Defines success criteria
5. Details all feature requirements
6. Provides technical specifications
7. Sets quality standards

**This PRD will be input to Step 2: Development Plan**

---

## To Complete This Task

1. Read the brief at `docs/planning/prd-brief-for-opus.md`
2. Think about the product from user perspective
3. Define comprehensive feature requirements
4. Write PRD to `docs/planning/PRD.md`

Focus on WHAT the product does, not HOW to build it. Think like a product manager, not a developer.

Begin with Executive Summary, then work through each section systematically. Good luck!

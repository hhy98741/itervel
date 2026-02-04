# Development Plan

# Itervel - AI-Powered Faceless Video Creation Platform

**Version:** 1.0
**Date:** January 2026
**Status:** Ready for Implementation
**Based on:** PRD v1.0

---

## Table of Contents

1. [Development Strategy Overview](#1-development-strategy-overview)
2. [Feature Breakdown & Build Order](#2-feature-breakdown--build-order)
3. [Phase Organization & Parallelization](#3-phase-organization--parallelization)
4. [Testing Strategy](#4-testing-strategy)
5. [Development Workflow](#5-development-workflow)
6. [Timeline & Milestones](#6-timeline--milestones)
7. [External Service Integration](#7-external-service-integration)
8. [Risk Mitigation](#8-risk-mitigation)
9. [Success Criteria](#9-success-criteria)
10. [AI Agent Implementation Guide](#10-ai-agent-implementation-guide)

---

## 1. Development Strategy Overview

### 1.1 Approach

This project follows a **modular, test-driven development** approach optimized for AI coding agents:

- **Feature-by-Feature Incremental Building**: Each feature is a self-contained unit built in dependency order
- **Test Before Moving On**: Every feature must pass its test suite before proceeding
- **Continuous Integration**: All code committed to git with quality gates
- **Checkpoint Reviews**: Human review points at the end of each phase

### 1.2 Technology Stack Summary

#### Frontend

| Component        | Technology      | Version |
| ---------------- | --------------- | ------- |
| Framework        | React           | 18.x    |
| Build Tool       | Vite            | 5.x     |
| Styling          | Tailwind CSS    | 3.x     |
| State Management | Zustand         | 4.x     |
| Routing          | React Router    | 6.x     |
| Forms            | React Hook Form | 7.x     |
| File Upload      | React Dropzone  | 14.x    |
| HTTP Client      | Axios           | 1.x     |
| Type Safety      | TypeScript      | 5.x     |

#### Backend

| Component   | Technology      | Version |
| ----------- | --------------- | ------- |
| Framework   | Laravel         | 11.x    |
| Language    | PHP             | 8.2+    |
| Database    | MariaDB         | 10.x    |
| Cache/Queue | Redis           | 7.x     |
| Auth        | Laravel Sanctum | —       |
| Storage     | Flysystem (R2)  | —       |

#### Infrastructure

| Component       | Technology        |
| --------------- | ----------------- |
| Web Hosting     | A2 Shared Hosting |
| File Storage    | Cloudflare R2     |
| CDN             | Cloudflare        |
| Video Rendering | Shotstack API     |
| Error Tracking  | Sentry            |
| CI/CD           | GitHub Actions    |

#### External Services

| Service  | Provider                           | Purpose              |
| -------- | ---------------------------------- | -------------------- |
| Text AI  | Anthropic (Claude), OpenAI (GPT-4) | Content generation   |
| Image AI | Google Gemini Imagen 3             | Thumbnail generation |
| TTS      | ElevenLabs                         | Voiceover generation |
| Video    | Shotstack                          | Video rendering      |
| Stock    | Pexels, Pixabay                    | Stock footage        |
| Payments | Stripe                             | Credit purchases     |

### 1.3 Development Principles

#### AI Agent Workflow

1. Read feature specification completely before starting
2. Create files in the specified order (migrations → models → services → controllers → frontend)
3. Write tests for each component
4. Run tests and fix until 100% pass
5. Commit with descriptive message
6. Move to next feature
7. Stop at checkpoints for human review

#### Code Quality Standards

- Follow PSR-12 for PHP code
- Follow Airbnb style guide for TypeScript/React
- No hardcoded credentials (use .env)
- Type hints on all function parameters and returns
- Doc blocks for complex functions
- Meaningful variable and function names

#### Testing Requirements

- Unit tests for all services and utilities
- Feature tests for all API endpoints
- E2E tests for critical user flows
- 80%+ coverage for critical paths
- 100% pass rate before proceeding

---

## 2. Feature Breakdown & Build Order

---

## Phase 1: Foundation

**Goal:** Establish core infrastructure, authentication, and basic UI framework
**Duration Estimate:** 2 weeks
**Checkpoint:** User can register, verify email, login, and see dashboard

---

### Feature 1.1: Development Environment & Database Schema

**Purpose:** Set up development environment and create all database tables

**Build Breakdown:**

- **Environment Setup:**
    - Laravel project initialization with Vite
    - React 18 + TypeScript setup
    - Tailwind CSS configuration
    - ESLint + Prettier configuration
    - PHP CS Fixer configuration
    - Git repository initialization

- **Database Migrations:**

    ```
    Table: users
    - id (BIGINT UNSIGNED, PK, AUTO_INCREMENT)
    - email (VARCHAR(255), UNIQUE, NOT NULL)
    - password (VARCHAR(255), NOT NULL)
    - email_verified_at (TIMESTAMP, NULL)
    - credits_balance (INT UNSIGNED, DEFAULT 1)
    - preferences_json (JSON, NULL)
    - remember_token (VARCHAR(100), NULL)
    - created_at, updated_at (TIMESTAMPS)

    Table: projects
    - id (BIGINT UNSIGNED, PK, AUTO_INCREMENT)
    - user_id (BIGINT UNSIGNED, FK → users.id)
    - name (VARCHAR(255), NOT NULL)
    - settings_json (JSON, NULL)
    - is_default (BOOLEAN, DEFAULT FALSE)
    - created_at, updated_at (TIMESTAMPS)

    Table: videos
    - id (BIGINT UNSIGNED, PK, AUTO_INCREMENT)
    - project_id (BIGINT UNSIGNED, FK → projects.id)
    - user_id (BIGINT UNSIGNED, FK → users.id)
    - title (VARCHAR(500), NULL)
    - status (ENUM: draft, processing, completed, failed)
    - current_step (VARCHAR(50), NULL)
    - input_json (JSON, NOT NULL)
    - outputs_json (JSON, NULL)
    - ai_models_json (JSON, NULL)
    - total_cost_usd (DECIMAL(10,4), DEFAULT 0)
    - error_message (TEXT, NULL)
    - created_at, updated_at (TIMESTAMPS)

    Table: generations
    - id (BIGINT UNSIGNED, PK, AUTO_INCREMENT)
    - video_id (BIGINT UNSIGNED, FK → videos.id)
    - step (VARCHAR(50), NOT NULL)
    - version (INT UNSIGNED, DEFAULT 1)
    - status (ENUM: pending, processing, completed, failed)
    - input_data (LONGTEXT, NULL)
    - output_data (LONGTEXT, NULL)
    - cost_usd (DECIMAL(10,6), DEFAULT 0)
    - created_at (TIMESTAMP)

    Table: api_calls
    - id (BIGINT UNSIGNED, PK, AUTO_INCREMENT)
    - video_id (BIGINT UNSIGNED, FK → videos.id)
    - generation_id (BIGINT UNSIGNED, NULL)
    - step (VARCHAR(50), NOT NULL)
    - provider (VARCHAR(50), NOT NULL)
    - model (VARCHAR(100), NOT NULL)
    - input_tokens (INT UNSIGNED, DEFAULT 0)
    - output_tokens (INT UNSIGNED, DEFAULT 0)
    - image_count (INT UNSIGNED, DEFAULT 0)
    - audio_duration_seconds (INT UNSIGNED, DEFAULT 0)
    - video_duration_seconds (INT UNSIGNED, DEFAULT 0)
    - cost_usd (DECIMAL(10,6), NOT NULL)
    - cost_calculation (TEXT, NULL)
    - request_payload (LONGTEXT, NULL)
    - response_data (LONGTEXT, NULL)
    - status (ENUM: success, failed, timeout)
    - response_time_ms (INT UNSIGNED, NULL)
    - error_message (TEXT, NULL)
    - retry_count (INT UNSIGNED, DEFAULT 0)
    - created_at (TIMESTAMP)

    Table: transactions
    - id (BIGINT UNSIGNED, PK, AUTO_INCREMENT)
    - user_id (BIGINT UNSIGNED, FK → users.id)
    - amount_cents (INT, NOT NULL)
    - credits (INT, NOT NULL)
    - type (ENUM: purchase, refund, deduction, bonus)
    - description (VARCHAR(500), NULL)
    - stripe_payment_id (VARCHAR(255), NULL)
    - stripe_session_id (VARCHAR(255), NULL)
    - created_at (TIMESTAMP)

    Table: files
    - id (BIGINT UNSIGNED, PK, AUTO_INCREMENT)
    - video_id (BIGINT UNSIGNED, FK → videos.id)
    - type (ENUM: video, thumbnail, audio, script, package)
    - storage_path (VARCHAR(500), NOT NULL)
    - original_filename (VARCHAR(255), NULL)
    - mime_type (VARCHAR(100), NULL)
    - size_bytes (BIGINT UNSIGNED, NULL)
    - expires_at (TIMESTAMP, NULL)
    - created_at (TIMESTAMP)
    ```

**Files to Create:**

```
.env.example
.gitignore
composer.json
package.json
vite.config.ts
tailwind.config.js
postcss.config.js
tsconfig.json
eslint.config.js
.prettierrc
phpcs.xml

database/migrations/0001_01_01_000001_create_users_table.php
database/migrations/0001_01_01_000002_create_projects_table.php
database/migrations/0001_01_01_000003_create_videos_table.php
database/migrations/0001_01_01_000004_create_generations_table.php
database/migrations/0001_01_01_000005_create_api_calls_table.php
database/migrations/0001_01_01_000006_create_transactions_table.php
database/migrations/0001_01_01_000007_create_files_table.php
database/migrations/0001_01_01_000008_create_cache_table.php
database/migrations/0001_01_01_000009_create_jobs_table.php
database/migrations/0001_01_01_000010_create_personal_access_tokens_table.php
```

**Dependencies:**

- Requires: None (START HERE)
- Blocks: All other features

**Acceptance Criteria:**

- `php artisan migrate` runs without errors
- All tables created with correct schema
- Development server starts (backend + frontend)
- Tailwind CSS compiles correctly

**Estimated Complexity:** Medium
**Estimated Time:** 2-3 days

**AI Agent Notes:**

- Run `composer create-project laravel/laravel` first
- Install React + Vite via `npm create vite@latest`
- Use Laravel's default migration naming convention
- Add foreign key constraints in migrations
- Test migrations with `migrate:fresh`

---

### Feature 1.2: User Registration (AUTH-001)

**Purpose:** Allow new users to create accounts with email and password

**Build Breakdown:**

- **Backend:**
    - Model: User (with fillable, casts, relationships)
    - Request: RegisterRequest (validation rules)
    - Controller: AuthController@register
    - Service: AuthService (registration logic)
    - Route: POST /api/auth/register

- **Frontend:**
    - Component: RegisterForm
    - Page: RegisterPage
    - API: authApi.register()
    - Validation: Client-side form validation

- **Testing:**
    - Unit: RegisterRequest validation tests (5 tests)
    - Feature: Registration endpoint tests (8 tests)
    - E2E: Registration flow test (1 test)

**Files to Create:**

```
app/Models/User.php
app/Http/Controllers/Api/AuthController.php
app/Http/Requests/Auth/RegisterRequest.php
app/Services/AuthService.php
app/Exceptions/AuthException.php
routes/api.php

resources/js/api/authApi.ts
resources/js/components/Auth/RegisterForm.tsx
resources/js/pages/Auth/RegisterPage.tsx
resources/js/types/auth.ts

tests/Unit/Http/Requests/RegisterRequestTest.php
tests/Feature/Auth/RegistrationTest.php
```

**Validation Rules:**

```php
[
    'email' => ['required', 'email', 'max:255', 'unique:users'],
    'password' => ['required', 'min:8', 'confirmed', 'regex:/[A-Z]/', 'regex:/[0-9]/'],
    'accept_terms' => ['required', 'accepted']
]
```

**API Response:**

```json
{
    "success": true,
    "data": {
        "user": {
            "id": 1,
            "email": "user@example.com",
            "credits_balance": 1,
            "email_verified": false
        },
        "token": "sanctum-token"
    },
    "message": "Registration successful. Please verify your email."
}
```

**Dependencies:**

- Requires: Feature 1.1 (Database Schema)
- Blocks: Features 1.3, 1.4
- Can parallelize with: Frontend UI setup (1.6)

**Acceptance Criteria:**

- User can register with valid email/password
- Duplicate email returns 422 error
- Weak password returns validation error
- User receives 1 free credit on registration
- Token returned for immediate auth

**Estimated Complexity:** Low
**Estimated Time:** 1 day

**AI Agent Notes:**

- Use Laravel Sanctum for token generation
- Hash passwords with bcrypt (Laravel default)
- Return validation errors in standard Laravel format
- Create default project "My Channel" after registration

---

### Feature 1.3: Email Verification (AUTH-002)

**Purpose:** Verify user email ownership before allowing video creation

**Build Breakdown:**

- **Backend:**
    - Notification: VerifyEmailNotification
    - Controller: AuthController@verifyEmail, @resendVerification
    - Middleware: EnsureEmailIsVerified
    - Routes: GET /api/auth/verify-email/{id}/{hash}, POST /api/auth/resend-verification

- **Frontend:**
    - Component: VerificationPendingBanner
    - Page: VerifyEmailPage
    - API: authApi.verifyEmail(), authApi.resendVerification()

- **Testing:**
    - Feature: Email verification tests (6 tests)
    - Integration: Email sending test (1 test)

**Files to Create:**

```
app/Notifications/VerifyEmailNotification.php
app/Http/Middleware/EnsureEmailIsVerified.php
app/Http/Controllers/Api/AuthController.php (update)

resources/js/components/Auth/VerificationPendingBanner.tsx
resources/js/pages/Auth/VerifyEmailPage.tsx
resources/js/api/authApi.ts (update)

tests/Feature/Auth/EmailVerificationTest.php
```

**Email Template:**

```
Subject: Verify your Itervel account

Hi {name},

Thanks for signing up for Itervel!

Please verify your email address by clicking the link below:

[Verify Email Address] - expires in 24 hours

If you didn't create this account, you can safely ignore this email.

— The Itervel Team
```

**Dependencies:**

- Requires: Feature 1.2 (Registration)
- Blocks: Video creation (unverified users blocked)

**Acceptance Criteria:**

- Verification email sent within 30 seconds of registration
- Link expires after 24 hours
- User can resend (max 3/hour)
- Unverified users can login but not create videos
- Clear success message after verification

**Estimated Complexity:** Low
**Estimated Time:** 0.5 days

**AI Agent Notes:**

- Use Laravel's built-in email verification scaffolding
- Use signed URLs for verification links
- Rate limit resend endpoint
- Update email_verified_at timestamp on success

---

### Feature 1.4: User Login (AUTH-003)

**Purpose:** Allow registered users to securely access their accounts

**Build Breakdown:**

- **Backend:**
    - Request: LoginRequest
    - Controller: AuthController@login, @logout
    - Service: AuthService (login logic, attempt tracking)
    - Routes: POST /api/auth/login, POST /api/auth/logout

- **Frontend:**
    - Component: LoginForm
    - Page: LoginPage
    - Store: useAuthStore (Zustand)
    - API: authApi.login(), authApi.logout()

- **Testing:**
    - Unit: LoginRequest validation (3 tests)
    - Feature: Login endpoint tests (7 tests)
    - E2E: Login flow test (1 test)

**Files to Create:**

```
app/Http/Requests/Auth/LoginRequest.php
app/Http/Controllers/Api/AuthController.php (update)
app/Services/AuthService.php (update)

resources/js/components/Auth/LoginForm.tsx
resources/js/pages/Auth/LoginPage.tsx
resources/js/stores/authStore.ts
resources/js/api/authApi.ts (update)
resources/js/hooks/useAuth.ts

tests/Unit/Http/Requests/LoginRequestTest.php
tests/Feature/Auth/LoginTest.php
```

**Security Requirements:**

- Lock account after 5 failed attempts (15 min cooldown)
- Session expires after 24 hours of inactivity
- HTTP-only, secure cookies for session
- Remember me option (30-day persistence)

**Dependencies:**

- Requires: Feature 1.2 (Registration)
- Blocks: All authenticated features

**Acceptance Criteria:**

- Valid credentials return token
- Invalid credentials return 401
- Account locked after 5 failures
- Remember me extends session to 30 days
- Logout invalidates token

**Estimated Complexity:** Low
**Estimated Time:** 1 day

**AI Agent Notes:**

- Use Laravel Sanctum abilities for token scopes
- Track failed attempts in cache (Redis)
- Clear failed attempts on successful login
- Include user data in login response

---

### Feature 1.5: Password Reset (AUTH-004)

**Purpose:** Allow users to reset forgotten passwords

**Build Breakdown:**

- **Backend:**
    - Notification: ResetPasswordNotification
    - Controller: AuthController@forgotPassword, @resetPassword
    - Requests: ForgotPasswordRequest, ResetPasswordRequest
    - Routes: POST /api/auth/forgot-password, POST /api/auth/reset-password

- **Frontend:**
    - Component: ForgotPasswordForm, ResetPasswordForm
    - Pages: ForgotPasswordPage, ResetPasswordPage
    - API: authApi.forgotPassword(), authApi.resetPassword()

- **Testing:**
    - Feature: Password reset tests (6 tests)

**Files to Create:**

```
app/Notifications/ResetPasswordNotification.php
app/Http/Requests/Auth/ForgotPasswordRequest.php
app/Http/Requests/Auth/ResetPasswordRequest.php
app/Http/Controllers/Api/AuthController.php (update)

resources/js/components/Auth/ForgotPasswordForm.tsx
resources/js/components/Auth/ResetPasswordForm.tsx
resources/js/pages/Auth/ForgotPasswordPage.tsx
resources/js/pages/Auth/ResetPasswordPage.tsx
resources/js/api/authApi.ts (update)

tests/Feature/Auth/PasswordResetTest.php
```

**Dependencies:**

- Requires: Feature 1.2 (Registration)
- Blocks: None (independent feature)

**Acceptance Criteria:**

- Reset link sent to valid email
- Link expires after 1 hour (single use)
- Max 3 reset requests per email per hour
- Password changed successfully
- User notified of password change via email

**Estimated Complexity:** Low
**Estimated Time:** 0.5 days

**AI Agent Notes:**

- Use Laravel's built-in password reset
- Rate limit forgot-password endpoint
- Invalidate all existing tokens after reset
- Send confirmation email after password change

---

### Feature 1.6: Frontend Foundation & UI Framework

**Purpose:** Establish React application structure and core UI components

**Build Breakdown:**

- **Application Shell:**
    - Layout: MainLayout (header, sidebar, content area)
    - Layout: AuthLayout (centered card for auth pages)
    - Router: Application routes with protected routes
    - Context: AuthProvider (global auth state)

- **Core UI Components:**
    - Button (variants: primary, secondary, outline, danger)
    - Input (text, email, password with validation states)
    - Card (content container)
    - Modal (dialog overlay)
    - Alert (success, error, warning, info)
    - Spinner (loading indicator)
    - Badge (status indicators)

- **Navigation:**
    - Header (logo, user menu, credit balance)
    - Sidebar (navigation links)
    - Breadcrumbs

- **Testing:**
    - Component tests for all UI components

**Files to Create:**

```
resources/js/App.tsx
resources/js/main.tsx
resources/js/router.tsx

resources/js/layouts/MainLayout.tsx
resources/js/layouts/AuthLayout.tsx

resources/js/components/ui/Button.tsx
resources/js/components/ui/Input.tsx
resources/js/components/ui/Card.tsx
resources/js/components/ui/Modal.tsx
resources/js/components/ui/Alert.tsx
resources/js/components/ui/Spinner.tsx
resources/js/components/ui/Badge.tsx
resources/js/components/ui/Select.tsx
resources/js/components/ui/Textarea.tsx
resources/js/components/ui/Checkbox.tsx
resources/js/components/ui/Radio.tsx
resources/js/components/ui/Slider.tsx
resources/js/components/ui/Progress.tsx
resources/js/components/ui/index.ts

resources/js/components/navigation/Header.tsx
resources/js/components/navigation/Sidebar.tsx
resources/js/components/navigation/Breadcrumbs.tsx
resources/js/components/navigation/UserMenu.tsx

resources/js/contexts/AuthContext.tsx
resources/js/hooks/useAuth.ts
resources/js/utils/api.ts
resources/js/utils/formatters.ts
resources/js/types/index.ts

resources/css/app.css

tests/js/components/Button.test.tsx
tests/js/components/Input.test.tsx
tests/js/components/Modal.test.tsx
```

**Dependencies:**

- Requires: Feature 1.1 (Environment Setup)
- Blocks: All frontend features
- Can parallelize with: Backend auth features (1.2-1.5)

**Acceptance Criteria:**

- All UI components render correctly
- Responsive design works on mobile/tablet/desktop
- Dark mode support (optional for Phase 1)
- Tailwind styles applied correctly
- TypeScript types for all props

**Estimated Complexity:** Medium
**Estimated Time:** 2 days

**AI Agent Notes:**

- Use React.forwardRef for form components
- Add data-testid attributes for E2E testing
- Follow Tailwind best practices (utility classes)
- Export all components from index.ts barrel file

---

### Feature 1.7: Dashboard Page

**Purpose:** Main landing page after login showing overview and quick actions

**Build Breakdown:**

- **Frontend:**
    - Page: DashboardPage
    - Components: QuickStats, RecentVideos, QuickActions
    - API: dashboardApi.getStats()

- **Backend:**
    - Controller: DashboardController@index
    - Route: GET /api/dashboard

**Files to Create:**

```
app/Http/Controllers/Api/DashboardController.php

resources/js/pages/DashboardPage.tsx
resources/js/components/dashboard/QuickStats.tsx
resources/js/components/dashboard/RecentVideos.tsx
resources/js/components/dashboard/QuickActions.tsx
resources/js/api/dashboardApi.ts

tests/Feature/DashboardTest.php
```

**Dashboard Data:**

```json
{
    "credits_balance": 5,
    "videos_this_month": 3,
    "total_videos": 12,
    "recent_videos": [
        {
            "id": 1,
            "title": "Video Title",
            "status": "completed",
            "created_at": "2026-01-15T10:30:00Z",
            "thumbnail_url": "..."
        }
    ],
    "active_project": {
        "id": 1,
        "name": "My Channel"
    }
}
```

**Dependencies:**

- Requires: Features 1.4 (Login), 1.6 (UI Framework)
- Blocks: None

**Acceptance Criteria:**

- Shows credit balance prominently
- Lists recent videos with status
- Quick action button to create new video
- Shows active project name

**Estimated Complexity:** Low
**Estimated Time:** 0.5 days

---

## Phase 2: Project Management & Video Input

**Goal:** Users can manage projects and start video creation workflow
**Duration Estimate:** 2 weeks
**Checkpoint:** User can create projects, configure settings, and input video topic with references

---

### Feature 2.1: User Profile & Preferences (AUTH-005)

**Purpose:** Allow users to manage account settings and default preferences

**Build Breakdown:**

- **Backend:**
    - Controller: ProfileController (show, update, updatePassword, destroy)
    - Requests: UpdateProfileRequest, UpdatePasswordRequest, UpdatePreferencesRequest
    - Routes: GET/PUT /api/profile, PUT /api/profile/password, PUT /api/profile/preferences, DELETE /api/profile

- **Frontend:**
    - Pages: ProfilePage, PreferencesPage
    - Components: ProfileForm, PasswordChangeForm, PreferencesForm, DeleteAccountModal

- **Testing:**
    - Feature: Profile update tests (8 tests)

**Files to Create:**

```
app/Http/Controllers/Api/ProfileController.php
app/Http/Requests/Profile/UpdateProfileRequest.php
app/Http/Requests/Profile/UpdatePasswordRequest.php
app/Http/Requests/Profile/UpdatePreferencesRequest.php

resources/js/pages/Settings/ProfilePage.tsx
resources/js/pages/Settings/PreferencesPage.tsx
resources/js/components/settings/ProfileForm.tsx
resources/js/components/settings/PasswordChangeForm.tsx
resources/js/components/settings/PreferencesForm.tsx
resources/js/components/settings/DeleteAccountModal.tsx
resources/js/api/profileApi.ts

tests/Feature/ProfileTest.php
```

**User Preferences Schema:**

```json
{
    "default_video_length": 12,
    "speaking_pace": 165,
    "default_script_iterations": 2,
    "email_notifications": true
}
```

**Dependencies:**

- Requires: Feature 1.4 (Login)
- Blocks: None

**Acceptance Criteria:**

- User can view email and join date
- User can change password with current password verification
- User can set default preferences
- User can toggle email notifications
- User can request account deletion (GDPR)

**Estimated Complexity:** Low
**Estimated Time:** 1 day

---

### Feature 2.2: Create Project (PROJ-001)

**Purpose:** Allow users to create separate projects for different YouTube channels

**Build Breakdown:**

- **Backend:**
    - Model: Project (with relationships, settings accessors)
    - Controller: ProjectController@store
    - Request: CreateProjectRequest
    - Service: ProjectService
    - Observer: ProjectObserver (auto-create default project)
    - Route: POST /api/projects

- **Frontend:**
    - Component: CreateProjectModal
    - Form: ProjectForm
    - API: projectApi.create()

- **Testing:**
    - Unit: Project model tests (5 tests)
    - Feature: Project creation tests (6 tests)

**Files to Create:**

```
app/Models/Project.php
app/Http/Controllers/Api/ProjectController.php
app/Http/Requests/Project/CreateProjectRequest.php
app/Services/ProjectService.php
app/Observers/ProjectObserver.php

resources/js/components/projects/CreateProjectModal.tsx
resources/js/components/projects/ProjectForm.tsx
resources/js/api/projectApi.ts
resources/js/types/project.ts

tests/Unit/Models/ProjectTest.php
tests/Feature/Project/CreateProjectTest.php
```

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

**Dependencies:**

- Requires: Feature 1.4 (Login)
- Blocks: Feature 2.3, Video Creation

**Acceptance Criteria:**

- User can create named project
- Free users: 1 project limit enforced
- Paid users: unlimited projects
- Project settings stored correctly
- Default project auto-created on first login

**Estimated Complexity:** Medium
**Estimated Time:** 1 day

---

### Feature 2.3: Manage Projects (PROJ-002)

**Purpose:** Allow users to view, edit, switch, and delete projects

**Build Breakdown:**

- **Backend:**
    - Controller: ProjectController@index, @show, @update, @destroy, @setDefault
    - Request: UpdateProjectRequest
    - Routes: GET/PUT/DELETE /api/projects/{id}, POST /api/projects/{id}/set-default

- **Frontend:**
    - Page: ProjectsPage
    - Components: ProjectList, ProjectCard, EditProjectModal, ProjectSwitcher
    - Store: useProjectStore (Zustand)

- **Testing:**
    - Feature: Project management tests (10 tests)

**Files to Create:**

```
app/Http/Requests/Project/UpdateProjectRequest.php
app/Http/Controllers/Api/ProjectController.php (update)

resources/js/pages/ProjectsPage.tsx
resources/js/components/projects/ProjectList.tsx
resources/js/components/projects/ProjectCard.tsx
resources/js/components/projects/EditProjectModal.tsx
resources/js/components/projects/ProjectSwitcher.tsx
resources/js/components/projects/DeleteProjectModal.tsx
resources/js/stores/projectStore.ts
resources/js/api/projectApi.ts (update)

tests/Feature/Project/ManageProjectTest.php
```

**Dependencies:**

- Requires: Feature 2.2 (Create Project)
- Blocks: None

**Acceptance Criteria:**

- User sees all projects in dropdown/tabs
- User can switch active project from nav
- User can update name and settings
- Delete warns about video loss
- Confirmation requires typing project name

**Estimated Complexity:** Medium
**Estimated Time:** 1 day

---

### Feature 2.4: Brand Guide Upload (PROJ-003)

**Purpose:** Allow users to upload a brand guide document to customize AI voice/tone

**Build Breakdown:**

- **Backend:**
    - Service: BrandGuideService (parse, validate, store)
    - Controller: ProjectController@uploadBrandGuide
    - Route: POST /api/projects/{id}/brand-guide

- **Frontend:**
    - Component: BrandGuideUploader
    - Component: BrandGuidePreview

- **Testing:**
    - Feature: Brand guide upload tests (5 tests)

**Files to Create:**

```
app/Services/BrandGuideService.php
app/Http/Controllers/Api/ProjectController.php (update)

resources/js/components/projects/BrandGuideUploader.tsx
resources/js/components/projects/BrandGuidePreview.tsx
resources/js/api/projectApi.ts (update)

storage/app/templates/brand-guide-template.md

tests/Feature/Project/BrandGuideTest.php
```

**File Restrictions:**

- Accepted: .txt, .md only
- Max size: 100KB
- Content extracted as plain text

**Dependencies:**

- Requires: Feature 2.2 (Create Project)
- Blocks: None

**Acceptance Criteria:**

- User can upload .txt or .md file
- File size limit enforced (100KB)
- Content parsed and stored in settings
- Sample template downloadable
- Can override per video

**Estimated Complexity:** Low
**Estimated Time:** 0.5 days

---

### Feature 2.5: Topic Input & Reference URL Processing (VID-001)

**Purpose:** Collect user input for video topic and process reference materials

**Build Breakdown:**

- **Backend:**
    - Model: Video (with relationships, status management)
    - Controller: VideoController@store
    - Request: CreateVideoRequest
    - Service: UrlProcessingService (article extraction, YouTube transcript)
    - Job: ProcessReferenceUrlJob
    - Routes: POST /api/videos

- **Frontend:**
    - Page: CreateVideoPage (Step 1)
    - Components: TopicInputForm, UrlInputField, UrlProcessingStatus
    - Store: useVideoWizardStore (Zustand)

- **Testing:**
    - Unit: UrlProcessingService tests (10 tests)
    - Feature: Video creation start tests (8 tests)

**Files to Create:**

```
app/Models/Video.php
app/Http/Controllers/Api/VideoController.php
app/Http/Requests/Video/CreateVideoRequest.php
app/Services/UrlProcessingService.php
app/Services/ArticleExtractorService.php
app/Services/YoutubeTranscriptService.php
app/Jobs/ProcessReferenceUrlJob.php

resources/js/pages/Video/CreateVideoPage.tsx
resources/js/components/video/TopicInputForm.tsx
resources/js/components/video/UrlInputField.tsx
resources/js/components/video/UrlProcessingStatus.tsx
resources/js/stores/videoWizardStore.ts
resources/js/api/videoApi.ts
resources/js/types/video.ts

tests/Unit/Services/UrlProcessingServiceTest.php
tests/Unit/Services/ArticleExtractorServiceTest.php
tests/Feature/Video/CreateVideoTest.php
```

**Input Validation:**

```php
[
    'project_id' => ['required', 'exists:projects,id'],
    'topic' => ['required', 'string', 'min:100', 'max:500'],
    'reference_urls' => ['array', 'max:3'],
    'reference_urls.*' => ['url', 'max:2048'],
]
```

**URL Processing Flow:**

1. Validate URL format and accessibility
2. Identify URL type (YouTube vs article)
3. Extract content (30-sec timeout per URL)
4. Store extracted key concepts
5. Return status to frontend

**Dependencies:**

- Requires: Feature 2.2 (Create Project)
- Blocks: Features 3.1, 3.2 (Content Generation)

**Acceptance Criteria:**

- Topic input required (100-500 chars)
- Up to 3 optional reference URLs
- URLs validated for format and accessibility
- YouTube URLs extract transcript
- Articles extract main content
- 30-second timeout per URL, fail gracefully
- Content cached for reuse

**Estimated Complexity:** High
**Estimated Time:** 2 days

**AI Agent Notes:**

- Use Guzzle for HTTP requests
- Consider using readability-php for article extraction
- Use youtube-transcript-api via Python or find PHP alternative
- Store extracted content in video's input_json

---

### Feature 2.6: AI Model Selection (VID-002)

**Purpose:** Allow users to select AI models for each generation step

**Build Breakdown:**

- **Backend:**
    - Config: ai_models.php (available models, pricing)
    - Controller: VideoController@store (accept model config)
    - Service: CostEstimationService

- **Frontend:**
    - Component: ModelSelectionPanel
    - Component: CostPreview
    - Helper: calculateEstimatedCost()

- **Testing:**
    - Unit: Cost estimation tests (5 tests)

**Files to Create:**

```
config/ai_models.php
app/Services/CostEstimationService.php

resources/js/components/video/ModelSelectionPanel.tsx
resources/js/components/video/CostPreview.tsx
resources/js/utils/costCalculation.ts

tests/Unit/Services/CostEstimationServiceTest.php
```

**Model Configuration:**

```php
return [
    'title_generation' => [
        'default' => 'claude-sonnet-4-5',
        'options' => ['claude-sonnet-4-5', 'claude-opus-4-5', 'gpt-4'],
    ],
    'outline_generation' => [
        'default' => 'claude-opus-4-5',
        'options' => ['claude-sonnet-4-5', 'claude-opus-4-5', 'gpt-4'],
    ],
    // ... etc
];
```

**Dependencies:**

- Requires: Feature 2.5 (Topic Input)
- Blocks: None

**Acceptance Criteria:**

- "Use recommended settings" checkbox (all Sonnet)
- Custom mode shows dropdown per step
- Cost preview updates on selection change
- Speed/quality indicator per model
- Last used configuration remembered

**Estimated Complexity:** Medium
**Estimated Time:** 1 day

---

### Feature 2.7: Iteration Configuration (VID-003)

**Purpose:** Allow users to configure the number of AI critique/refinement rounds

**Build Breakdown:**

- **Frontend:**
    - Component: IterationSlider
    - Component: IterationTooltip

- **Testing:**
    - Component: IterationSlider tests (3 tests)

**Files to Create:**

```
resources/js/components/video/IterationSlider.tsx
resources/js/components/video/IterationTooltip.tsx

tests/js/components/IterationSlider.test.tsx
```

**Configuration Options:**

- Slider: 0-5 rounds
- Tooltip: Explains what iterations do
- Cost impact: Shows additional cost per round
- Free tier: Max 1 iteration
- Warning at 3+: "More iterations increase quality but also cost"

**Dependencies:**

- Requires: Feature 2.6 (Model Selection)
- Blocks: Feature 3.4 (Script Critique)

**Acceptance Criteria:**

- User can select 0-5 iterations via slider
- Tooltip explains iteration purpose
- Cost preview updates
- Free tier limited to 1 iteration
- Warning shown at 3+ iterations

**Estimated Complexity:** Low
**Estimated Time:** 0.5 days

---

## Phase 3: Content Generation Pipeline

**Goal:** Implement AI-powered title, outline, script generation with critique loop
**Duration Estimate:** 2 weeks
**Checkpoint:** User can generate titles, outlines, scripts with iterative refinement

---

### Feature 3.1: AI Model Router & API Integration

**Purpose:** Centralized service for routing AI requests to different providers

**Build Breakdown:**

- **Backend:**
    - Service: AIModelRouter (provider abstraction)
    - Service: AnthropicService
    - Service: OpenAIService
    - Service: CostTrackingService
    - Model: ApiCall (for logging)

- **Testing:**
    - Unit: AIModelRouter tests (10 tests)
    - Integration: API provider tests (mocked)

**Files to Create:**

```
app/Services/AI/AIModelRouter.php
app/Services/AI/AnthropicService.php
app/Services/AI/OpenAIService.php
app/Services/AI/CostTrackingService.php
app/Contracts/AIProviderInterface.php

config/services.php (update with API keys)

tests/Unit/Services/AI/AIModelRouterTest.php
tests/Unit/Services/AI/AnthropicServiceTest.php
tests/Unit/Services/AI/OpenAIServiceTest.php
```

**AIModelRouter Interface:**

```php
interface AIProviderInterface
{
    public function generateText(string $prompt, array $options = []): AIResponse;
    public function estimateCost(int $inputTokens, int $outputTokens): float;
}
```

**Dependencies:**

- Requires: Feature 1.1 (Database Schema)
- Blocks: All content generation features

**Acceptance Criteria:**

- Can route to Anthropic or OpenAI based on model selection
- Logs all API calls with tokens, cost, response time
- Handles API errors gracefully with retry logic
- Tracks costs in real-time

**Estimated Complexity:** High
**Estimated Time:** 2 days

**AI Agent Notes:**

- Use Laravel HTTP client for API calls
- Implement retry with exponential backoff
- Store full request/response for debugging
- Calculate cost based on token usage

---

### Feature 3.2: Title Generation (CONTENT-001)

**Purpose:** Generate 5 title options with loglines, ranked by click potential

**Build Breakdown:**

- **Backend:**
    - Service: TitleGenerationService
    - Job: GenerateTitlesJob
    - Controller: VideoController@generateTitles, @selectTitle
    - Prompt: title_generation.md

- **Frontend:**
    - Page: CreateVideoPage (Step 2: Title Selection)
    - Component: TitleSelectionPanel
    - Component: TitleCard

- **Testing:**
    - Unit: TitleGenerationService tests (8 tests)
    - Feature: Title generation endpoint tests (5 tests)

**Files to Create:**

```
app/Services/Content/TitleGenerationService.php
app/Jobs/GenerateTitlesJob.php
app/Http/Controllers/Api/VideoController.php (update)
resources/prompts/title_generation.md

resources/js/pages/Video/CreateVideoPage.tsx (update)
resources/js/components/video/TitleSelectionPanel.tsx
resources/js/components/video/TitleCard.tsx
resources/js/api/videoApi.ts (update)

tests/Unit/Services/Content/TitleGenerationServiceTest.php
tests/Feature/Video/TitleGenerationTest.php
```

**Title Output Schema:**

```json
{
    "titles": [
        {
            "rank": 1,
            "title": "The One Number That Buys Your Freedom",
            "logline": "A 35-year-old professional discovers that retiring early isn't about earning more—it's about the 4% rule.",
            "framework": "The Only [X] You Need",
            "character_count": 42,
            "predicted_ctr": "8-10%",
            "why_it_works": "Creates strong curiosity gap, promises specific benefit"
        }
    ]
}
```

**Dependencies:**

- Requires: Feature 3.1 (AI Router), Feature 2.5 (Topic Input)
- Blocks: Feature 3.3 (Outline)

**Acceptance Criteria:**

- AI generates exactly 5 titles
- Each title has logline, framework, character count
- Titles ranked 1-5 by predicted CTR
- User selects 1 via radio button
- Selection stored and locked

**Estimated Complexity:** Medium
**Estimated Time:** 1.5 days

---

### Feature 3.3: Outline Creation (CONTENT-002)

**Purpose:** Generate structured video outline with timing and section details

**Build Breakdown:**

- **Backend:**
    - Service: OutlineGenerationService
    - Job: GenerateOutlineJob
    - Controller: VideoController@generateOutline, @updateOutline, @approveOutline
    - Prompt: outline_generation.md

- **Frontend:**
    - Page: CreateVideoPage (Step 3: Outline)
    - Component: OutlineEditor
    - Component: OutlineSection (draggable)
    - Component: OutlineTimeline

- **Testing:**
    - Unit: OutlineGenerationService tests (6 tests)
    - Feature: Outline generation tests (5 tests)

**Files to Create:**

```
app/Services/Content/OutlineGenerationService.php
app/Jobs/GenerateOutlineJob.php
app/Http/Requests/Video/UpdateOutlineRequest.php
resources/prompts/outline_generation.md

resources/js/components/video/OutlineEditor.tsx
resources/js/components/video/OutlineSection.tsx
resources/js/components/video/OutlineTimeline.tsx
resources/js/api/videoApi.ts (update)

tests/Unit/Services/Content/OutlineGenerationServiceTest.php
tests/Feature/Video/OutlineGenerationTest.php
```

**Outline Schema:**

```json
{
    "total_duration_minutes": 12,
    "sections": [
        {
            "id": "section-1",
            "type": "hook",
            "title": "The Hook",
            "duration_seconds": 30,
            "key_points": ["Point 1", "Point 2"],
            "engagement_note": "High energy, fast cuts"
        }
    ]
}
```

**Dependencies:**

- Requires: Feature 3.2 (Title Selected)
- Blocks: Feature 3.4 (Script)

**Acceptance Criteria:**

- AI generates outline from selected title
- Hook (30s) + 3-5 main sections + closing
- Each section has timing estimate and key points
- User can edit sections inline
- User can reorder via drag-and-drop
- User can add/remove sections
- Must approve before proceeding

**Estimated Complexity:** Medium
**Estimated Time:** 1.5 days

**AI Agent Notes:**

- Use @dnd-kit/core for drag-and-drop
- Calculate total duration from sections
- Validate timing totals match target length

---

### Feature 3.4: Script Writing (CONTENT-003)

**Purpose:** Generate full conversational script from approved outline

**Build Breakdown:**

- **Backend:**
    - Service: ScriptWritingService
    - Job: GenerateScriptJob
    - Controller: VideoController@generateScript, @updateScript, @approveScript
    - Prompt: script_writing.md

- **Frontend:**
    - Page: CreateVideoPage (Step 4: Script)
    - Component: ScriptEditor
    - Component: ScriptStats (word count, duration)

- **Testing:**
    - Unit: ScriptWritingService tests (6 tests)
    - Feature: Script generation tests (5 tests)

**Files to Create:**

```
app/Services/Content/ScriptWritingService.php
app/Jobs/GenerateScriptJob.php
app/Http/Requests/Video/UpdateScriptRequest.php
resources/prompts/script_writing.md

resources/js/components/video/ScriptEditor.tsx
resources/js/components/video/ScriptStats.tsx
resources/js/api/videoApi.ts (update)

tests/Unit/Services/Content/ScriptWritingServiceTest.php
tests/Feature/Video/ScriptGenerationTest.php
```

**Script Requirements:**

- 2,000-2,500 words for 12-minute video
- Follows approved outline structure
- Uses brand guide if provided
- Includes delivery cues: [PAUSE], [BEAT], [EMPHASIS]
- Target pace: 165-170 words per minute
- Max 15 words per quote, one quote per source

**Dependencies:**

- Requires: Feature 3.3 (Outline Approved)
- Blocks: Feature 3.5 (Script Critique), Feature 4.3 (Voiceover)

**Acceptance Criteria:**

- Script generated from outline
- Word count and estimated duration shown
- Delivery cues included
- User can edit script directly
- Script stored in outputs_json

**Estimated Complexity:** Medium
**Estimated Time:** 1 day

---

### Feature 3.5: Script Critique & Refinement (CONTENT-004)

**Purpose:** AI critiques script from audience perspective and iteratively improves it

**Build Breakdown:**

- **Backend:**
    - Service: ScriptCritiqueService
    - Job: CritiqueScriptJob
    - Controller: VideoController (critique runs automatically)
    - Prompt: script_critique.md

- **Frontend:**
    - Component: CritiqueProgress
    - Component: CritiqueResults (optional view)

- **Testing:**
    - Unit: ScriptCritiqueService tests (8 tests)
    - Feature: Script critique tests (6 tests)

**Files to Create:**

```
app/Services/Content/ScriptCritiqueService.php
app/Jobs/CritiqueScriptJob.php
resources/prompts/script_critique.md

resources/js/components/video/CritiqueProgress.tsx
resources/js/components/video/CritiqueResults.tsx
resources/js/api/videoApi.ts (update)

tests/Unit/Services/Content/ScriptCritiqueServiceTest.php
tests/Feature/Video/ScriptCritiqueTest.php
```

**Critique Output Schema:**

```json
{
    "round": 2,
    "issues": [
        {
            "priority": "high",
            "location": "Hook, line 3",
            "issue": "The promise is vague",
            "viewer_impact": "Viewers may not feel compelled to watch",
            "suggested_fix": "Make the promise specific..."
        }
    ],
    "overall_assessment": "Script has strong structure..."
}
```

**Critique Loop:**

1. Generate critique from audience perspective
2. Identify weak points ("Where would viewers click away?")
3. Prioritize issues (High/Medium/Low)
4. Apply fixes automatically
5. Store version history (v1, v2, v3...)
6. Repeat for configured rounds
7. Force stop at 5 rounds maximum

**Dependencies:**

- Requires: Feature 3.4 (Script Generated)
- Blocks: Feature 4.3 (Voiceover)

**Acceptance Criteria:**

- Critique runs 0-5 rounds based on configuration
- Progress shown: "Refining script... Round 2 of 3"
- Each round produces critique + revised script
- Version history tracked
- Final script presented after all rounds
- User can view critique details (optional)

**Estimated Complexity:** High
**Estimated Time:** 2 days

**AI Agent Notes:**

- Run critique and revision as separate API calls
- Store each version in generations table
- Display progress in real-time via polling or websockets
- Handle timeout gracefully (partial completion OK)

---

### Feature 3.6: Metadata Generation (CONTENT-005)

**Purpose:** Generate YouTube-optimized title, description, and tags

**Build Breakdown:**

- **Backend:**
    - Service: MetadataGenerationService
    - Job: GenerateMetadataJob
    - Controller: VideoController@generateMetadata
    - Prompt: metadata_generation.md

- **Frontend:**
    - Component: MetadataEditor
    - Component: CopyButton

- **Testing:**
    - Unit: MetadataGenerationService tests (4 tests)
    - Feature: Metadata generation tests (3 tests)

**Files to Create:**

```
app/Services/Content/MetadataGenerationService.php
app/Jobs/GenerateMetadataJob.php
resources/prompts/metadata_generation.md

resources/js/components/video/MetadataEditor.tsx
resources/js/components/video/CopyButton.tsx
resources/js/api/videoApi.ts (update)

tests/Unit/Services/Content/MetadataGenerationServiceTest.php
tests/Feature/Video/MetadataGenerationTest.php
```

**Metadata Schema:**

```json
{
  "title": "The One Number That Buys Your Freedom",
  "description": "Most people think retiring early means earning more...\n\nIn this video, you'll learn:\n• Key takeaway 1\n...",
  "tags": ["personal finance", "FIRE", "4% rule", ...],
  "timestamps": [
    {"time": "0:00", "label": "Introduction"},
    {"time": "0:45", "label": "The Problem"}
  ]
}
```

**Dependencies:**

- Requires: Feature 3.5 (Script Finalized)
- Blocks: Feature 5.1 (Download Package)

**Acceptance Criteria:**

- Title from user selection
- Description 150-300 words, SEO-optimized
- 10-15 relevant tags
- Timestamps generated from outline
- All fields editable
- One-click copy for each field

**Estimated Complexity:** Low
**Estimated Time:** 0.5 days

---

## Phase 4: Asset Generation

**Goal:** Generate thumbnails, voiceover, music selection, and stock footage
**Duration Estimate:** 2 weeks
**Checkpoint:** All video assets ready for assembly

---

### Feature 4.1: Thumbnail Concept Generation (ASSET-001)

**Purpose:** Generate 5 thumbnail concepts, critique and select top 3

**Build Breakdown:**

- **Backend:**
    - Service: ThumbnailConceptService
    - Job: GenerateThumbnailConceptsJob
    - Prompt: thumbnail_concepts.md

- **Testing:**
    - Unit: ThumbnailConceptService tests (5 tests)
    - Feature: Thumbnail concept tests (4 tests)

**Files to Create:**

```
app/Services/Asset/ThumbnailConceptService.php
app/Jobs/GenerateThumbnailConceptsJob.php
resources/prompts/thumbnail_concepts.md

tests/Unit/Services/Asset/ThumbnailConceptServiceTest.php
tests/Feature/Asset/ThumbnailConceptTest.php
```

**Concept Schema:**

```json
{
    "concept_id": "thumb-1",
    "text_overlay": "THE 4% RULE",
    "visual_elements": {
        "main_subject": "Stack of $100 bills fanning out",
        "background": "Soft gradient, dark blue to green",
        "secondary_elements": ["Small calendar icon"]
    },
    "color_scheme": ["#1a365d", "#38a169", "#ffffff"],
    "emotion": "Curiosity and aspiration",
    "text_position": "Upper left, large bold"
}
```

**Dependencies:**

- Requires: Feature 3.2 (Title Selected)
- Blocks: Feature 4.2 (Image Generation)
- Can parallelize with: Features 3.3-3.5 (Script generation)

**Acceptance Criteria:**

- 5 concepts generated from title/topic
- Runs parallel to outline/script generation
- AI critiques concepts from "scrolling viewer" perspective
- Top 3 selected with refinement notes

**Estimated Complexity:** Medium
**Estimated Time:** 1 day

---

### Feature 4.2: Thumbnail Image Generation (ASSET-002)

**Purpose:** Generate 3 thumbnail images from concepts using Google Imagen 3

**Build Breakdown:**

- **Backend:**
    - Service: ThumbnailImageService
    - Service: GoogleImagenService
    - Job: GenerateThumbnailImagesJob
    - Controller: VideoController@selectThumbnail

- **Frontend:**
    - Page: CreateVideoPage (Step 6: Thumbnail)
    - Component: ThumbnailSelector
    - Component: ThumbnailPreview

- **Testing:**
    - Unit: ThumbnailImageService tests (5 tests)
    - Feature: Thumbnail image tests (4 tests)

**Files to Create:**

```
app/Services/Asset/ThumbnailImageService.php
app/Services/AI/GoogleImagenService.php
app/Jobs/GenerateThumbnailImagesJob.php

resources/js/components/video/ThumbnailSelector.tsx
resources/js/components/video/ThumbnailPreview.tsx
resources/js/api/videoApi.ts (update)

tests/Unit/Services/Asset/ThumbnailImageServiceTest.php
tests/Feature/Asset/ThumbnailImageTest.php
```

**Image Specifications:**

- Resolution: 1280x720 pixels (16:9)
- Format: PNG
- Text must be readable on mobile
- Professional YouTube thumbnail style

**Dependencies:**

- Requires: Feature 4.1 (Concepts)
- Blocks: Feature 5.1 (Download)

**Acceptance Criteria:**

- 3 thumbnails generated from top 3 concepts
- 1280x720 PNG format
- Large preview for each
- User selects 1
- Regenerate option available

**Estimated Complexity:** Medium
**Estimated Time:** 1.5 days

**AI Agent Notes:**

- Use Google AI's Imagen 3 API
- Store images in Cloudflare R2
- Generate signed URLs for preview
- Handle image generation failures gracefully

---

### Feature 4.3: Voiceover Generation (ASSET-003)

**Purpose:** Generate AI voiceover or process user-uploaded audio

**Build Breakdown:**

- **Backend:**
    - Service: VoiceoverService
    - Service: ElevenLabsService
    - Job: GenerateVoiceoverJob
    - Controller: VideoController@selectVoice, @uploadVoiceover

- **Frontend:**
    - Page: CreateVideoPage (Step 5: Voice)
    - Component: VoiceSelector
    - Component: VoicePreview
    - Component: AudioUploader

- **Testing:**
    - Unit: VoiceoverService tests (6 tests)
    - Feature: Voiceover tests (5 tests)

**Files to Create:**

```
app/Services/Asset/VoiceoverService.php
app/Services/AI/ElevenLabsService.php
app/Jobs/GenerateVoiceoverJob.php
app/Http/Requests/Video/UploadVoiceoverRequest.php

resources/js/components/video/VoiceSelector.tsx
resources/js/components/video/VoicePreview.tsx
resources/js/components/video/AudioUploader.tsx
resources/js/api/videoApi.ts (update)

tests/Unit/Services/Asset/VoiceoverServiceTest.php
tests/Feature/Asset/VoiceoverTest.php
```

**Voice Selection Options:**

1. **AI Voice**: Select from ElevenLabs library with 10-second preview
2. **Upload Own**: Accept MP3, WAV, M4A, AAC (max 50MB)

**Audio Processing:**

- Normalize volume via FFmpeg
- Extract exact duration for video timing
- Store in Cloudflare R2

**Dependencies:**

- Requires: Feature 3.5 (Script Finalized)
- Blocks: Feature 5.2 (Video Rendering)

**Acceptance Criteria:**

- ElevenLabs voice library accessible
- 10-second preview per voice
- Script converted to MP3
- Upload option accepts multiple formats
- Max 50MB file size
- Audio normalized for consistent volume
- Duration extracted accurately

**Estimated Complexity:** High
**Estimated Time:** 2 days

**AI Agent Notes:**

- Use ElevenLabs streaming API for long scripts
- Install php-ffmpeg for audio processing
- Store voice selection preference per project
- Handle ElevenLabs rate limits

---

### Feature 4.4: Music Selection (ASSET-004)

**Purpose:** Select background music from library or upload custom track

**Build Breakdown:**

- **Backend:**
    - Service: MusicService
    - Seeder: MusicLibrarySeeder (20 tracks)
    - Controller: VideoController@selectMusic, @uploadMusic

- **Frontend:**
    - Page: CreateVideoPage (Step 7: Music)
    - Component: MusicLibrary
    - Component: MusicTrack
    - Component: MusicUploader

- **Testing:**
    - Feature: Music selection tests (4 tests)

**Files to Create:**

```
app/Services/Asset/MusicService.php
database/seeders/MusicLibrarySeeder.php

resources/js/components/video/MusicLibrary.tsx
resources/js/components/video/MusicTrack.tsx
resources/js/components/video/MusicUploader.tsx
resources/js/api/videoApi.ts (update)

storage/app/music/ (20 royalty-free tracks)

tests/Feature/Asset/MusicSelectionTest.php
```

**Music Library:**
| Track Name | Mood | Duration | BPM |
|------------|------|----------|-----|
| Morning Clarity | Inspiring | 3:45 | 90 |
| Forward Motion | Upbeat | 4:20 | 120 |
| Deep Focus | Calm | 5:00 | 70 |
| Rising Action | Dramatic | 3:30 | 100 |
| Steady Progress | Neutral | 4:00 | 85 |
| ... (15 more) | | | |

**Dependencies:**

- Requires: Feature 2.5 (Video Created)
- Blocks: Feature 5.2 (Video Rendering)

**Acceptance Criteria:**

- 20 curated royalty-free tracks
- Mood categories: Upbeat, Calm, Dramatic, Inspiring, Neutral
- 30-second preview for each
- Upload option (MP3, max 20MB)
- "No music" option available
- Music mixed at -15dB to -20dB below voiceover

**Estimated Complexity:** Medium
**Estimated Time:** 1 day

---

### Feature 4.5: Stock Footage Selection (ASSET-005)

**Purpose:** Automatically match stock footage to script segments

**Build Breakdown:**

- **Backend:**
    - Service: StockFootageService
    - Service: PexelsService
    - Service: PixabayService
    - Job: SelectStockFootageJob

- **Testing:**
    - Unit: StockFootageService tests (6 tests)
    - Feature: Stock footage tests (4 tests)

**Files to Create:**

```
app/Services/Asset/StockFootageService.php
app/Services/Media/PexelsService.php
app/Services/Media/PixabayService.php
app/Jobs/SelectStockFootageJob.php

tests/Unit/Services/Asset/StockFootageServiceTest.php
tests/Feature/Asset/StockFootageTest.php
```

**Footage Selection Process:**

1. Split script into 3-5 thematic segments
2. Extract search keywords per segment
3. Search Pexels and Pixabay APIs
4. AI selects clips to fill segment duration
5. Target 3-6 seconds per clip (pattern interrupts)
6. Use broader terms if no results
7. Store URLs only (download at render time)

**Footage Plan Schema:**

```json
{
    "segments": [
        {
            "segment_id": "seg-1",
            "duration_seconds": 30,
            "keywords": ["stock market", "investing"],
            "clips": [
                {
                    "source": "pexels",
                    "video_id": "123456",
                    "url": "https://videos.pexels.com/...",
                    "duration": 5,
                    "start_time": 0
                }
            ]
        }
    ]
}
```

**Dependencies:**

- Requires: Feature 3.5 (Script Finalized)
- Blocks: Feature 5.2 (Video Rendering)

**Acceptance Criteria:**

- Script segmented by theme
- Keywords extracted for each segment
- Pexels and Pixabay searched
- 3-6 second clips selected
- Clips cover segment duration
- Fallback to broader terms
- Only URLs stored (not actual video files)

**Estimated Complexity:** High
**Estimated Time:** 2 days

**AI Agent Notes:**

- Use AI to extract keywords from script sections
- Prioritize Pexels (higher quality)
- Cache API responses to reduce calls
- Handle API rate limits

---

## Phase 5: Video Production & Delivery

**Goal:** Assemble video, render, and provide downloads with payment system
**Duration Estimate:** 2 weeks
**Checkpoint:** User can generate complete video and download all assets

---

### Feature 5.1: Storyboard Preview (RENDER-001)

**Purpose:** Show visual preview of video before final render

**Build Breakdown:**

- **Backend:**
    - Service: StoryboardService
    - Controller: VideoController@getStoryboard

- **Frontend:**
    - Page: CreateVideoPage (Step 8: Review)
    - Component: StoryboardPreview
    - Component: StoryboardSegment
    - Component: TimelineView

- **Testing:**
    - Feature: Storyboard preview tests (3 tests)

**Files to Create:**

```
app/Services/Video/StoryboardService.php

resources/js/components/video/StoryboardPreview.tsx
resources/js/components/video/StoryboardSegment.tsx
resources/js/components/video/TimelineView.tsx
resources/js/api/videoApi.ts (update)

tests/Feature/Video/StoryboardTest.php
```

**Storyboard Display:**

- Script segments with matched footage thumbnails
- Timeline view showing clip arrangement
- Hover to see footage thumbnail
- Cost summary
- "Generate Video" button

**Dependencies:**

- Requires: Features 4.3, 4.4, 4.5 (All Assets)
- Blocks: Feature 5.2 (Rendering)

**Acceptance Criteria:**

- Shows script segments with footage thumbnails
- Visual timeline representation
- User must approve before render
- Estimated cost displayed
- Uses thumbnails, not actual video

**Estimated Complexity:** Medium
**Estimated Time:** 1 day

---

### Feature 5.2: Shotstack Integration (RENDER-002)

**Purpose:** Render final video using Shotstack cloud API

**Build Breakdown:**

- **Backend:**
    - Service: ShotstackService
    - Service: VideoRenderingService
    - Job: RenderVideoJob
    - Controller: VideoController@render
    - Webhook: ShotstackWebhookController

- **Frontend:**
    - Component: RenderProgress
    - Component: RenderStatus

- **Testing:**
    - Unit: ShotstackService tests (6 tests)
    - Feature: Video rendering tests (5 tests)

**Files to Create:**

```
app/Services/Video/ShotstackService.php
app/Services/Video/VideoRenderingService.php
app/Jobs/RenderVideoJob.php
app/Http/Controllers/Webhook/ShotstackWebhookController.php

resources/js/components/video/RenderProgress.tsx
resources/js/components/video/RenderStatus.tsx
resources/js/api/videoApi.ts (update)

routes/webhooks.php

tests/Unit/Services/Video/ShotstackServiceTest.php
tests/Feature/Video/VideoRenderingTest.php
```

**Shotstack Timeline Structure:**

```json
{
    "timeline": {
        "tracks": [
            {
                "clips": [
                    /* stock footage clips */
                ]
            },
            {
                "clips": [
                    /* voiceover audio */
                ]
            },
            {
                "clips": [
                    /* background music at -15dB */
                ]
            }
        ]
    },
    "output": {
        "format": "mp4",
        "resolution": "hd"
    }
}
```

**Output Specifications:**

- Resolution: 1920x1080 (1080p)
- Codec: H.264
- Format: MP4
- Aspect Ratio: 16:9
- Max Duration: 20 minutes
- Render Time: 2-5 minutes

**Dependencies:**

- Requires: Feature 5.1 (Storyboard Approved)
- Blocks: Feature 5.3 (Downloads)

**Acceptance Criteria:**

- Shotstack timeline built from all assets
- Render submitted via API
- Progress tracked (polling or webhook)
- Rendered video downloaded to R2 storage
- Failure retries once, then alerts user
- Webhook processes completion callbacks

**Estimated Complexity:** High
**Estimated Time:** 2.5 days

**AI Agent Notes:**

- Build Shotstack JSON programmatically
- Use webhooks for completion notification
- Download rendered video to Cloudflare R2
- Handle timeout and failure cases
- Add watermark for free tier videos

---

### Feature 5.3: Download Package (DELIVERY-001)

**Purpose:** Provide complete downloadable package of all outputs

**Build Breakdown:**

- **Backend:**
    - Service: DownloadService
    - Controller: VideoController@download
    - Job: CreateDownloadPackageJob

- **Frontend:**
    - Page: CreateVideoPage (Step 9: Download)
    - Page: VideoDetailPage
    - Component: DownloadPanel
    - Component: VideoPlayer
    - Component: MetadataDisplay

- **Testing:**
    - Feature: Download tests (5 tests)

**Files to Create:**

```
app/Services/Delivery/DownloadService.php
app/Jobs/CreateDownloadPackageJob.php
app/Http/Controllers/Api/VideoController.php (update)

resources/js/pages/Video/VideoDetailPage.tsx
resources/js/components/video/DownloadPanel.tsx
resources/js/components/video/VideoPlayer.tsx
resources/js/components/video/MetadataDisplay.tsx
resources/js/components/video/CostBreakdown.tsx
resources/js/api/videoApi.ts (update)

tests/Feature/Video/DownloadTest.php
```

**Download Package:**

```
video_[title]_[date].zip
├── video.mp4           (Final rendered video)
├── thumbnail.png       (Selected thumbnail)
├── script.md           (Final script with delivery cues)
└── metadata.txt        (Title, description, tags)
```

**Dependencies:**

- Requires: Feature 5.2 (Video Rendered)
- Blocks: None

**Acceptance Criteria:**

- Video plays in embedded player
- Individual download buttons (video, thumbnail, script)
- "Download All (ZIP)" button
- Copy buttons for title, description, tags
- Cost breakdown displayed
- Files available for retention period

**Estimated Complexity:** Medium
**Estimated Time:** 1.5 days

---

### Feature 5.4: Video Library (LIBRARY-001)

**Purpose:** Display all user videos with status and actions

**Build Breakdown:**

- **Backend:**
    - Controller: VideoController@index
    - Resource: VideoResource, VideoCollection

- **Frontend:**
    - Page: LibraryPage
    - Component: VideoList
    - Component: VideoCard

- **Testing:**
    - Feature: Video library tests (5 tests)

**Files to Create:**

```
app/Http/Resources/VideoResource.php
app/Http/Resources/VideoCollection.php
app/Http/Controllers/Api/VideoController.php (update)

resources/js/pages/LibraryPage.tsx
resources/js/components/library/VideoList.tsx
resources/js/components/library/VideoCard.tsx
resources/js/components/library/VideoFilters.tsx
resources/js/api/videoApi.ts (update)

tests/Feature/Video/VideoLibraryTest.php
```

**Video Card Display:**

- Thumbnail preview
- Title and duration
- Status badge (Draft, Processing, Completed, Failed)
- Created date
- Quick actions: View, Download, Delete

**Dependencies:**

- Requires: Feature 2.5 (Videos exist)
- Blocks: None

**Acceptance Criteria:**

- Lists videos in current project
- Status badges (Draft, Processing, Completed, Failed)
- Sort by date, title, status
- Thumbnail preview for completed
- Quick actions: View, Download, Delete
- Pagination (20 per page)

**Estimated Complexity:** Low
**Estimated Time:** 1 day

---

### Feature 5.5: Retention Policy (LIBRARY-002)

**Purpose:** Manage video file storage with time-based retention

**Build Breakdown:**

- **Backend:**
    - Command: CleanupExpiredFilesCommand
    - Notification: FileExpirationWarningNotification
    - Scheduler: Daily cleanup job

- **Testing:**
    - Unit: File cleanup tests (4 tests)

**Files to Create:**

```
app/Console/Commands/CleanupExpiredFilesCommand.php
app/Notifications/FileExpirationWarningNotification.php
app/Console/Kernel.php (update scheduler)

tests/Unit/Console/CleanupExpiredFilesTest.php
```

**Retention Rules:**

- Free tier: 30 days
- Paid tier: 90 days
- Email warning 7 days before deletion
- Metadata preserved after file deletion
- Re-generation available at cost

**Dependencies:**

- Requires: Feature 5.3 (Files exist)
- Blocks: None

**Acceptance Criteria:**

- Files auto-deleted after retention period
- Email sent 7 days before deletion
- Project data preserved after file deletion
- User can re-generate video at cost
- Scheduler runs daily

**Estimated Complexity:** Medium
**Estimated Time:** 1 day

---

### Feature 5.6: Credit System (PAY-001)

**Purpose:** Manage user credits for video generation

**Build Breakdown:**

- **Backend:**
    - Service: CreditService
    - Observer: VideoObserver (deduct on completion)
    - Controller: PaymentController@balance

- **Frontend:**
    - Component: CreditBalance (in header)
    - Component: LowCreditWarning
    - Component: InsufficientCreditsModal

- **Testing:**
    - Unit: CreditService tests (8 tests)
    - Feature: Credit tests (6 tests)

**Files to Create:**

```
app/Services/Payment/CreditService.php
app/Observers/VideoObserver.php
app/Http/Controllers/Api/PaymentController.php

resources/js/components/payment/CreditBalance.tsx
resources/js/components/payment/LowCreditWarning.tsx
resources/js/components/payment/InsufficientCreditsModal.tsx
resources/js/api/paymentApi.ts

tests/Unit/Services/Payment/CreditServiceTest.php
tests/Feature/Payment/CreditTest.php
```

**Credit Rules:**

- New users: 1 free credit
- Deduct 1 credit on video completion
- Auto-refund on generation failure
- Warning at 1 credit remaining
- Block generation at 0 credits

**Dependencies:**

- Requires: Feature 1.2 (Users exist)
- Blocks: Feature 5.7 (Purchases)

**Acceptance Criteria:**

- Balance shown in header
- 1 credit deducted on completion
- Refund on failure
- Warning at 1 credit
- Blocked at 0 credits

**Estimated Complexity:** Medium
**Estimated Time:** 1 day

---

### Feature 5.7: Credit Packages & Stripe Integration (PAY-002, PAY-003)

**Purpose:** Sell credit packages via Stripe

**Build Breakdown:**

- **Backend:**
    - Service: StripeService
    - Controller: PaymentController@checkout, @success
    - Webhook: StripeWebhookController
    - Routes: POST /api/payments/checkout, webhooks/stripe

- **Frontend:**
    - Page: PricingPage
    - Component: PricingCard
    - Component: CheckoutButton

- **Testing:**
    - Unit: StripeService tests (6 tests)
    - Feature: Payment tests (8 tests)

**Files to Create:**

```
app/Services/Payment/StripeService.php
app/Http/Controllers/Api/PaymentController.php (update)
app/Http/Controllers/Webhook/StripeWebhookController.php
config/stripe.php

resources/js/pages/PricingPage.tsx
resources/js/components/payment/PricingCard.tsx
resources/js/components/payment/CheckoutButton.tsx
resources/js/api/paymentApi.ts (update)

tests/Unit/Services/Payment/StripeServiceTest.php
tests/Feature/Payment/StripeCheckoutTest.php
```

**Credit Packages:**
| Package | Credits | Price | Per Credit |
|---------|---------|-------|------------|
| Starter | 5 | $20 | $4.00 |
| Popular | 15 | $50 | $3.33 |
| Best Value | 35 | $100 | $2.86 |

**Stripe Integration:**

- Create Checkout Session
- Handle success redirect
- Process webhooks:
    - `checkout.session.completed` → Add credits
    - `payment_intent.payment_failed` → Log failure
    - `charge.refunded` → Deduct credits

**Dependencies:**

- Requires: Feature 5.6 (Credit System)
- Blocks: None

**Acceptance Criteria:**

- Three packages displayed with pricing
- Stripe Checkout redirect
- Credits added on success
- Transaction history stored
- Webhook handles all events
- Receipt sent by Stripe

**Estimated Complexity:** High
**Estimated Time:** 2 days

**AI Agent Notes:**

- Use Stripe Checkout (hosted) for PCI compliance
- Verify webhook signatures
- Handle idempotency for webhook retries
- Store stripe_session_id for reference

---

### Feature 5.8: Free Tier Limitations (PAY-004)

**Purpose:** Enforce limitations for free trial video

**Build Breakdown:**

- **Backend:**
    - Middleware: EnforceFreeTierLimits
    - Service: TierService

- **Frontend:**
    - Component: FreeTierBanner
    - Component: UpgradePrompt

- **Testing:**
    - Feature: Free tier tests (5 tests)

**Files to Create:**

```
app/Http/Middleware/EnforceFreeTierLimits.php
app/Services/TierService.php

resources/js/components/tier/FreeTierBanner.tsx
resources/js/components/tier/UpgradePrompt.tsx

tests/Feature/TierTest.php
```

**Free Tier Restrictions:**
| Feature | Free Tier | Paid Tier |
|---------|-----------|-----------|
| Video count | 1 | Unlimited |
| Max duration | 1 minute | 20 minutes |
| Script iterations | 1 | Up to 5 |
| Watermark | Yes | No |
| Projects | 1 | Unlimited |
| File retention | 30 days | 90 days |

**Watermark Specification:**

- Position: Bottom right corner
- Content: "Made with Itervel"
- Opacity: 50%
- Size: Small, non-intrusive

**Dependencies:**

- Requires: Feature 5.7 (Paid tier exists)
- Blocks: None

**Acceptance Criteria:**

- Free video limited to 1 minute
- Watermark added to free videos
- Only 1 iteration allowed
- Only 1 project allowed
- 30-day retention enforced
- Upgrade prompts shown

**Estimated Complexity:** Medium
**Estimated Time:** 1 day

---

## Phase 6: Testing & Launch

**Goal:** Comprehensive testing, bug fixes, and deployment
**Duration Estimate:** 2 weeks
**Checkpoint:** MVP ready for beta users

---

### Feature 6.1: End-to-End Testing Suite

**Purpose:** Verify all critical user flows work correctly

**Build Breakdown:**

- **E2E Tests (Playwright):**
    - User Registration Flow
    - Email Verification Flow
    - Login/Logout Flow
    - Password Reset Flow
    - Project Creation Flow
    - Complete Video Creation Flow
    - Credit Purchase Flow
    - Video Download Flow

**Files to Create:**

```
tests/e2e/playwright.config.ts
tests/e2e/auth/registration.spec.ts
tests/e2e/auth/login.spec.ts
tests/e2e/auth/password-reset.spec.ts
tests/e2e/projects/create-project.spec.ts
tests/e2e/videos/create-video.spec.ts
tests/e2e/payments/purchase-credits.spec.ts
tests/e2e/videos/download-video.spec.ts
tests/e2e/helpers/auth.ts
tests/e2e/helpers/factories.ts
```

**Critical Path Test:**

```typescript
test('complete video creation flow', async ({ page }) => {
    // Login
    // Create new video with topic
    // Select title
    // Approve outline
    // Wait for script generation
    // Select voice
    // Select thumbnail
    // Select music
    // Approve storyboard
    // Wait for render
    // Download video
    // Verify all files present
});
```

**Dependencies:**

- Requires: All features complete
- Blocks: Launch

**Acceptance Criteria:**

- All E2E tests pass
- Critical flows work end-to-end
- 10-minute timeout for video creation test
- Tests run in CI pipeline

**Estimated Complexity:** High
**Estimated Time:** 3 days

---

### Feature 6.2: Performance Optimization

**Purpose:** Ensure application meets performance requirements

**Build Breakdown:**

- **Optimizations:**
    - Database query optimization (eager loading)
    - Redis caching for API responses
    - Frontend code splitting
    - Image optimization
    - API response time monitoring

- **Load Testing:**
    - 100 concurrent users
    - p95 response time < 500ms

**Files to Create:**

```
config/cache.php (update)
app/Http/Middleware/CacheResponse.php
tests/load/k6-load-test.js
```

**Dependencies:**

- Requires: All features complete
- Blocks: Launch

**Acceptance Criteria:**

- Page load < 2 seconds
- API response < 500ms (p95)
- 100 concurrent users supported
- No N+1 query issues

**Estimated Complexity:** Medium
**Estimated Time:** 2 days

---

### Feature 6.3: Security Audit

**Purpose:** Verify security requirements are met

**Build Breakdown:**

- **Security Checklist:**
    - [ ] SQL injection testing
    - [ ] XSS vulnerability scan
    - [ ] CSRF protection verified
    - [ ] Authentication bypass testing
    - [ ] Rate limiting verified
    - [ ] API key exposure check
    - [ ] File upload validation
    - [ ] Sensitive data exposure check

**Files to Review:**

```
All controllers
All requests (validation)
All middleware
Environment variables
API integrations
```

**Dependencies:**

- Requires: All features complete
- Blocks: Launch

**Acceptance Criteria:**

- No critical vulnerabilities
- All inputs validated server-side
- Rate limiting active
- No secrets in code
- HTTPS enforced

**Estimated Complexity:** Medium
**Estimated Time:** 1 day

---

### Feature 6.4: Deployment Setup

**Purpose:** Configure production deployment pipeline

**Build Breakdown:**

- **Infrastructure:**
    - A2 Hosting configuration
    - Cloudflare R2 buckets
    - Redis configuration
    - SSL certificates
    - Domain configuration

- **CI/CD:**
    - GitHub Actions workflow
    - Automated testing on PR
    - Production deployment script

**Files to Create:**

```
.github/workflows/ci.yml
.github/workflows/deploy.yml
deploy.sh
.env.production.example
```

**Dependencies:**

- Requires: All features complete, tests passing
- Blocks: Launch

**Acceptance Criteria:**

- Automated deployment pipeline
- Zero-downtime deployments
- Rollback capability
- Environment variables secured
- Monitoring configured (Sentry)

**Estimated Complexity:** High
**Estimated Time:** 2 days

---

### Feature 6.5: Documentation

**Purpose:** Create user and developer documentation

**Build Breakdown:**

- **User Documentation:**
    - Getting Started Guide
    - Feature Documentation
    - FAQ
    - Brand Guide Template

- **Developer Documentation:**
    - API Documentation
    - Architecture Overview
    - Deployment Guide
    - Contributing Guide

**Files to Create:**

```
docs/user/getting-started.md
docs/user/features.md
docs/user/faq.md
docs/api/README.md
docs/api/endpoints.md
docs/dev/architecture.md
docs/dev/deployment.md
```

**Dependencies:**

- Requires: All features complete
- Blocks: None

**Acceptance Criteria:**

- New user can complete first video following guide
- All API endpoints documented
- Deployment process documented
- Architecture decisions documented

**Estimated Complexity:** Medium
**Estimated Time:** 2 days

---

## 3. Phase Organization & Parallelization

### 3.1 Phase Overview

```
Phase 1: Foundation (Week 1-2)
├── Sequential: Database Schema → Auth Features
├── Parallel Track A: Backend Auth (1.2-1.5)
└── Parallel Track B: Frontend Foundation (1.6-1.7)

Phase 2: Project & Input (Week 3-4)
├── Sequential: Projects → Video Input
├── Parallel Track A: Profile & Projects (2.1-2.4)
└── Parallel Track B: Video Input & Config (2.5-2.7)

Phase 3: Content Generation (Week 5-6)
├── Sequential: AI Router → Title → Outline → Script → Critique → Metadata
└── All sequential due to dependencies

Phase 4: Asset Generation (Week 7-8)
├── Parallel Track A: Thumbnails (4.1-4.2, can start after title)
├── Parallel Track B: Voiceover (4.3, after script)
├── Parallel Track C: Music (4.4, can start early)
└── Parallel Track D: Stock Footage (4.5, after script)

Phase 5: Video Production (Week 9-10)
├── Sequential: Storyboard → Render → Download
├── Parallel Track A: Video Assembly (5.1-5.3)
├── Parallel Track B: Library (5.4-5.5)
└── Parallel Track C: Payments (5.6-5.8)

Phase 6: Testing & Launch (Week 11-12)
├── Sequential: E2E Tests → Performance → Security → Deploy
└── Parallel Track: Documentation
```

### 3.2 Dependency Diagram

```
┌─────────────────┐
│ 1.1 Database    │ (START HERE)
│     Schema      │
└────────┬────────┘
         │
         ├─────────────────┬──────────────────┬─────────────────┐
         │                 │                  │                 │
    ┌────▼────┐       ┌────▼────┐       ┌────▼────┐      ┌────▼─────┐
    │1.2 Regis│       │1.6 UI   │       │         │      │          │
    │  ter    │       │Framework│       │(parallel)│     │(parallel)│
    └────┬────┘       └────┬────┘       └──────────┘     └──────────┘
         │                 │
    ┌────▼────┐       ┌────▼────┐
    │1.3 Email│       │1.7 Dash │
    │ Verify  │       │  board  │
    └────┬────┘       └─────────┘
         │
    ┌────▼────┐
    │1.4 Login│
    └────┬────┘
         │
    ┌────▼────┐
    │1.5 Pass │
    │ Reset   │
    └────┬────┘
         │
═════════╪═════════ CHECKPOINT 1 ═════════════════════════════════
         │
    ┌────▼────┐
    │2.1 Prof │
    │  ile    │
    └────┬────┘
         │
    ┌────▼────┐
    │2.2 Proj │
    │ Create  │
    └────┬────┘
         │
         ├─────────────────┐
         │                 │
    ┌────▼────┐       ┌────▼────┐
    │2.3 Proj │       │2.4 Brand│
    │ Manage  │       │ Guide   │
    └────┬────┘       └─────────┘
         │
    ┌────▼────┐
    │2.5 Topic│
    │ Input   │
    └────┬────┘
         │
         ├─────────────────┐
         │                 │
    ┌────▼────┐       ┌────▼────┐
    │2.6 Model│       │2.7 Iter │
    │ Select  │       │ Config  │
    └────┬────┘       └─────────┘
         │
═════════╪═════════ CHECKPOINT 2 ═════════════════════════════════
         │
    ┌────▼────┐
    │3.1 AI   │
    │ Router  │
    └────┬────┘
         │
    ┌────▼────┐
    │3.2 Title│────────────────────────────┐
    │  Gen    │                            │
    └────┬────┘                            │
         │                                 │
    ┌────▼────┐                       ┌────▼────┐
    │3.3 Outl │                       │4.1 Thumb│
    │  ine    │                       │ Concept │
    └────┬────┘                       └────┬────┘
         │                                 │
    ┌────▼────┐                       ┌────▼────┐
    │3.4 Scrip│                       │4.2 Thumb│
    │ Write   │                       │ Images  │
    └────┬────┘                       └─────────┘
         │
    ┌────▼────┐
    │3.5 Scrip│──────────┬──────────────────┐
    │ Critique│          │                  │
    └────┬────┘          │                  │
         │               │                  │
    ┌────▼────┐     ┌────▼────┐       ┌────▼────┐
    │3.6 Meta │     │4.3 Voice│       │4.5 Stock│
    │  data   │     │  over   │       │ Footage │
    └─────────┘     └─────────┘       └─────────┘
                                           │
                                      ┌────▼────┐
                                      │4.4 Music│
                                      │         │
                                      └─────────┘
         │
═════════╪═════════ CHECKPOINT 3 ═════════════════════════════════
         │
    ┌────▼────┐
    │5.1 Story│
    │ board   │
    └────┬────┘
         │
    ┌────▼────┐
    │5.2 Shot │
    │ stack   │
    └────┬────┘
         │
    ┌────▼────┐     ┌─────────┐     ┌─────────┐
    │5.3 Down │     │5.4 Video│     │5.6 Credit│
    │  load   │     │ Library │     │ System  │
    └─────────┘     └─────────┘     └────┬────┘
                                         │
                    ┌─────────┐     ┌────▼────┐
                    │5.5 Reten│     │5.7 Stripe│
                    │  tion   │     │         │
                    └─────────┘     └────┬────┘
                                         │
                                    ┌────▼────┐
                                    │5.8 Free │
                                    │ Tier    │
                                    └─────────┘
         │
═════════╪═════════ CHECKPOINT 4 ═════════════════════════════════
         │
    ┌────▼────┐
    │6.1 E2E  │
    │ Tests   │
    └────┬────┘
         │
         ├─────────────────┐
         │                 │
    ┌────▼────┐       ┌────▼────┐
    │6.2 Perf │       │6.5 Docs │
    │         │       │         │
    └────┬────┘       └─────────┘
         │
    ┌────▼────┐
    │6.3 Secur│
    │  ity    │
    └────┬────┘
         │
    ┌────▼────┐
    │6.4 Deploy│
    └─────────┘
         │
═════════╪═════════ CHECKPOINT 5 (MVP COMPLETE) ══════════════════
```

### 3.3 Parallel Development Opportunities

**Phase 1 Parallelization:**

- Track A: Backend auth (1.2 → 1.3 → 1.4 → 1.5)
- Track B: Frontend foundation (1.6 → 1.7)
- Integration point: End of Week 2

**Phase 2 Parallelization:**

- Track A: Profile & Project management (2.1 → 2.2 → 2.3 → 2.4)
- Track B: Video input & configuration (2.5 → 2.6 → 2.7)
- Integration point: End of Week 4

**Phase 3-4 Parallelization:**

- Thumbnail generation (4.1 → 4.2) can run parallel to script generation (3.3 → 3.4 → 3.5)
- Music selection (4.4) can be built early (no dependencies after 2.5)
- Stock footage (4.5) runs after script is complete

**Phase 5 Parallelization:**

- Track A: Video assembly pipeline (5.1 → 5.2 → 5.3)
- Track B: Library features (5.4 → 5.5)
- Track C: Payment system (5.6 → 5.7 → 5.8)

---

## 4. Testing Strategy

### 4.1 Test-Driven Development Approach

- Write tests before or alongside feature implementation
- All tests must pass before marking feature complete
- 100% pass rate required at each checkpoint
- No code merges with failing tests

### 4.2 Testing Levels

#### Unit Tests (PHPUnit, Jest)

| Component             | Target Coverage | Priority |
| --------------------- | --------------- | -------- |
| Services              | 85%             | Critical |
| Controllers           | 80%             | High     |
| Requests (Validation) | 90%             | Critical |
| Models                | 75%             | Medium   |
| React Components      | 70%             | Medium   |
| Utility Functions     | 90%             | High     |

#### Integration/Feature Tests (Laravel Test Suite)

- All API endpoints tested
- Database relationships verified
- Service integrations tested with mocks
- Authentication/authorization verified

#### End-to-End Tests (Playwright)

| Flow                    | Priority | Timeout |
| ----------------------- | -------- | ------- |
| User Registration       | Critical | 30s     |
| Email Verification      | Critical | 60s     |
| Login/Logout            | Critical | 30s     |
| Password Reset          | Critical | 60s     |
| Project Creation        | High     | 30s     |
| Complete Video Creation | Critical | 10min   |
| Credit Purchase         | Critical | 60s     |
| Video Download          | High     | 30s     |

### 4.3 Test Coverage Requirements

| Path Type                                        | Minimum Coverage |
| ------------------------------------------------ | ---------------- |
| Critical Paths (auth, payment, video generation) | 80%              |
| Business Logic (services)                        | 85%              |
| API Endpoints                                    | 100% tested      |
| UI Components                                    | 70%              |

### 4.4 Quality Gates

Before each feature is marked complete:

- [ ] All unit tests pass (100%)
- [ ] All feature tests pass (100%)
- [ ] No linter errors (PHP CS Fixer, ESLint)
- [ ] No TypeScript errors
- [ ] Code committed to git
- [ ] PR description complete

Before each checkpoint:

- [ ] All phase features complete
- [ ] E2E tests for phase pass
- [ ] No security vulnerabilities
- [ ] Performance benchmarks met
- [ ] Documentation updated

---

## 5. Development Workflow

### 5.1 Git Branching Strategy

```
main                 (production code)
  └── develop        (integration branch)
        ├── feature/phase-1-auth
        ├── feature/phase-1-ui
        ├── feature/phase-2-projects
        ├── feature/phase-2-video-input
        └── ...
```

**Branch Naming:**

- `feature/phase-X-feature-name`
- `bugfix/issue-description`
- `hotfix/critical-issue`

### 5.2 Checkpoint Process

After each phase:

1. Run full test suite (`php artisan test`, `npm test`)
2. Self-review: security, performance, code quality
3. Fix any issues found
4. Push feature branch to remote
5. Create PR with phase summary
6. Human review and approval
7. Merge to develop
8. Tag checkpoint: `checkpoint-1`, `checkpoint-2`, etc.

### 5.3 Autonomous Development Flow

For AI coding agents:

1. Read feature specification completely
2. Identify all files to create/modify
3. Create database migrations first (if any)
4. Create models with relationships
5. Create services with business logic
6. Create controllers and routes
7. Create frontend components
8. Write unit tests for services
9. Write feature tests for endpoints
10. Run tests, fix until 100% pass
11. Run linter, fix until clean
12. Commit with descriptive message
13. Update todo tracking
14. Move to next feature
15. Stop at checkpoint for human review

### 5.4 Commit Message Format

```
[Phase X.Y] Feature Name: Brief description

- Implementation detail 1
- Implementation detail 2
- Implementation detail 3

Tests: X unit, Y feature, Z e2e
```

Example:

```
[Phase 1.2] User Registration: Implement email/password registration

- Created User model with fillable attributes
- Added RegisterRequest with validation rules
- Implemented AuthController@register endpoint
- Created RegisterForm React component
- Added authApi.register() function

Tests: 5 unit, 8 feature, 1 e2e
```

---

## 6. Timeline & Milestones

### 6.1 Week-by-Week Schedule

```
Week 1: Foundation Setup
├── Day 1-2: Environment setup, database schema (1.1)
├── Day 3: User registration (1.2)
├── Day 4: Email verification (1.3)
└── Day 5: Login (1.4)

Week 2: Foundation Complete
├── Day 1: Password reset (1.5)
├── Day 2-3: Frontend foundation (1.6)
├── Day 4: Dashboard (1.7)
└── Day 5: Integration testing, CHECKPOINT 1

Week 3: Project Management
├── Day 1: User profile (2.1)
├── Day 2: Create project (2.2)
├── Day 3: Manage projects (2.3)
├── Day 4: Brand guide (2.4)
└── Day 5: Topic input start (2.5)

Week 4: Video Input Complete
├── Day 1-2: Topic input, URL processing (2.5)
├── Day 3: Model selection (2.6)
├── Day 4: Iteration config (2.7)
└── Day 5: Integration testing, CHECKPOINT 2

Week 5: Content Generation Start
├── Day 1-2: AI Model Router (3.1)
├── Day 3: Title generation (3.2)
├── Day 4: Outline creation (3.3)
└── Day 5: Script writing start (3.4)

Week 6: Content Generation Complete
├── Day 1: Script writing complete (3.4)
├── Day 2-3: Script critique & refinement (3.5)
├── Day 4: Metadata generation (3.6)
└── Day 5: Integration testing, CHECKPOINT 3

Week 7: Asset Generation Start
├── Day 1: Thumbnail concepts (4.1)
├── Day 2: Thumbnail images (4.2)
├── Day 3-4: Voiceover generation (4.3)
└── Day 5: Music selection (4.4)

Week 8: Asset Generation Complete
├── Day 1-2: Stock footage selection (4.5)
├── Day 3: Integration of all assets
├── Day 4: Asset testing
└── Day 5: CHECKPOINT 4 prep

Week 9: Video Production
├── Day 1: Storyboard preview (5.1)
├── Day 2-3: Shotstack integration (5.2)
├── Day 4: Download package (5.3)
└── Day 5: Video library (5.4)

Week 10: Payments & Polish
├── Day 1: Retention policy (5.5)
├── Day 2: Credit system (5.6)
├── Day 3-4: Stripe integration (5.7)
├── Day 5: Free tier (5.8), CHECKPOINT 5

Week 11: Testing
├── Day 1-3: E2E testing suite (6.1)
├── Day 4: Performance optimization (6.2)
└── Day 5: Security audit (6.3)

Week 12: Launch Prep
├── Day 1-2: Bug fixes from testing
├── Day 3: Deployment setup (6.4)
├── Day 4: Documentation (6.5)
└── Day 5: Final review, MVP LAUNCH
```

### 6.2 Milestones

| Milestone    | Week | Deliverables                 |
| ------------ | ---- | ---------------------------- |
| Checkpoint 1 | 2    | Auth complete, basic UI      |
| Checkpoint 2 | 4    | Projects, video input        |
| Checkpoint 3 | 6    | Content generation pipeline  |
| Checkpoint 4 | 8    | All assets generated         |
| Checkpoint 5 | 10   | Full video production        |
| MVP Launch   | 12   | Production-ready application |

### 6.3 Risk Buffer

- 20% buffer built into estimates
- Week 11-12 includes buffer for unexpected issues
- Features ordered by priority (critical first)
- Can defer P2 features if behind schedule

---

## 7. External Service Integration

### 7.1 API Integration Order

**Phase 3 (Week 5-6):**

1. Anthropic API (text generation) - First integration
2. OpenAI API (alternative) - Second integration

**Phase 4 (Week 7-8):** 3. Google Gemini/Imagen API (thumbnails) 4. ElevenLabs API (voiceover) 5. Pexels API (stock footage) 6. Pixabay API (stock footage fallback)

**Phase 5 (Week 9-10):** 7. Shotstack API (video rendering) 8. Stripe API (payments)

### 7.2 API Testing Strategy

| Phase       | Strategy                           |
| ----------- | ---------------------------------- |
| Development | Use mocks for all external APIs    |
| Integration | Test with real APIs at checkpoints |
| Production  | Monitor costs, set rate limits     |

**Mock Services:**

- Create mock implementations of all API services
- Use mocks for unit tests (always)
- Use real APIs for integration tests (sparingly)
- Track API costs during testing

### 7.3 API Cost Management

| Service       | Estimated Cost/Video | Daily Limit        |
| ------------- | -------------------- | ------------------ |
| Anthropic     | $0.20                | $50                |
| Google Imagen | $0.15                | $30                |
| ElevenLabs    | $0.30                | $50                |
| Shotstack     | $0.50                | $100               |
| Pexels        | Free                 | 200 requests/hour  |
| Pixabay       | Free                 | 5000 requests/hour |
| Stripe        | 2.9% + $0.30         | N/A                |

---

## 8. Risk Mitigation

### 8.1 Technical Risks

| Risk                       | Likelihood | Impact | Mitigation                              |
| -------------------------- | ---------- | ------ | --------------------------------------- |
| API costs exceed estimates | Medium     | High   | Real-time cost tracking, per-video caps |
| Video rendering failures   | Medium     | High   | Auto-retry, credit refund               |
| AI output quality issues   | Medium     | Medium | Iterative refinement, user editing      |
| External API changes       | Low        | High   | Abstract into services, version pinning |

### 8.2 Timeline Risks

| Risk                                | Likelihood | Impact | Mitigation                        |
| ----------------------------------- | ---------- | ------ | --------------------------------- |
| Features take longer than estimated | Medium     | Medium | 20% buffer, prioritize critical   |
| Integration complexity              | Medium     | Medium | Early integration testing         |
| Testing reveals major bugs          | Medium     | High   | Continuous testing, quality gates |

### 8.3 Quality Risks

| Risk                       | Likelihood | Impact | Mitigation                         |
| -------------------------- | ---------- | ------ | ---------------------------------- |
| AI-generated code has bugs | Medium     | Medium | Comprehensive testing, checkpoints |
| Security vulnerabilities   | Low        | High   | Security audit, automated scanning |
| Performance issues         | Medium     | Medium | Load testing, optimization         |

### 8.4 Contingency Plans

**If behind schedule:**

1. Defer P2 features (brand guide upload, retention policy)
2. Simplify UI (fewer polish features)
3. Reduce test coverage to 70% minimum
4. Skip documentation (add post-launch)

**If API integration fails:**

1. Use alternative providers (already abstracted)
2. Implement mock mode for demo
3. Defer feature to Phase 2

---

## 9. Success Criteria

### 9.1 MVP Completion Criteria

- [ ] All Phase 1-5 features implemented
- [ ] All tests passing (100%)
- [ ] End-to-end workflow functional
- [ ] User can register, login, create project
- [ ] User can generate complete video with AI
- [ ] User can download video, thumbnail, script
- [ ] User can purchase credits via Stripe
- [ ] No critical security vulnerabilities
- [ ] Documentation complete

### 9.2 Quality Standards

| Metric                         | Target  |
| ------------------------------ | ------- |
| Code coverage (critical paths) | >80%    |
| API response time (p95)        | <500ms  |
| Page load time                 | <2s     |
| Video generation time (10 min) | <10 min |
| System uptime                  | 99%     |
| Video generation success rate  | >95%    |

### 9.3 Functional Requirements

| Requirement                      | Status   |
| -------------------------------- | -------- |
| User registration & login        | Required |
| Email verification               | Required |
| Password reset                   | Required |
| Project management               | Required |
| Video wizard (all steps)         | Required |
| Title generation (5 options)     | Required |
| Outline generation & editing     | Required |
| Script generation & critique     | Required |
| Thumbnail generation (3 options) | Required |
| Voiceover (AI or upload)         | Required |
| Music selection                  | Required |
| Stock footage matching           | Required |
| Video rendering (Shotstack)      | Required |
| Download package (ZIP)           | Required |
| Credit system                    | Required |
| Stripe payments                  | Required |
| Free tier limits                 | Required |

---

## 10. AI Agent Implementation Guide

### 10.1 How to Use This Plan

**For Claude Code (or similar AI coding agent):**

1. **Read the PRD first** (`docs/planning/PRD.md`) to understand WHAT you're building

2. **Read this Development Plan** to understand the build order

3. **Start with Phase 1, Feature 1.1** (Database Schema)

4. **Follow the feature breakdown exactly:**

    ```
    For each feature:
    1. Read the full feature specification
    2. Create database migrations first
    3. Create models with relationships
    4. Create services with business logic
    5. Create request validation classes
    6. Create controllers and routes
    7. Create frontend components
    8. Create pages that use components
    9. Write unit tests for services
    10. Write feature tests for endpoints
    11. Run tests and fix until 100% pass
    12. Run linter and fix until clean
    13. Commit with descriptive message
    ```

5. **Run tests after each feature** - must pass before continuing

6. **Stop at checkpoints** for human review

7. **Update progress tracking** after each feature

### 10.2 Common Pitfalls to Avoid

- **Building out of order**: Respect dependencies! Don't build script generation before title generation
- **Skipping tests**: Test before moving on! Untested code will fail later
- **Not checking existing code**: Don't recreate files that already exist
- **Moving on with failing tests**: Fix first! 100% pass rate required
- **Hardcoding values**: Use .env for all configuration
- **Missing validation**: Validate all inputs server-side
- **N+1 queries**: Use eager loading for relationships
- **Not handling errors**: Always handle API failures gracefully

### 10.3 Code Organization Standards

```
Backend (Laravel):
app/
├── Models/              # Eloquent models
├── Http/
│   ├── Controllers/
│   │   └── Api/        # API controllers
│   ├── Requests/       # Form request validation
│   ├── Resources/      # API resources
│   └── Middleware/     # Custom middleware
├── Services/           # Business logic
│   ├── AI/            # AI provider integrations
│   ├── Content/       # Content generation
│   ├── Asset/         # Asset generation
│   ├── Video/         # Video assembly
│   ├── Payment/       # Payment handling
│   └── Delivery/      # Download handling
├── Jobs/              # Queue jobs
├── Notifications/     # Email notifications
├── Observers/         # Model observers
├── Contracts/         # Interfaces
└── Exceptions/        # Custom exceptions

Frontend (React):
resources/js/
├── components/        # Reusable UI components
│   ├── ui/           # Base components (Button, Input, etc.)
│   ├── auth/         # Auth-related components
│   ├── projects/     # Project components
│   ├── video/        # Video creation components
│   ├── payment/      # Payment components
│   └── navigation/   # Navigation components
├── pages/            # Page components
│   ├── Auth/         # Auth pages
│   ├── Settings/     # Settings pages
│   ├── Video/        # Video pages
│   └── ...
├── stores/           # Zustand stores
├── hooks/            # Custom React hooks
├── api/              # API client functions
├── types/            # TypeScript types
├── utils/            # Utility functions
└── contexts/         # React contexts

Tests:
tests/
├── Unit/             # PHPUnit unit tests
├── Feature/          # PHPUnit feature tests
├── e2e/              # Playwright E2E tests
└── js/               # Jest component tests
```

### 10.4 API Response Format

All API responses follow this structure:

**Success Response:**

```json
{
  "success": true,
  "data": { ... },
  "message": "Optional success message"
}
```

**Error Response:**

```json
{
    "success": false,
    "message": "Error description",
    "errors": {
        "field": ["Validation error message"]
    }
}
```

### 10.5 Environment Variables

Required in `.env`:

```
# Application
APP_NAME=Itervel
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

# Database
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=itervel
DB_USERNAME=root
DB_PASSWORD=

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Storage
FILESYSTEM_DISK=r2
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=auto
AWS_BUCKET=itervel
AWS_ENDPOINT=https://xxx.r2.cloudflarestorage.com

# Mail
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=noreply@itervel.com

# AI Services
ANTHROPIC_API_KEY=
OPENAI_API_KEY=
GOOGLE_AI_API_KEY=

# Media Services
ELEVENLABS_API_KEY=
SHOTSTACK_API_KEY=
PEXELS_API_KEY=
PIXABAY_API_KEY=

# Payments
STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=
```

### 10.6 Feature Checklist Template

Use this checklist for each feature:

```markdown
## Feature X.Y: [Name]

### Implementation

- [ ] Database migration created
- [ ] Model created with relationships
- [ ] Service created with business logic
- [ ] Request validation class created
- [ ] Controller methods implemented
- [ ] Routes added to api.php
- [ ] Frontend components created
- [ ] Page component created
- [ ] API client function added

### Testing

- [ ] Unit tests written and passing
- [ ] Feature tests written and passing
- [ ] Manual testing completed

### Quality

- [ ] No linter errors
- [ ] No TypeScript errors
- [ ] Code reviewed for security
- [ ] Committed to git

### Notes

[Any implementation notes or decisions]
```

---

## Document History

| Version | Date         | Author           | Changes                  |
| ------- | ------------ | ---------------- | ------------------------ |
| 1.0     | January 2026 | Development Team | Initial development plan |

---

**End of Development Plan**

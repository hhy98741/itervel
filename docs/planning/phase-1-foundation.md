# Phase 1: Foundation

## Database, Authentication, UI Framework

**Duration:** 2 weeks (Week 1-2)  
**Features:** 7 features (1.1-1.7)  
**Checkpoint:** User can register, verify email, login, see dashboard

---

## 🎯 CONTEXT: What Came Before

**This is Phase 1 - The very beginning!**

**Nothing exists yet:**

- No database tables
- No authentication system
- No frontend UI
- Fresh Laravel + React project

**Starting from scratch:**

- Initialize development environment
- Create complete database schema (all tables for entire project)
- Build authentication system
- Create UI framework and components

---

## 📋 PHASE 1 GOAL

Build the foundation that everything else depends on:

1. ✅ Database schema (all tables)
2. ✅ User authentication (register, verify, login, reset)
3. ✅ Frontend UI framework (React + Tailwind components)
4. ✅ Basic dashboard

**After this phase:** Users can create accounts and see an empty dashboard.

---

## 🔨 Features in This Phase

### Feature 1.1: Development Environment & Database Schema

**Purpose:** Set up dev environment and create ALL database tables for entire project

**Build Breakdown:**

**Environment Setup:**

- Laravel 11 + Vite configuration
- React 18 + TypeScript setup
- Tailwind CSS + PostCSS
- ESLint + Prettier + PHP CS Fixer

**Database Migrations (CREATE ALL TABLES):**

```sql
-- Users & Auth
users (id, email, password, email_verified_at, credits_balance, preferences_json, timestamps)

-- Projects & Videos
projects (id, user_id, name, settings_json, is_default, timestamps)
videos (id, project_id, user_id, title, status, current_step, input_json, outputs_json, ai_models_json, total_cost_usd, error_message, timestamps)

-- Generation Tracking
generations (id, video_id, step, version, status, input_data, output_data, cost_usd, created_at)
api_calls (id, video_id, generation_id, step, provider, model, input_tokens, output_tokens, image_count, audio/video_duration, cost_usd, cost_calculation, request_payload, response_data, status, response_time_ms, error_message, retry_count, created_at)

-- Payments & Files
transactions (id, user_id, amount_cents, credits, type, description, stripe_payment_id, stripe_session_id, created_at)
files (id, video_id, type, storage_path, original_filename, mime_type, size_bytes, expires_at, created_at)

-- System Tables
cache (key, value, expiration)
jobs (id, queue, payload, attempts, reserved_at, available_at, created_at)
personal_access_tokens (Laravel Sanctum)
```

**Files to Create:**

```
Configuration:
├── .env.example
├── .gitignore
├── composer.json
├── package.json
├── vite.config.ts
├── tailwind.config.js
├── postcss.config.js
├── tsconfig.json
├── eslint.config.js
├── .prettierrc
└── phpcs.xml

Migrations:
database/migrations/
├── 0001_01_01_000001_create_users_table.php
├── 0001_01_01_000002_create_projects_table.php
├── 0001_01_01_000003_create_videos_table.php
├── 0001_01_01_000004_create_generations_table.php
├── 0001_01_01_000005_create_api_calls_table.php
├── 0001_01_01_000006_create_transactions_table.php
├── 0001_01_01_000007_create_files_table.php
├── 0001_01_01_000008_create_cache_table.php
├── 0001_01_01_000009_create_jobs_table.php
└── 0001_01_01_000010_create_personal_access_tokens_table.php
```

**Dependencies:**

- Requires: None (START HERE)
- Blocks: All other features

**Acceptance Criteria:**

- ✅ `php artisan migrate` runs without errors
- ✅ All tables created with correct schemas
- ✅ Foreign key constraints properly set
- ✅ Dev server starts (backend + frontend)
- ✅ Tailwind CSS compiles

**Estimated Time:** 2-3 days  
**Complexity:** Medium

**AI Agent Notes:**

- Create ALL tables now (even for future phases)
- Use proper foreign key constraints
- Add indexes on frequently queried columns (user_id, project_id, video_id, status)
- Test with `php artisan migrate:fresh`

---

### Feature 1.2: User Registration

**Purpose:** Allow new users to create accounts

**Build Breakdown:**

**Backend:**

- Model: `User` (with fillable, casts, relationships)
- Request: `RegisterRequest` (validation)
- Controller: `AuthController@register`
- Service: `AuthService` (business logic)
- Route: `POST /api/auth/register`

**Frontend:**

- Component: `RegisterForm.tsx`
- Page: `RegisterPage.tsx`
- API: `authApi.register()`

**Validation:**

```php
email: required, email, max:255, unique:users
password: required, min:8, confirmed, regex:/[A-Z]/, regex:/[0-9]/
accept_terms: required, accepted
```

**API Response:**

```json
{
    "success": true,
    "data": {
        "user": { "id": 1, "email": "...", "credits_balance": 1 },
        "token": "sanctum-token"
    }
}
```

**Files to Create:**

```
Backend:
├── app/Models/User.php
├── app/Http/Controllers/Api/AuthController.php
├── app/Http/Requests/Auth/RegisterRequest.php
├── app/Services/AuthService.php
├── app/Exceptions/AuthException.php
└── routes/api.php (add routes)

Frontend:
├── resources/js/api/authApi.ts
├── resources/js/components/Auth/RegisterForm.tsx
├── resources/js/pages/Auth/RegisterPage.tsx
└── resources/js/types/auth.ts

Tests:
├── tests/Unit/Http/Requests/RegisterRequestTest.php
└── tests/Feature/Auth/RegistrationTest.php (8 tests)
```

**Dependencies:**

- Requires: 1.1 (Database)
- Blocks: 1.3, 1.4

**Acceptance Criteria:**

- ✅ Valid registration creates user + returns token
- ✅ Duplicate email returns 422 error
- ✅ Weak password rejected
- ✅ User gets 1 free credit on signup
- ✅ Creates default project "My Channel"

**Estimated Time:** 1 day  
**Complexity:** Low

---

### Feature 1.3: Email Verification

**Purpose:** Verify email ownership before allowing video creation

**Build Breakdown:**

**Backend:**

- Notification: `VerifyEmailNotification`
- Controller: `AuthController@verifyEmail`, `@resendVerification`
- Middleware: `EnsureEmailIsVerified`
- Routes: `GET /api/auth/verify-email/{id}/{hash}`, `POST /api/auth/resend-verification`

**Frontend:**

- Component: `VerificationPendingBanner.tsx`
- Page: `VerifyEmailPage.tsx`

**Files to Create:**

```
Backend:
├── app/Notifications/VerifyEmailNotification.php
├── app/Http/Middleware/EnsureEmailIsVerified.php
└── app/Http/Controllers/Api/AuthController.php (update)

Frontend:
├── resources/js/components/Auth/VerificationPendingBanner.tsx
├── resources/js/pages/Auth/VerifyEmailPage.tsx
└── resources/js/api/authApi.ts (update)

Tests:
└── tests/Feature/Auth/EmailVerificationTest.php (6 tests)
```

**Dependencies:**

- Requires: 1.2 (Registration)
- Blocks: Video creation features (Phase 2+)

**Acceptance Criteria:**

- ✅ Verification email sent on registration
- ✅ Link verifies email and marks user as verified
- ✅ User can resend verification email (rate limited)
- ✅ Video creation blocked until verified

**Estimated Time:** 1 day  
**Complexity:** Low

---

### Feature 1.4: Login & Logout

**Purpose:** Allow users to securely access accounts

**Build Breakdown:**

**Backend:**

- Request: `LoginRequest`
- Controller: `AuthController@login`, `@logout`
- Service: `AuthService` (login logic, rate limiting)
- Routes: `POST /api/auth/login`, `POST /api/auth/logout`

**Frontend:**

- Component: `LoginForm.tsx`
- Page: `LoginPage.tsx`
- Store: `authStore.ts` (Zustand)
- Hook: `useAuth.ts`

**Security:**

- Lock account after 5 failed attempts (15 min cooldown)
- Track attempts in Redis cache
- HTTP-only secure cookies
- Remember me (30-day token)

**Files to Create:**

```
Backend:
├── app/Http/Requests/Auth/LoginRequest.php
├── app/Http/Controllers/Api/AuthController.php (update)
└── app/Services/AuthService.php (update)

Frontend:
├── resources/js/components/Auth/LoginForm.tsx
├── resources/js/pages/Auth/LoginPage.tsx
├── resources/js/stores/authStore.ts
├── resources/js/api/authApi.ts (update)
└── resources/js/hooks/useAuth.ts

Tests:
├── tests/Unit/Http/Requests/LoginRequestTest.php (3 tests)
└── tests/Feature/Auth/LoginTest.php (7 tests)
```

**Dependencies:**

- Requires: 1.2 (Registration)
- Blocks: All authenticated features

**Acceptance Criteria:**

- ✅ Valid credentials return token
- ✅ Invalid credentials return 401
- ✅ Account locked after 5 failures
- ✅ Remember me extends session to 30 days
- ✅ Logout invalidates token

**Estimated Time:** 1 day  
**Complexity:** Low

---

### Feature 1.5: Password Reset

**Purpose:** Allow users to reset forgotten passwords

**Build Breakdown:**

**Backend:**

- Notification: `ResetPasswordNotification`
- Controller: `AuthController@forgotPassword`, `@resetPassword`
- Requests: `ForgotPasswordRequest`, `ResetPasswordRequest`

**Frontend:**

- Components: `ForgotPasswordForm.tsx`, `ResetPasswordForm.tsx`
- Pages: `ForgotPasswordPage.tsx`, `ResetPasswordPage.tsx`

**Files to Create:**

```
Backend:
├── app/Notifications/ResetPasswordNotification.php
├── app/Http/Requests/Auth/ForgotPasswordRequest.php
├── app/Http/Requests/Auth/ResetPasswordRequest.php
└── app/Http/Controllers/Api/AuthController.php (update)

Frontend:
├── resources/js/components/Auth/ForgotPasswordForm.tsx
├── resources/js/components/Auth/ResetPasswordForm.tsx
├── resources/js/pages/Auth/ForgotPasswordPage.tsx
├── resources/js/pages/Auth/ResetPasswordPage.tsx
└── resources/js/api/authApi.ts (update)

Tests:
└── tests/Feature/Auth/PasswordResetTest.php (6 tests)
```

**Dependencies:**

- Requires: 1.2 (Registration)
- Blocks: None (independent)

**Acceptance Criteria:**

- ✅ Reset link sent to valid email
- ✅ Link expires after 1 hour
- ✅ Max 3 requests per email per hour
- ✅ Password changed successfully
- ✅ User notified via email

**Estimated Time:** 0.5 days  
**Complexity:** Low

---

### Feature 1.6: Frontend Foundation & UI Framework

**Purpose:** React app structure and reusable UI components

**Build Breakdown:**

**Application Shell:**

- Layouts: `MainLayout` (header, sidebar, content), `AuthLayout` (centered card)
- Router: Protected routes, auth routes
- Context: `AuthProvider` (global auth state)

**Core UI Components:**

- Button (variants: primary, secondary, outline, danger)
- Input (text, email, password with validation states)
- Card, Modal, Alert, Spinner, Badge
- Select, Textarea, Checkbox, Radio, Slider, Progress

**Navigation:**

- Header (logo, user menu, credit balance)
- Sidebar (nav links)
- Breadcrumbs, UserMenu

**Files to Create:**

```
App Structure:
├── resources/js/App.tsx
├── resources/js/main.tsx
└── resources/js/router.tsx

Layouts:
├── resources/js/layouts/MainLayout.tsx
└── resources/js/layouts/AuthLayout.tsx

UI Components (13 components):
resources/js/components/ui/
├── Button.tsx
├── Input.tsx
├── Card.tsx
├── Modal.tsx
├── Alert.tsx
├── Spinner.tsx
├── Badge.tsx
├── Select.tsx
├── Textarea.tsx
├── Checkbox.tsx
├── Radio.tsx
├── Slider.tsx
├── Progress.tsx
└── index.ts (barrel export)

Navigation:
resources/js/components/navigation/
├── Header.tsx
├── Sidebar.tsx
├── Breadcrumbs.tsx
└── UserMenu.tsx

Utils:
├── resources/js/contexts/AuthContext.tsx
├── resources/js/hooks/useAuth.ts
├── resources/js/utils/api.ts
├── resources/js/utils/formatters.ts
└── resources/js/types/index.ts

Styles:
└── resources/css/app.css

Tests:
├── tests/js/components/Button.test.tsx
├── tests/js/components/Input.test.tsx
└── tests/js/components/Modal.test.tsx
```

**Dependencies:**

- Requires: 1.1 (Environment)
- Blocks: All frontend features
- Can parallelize with: Backend auth (1.2-1.5)

**Acceptance Criteria:**

- ✅ All components render correctly
- ✅ Responsive (mobile/tablet/desktop)
- ✅ Tailwind styles applied
- ✅ TypeScript types for all props
- ✅ Accessible (WCAG 2.1 AA)

**Estimated Time:** 2 days  
**Complexity:** Medium

**AI Agent Notes:**

- Use `React.forwardRef` for form components
- Add `data-testid` for E2E testing
- Export all from `index.ts` barrel file
- Follow Tailwind utility-first approach

---

### Feature 1.7: Dashboard Page

**Purpose:** Main landing page after login

**Build Breakdown:**

**Frontend:**

- Page: `DashboardPage.tsx`
- Components: `QuickStats`, `RecentVideos`, `QuickActions`
- API: `dashboardApi.getStats()`

**Backend:**

- Controller: `DashboardController@index`
- Route: `GET /api/dashboard`

**Dashboard Data:**

```json
{
  "credits_balance": 5,
  "videos_this_month": 3,
  "total_videos": 12,
  "recent_videos": [...],
  "active_project": { "id": 1, "name": "My Channel" }
}
```

**Files to Create:**

```
Backend:
└── app/Http/Controllers/Api/DashboardController.php

Frontend:
├── resources/js/pages/DashboardPage.tsx
├── resources/js/components/dashboard/QuickStats.tsx
├── resources/js/components/dashboard/RecentVideos.tsx
├── resources/js/components/dashboard/QuickActions.tsx
└── resources/js/api/dashboardApi.ts

Tests:
└── tests/Feature/DashboardTest.php
```

**Dependencies:**

- Requires: 1.4 (Login), 1.6 (UI Framework)

**Acceptance Criteria:**

- ✅ Shows credit balance prominently
- ✅ Lists recent videos with status
- ✅ Quick action: "Create New Video"
- ✅ Shows active project name

**Estimated Time:** 0.5 days  
**Complexity:** Low

---

## ✅ CHECKPOINT 1: STOP HERE

**After completing all 7 features above, STOP and wait for review.**

### What You've Built:

- ✅ Complete database schema (all tables)
- ✅ User authentication (register, verify, login, reset)
- ✅ Frontend UI framework (React components)
- ✅ Dashboard page

### Test This Phase:

```bash
# Run all tests
vendor/bin/phpunit
npm test

# Self-review checklist
- All migrations run successfully
- User can register with email/password
- Verification email sent and works
- User can login/logout
- Password reset works
- Dashboard shows after login
- All UI components render
- No console errors
- All tests passing (100%)
```

### Integration Point:

```bash
# Push feature branch
git push origin feature/phase-1-foundation

# Create PR: feature/phase-1-foundation → develop
# Wait for review before Phase 2
```

---

## 🚫 DO NOT BUILD (Next Phase Preview)

**Phase 2: Project Management & Video Input (STOP - Don't start yet!)**

Phase 2 will add:

- User profile management
- Project CRUD operations
- Video creation workflow initiation
- Reference URL processing
- AI model configuration

**Why stop here:**

- Phase 1 is foundation that must be solid
- Phase 2 depends on authentication working
- Human review ensures auth is secure
- Integration point for testing

**DO NOT BUILD:**

- Profile pages
- Project management
- Video creation forms
- AI model routing
- URL processing

**These are Phase 2 features - build them in the next session!**

---

## 📊 Phase 1 Summary

**Features:** 7  
**Files Created:** ~100  
**Tests:** ~40  
**Time:** 2 weeks

**Result:** Foundation complete! Users can create accounts and see dashboard. Ready for Phase 2! ✅

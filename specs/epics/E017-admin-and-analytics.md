# Features

Admin dashboard, API cost tracking, admin refund management, and post-video quality rating surveys.

**Depends on**: E005-credits-and-billing.md, E014-video-assembly-and-rendering.md

## 1. Admin Dashboard

**What it does**: Provides administrators with an overview of platform activity including user counts, video generation stats, revenue, costs, and error tracking.
**Expected outcome**: Admins see daily, weekly, and monthly metrics for new users, videos created, revenue, and API costs. A conversion funnel visualization shows the user journey from registration through repeat purchases. Top errors from the last 7 days are listed.

## 2. API Cost Tracking

**What it does**: Tracks the actual cost of every external API call made during video generation.
**Expected outcome**: Every AI text generation, image generation, text-to-speech, stock footage search, and video rendering call is logged with its cost. This data feeds into the cost breakdown display and admin dashboard.

## 3. Admin Refund Management

**What it does**: Lets administrators issue refunds to users through Stripe.
**Expected outcome**: An admin can look up a user's transaction, issue a refund via Stripe, and the corresponding credits are deducted from the user's balance. The refund is logged in the transaction history.

## 4. Post-Video Quality Rating Survey

**What it does**: Asks users to rate the quality of their generated video after completion.
**Expected outcome**: After downloading or previewing a completed video, the user is prompted to rate the video quality on a 1-5 scale. This feedback is collected and displayed in the admin dashboard.

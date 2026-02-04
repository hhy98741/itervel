# Phase 5: Video Production & Delivery

## Assembly, Rendering, Downloads, Payments

**Duration:** 2 weeks (Week 9-10)  
**Features:** 8 features (5.1-5.8)  
**Checkpoint:** User can render and download complete videos

---

## 🎯 CONTEXT: What Came Before

**Completed Phases:**

- ✅ Phase 1: Foundation - Complete
- ✅ Phase 2: Projects & Input - Complete
- ✅ Phase 3: Content Generation - Complete
- ✅ Phase 4: Asset Generation - Complete

**What Exists:**

- ✅ Full authentication system
- ✅ Project management
- ✅ Video creation workflow
- ✅ AI-generated titles, scripts, metadata
- ✅ AI-generated thumbnails
- ✅ Voiceover audio (TTS)
- ✅ Background music
- ✅ Stock footage clips

**DO NOT Rebuild:**

- Any Phase 1-4 features
- Don't recreate content generation
- Don't recreate asset generation
- All previous features are complete

---

## 📋 PHASE 5 GOAL

Build video production and delivery:

1. ✅ Storyboard creation (timeline planning)
2. ✅ Video assembly (combine all assets)
3. ✅ Shotstack rendering
4. ✅ Download & packaging
5. ✅ Video library & history
6. ✅ Credit system (deduction tracking)
7. ✅ Payment integration (Stripe)
8. ✅ Purchase flow

**After this phase:** Users can render complete videos and download them!

---

## 🔨 Features in This Phase

### Feature 5.1: Storyboard Creation

**Purpose:** Plan video timeline and asset placement

**Key Components:**

- Service: StoryboardService
- Timeline: Map script sections to video clips
- Duration calculation: Match voiceover timing
- Asset assignment: Which footage for which section

**Storyboard Structure:**

```json
{
  "total_duration": 725,
  "clips": [
    {
      "start_time": 0,
      "duration": 30,
      "type": "footage",
      "asset_id": 1,
      "audio_segment": "intro",
      "transition": "fade"
    },
    {
      "start_time": 30,
      "duration": 45,
      "type": "footage",
      "asset_id": 2,
      "audio_segment": "section1",
      "transition": "dissolve"
    },
    ...
  ],
  "audio_track": {
    "voiceover": "file_id_123",
    "music": "file_id_456",
    "music_volume": 0.2
  },
  "title_card": {
    "duration": 3,
    "text": "[video title]"
  }
}
```

**Files to Create:**

```
Backend:
├── app/Services/StoryboardService.php
├── app/Http/Controllers/Api/StoryboardController.php
└── app/Jobs/CreateStoryboardJob.php

Frontend:
├── resources/js/components/storyboard/StoryboardEditor.tsx
├── resources/js/components/storyboard/TimelineView.tsx
├── resources/js/components/storyboard/ClipAssignment.tsx
├── resources/js/api/storyboardApi.ts

Tests:
└── tests/Feature/StoryboardTest.php
```

**Dependencies:** Requires 4.3, 4.4, 4.5 (all assets ready)  
**Time:** 1.5 days | **Complexity:** High

---

### Feature 5.2: Video Assembly

**Purpose:** Combine all assets into Shotstack JSON specification

**Key Components:**

- Service: VideoAssemblyService
- Generate Shotstack JSON from storyboard
- Include: Footage clips, voiceover, music, transitions, title cards
- Quality: 1080p, 30fps

**Shotstack JSON Generation:**

```json
{
  "timeline": {
    "soundtrack": {
      "src": "[voiceover_url]",
      "effect": "fadeInFadeOut"
    },
    "background": "#000000",
    "tracks": [
      {
        "clips": [
          {
            "asset": {
              "type": "video",
              "src": "[footage_url]"
            },
            "start": 0,
            "length": 30,
            "transition": {
              "in": "fade",
              "out": "fade"
            }
          },
          ...
        ]
      },
      {
        "clips": [
          {
            "asset": {
              "type": "audio",
              "src": "[music_url]",
              "volume": 0.2
            },
            "start": 0,
            "length": 725
          }
        ]
      }
    ]
  },
  "output": {
    "format": "mp4",
    "resolution": "1080",
    "fps": 30,
    "quality": "high"
  }
}
```

**Files to Create:**

```
Backend:
├── app/Services/VideoAssemblyService.php
├── app/Services/ShotstackJsonBuilder.php
└── app/Jobs/AssembleVideoJob.php

Tests:
└── tests/Unit/Services/ShotstackJsonBuilderTest.php
```

**Dependencies:** Requires 5.1 (Storyboard)  
**Time:** 1 day | **Complexity:** High

---

### Feature 5.3: Shotstack Video Rendering

**Purpose:** Submit to Shotstack API and track rendering progress

**Key Components:**

- Service: ShotstackService
- Submit: POST to Shotstack render endpoint
- Polling: Check status every 5 seconds
- Webhook: Receive completion notification
- Download: Retrieve rendered video from Shotstack
- Storage: Save to Cloudflare R2

**Rendering Flow:**

1. Submit Shotstack JSON
2. Receive render ID
3. Update video status to 'processing'
4. Poll for completion (or wait for webhook)
5. Download rendered video
6. Upload to R2
7. Update video status to 'completed'
8. Create file record

**Webhook Handler:**

```json
{
    "action": "render.completed",
    "data": {
        "id": "[render_id]",
        "url": "[rendered_video_url]",
        "duration": 725,
        "size": 45632100
    }
}
```

**Files to Create:**

```
Backend:
├── app/Services/ShotstackService.php
├── app/Http/Controllers/Api/RenderController.php
├── app/Http/Controllers/Webhooks/ShotstackWebhookController.php
├── app/Jobs/RenderVideoJob.php
├── app/Jobs/ProcessRenderCompleteJob.php
└── routes/web.php (add webhook route)

Frontend:
├── resources/js/components/render/RenderProgress.tsx
├── resources/js/components/render/RenderStatusBadge.tsx
├── resources/js/api/renderApi.ts

Tests:
├── tests/Feature/VideoRenderingTest.php
└── tests/Feature/ShotstackWebhookTest.php
```

**Dependencies:** Requires 5.2 (Assembly)  
**Time:** 2 days | **Complexity:** High

---

### Feature 5.4: Download & Packaging

**Purpose:** Allow users to download completed videos with assets

**Key Components:**

- Generate download package: Video + Thumbnail + Script + Metadata
- Temporary signed URLs (expires in 24 hours)
- ZIP packaging for complete package download
- Individual asset downloads

**Download Package Contents:**

```
video-title.zip
├── video.mp4 (rendered video)
├── thumbnail.jpg (selected thumbnail)
├── script.txt (final script)
├── metadata.txt (title, description, tags)
└── credits_report.txt (cost breakdown)
```

**Files to Create:**

```
Backend:
├── app/Services/DownloadService.php
├── app/Services/PackagingService.php
├── app/Http/Controllers/Api/DownloadController.php
└── routes/api.php (download routes with signed URLs)

Frontend:
├── resources/js/components/download/DownloadButtons.tsx
├── resources/js/components/download/PackagePreview.tsx
├── resources/js/api/downloadApi.ts

Tests:
└── tests/Feature/DownloadTest.php
```

**Dependencies:** Requires 5.3 (Rendering complete)  
**Time:** 1 day | **Complexity:** Medium

---

### Feature 5.5: Video Library & History

**Purpose:** Browse all completed videos with filtering and search

**Key Components:**

- Page: Video library with grid/list views
- Filters: Project, status, date range
- Search: By title or topic
- Actions: Re-download, delete, duplicate

**Files to Create:**

```
Backend:
├── app/Http/Controllers/Api/VideoLibraryController.php
└── routes/api.php

Frontend:
├── resources/js/pages/VideoLibraryPage.tsx
├── resources/js/components/library/VideoGrid.tsx
├── resources/js/components/library/VideoCard.tsx
├── resources/js/components/library/VideoFilters.tsx
├── resources/js/components/library/VideoSearch.tsx
├── resources/js/api/libraryApi.ts

Tests:
└── tests/Feature/VideoLibraryTest.php
```

**Dependencies:** Requires 5.3 (Videos can be completed)  
**Time:** 1 day | **Complexity:** Low

---

### Feature 5.6: Credit System

**Purpose:** Track credit balance and deductions

**Key Components:**

- Transaction model: Track all credit changes
- Deduction: Automatically deduct credits on video completion
- Balance check: Prevent video creation if insufficient credits
- Cost calculation: Sum of all API calls for video

**Credit Deduction Flow:**

1. Video completes rendering
2. Calculate total cost from api_calls table
3. Deduct equivalent credits from user balance
4. Create transaction record (type: 'deduction')
5. Update video with final cost

**Transaction Schema:**

```php
[
    'user_id' => 1,
    'amount_cents' => -500,  // negative for deduction
    'credits' => -1,
    'type' => 'deduction',
    'description' => 'Video: How to Train Your Puppy',
    'created_at' => '2026-01-21 10:30:00'
]
```

**Files to Create:**

```
Backend:
├── app/Models/Transaction.php
├── app/Services/CreditService.php
├── app/Http/Controllers/Api/CreditController.php
├── app/Observers/VideoObserver.php (auto-deduct on complete)
└── app/Exceptions/InsufficientCreditsException.php

Frontend:
├── resources/js/components/credits/CreditBalance.tsx
├── resources/js/components/credits/TransactionHistory.tsx
├── resources/js/api/creditApi.ts

Tests:
├── tests/Unit/Services/CreditServiceTest.php
└── tests/Feature/CreditDeductionTest.php
```

**Dependencies:** Requires 5.3 (Video completion)  
**Time:** 1 day | **Complexity:** Medium

---

### Feature 5.7: Stripe Payment Integration

**Purpose:** Purchase credits using Stripe

**Key Components:**

- Stripe Checkout: Hosted payment page
- Packages: $5 (5 credits), $10 (11 credits), $25 (28 credits)
- Webhooks: Handle payment success/failure
- Add credits: On successful payment

**Credit Packages:**

```php
[
    'starter' => ['price' => 500, 'credits' => 5, 'name' => 'Starter Pack'],
    'popular' => ['price' => 1000, 'credits' => 11, 'name' => 'Popular Pack'],
    'pro' => ['price' => 2500, 'credits' => 28, 'name' => 'Pro Pack']
]
```

**Payment Flow:**

1. User clicks "Buy Credits"
2. Select package
3. Redirect to Stripe Checkout
4. User completes payment
5. Stripe webhook notifies completion
6. Add credits to user account
7. Create transaction record (type: 'purchase')
8. Redirect user to dashboard with success message

**Files to Create:**

```
Backend:
├── app/Services/StripeService.php
├── app/Http/Controllers/Api/PaymentController.php
├── app/Http/Controllers/Webhooks/StripeWebhookController.php
├── app/Jobs/ProcessPaymentJob.php
├── config/services.php (add Stripe config)
└── routes/web.php (add webhook route)

Frontend:
├── resources/js/pages/PurchaseCreditsPage.tsx
├── resources/js/components/payment/PackageCard.tsx
├── resources/js/components/payment/CheckoutButton.tsx
├── resources/js/api/paymentApi.ts

Tests:
├── tests/Feature/StripePaymentTest.php
└── tests/Feature/StripeWebhookTest.php
```

**Dependencies:** None (independent)  
**Time:** 1.5 days | **Complexity:** High

---

### Feature 5.8: Purchase Flow & Success Pages

**Purpose:** Complete user experience for purchasing credits

**Key Components:**

- Purchase page: Display packages with pricing
- Success page: Confirmation after payment
- Failure page: Handle payment errors
- Email notifications: Receipt and confirmation

**Files to Create:**

```
Backend:
├── app/Notifications/PaymentSuccessNotification.php
├── app/Notifications/PaymentReceiptNotification.php

Frontend:
├── resources/js/pages/PaymentSuccessPage.tsx
├── resources/js/pages/PaymentFailedPage.tsx
├── resources/js/components/payment/PaymentConfirmation.tsx

Tests:
└── tests/Feature/PaymentFlowTest.php
```

**Dependencies:** Requires 5.7 (Stripe integration)  
**Time:** 0.5 days | **Complexity:** Low

---

## ✅ CHECKPOINT 5: STOP HERE

**After completing all 8 features above, STOP and wait for review.**

### What You've Built:

- ✅ Storyboard creation & timeline planning
- ✅ Video assembly (Shotstack JSON)
- ✅ Shotstack rendering with webhooks
- ✅ Download & packaging system
- ✅ Video library & history
- ✅ Credit system with auto-deduction
- ✅ Stripe payment integration
- ✅ Complete purchase flow

### Test This Phase:

```bash
# Run all tests
vendor/bin/phpunit
npm test

# Self-review checklist
- Storyboard creates proper timeline
- Video assembly generates valid Shotstack JSON
- Rendering submits to Shotstack successfully
- Webhooks handle completion correctly
- Videos download as MP4 and ZIP packages
- Video library displays all videos
- Credits deduct correctly on completion
- Stripe checkout works (test mode)
- Webhooks add credits on payment
- All tests passing (100%)
```

### Integration Point:

```bash
# Push feature branch
git push origin feature/phase-5-video-production

# Create PR: feature/phase-5-video-production → develop
# Wait for review before Phase 6
```

---

## 🚫 DO NOT BUILD (Next Phase Preview)

**Phase 6: Testing & Launch (STOP - Don't start yet!)**

Phase 6 will add:

- E2E testing (complete user journeys)
- Performance optimization
- Security hardening
- Production deployment prep

**Why stop here:**

- Phase 5 is the core MVP - must work perfectly
- Phase 6 is about polishing and deploying
- Human review ensures entire video pipeline works
- Integration point for full system testing

**DO NOT BUILD:**

- E2E test suites
- Performance optimizations
- Security hardening
- Deployment configurations

**These are Phase 6 features - build them in the next session!**

---

## 📊 Phase 5 Summary

**Features:** 8  
**Files Created:** ~60  
**Tests:** ~45  
**Time:** 2 weeks

**Result:** Video production complete! Users can render videos and purchase credits. Almost ready for launch! ✅

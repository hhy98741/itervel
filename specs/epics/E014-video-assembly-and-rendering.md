# Features

Stock footage matching, storyboard preview, video rendering, progress tracking, failure handling, and webhook processing.

**Depends on**: E011-outline-and-script-generation.md, E012-thumbnail-generation.md, E013-audio-production.md

## 1. Stock Footage Matching

**What it does**: Automatically finds and matches stock footage clips to each segment of the script.
**Expected outcome**: The system splits the script into 3-5 thematic segments, extracts search keywords, searches free stock footage APIs (Pexels, Pixabay), and selects clips of 3-6 seconds each to fill each segment's duration. Broader search terms are used as fallback if specific searches return no results.

## 2. Storyboard Preview

**What it does**: Shows a visual preview of the planned video before the final render, displaying script segments alongside matched footage thumbnails.
**Expected outcome**: The user sees a timeline view showing which stock footage clips will appear with which parts of the script. They can hover to see footage thumbnails. The user must approve the storyboard before rendering begins.

## 3. Video Rendering

**What it does**: Assembles the voiceover, stock footage clips, background music, and other assets into a final MP4 video using a cloud rendering service.
**Expected outcome**: The system builds a video timeline, submits it for cloud rendering, and produces a 1080p MP4 video (16:9 aspect ratio, H.264 codec). Rendering takes 2-5 minutes. The maximum video duration is 20 minutes.

## 4. Render Progress Tracking

**What it does**: Shows the user real-time progress of the video rendering process.
**Expected outcome**: The user sees a progress indicator showing which steps are complete, which are in progress, and which are pending. An estimated time remaining is displayed.

## 5. Render Failure Handling

**What it does**: Handles cases where the video rendering fails.
**Expected outcome**: If rendering fails, the system automatically retries once. If it fails again, the user is alerted and their credit is refunded.

## 6. Shotstack Completion Webhook Processing

**What it does**: Receives and processes completion notifications from the video rendering service when a video finishes rendering.
**Expected outcome**: When the rendering service signals that a video is done (or has failed), the system verifies the webhook signature, downloads the rendered video to storage, updates the video status, and optionally notifies the user by email.

## 7. AI API Failure Retry and Recovery

**What it does**: Automatically retries failed AI API calls during content generation and alerts the user if recovery is not possible.
**Expected outcome**: If an AI API call fails (text generation, image generation, etc.), the system retries the request once. If it fails again, the user is notified of the specific issue and given the option to retry manually or adjust their settings.

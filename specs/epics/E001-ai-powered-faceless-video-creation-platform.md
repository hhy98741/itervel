# Features

Itervel is an AI-powered web application that helps faceless YouTube creators produce high-quality, engaging videos through an iterative refinement workflow, delivering complete upload-ready packages including script, voiceover, video, thumbnail, and metadata.

## 1. User Registration

**What it does**: Allows new users to create an account using their email address and a password.
**Expected outcome**: A user fills in their email, creates a password (meeting strength requirements), confirms the password, accepts the Terms of Service, and submits the form. They receive a new account with 1 free video credit and are prompted to verify their email.

## 2. Email Verification

**What it does**: Sends a verification email after registration so users can confirm they own the email address.
**Expected outcome**: The user receives an email with a verification link within 30 seconds of registering. Clicking the link verifies their account. The link expires after 24 hours. Users can request up to 3 new verification emails per hour. Unverified users can log in but cannot create videos.

## 3. User Login

**What it does**: Allows registered users to log in to their account with their email and password.
**Expected outcome**: The user enters their credentials and gains access to the application. They can optionally choose "Remember me" for 30-day session persistence. After 5 failed login attempts, the account is locked for 15 minutes. Sessions expire after 24 hours of inactivity.

## 4. Password Reset

**What it does**: Allows users who have forgotten their password to reset it via email.
**Expected outcome**: The user requests a password reset, receives a single-use link (expires after 1 hour), sets a new password, and is notified of the change via email. Maximum 3 reset requests per email per hour.

## 5. User Profile Viewing

**What it does**: Lets users view their account information such as email, join date, and credit balance.
**Expected outcome**: The user sees a profile page displaying their account details at a glance.

## 6. Password Change

**What it does**: Allows logged-in users to change their current password.
**Expected outcome**: The user enters their current password for verification, then sets a new password. The password is updated immediately.

## 7. User Default Preferences

**What it does**: Lets users set default preferences for video creation, such as default video length, speaking pace, and number of script iterations.
**Expected outcome**: The user configures their preferred defaults (e.g., 12-minute video, 165 words per minute, 2 script iterations) and these are automatically applied when creating new videos.

## 8. Notification Settings

**What it does**: Allows users to toggle email notifications on or off.
**Expected outcome**: The user can enable or disable email notifications from a settings page, and the system respects their preference.

## 9. Account Deletion

**What it does**: Allows users to permanently delete their account and all associated data.
**Expected outcome**: The user requests account deletion. All their data, videos, projects, and files are removed. This supports GDPR compliance.

## 10. Create Project

**What it does**: Lets users create separate projects to organize videos by YouTube channel or content category.
**Expected outcome**: The user creates a named project with optional settings like target audience, tone, and speaking pace. Free users can have 1 project; paid users can have unlimited projects.

## 11. Auto-Create Default Project

**What it does**: Automatically creates a starter project for new users when they first log in.
**Expected outcome**: Upon first login, a project named "My Channel" is automatically created and set as the default project, so the user can start creating videos immediately.

## 12. List and Switch Projects

**What it does**: Shows all of a user's projects and lets them switch between them.
**Expected outcome**: The user sees all their projects in a dropdown or tab interface and can switch the active project. Each project shows its video count.

## 13. Edit Project Settings

**What it does**: Lets users update a project's name and settings (target audience, tone, thumbnail style, music preference, etc.).
**Expected outcome**: The user modifies project details and the changes are saved. Future videos in this project use the updated settings.

## 14. Delete Project

**What it does**: Allows users to delete a project they no longer need.
**Expected outcome**: The user is warned that deleting the project will also delete all associated videos. They must type the project name to confirm. Once confirmed, the project and its videos are permanently removed.

## 15. Brand Guide Upload

**What it does**: Lets users upload a brand guide document (.txt or .md) to customize the AI's voice and tone for a specific project.
**Expected outcome**: The user uploads a brand guide file (max 100KB). The system extracts the content and uses it as context when generating scripts for that project. Users can also override the brand guide for individual videos.

## 16. Brand Guide Template Download

**What it does**: Provides a sample brand guide template that users can download and fill in.
**Expected outcome**: The user downloads a pre-formatted template showing how to describe their channel's voice, tone, target audience, content pillars, and preferred language.

## 17. Topic Input

**What it does**: Lets the user enter the main topic or concept for their video.
**Expected outcome**: The user types a topic description (100-500 characters) into a text field. This topic drives all subsequent content generation.

## 18. Reference URL Input

**What it does**: Allows users to provide up to 3 reference URLs (articles or YouTube videos) to inform the AI's research.
**Expected outcome**: The user pastes up to 3 URLs. The system validates the URL format and checks accessibility.

## 19. Reference URL Processing

**What it does**: Automatically extracts useful content from the reference URLs the user provided.
**Expected outcome**: For articles, the system scrapes the text content. For YouTube videos, it extracts the transcript. Key concepts are pulled out as bullet points. Each URL has a 30-second processing timeout. If extraction fails, the user is warned but can continue without that reference. Extracted content is cached for reuse.

## 20. AI Model Selection

**What it does**: Lets users choose which AI model to use for each step of the video creation process (title, outline, script, critique, thumbnails).
**Expected outcome**: By default, "Use recommended settings" is checked (all Sonnet). Users can uncheck this to customize the AI model for each step via dropdowns. A cost preview updates as they change selections. Their last configuration is remembered.

## 21. Script Iteration Configuration

**What it does**: Allows users to set how many AI critique-and-refinement rounds the script goes through (0-5 rounds).
**Expected outcome**: The user selects the number of iterations via a slider or dropdown. A tooltip explains what iterations do. The cost impact is shown. Free tier users are limited to 1 iteration. A warning appears at 3+ iterations about increased cost.

## 22. Title Generation

**What it does**: Uses AI to generate 5 title options for the video, each with a logline, framework used, character count, and click-potential ranking.
**Expected outcome**: The user sees 5 title options ranked 1-5 by predicted click-through rate. Each title includes the title text, a one-sentence logline, the framework used (e.g., "Contrarian Angle"), character count, and reasoning for the ranking.

## 23. Title Selection

**What it does**: Lets the user pick one title from the 5 AI-generated options.
**Expected outcome**: The user selects a title via radio button and clicks "Continue." The selected title is used for all subsequent generation steps.

## 24. Outline Generation

**What it does**: Uses AI to create a structured video outline from the selected title and logline, with sections, timing estimates, and key points.
**Expected outcome**: The user sees an outline containing a hook (30 seconds), 3-5 main sections, and a closing. Each section has a title, estimated duration, bullet-point key points, and engagement/build-up notes.

## 25. Outline Editing

**What it does**: Lets users edit the AI-generated outline before proceeding to script writing.
**Expected outcome**: The user can edit section titles and content inline, reorder sections via drag-and-drop, add or remove sections, and adjust timing. They can approve the outline or request it be regenerated.

## 26. Script Writing

**What it does**: Uses AI to generate a full conversational video script based on the approved outline.
**Expected outcome**: A 2,000-2,500 word script is generated that follows the outline structure, matches the brand voice (if a brand guide is provided), includes delivery cues like [PAUSE] and [EMPHASIS], and targets the configured speaking pace.

## 27. Script Critique and Refinement

**What it does**: The AI automatically critiques the script from an audience perspective and refines it through multiple rounds.
**Expected outcome**: For each critique round, the AI identifies weak points (where viewers would click away), rates issues by impact, and suggests specific fixes. The AI then revises the script based on the critique. The user sees progress updates ("Refining script... Round 2 of 3"). This repeats for the configured number of rounds (0-5), maxing out at 5.

## 28. Script Review and Editing

**What it does**: Presents the final refined script to the user for review and optional manual editing.
**Expected outcome**: The user sees the full script in an editable text field with word count, estimated duration, delivery cues, and a summary of how many critique rounds it went through. The user can edit the script directly before approving it.

## 29. YouTube Metadata Generation

**What it does**: Automatically generates a YouTube-optimized title, description, and tags based on the video content.
**Expected outcome**: The system produces a 150-300 word SEO-optimized description, 10-15 relevant tags, and optional chapter timestamps derived from the outline. All fields are editable by the user and have one-click copy buttons.

## 30. Thumbnail Concept Generation

**What it does**: Uses AI to create 5 thumbnail concept descriptions, critique them, and select the top 3.
**Expected outcome**: The AI generates 5 detailed thumbnail descriptions (text overlay, visual elements, colors, emotion). It then critiques each from a "scrolling viewer" perspective, identifies strengths and weaknesses, and selects the 3 most click-worthy concepts with improvement suggestions. This runs in parallel with outline and script generation.

## 31. Thumbnail Image Generation

**What it does**: Creates 3 actual thumbnail images from the top 3 refined concepts using AI image generation.
**Expected outcome**: 3 thumbnail images are generated at 1280x720 pixels (16:9), in PNG format, with text that is readable on mobile. The images match the concept descriptions.

## 32. Thumbnail Selection

**What it does**: Lets the user choose one thumbnail from the 3 AI-generated options.
**Expected outcome**: The user sees large previews of all 3 thumbnails with text overlays visible. They click to select one. A "Regenerate" option is available if none are satisfactory.

## 33. AI Voiceover Generation

**What it does**: Converts the approved script into a spoken voiceover using AI text-to-speech.
**Expected outcome**: The user browses an AI voice library (via ElevenLabs) with preview samples, selects a voice, and the system generates an MP3 voiceover of the full script. The audio is normalized for consistent volume.

## 34. User Voiceover Upload

**What it does**: Allows users to upload their own pre-recorded voiceover instead of using AI-generated speech.
**Expected outcome**: The user uploads an audio file (MP3, WAV, M4A, or AAC, max 50MB). The system validates the file, normalizes the volume, and detects the exact duration for video timing.

## 35. Background Music Library Selection

**What it does**: Lets users choose background music from a curated library of royalty-free tracks.
**Expected outcome**: The user browses 20 curated tracks organized by mood (Upbeat, Calm, Dramatic, Inspiring, Neutral). Each track has a 30-second preview. The selected track is mixed at -15dB to -20dB below the voiceover volume.

## 36. User Music Upload

**What it does**: Allows users to upload their own background music track.
**Expected outcome**: The user uploads an MP3 file (max 20MB). It is mixed into the video at the appropriate volume level beneath the voiceover.

## 37. No Music Option

**What it does**: Lets users opt out of background music entirely.
**Expected outcome**: The user selects "No music" and the final video contains only the voiceover without any background music.

## 38. Stock Footage Matching

**What it does**: Automatically finds and matches stock footage clips to each segment of the script.
**Expected outcome**: The system splits the script into 3-5 thematic segments, extracts search keywords, searches free stock footage APIs (Pexels, Pixabay), and selects clips of 3-6 seconds each to fill each segment's duration. Broader search terms are used as fallback if specific searches return no results.

## 39. Storyboard Preview

**What it does**: Shows a visual preview of the planned video before the final render, displaying script segments alongside matched footage thumbnails.
**Expected outcome**: The user sees a timeline view showing which stock footage clips will appear with which parts of the script. They can hover to see footage thumbnails. The user must approve the storyboard before rendering begins.

## 40. Video Rendering

**What it does**: Assembles the voiceover, stock footage clips, background music, and other assets into a final MP4 video using a cloud rendering service.
**Expected outcome**: The system builds a video timeline, submits it for cloud rendering, and produces a 1080p MP4 video (16:9 aspect ratio, H.264 codec). Rendering takes 2-5 minutes. The maximum video duration is 20 minutes.

## 41. Render Progress Tracking

**What it does**: Shows the user real-time progress of the video rendering process.
**Expected outcome**: The user sees a progress indicator showing which steps are complete, which are in progress, and which are pending. An estimated time remaining is displayed.

## 42. Render Failure Handling

**What it does**: Handles cases where the video rendering fails.
**Expected outcome**: If rendering fails, the system automatically retries once. If it fails again, the user is alerted and their credit is refunded.

## 43. Video File Download

**What it does**: Lets users download the completed video as an MP4 file.
**Expected outcome**: A direct download link is provided for the rendered MP4 video.

## 44. Thumbnail File Download

**What it does**: Lets users download the selected thumbnail as a PNG file.
**Expected outcome**: A direct download link is provided for the selected 1280x720 PNG thumbnail.

## 45. Script File Download

**What it does**: Lets users download the final script as a Markdown file.
**Expected outcome**: A direct download link is provided for the script with delivery cues included.

## 46. Full Package ZIP Download

**What it does**: Lets users download all output files in a single ZIP archive.
**Expected outcome**: The user clicks "Download All" and receives a ZIP file containing the video (MP4), thumbnail (PNG), script (Markdown), and metadata (text file).

## 47. Metadata Copy Buttons

**What it does**: Provides one-click copy buttons for the video title, description, and tags.
**Expected outcome**: The user clicks a copy button next to the title, description, or tags field, and the content is copied to their clipboard for easy pasting into YouTube.

## 48. In-Browser Video Preview

**What it does**: Lets users watch the completed video directly in the browser before downloading.
**Expected outcome**: An embedded video player shows the rendered video. The user can play, pause, and scrub through it.

## 49. Cost Breakdown Display

**What it does**: Shows the user a transparent breakdown of the actual production costs for each video.
**Expected outcome**: After a video is completed, the user sees a detailed cost breakdown showing the cost of content generation, thumbnails, voiceover, and video rendering, plus their total price (1 credit).

## 50. Video Library List

**What it does**: Displays all of the user's videos within the current project.
**Expected outcome**: The user sees a list of their videos showing the title, duration, creation date, thumbnail preview (for completed videos), and status badge. Videos can be sorted by date, title, or status. The list is paginated at 20 videos per page.

## 51. Video Status Badges

**What it does**: Shows the current status of each video in the library.
**Expected outcome**: Each video displays one of four status badges: Draft (creation in progress), Processing (rendering), Completed (ready to download), or Failed (something went wrong).

## 52. Video Quick Actions

**What it does**: Provides quick action buttons for each video in the library.
**Expected outcome**: Each video card has View, Download, and Delete action buttons for fast access.

## 53. File Retention Policy

**What it does**: Manages how long generated video files are stored before automatic deletion.
**Expected outcome**: Free tier files are retained for 30 days; paid tier files for 90 days. Users receive an email warning 7 days before their files are scheduled for deletion. After deletion, project metadata is preserved but files must be re-generated at cost.

## 54. Credit Balance Display

**What it does**: Shows the user's current credit balance prominently in the application header.
**Expected outcome**: The user always sees how many credits they have remaining in the navigation bar.

## 55. Free Credit for New Users

**What it does**: Gives every new user 1 free credit upon registration.
**Expected outcome**: When a user creates an account, they start with 1 credit, allowing them to generate one free trial video.

## 56. Credit Deduction on Completion

**What it does**: Deducts 1 credit from the user's balance when a video is successfully completed.
**Expected outcome**: Upon successful video rendering, 1 credit is automatically deducted. The balance updates immediately.

## 57. Credit Refund on Failure

**What it does**: Automatically refunds the credit if video generation fails.
**Expected outcome**: If the rendering process fails and cannot be recovered, the user's credit is restored to their balance.

## 58. Low Balance Warning

**What it does**: Alerts users when their credit balance is running low.
**Expected outcome**: When the user has only 1 credit remaining, they see a warning notification encouraging them to purchase more.

## 59. Insufficient Credits Block

**What it does**: Prevents video generation when the user has no credits.
**Expected outcome**: If the user tries to create a video with 0 credits, the system blocks the action and directs them to purchase credits.

## 60. Credit Package Purchase

**What it does**: Offers credit packages at different price points with volume discounts.
**Expected outcome**: The user can purchase credits in three packages: 5 credits for $20 (standard), 15 credits for $50 (17% discount), or 35 credits for $100 (29% discount).

## 61. Stripe Checkout

**What it does**: Processes credit purchases securely through Stripe's hosted checkout page.
**Expected outcome**: The user selects a credit package and is redirected to Stripe's checkout page. After successful payment, credits are added to their account and they receive a receipt email.

## 62. Payment Webhook Processing

**What it does**: Handles payment event notifications from Stripe to update user accounts.
**Expected outcome**: When Stripe confirms a successful payment, the system automatically adds credits. Failed payments are logged and the user is notified. Refunds deduct the corresponding credits.

## 63. Transaction History

**What it does**: Shows a history of all credit purchases, deductions, and refunds.
**Expected outcome**: The user sees a chronological list of all their transactions including purchases, video credit usage, refunds, and bonuses.

## 64. Free Tier Video Limitations

**What it does**: Restricts the free trial video to demonstrate the product while encouraging upgrade.
**Expected outcome**: The free video is limited to 1 minute maximum duration, has a maximum of 1 script iteration, and includes a watermark. The watermark reads "Made with Itervel" in the bottom right at 50% opacity.

## 65. Landing Page

**What it does**: Presents the product to potential users with a compelling value proposition and call to action.
**Expected outcome**: Visitors see a hero section ("Create YouTube Videos That Actually Perform"), feature highlights, social proof, and a clear call to action ("Create Your First Video Free") that leads to registration.

## 66. Welcome Tour

**What it does**: Introduces new users to the platform through a brief guided tour after their first login.
**Expected outcome**: The user sees 4 slides explaining the workflow: (1) enter topic and references, (2) AI generates and refines content, (3) review and customize at each step, (4) download complete video package.

## 67. Guided First Video Experience

**What it does**: Walks new users through creating their first video with helpful prompts and tooltips.
**Expected outcome**: The first video creation experience includes a pre-filled example topic, tooltips at each step explaining what to do, and guidance throughout the wizard. The free trial video is limited to 1 minute.

## 68. Conversion Prompt

**What it does**: Encourages users to purchase credits after completing their free trial video.
**Expected outcome**: After the user downloads their free video, they see a prompt asking "Ready for full-length videos?" along with pricing information and a button to purchase credits.

## 69. Video Creation Progress Indicator

**What it does**: Shows the overall progress of the video creation workflow across all steps.
**Expected outcome**: The user sees a visual indicator showing which steps are complete (checkmark), which is currently active (spinner with progress), and which are upcoming. Estimated time remaining is displayed.

## 70. Step-by-Step Wizard Navigation

**What it does**: Guides users through the video creation process in a clear, sequential wizard interface.
**Expected outcome**: The user moves through 9 steps: Input, Title Selection, Outline Review, Script Review, Voiceover Selection, Thumbnail Selection, Music Selection, Final Review, and Download. Each step is clearly labeled and the user can see their position in the workflow.

## 71. Final Review Screen

**What it does**: Shows a summary of all selections before the user commits to generating the video.
**Expected outcome**: The user sees the selected title, script preview (first 200 words), selected thumbnail, voice selection, music selection, estimated cost in credits, and a storyboard preview. They click "Generate Video" to start rendering.

## 72. Responsive Layout

**What it does**: Adapts the application layout for desktop, tablet, and mobile screens.
**Expected outcome**: On desktop (1200px+), all features are available with side-by-side layouts. On tablet (768-1199px), layouts stack vertically with full functionality. On mobile (under 768px), users can review outputs, download files, and monitor progress. Full video creation is optimized for desktop.

## 73. Terms of Service Acceptance

**What it does**: Requires users to accept the Terms of Service before creating an account.
**Expected outcome**: A checkbox must be checked before the registration form can be submitted. If unchecked, the user sees an error message.

## 74. GDPR Data Export

**What it does**: Allows users to export all of their personal data stored in the system.
**Expected outcome**: The user can request an export of their data, receiving a downloadable file containing all their account information, project data, and video metadata.

## 75. Cookie Consent

**What it does**: Displays a cookie consent banner for EU users.
**Expected outcome**: Visitors from the EU see a banner informing them about cookie usage and can accept or configure their preferences.

## 76. Admin Dashboard

**What it does**: Provides administrators with an overview of platform activity including user counts, video generation stats, revenue, costs, and error tracking.
**Expected outcome**: Admins see daily, weekly, and monthly metrics for new users, videos created, revenue, and API costs. A conversion funnel visualization shows the user journey from registration through repeat purchases. Top errors from the last 7 days are listed.

## 77. Video Deletion

**What it does**: Allows users to delete a video from their library.
**Expected outcome**: The user can delete a video, which removes the video and all associated files permanently.

## 78. Cost Preview Before Generation

**What it does**: Shows the user an estimated cost (in credits) for their video before they start the generation process.
**Expected outcome**: Based on the selected AI models, number of iterations, and video length, the user sees how many credits the video will cost before committing.

## 79. Parallel Thumbnail and Script Processing

**What it does**: Generates thumbnails at the same time as the outline and script, rather than sequentially.
**Expected outcome**: Thumbnail concept generation begins immediately after title selection and runs in parallel with outline and script generation, reducing total wait time.

## 80. API Cost Tracking

**What it does**: Tracks the actual cost of every external API call made during video generation.
**Expected outcome**: Every AI text generation, image generation, text-to-speech, stock footage search, and video rendering call is logged with its cost. This data feeds into the cost breakdown display and admin dashboard.

## 81. Voice Preview

**What it does**: Lets users listen to a short sample of each AI voice before selecting one.
**Expected outcome**: Each voice in the library has a 10-second audio sample that the user can play to hear what it sounds like.

## 82. Project-Level Settings Inheritance

**What it does**: Applies project-level settings (target audience, tone, speaking pace, etc.) as defaults for every video created in that project.
**Expected outcome**: When a user starts a new video in a project, the project's settings are automatically pre-filled, saving time on repeated configuration.

## 83. Set Default Project

**What it does**: Lets users designate one project as their default, which is pre-selected when creating new videos.
**Expected outcome**: The user sets a project as default. When starting a new video, this project is automatically selected in the project dropdown.

## 84. Video File Regeneration After Expiration

**What it does**: Allows users to regenerate video files that have been automatically deleted due to the retention policy.
**Expected outcome**: If a user's video files have expired and been deleted, they can re-trigger the generation process at the cost of 1 credit. The project metadata (title, script, settings) is preserved, so the regeneration uses the same content.

## 85. Admin Refund Management

**What it does**: Lets administrators issue refunds to users through Stripe.
**Expected outcome**: An admin can look up a user's transaction, issue a refund via Stripe, and the corresponding credits are deducted from the user's balance. The refund is logged in the transaction history.

## 86. Graceful URL Processing Failure

**What it does**: Handles failures when extracting content from user-provided reference URLs without blocking the video creation workflow.
**Expected outcome**: If a reference URL cannot be processed (timeout, inaccessible, unsupported format), the user sees a warning message for that specific URL but can continue creating their video with the remaining references or without any references.

## 87. AI API Failure Retry and Recovery

**What it does**: Automatically retries failed AI API calls during content generation and alerts the user if recovery is not possible.
**Expected outcome**: If an AI API call fails (text generation, image generation, etc.), the system retries the request once. If it fails again, the user is notified of the specific issue and given the option to retry manually or adjust their settings.

## 88. Post-Video Quality Rating Survey

**What it does**: Asks users to rate the quality of their generated video after completion.
**Expected outcome**: After downloading or previewing a completed video, the user is prompted to rate the video quality on a 1-5 scale. This feedback is collected and displayed in the admin dashboard.

## 89. Shotstack Completion Webhook Processing

**What it does**: Receives and processes completion notifications from the video rendering service when a video finishes rendering.
**Expected outcome**: When the rendering service signals that a video is done (or has failed), the system verifies the webhook signature, downloads the rendered video to storage, updates the video status, and optionally notifies the user by email.

## 90. Stripe Webhook Signature Verification

**What it does**: Verifies the authenticity of incoming Stripe webhook events to prevent tampering.
**Expected outcome**: Every incoming Stripe webhook request is verified using Stripe's signature verification before being processed. Invalid or tampered requests are rejected and logged.

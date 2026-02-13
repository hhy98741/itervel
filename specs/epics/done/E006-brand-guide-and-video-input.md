# Features

Brand guide management and video creation input including topic entry, reference URLs, and URL processing.

**Depends on**: E004-project-management.md

## 1. Brand Guide Upload

**What it does**: Lets users upload a brand guide document (.txt or .md) to customize the AI's voice and tone for a specific project.
**Expected outcome**: The user uploads a brand guide file (max 100KB). The system extracts the content and uses it as context when generating scripts for that project. Users can also override the brand guide for individual videos.

## 2. Brand Guide Template Download

**What it does**: Provides a sample brand guide template that users can download and fill in.
**Expected outcome**: The user downloads a pre-formatted template showing how to describe their channel's voice, tone, target audience, content pillars, and preferred language.

## 3. Topic Input

**What it does**: Lets the user enter the main topic or concept for their video.
**Expected outcome**: The user types a topic description (100-500 characters) into a text field. This topic drives all subsequent content generation.

## 4. Reference URL Input

**What it does**: Allows users to provide up to 3 reference URLs (articles or YouTube videos) to inform the AI's research.
**Expected outcome**: The user pastes up to 3 URLs. The system validates the URL format and checks accessibility.

## 5. Reference URL Processing

**What it does**: Automatically extracts useful content from the reference URLs the user provided.
**Expected outcome**: For articles, the system scrapes the text content. For YouTube videos, it extracts the transcript. Key concepts are pulled out as bullet points. Each URL has a 30-second processing timeout. If extraction fails, the user is warned but can continue without that reference. Extracted content is cached for reuse.

## 6. Graceful URL Processing Failure

**What it does**: Handles failures when extracting content from user-provided reference URLs without blocking the video creation workflow.
**Expected outcome**: If a reference URL cannot be processed (timeout, inaccessible, unsupported format), the user sees a warning message for that specific URL but can continue creating their video with the remaining references or without any references.

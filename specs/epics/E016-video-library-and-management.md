# Features

Video library listing, status badges, quick actions, file retention policy, video deletion, and file regeneration.

**Depends on**: E014-video-assembly-and-rendering.md

## 1. Video Library List

**What it does**: Displays all of the user's videos within the current project.
**Expected outcome**: The user sees a list of their videos showing the title, duration, creation date, thumbnail preview (for completed videos), and status badge. Videos can be sorted by date, title, or status. The list is paginated at 20 videos per page.

## 2. Video Status Badges

**What it does**: Shows the current status of each video in the library.
**Expected outcome**: Each video displays one of four status badges: Draft (creation in progress), Processing (rendering), Completed (ready to download), or Failed (something went wrong).

## 3. Video Quick Actions

**What it does**: Provides quick action buttons for each video in the library.
**Expected outcome**: Each video card has View, Download, and Delete action buttons for fast access.

## 4. File Retention Policy

**What it does**: Manages how long generated video files are stored before automatic deletion.
**Expected outcome**: Free tier files are retained for 30 days; paid tier files for 90 days. Users receive an email warning 7 days before their files are scheduled for deletion. After deletion, project metadata is preserved but files must be re-generated at cost.

## 5. Video Deletion

**What it does**: Allows users to delete a video from their library.
**Expected outcome**: The user can delete a video, which removes the video and all associated files permanently.

## 6. Video File Regeneration After Expiration

**What it does**: Allows users to regenerate video files that have been automatically deleted due to the retention policy.
**Expected outcome**: If a user's video files have expired and been deleted, they can re-trigger the generation process at the cost of 1 credit. The project metadata (title, script, settings) is preserved, so the regeneration uses the same content.

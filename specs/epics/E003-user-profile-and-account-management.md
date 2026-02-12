# Features

User profile viewing, password management, preferences, notifications, account deletion, and GDPR data export.

**Depends on**: E002-user-authentication.md

## 1. User Profile Viewing

**What it does**: Lets users view their account information such as email, join date, and credit balance.
**Expected outcome**: The user sees a profile page displaying their account details at a glance.

## 2. Password Change

**What it does**: Allows logged-in users to change their current password.
**Expected outcome**: The user enters their current password for verification, then sets a new password. The password is updated immediately.

## 3. User Default Preferences

**What it does**: Lets users set default preferences for video creation, such as default video length, speaking pace, and number of script iterations.
**Expected outcome**: The user configures their preferred defaults (e.g., 12-minute video, 165 words per minute, 2 script iterations) and these are automatically applied when creating new videos.

## 4. Notification Settings

**What it does**: Allows users to toggle email notifications on or off.
**Expected outcome**: The user can enable or disable email notifications from a settings page, and the system respects their preference.

## 5. Account Deletion

**What it does**: Allows users to permanently delete their account and all associated data.
**Expected outcome**: The user requests account deletion. All their data, videos, projects, and files are removed. This supports GDPR compliance.

## 6. GDPR Data Export

**What it does**: Allows users to export all of their personal data stored in the system.
**Expected outcome**: The user can request an export of their data, receiving a downloadable file containing all their account information, project data, and video metadata.

# Features

Project creation, listing, editing, deletion, settings inheritance, and default project management.

**Depends on**: E002-user-authentication.md

## 1. Create Project

**What it does**: Lets users create separate projects to organize videos by YouTube channel or content category.
**Expected outcome**: The user creates a named project with optional settings like target audience, tone, and speaking pace. Free users can have 1 project; paid users can have unlimited projects.

## 2. Auto-Create Default Project

**What it does**: Automatically creates a starter project for new users when they first log in.
**Expected outcome**: Upon first login, a project named "My Channel" is automatically created and set as the default project, so the user can start creating videos immediately.

## 3. List and Switch Projects

**What it does**: Shows all of a user's projects and lets them switch between them.
**Expected outcome**: The user sees all their projects in a dropdown or tab interface and can switch the active project. Each project shows its video count.

## 4. Edit Project Settings

**What it does**: Lets users update a project's name and settings (target audience, tone, thumbnail style, music preference, etc.).
**Expected outcome**: The user modifies project details and the changes are saved. Future videos in this project use the updated settings.

## 5. Delete Project

**What it does**: Allows users to delete a project they no longer need.
**Expected outcome**: The user is warned that deleting the project will also delete all associated videos. They must type the project name to confirm. Once confirmed, the project and its videos are permanently removed.

## 6. Project-Level Settings Inheritance

**What it does**: Applies project-level settings (target audience, tone, speaking pace, etc.) as defaults for every video created in that project.
**Expected outcome**: When a user starts a new video in a project, the project's settings are automatically pre-filled, saving time on repeated configuration.

## 7. Set Default Project

**What it does**: Lets users designate one project as their default, which is pre-selected when creating new videos.
**Expected outcome**: The user sets a project as default. When starting a new video, this project is automatically selected in the project dropdown.

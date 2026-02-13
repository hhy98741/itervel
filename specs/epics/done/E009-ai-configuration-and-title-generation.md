# Features

AI model selection, script iteration configuration, title generation, title selection, and cost preview.

**Depends on**: E006-brand-guide-and-video-input.md

## 1. AI Model Selection

**What it does**: Lets users choose which AI model to use for each step of the video creation process (title, outline, script, critique, thumbnails).
**Expected outcome**: By default, "Use recommended settings" is checked (all Sonnet). Users can uncheck this to customize the AI model for each step via dropdowns. A cost preview updates as they change selections. Their last configuration is remembered.

## 2. Script Iteration Configuration

**What it does**: Allows users to set how many AI critique-and-refinement rounds the script goes through (0-5 rounds).
**Expected outcome**: The user selects the number of iterations via a slider or dropdown. A tooltip explains what iterations do. The cost impact is shown. Free tier users are limited to 1 iteration. A warning appears at 3+ iterations about increased cost.

## 3. Title Generation

**What it does**: Uses AI to generate 5 title options for the video, each with a logline, framework used, character count, and click-potential ranking.
**Expected outcome**: The user sees 5 title options ranked 1-5 by predicted click-through rate. Each title includes the title text, a one-sentence logline, the framework used (e.g., "Contrarian Angle"), character count, and reasoning for the ranking.

## 4. Title Selection

**What it does**: Lets the user pick one title from the 5 AI-generated options.
**Expected outcome**: The user selects a title via radio button and clicks "Continue." The selected title is used for all subsequent generation steps.

## 5. Cost Preview Before Generation

**What it does**: Shows the user an estimated cost (in credits) for their video before they start the generation process.
**Expected outcome**: Based on the selected AI models, number of iterations, and video length, the user sees how many credits the video will cost before committing.

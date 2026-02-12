# Features

AI voiceover generation, user voiceover upload, background music selection and upload, no-music option, and voice preview.

**Depends on**: E011-outline-and-script-generation.md

## 1. AI Voiceover Generation

**What it does**: Converts the approved script into a spoken voiceover using AI text-to-speech.
**Expected outcome**: The user browses an AI voice library (via ElevenLabs) with preview samples, selects a voice, and the system generates an MP3 voiceover of the full script. The audio is normalized for consistent volume.

## 2. User Voiceover Upload

**What it does**: Allows users to upload their own pre-recorded voiceover instead of using AI-generated speech.
**Expected outcome**: The user uploads an audio file (MP3, WAV, M4A, or AAC, max 50MB). The system validates the file, normalizes the volume, and detects the exact duration for video timing.

## 3. Background Music Library Selection

**What it does**: Lets users choose background music from a curated library of royalty-free tracks.
**Expected outcome**: The user browses 20 curated tracks organized by mood (Upbeat, Calm, Dramatic, Inspiring, Neutral). Each track has a 30-second preview. The selected track is mixed at -15dB to -20dB below the voiceover volume.

## 4. User Music Upload

**What it does**: Allows users to upload their own background music track.
**Expected outcome**: The user uploads an MP3 file (max 20MB). It is mixed into the video at the appropriate volume level beneath the voiceover.

## 5. No Music Option

**What it does**: Lets users opt out of background music entirely.
**Expected outcome**: The user selects "No music" and the final video contains only the voiceover without any background music.

## 6. Voice Preview

**What it does**: Lets users listen to a short sample of each AI voice before selecting one.
**Expected outcome**: Each voice in the library has a 10-second audio sample that the user can play to hear what it sounds like.

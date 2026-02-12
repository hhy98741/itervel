# Features

Core user authentication features including registration, login, password management, and terms acceptance.

**Depends on**: None

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

## 5. Terms of Service Acceptance

**What it does**: Requires users to accept the Terms of Service before creating an account.
**Expected outcome**: A checkbox must be checked before the registration form can be submitted. If unchecked, the user sees an error message.

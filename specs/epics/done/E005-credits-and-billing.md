# Features

Credit balance tracking, free credits for new users, credit deduction and refund logic, and balance warnings.

**Depends on**: E002-user-authentication.md

## 1. Credit Balance Display

**What it does**: Shows the user's current credit balance prominently in the application header.
**Expected outcome**: The user always sees how many credits they have remaining in the navigation bar.

## 2. Free Credit for New Users

**What it does**: Gives every new user 1 free credit upon registration.
**Expected outcome**: When a user creates an account, they start with 1 credit, allowing them to generate one free trial video.

## 3. Credit Deduction on Completion

**What it does**: Deducts 1 credit from the user's balance when a video is successfully completed.
**Expected outcome**: Upon successful video rendering, 1 credit is automatically deducted. The balance updates immediately.

## 4. Credit Refund on Failure

**What it does**: Automatically refunds the credit if video generation fails.
**Expected outcome**: If the rendering process fails and cannot be recovered, the user's credit is restored to their balance.

## 5. Low Balance Warning

**What it does**: Alerts users when their credit balance is running low.
**Expected outcome**: When the user has only 1 credit remaining, they see a warning notification encouraging them to purchase more.

## 6. Insufficient Credits Block

**What it does**: Prevents video generation when the user has no credits.
**Expected outcome**: If the user tries to create a video with 0 credits, the system blocks the action and directs them to purchase credits.

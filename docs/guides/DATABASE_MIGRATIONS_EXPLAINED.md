# Database Migrations in GitHub Actions

## How Schema Upgrades Happen Automatically

This document explains exactly how database schema changes are deployed to production via GitHub Actions.

---

## 🎯 Quick Answer

**Yes, GitHub Actions handles database migrations automatically.**

**Location in YAML:** Line 442-443 in the `SCRIPT_AFTER` section

```yaml
# Run migrations (with --force for production)
php artisan migrate --force
```

---

## 📋 Complete Flow: From Development to Production

### **Step 1: Claude Creates Migration (Development)**

```bash
# In Docker container during autonomous development
Claude: Creating Feature 1.1: Database Setup

# Claude runs:
php artisan make:migration create_users_table
php artisan make:migration create_projects_table
php artisan make:migration create_videos_table

# Creates files:
database/migrations/2026_01_20_000001_create_users_table.php
database/migrations/2026_01_20_000002_create_projects_table.php
database/migrations/2026_01_20_000003_create_videos_table.php

# Claude runs migrations locally:
php artisan migrate

# Claude tests:
vendor/bin/phpunit

# Claude commits:
git add database/migrations/*
git commit -m "feat: add database schema for users, projects, videos"
```

### **Step 2: You Push to GitHub**

```bash
# On your local machine
git push origin main
```

### **Step 3: GitHub Actions Deploys (Automatic)**

**The complete workflow:**

```yaml
name: Deploy to Production

on:
    push:
        branches: [main]

jobs:
    build-and-deploy:
        steps:
            # 1. Build Phase (on GitHub runners)
            - name: Checkout code
            - name: Install dependencies
            - name: Build assets

            # 2. Deploy Phase (on A2 server via SSH)
            - name: Deploy to A2 Hosting via SSH
              env:
                  SCRIPT_AFTER: |
                      cd application

                      # Install/update dependencies
                      composer install --no-dev

                      # Cache configs
                      php artisan config:cache
                      php artisan route:cache
                      php artisan view:cache

                      # ✅ RUN MIGRATIONS (This is where schema updates happen!)
                      php artisan migrate --force

                      # Clear cache
                      php artisan cache:clear
```

### **Step 4: Migrations Run on Production Database**

```bash
# On A2 server (automatic via GitHub Actions)
cd /home/username/application

php artisan migrate --force

# Output:
# Migrating: 2026_01_20_000001_create_users_table
# Migrated:  2026_01_20_000001_create_users_table (45.23ms)
# Migrating: 2026_01_20_000002_create_projects_table
# Migrated:  2026_01_20_000002_create_projects_table (32.15ms)
# Migrating: 2026_01_20_000003_create_videos_table
# Migrated:  2026_01_20_000003_create_videos_table (28.47ms)
```

---

## 📍 Exact Location in deploy-production.yml

### **Line-by-Line Breakdown:**

```yaml
SCRIPT_AFTER: |
    cd /home/${{ secrets.A2_USERNAME }}

    # Extract new deployment
    tar -xzf deploy-temp/deploy.tar.gz -C application/

    # Run post-deployment commands
    cd application

    # Install/update Composer dependencies
    ~/bin/composer install --no-dev --optimize-autoloader

    # Clear and cache Laravel config
    php artisan config:cache        # Line 438
    php artisan route:cache         # Line 439
    php artisan view:cache          # Line 440

    # ✅✅✅ DATABASE MIGRATIONS HAPPEN HERE ✅✅✅
    php artisan migrate --force     # Line 443

    # Set permissions
    chmod -R 755 storage bootstrap/cache

    # Clear old cache
    php artisan cache:clear

    # Restart queue workers
    php artisan queue:restart
```

**Key line:** `php artisan migrate --force`

---

## 🔍 Understanding `php artisan migrate --force`

### **What It Does:**

```bash
php artisan migrate --force
```

1. **Connects to production database** (using credentials from `.env`)
2. **Checks `migrations` table** to see which migrations have already run
3. **Runs only NEW migrations** (migrations that haven't been run yet)
4. **Updates `migrations` table** to track which migrations are complete
5. **Skips migrations already run** (safe to run multiple times)

### **The `--force` Flag:**

**Why it's needed:**

- Laravel requires `--force` flag in production environments
- Prevents accidental migrations in production
- Confirms you really want to modify production database

**Without `--force`:**

```bash
php artisan migrate
# Output: ❌ ERROR: Application is in production mode!
```

**With `--force`:**

```bash
php artisan migrate --force
# Output: ✅ Migrations run successfully
```

---

## 🛡️ Safety Features

### **1. Idempotent (Safe to Run Multiple Times)**

```bash
# First deployment
php artisan migrate --force
# Runs: 2026_01_20_000001_create_users_table ✅

# Second deployment (no new migrations)
php artisan migrate --force
# Output: Nothing to migrate ✅

# Third deployment (new migration added)
php artisan migrate --force
# Runs: 2026_01_20_000004_add_thumbnail_to_videos ✅
# Skips: Previously run migrations ✅
```

### **2. Tracks What's Been Run**

**Laravel maintains a `migrations` table:**

```sql
SELECT * FROM migrations;

+----+------------------------------------------------+-------+
| id | migration                                      | batch |
+----+------------------------------------------------+-------+
|  1 | 2026_01_20_000001_create_users_table          |     1 |
|  2 | 2026_01_20_000002_create_projects_table       |     1 |
|  3 | 2026_01_20_000003_create_videos_table         |     1 |
|  4 | 2026_01_20_000004_add_thumbnail_to_videos     |     2 |
+----+------------------------------------------------+-------+
```

**This ensures:**

- Each migration runs exactly once
- No duplicate tables/columns
- Deployments are safe and predictable

### **3. Automatic Backup Before Deploy**

**In the GitHub Actions workflow:**

```yaml
SCRIPT_BEFORE: |
    # Backup current deployment (includes database state)
    cd /home/${{ secrets.A2_USERNAME }}
    if [ -d "application" ]; then
      tar -czf backups/backup-$(date +%Y%m%d-%H%M%S).tar.gz application/
    fi
```

**Plus your daily database backups:**

```bash
# Cron runs daily at 2 AM
0 2 * * * ~/bin/backup-db.sh
```

**If migration fails:**

1. Check GitHub Actions logs
2. Restore from automatic backup if needed
3. Fix migration
4. Push again

---

## 🔄 Complete Deployment Timeline

```
Claude builds Feature 2.1 (local)
  ↓
Creates migration: add_api_models_to_videos_table
  ↓
Runs migration locally (tests it works)
  ↓
Commits migration file to git
  ↓
You push to GitHub
  ↓
GitHub Actions starts
  ↓
Builds application
  ↓
Runs tests (including migration tests)
  ↓
Deploys to A2 server via SSH
  ↓
Backs up current deployment
  ↓
Extracts new code
  ↓
Installs dependencies
  ↓
✅ RUNS MIGRATIONS (php artisan migrate --force)
  ↓
Caches configs
  ↓
Clears old cache
  ↓
Health check
  ↓
Posts success/failure to GitHub
  ↓
Production database schema is updated!
```

**Total time:** 2-3 minutes

**Your involvement:** Zero (just push to git)

---

## 📝 Example: Adding a New Column

### **Scenario:** Add `thumbnail_url` column to `videos` table

**Development (Claude does this):**

```bash
# Create migration
php artisan make:migration add_thumbnail_to_videos_table

# Edit migration file:
database/migrations/2026_01_20_143022_add_thumbnail_to_videos_table.php
```

```php
public function up()
{
    Schema::table('videos', function (Blueprint $table) {
        $table->string('thumbnail_url')->nullable()->after('title');
    });
}

public function down()
{
    Schema::table('videos', function (Blueprint $table) {
        $table->dropColumn('thumbnail_url');
    });
}
```

```bash
# Run locally
php artisan migrate

# Test
vendor/bin/phpunit

# Commit
git add database/migrations/*
git commit -m "feat: add thumbnail support to videos"
```

**Deployment (GitHub Actions does this):**

```bash
# You push
git push origin main

# GitHub Actions runs on A2 server:
php artisan migrate --force

# Output on A2 server:
Migrating: 2026_01_20_143022_add_thumbnail_to_videos_table
Migrated:  2026_01_20_143022_add_thumbnail_to_videos_table (23.45ms)

# Production database now has thumbnail_url column!
```

**Result:**

- Development database has new column ✅
- Production database has new column ✅
- No manual intervention needed ✅

---

## 🚨 What If Migration Fails?

### **GitHub Actions will:**

1. **Stop deployment** (won't complete if migration fails)
2. **Keep old code running** (new code not activated)
3. **Post error to GitHub** (shows what went wrong)
4. **Send notification** (you see the failure)

### **You can:**

1. **Check GitHub Actions logs:**

    ```
    Go to: GitHub repo > Actions > Latest workflow run
    See: Detailed error message from migration
    ```

2. **Fix the migration:**

    ```bash
    # Edit the migration file
    # Fix the SQL error
    git add .
    git commit -m "fix: correct migration syntax"
    git push origin main
    ```

3. **Or rollback if needed:**

    ```bash
    # SSH to server
    ssh username@yourdomain.com
    cd application

    # Rollback last migration
    php artisan migrate:rollback --step=1

    # Or restore from backup
    cd ~/backups
    tar -xzf backup-20260120-140500.tar.gz
    ```

### **Common Migration Failures:**

```yaml
❌ "Table 'videos' already exists"
→ Migration already ran, or duplicate migration

❌ "Column 'thumbnail_url' doesn't exist"
→ Typo in column name, or wrong table

❌ "Syntax error in SQL"
→ Invalid SQL in migration file

❌ "Foreign key constraint fails"
→ Referenced table doesn't exist yet
```

**Fix and redeploy:**

```bash
git add .
git commit -m "fix: migration error"
git push origin main
# GitHub Actions retries deployment
```

---

## ✅ Best Practices

### **1. Always Test Migrations Locally First**

Claude does this automatically:

```bash
php artisan migrate
vendor/bin/phpunit
# Only commits if tests pass
```

### **2. Write Reversible Migrations**

```php
public function up()
{
    // Add column
}

public function down()
{
    // Remove column (allows rollback)
}
```

### **3. Don't Delete Data in Migrations**

```php
// ❌ BAD: Drops data permanently
Schema::dropColumn('old_column');

// ✅ GOOD: Keeps data, marks as unused
Schema::table('videos', function ($table) {
    $table->string('old_column')->nullable()->comment('Deprecated - use new_column');
});
```

### **4. Use Transactions When Possible**

Laravel handles this automatically for most migrations.

---

## 🎯 Summary

**Q: Does GitHub Actions handle database migrations?**
**A:** Yes! ✅

**Q: Where in the YAML?**
**A:** Line 443: `php artisan migrate --force` in the `SCRIPT_AFTER` section

**Q: What does it do?**
**A:**

1. Connects to production database
2. Runs any NEW migrations
3. Skips already-run migrations
4. Updates schema automatically

**Q: Is it safe?**
**A:** Yes!

- Runs only new migrations
- Backs up before deploying
- Tracks what's been run
- Fails safely if error occurs

**Q: Do I need to do anything?**
**A:** No!

- Claude creates migrations
- You push to git
- GitHub Actions runs migrations
- Schema updates automatically

---

## 📋 Quick Reference

**GitHub Actions workflow location:**

```
.github/workflows/deploy-production.yml
Line 443: php artisan migrate --force
```

**What happens on every deployment:**

```yaml
1. Build code
2. Run tests
3. Deploy to server
4. Backup current version
5. Extract new code
6. Install dependencies
7. ✅ Run migrations (schema updates)
8. Cache configs
9. Clear cache
10. Health check
```

**Your workflow:**

```bash
# Claude creates migrations locally
# You push to git
git push origin main

# GitHub Actions deploys + runs migrations
# 2-3 minutes later: Schema updated in production!
```

**Zero manual database work required!** 🚀

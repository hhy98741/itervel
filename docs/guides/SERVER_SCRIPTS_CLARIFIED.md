# Server Management Scripts - Clarified

## What You Actually Need vs Optional

This guide clarifies which server scripts are essential and which are optional backup methods.

---

## 🎯 Quick Summary

| Script                     | Purpose                     | Needed?     | When Used                        |
| -------------------------- | --------------------------- | ----------- | -------------------------------- |
| **GitHub Actions CI/CD**   | Automated deployment        | ✅ **YES**  | Every code push (primary method) |
| **Database Backup Script** | Daily DB backups            | ✅ **YES**  | Automatically daily via cron     |
| **Manual Deploy Script**   | Emergency manual deployment | ⚠️ Optional | Only if GitHub Actions fails     |

---

## ✅ ESSENTIAL: Automated Deployment (GitHub Actions)

### **What It Is:**

A GitHub Actions workflow that automatically deploys your code whenever you push to the main branch.

### **How It Works:**

```
You: git push origin main

GitHub Actions:
1. Builds the app
2. Runs tests
3. Deploys to A2 server
4. Runs migrations
5. Clears caches
6. Health check

2-3 minutes later: Live on production!
```

### **Setup:**

Already covered in DEPLOYMENT_GUIDE.md - the `.github/workflows/deploy-production.yml` file.

### **Daily Use:**

```bash
# Your workflow:
git add .
git commit -m "feat: add feature"
git push origin main

# Done! GitHub deploys automatically
# No SSH, no manual scripts, no thinking
```

### **Cost:** $0 (GitHub Actions free tier)

### **Status:** ✅ **Primary deployment method - Use this!**

---

## ✅ ESSENTIAL: Database Backup Script

### **What It Is:**

A script that backs up your database every day automatically.

### **Why You Need It:**

- ✅ Protection against data loss
- ✅ Can restore if migration goes wrong
- ✅ Can restore if database gets corrupted
- ✅ Required for production system

### **Setup (One Time):**

**Step 1: Create the script**

```bash
# SSH to server
ssh username@yourdomain.com

# Create backup directory
mkdir -p ~/backups

# Create script
nano ~/bin/backup-db.sh
```

**Paste this:**

```bash
#!/bin/bash

# Configuration
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
BACKUP_DIR=~/backups
DB_NAME=username_faceless_prod
DB_USER=username_faceless
DB_PASS=your_password

# Create backup
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/db-$TIMESTAMP.sql.gz

# Keep only last 14 days
find $BACKUP_DIR -name "db-*.sql.gz" -mtime +14 -delete

echo "✅ Database backed up to: db-$TIMESTAMP.sql.gz"
```

**Step 2: Make it executable**

```bash
chmod +x ~/bin/backup-db.sh

# Test it works
~/bin/backup-db.sh

# Should see: ✅ Database backed up to: db-20260120-140522.sql.gz
```

**Step 3: Set up automatic daily backup**

```bash
# Edit cron schedule
crontab -e

# Add this line (runs at 2 AM daily):
0 2 * * * ~/bin/backup-db.sh >> ~/logs/backup.log 2>&1

# Save and exit
```

### **After Setup:**

- ✅ Runs automatically every day
- ✅ You never think about it again
- ✅ Keeps last 14 days of backups
- ✅ Older backups auto-delete

### **How to Restore (If Needed):**

```bash
# SSH to server
ssh username@yourdomain.com

# List available backups
ls -lh ~/backups/

# Restore from backup
cd ~/backups
gunzip < db-20260120-140522.sql.gz | mysql -u username_faceless -p itervel_prod

# Done!
```

### **Cost:** $0

### **Status:** ✅ **Essential - Set up during initial server config**

---

## ⚠️ OPTIONAL: Manual Deployment Script

### **What It Is:**

A script you run manually on the server to deploy code when GitHub Actions isn't available.

### **Why It Exists:**

- Emergency backup if GitHub Actions is down
- Manual override if CI/CD is broken
- For people who don't want to use GitHub Actions

### **When You'd Use It:**

**Scenario 1: GitHub is down**

```
You: Need to deploy critical hotfix
You: GitHub.com is down (rare but happens)
You: SSH to server and run manual script
```

**Scenario 2: CI/CD is broken**

```
You: GitHub Actions workflow has a bug
You: Don't have time to fix it right now
You: Need to deploy anyway
You: SSH to server and run manual script
```

**How often this happens:** Maybe 1-2 times per year, if ever.

### **The Script:**

**Create: `~/bin/deploy.sh`**

```bash
#!/bin/bash

cd ~/application

echo "🚀 Starting manual deployment..."

# Maintenance mode
php artisan down --message="Deploying update" --retry=60

# Pull latest code
git pull origin main

# Update dependencies
composer install --no-dev --optimize-autoloader
npm ci
npm run build

# Run migrations
php artisan migrate --force

# Optimize Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# Back online
php artisan up

echo "✅ Deployment complete!"
```

**Make executable:**

```bash
chmod +x ~/bin/deploy.sh
```

**Usage (emergency only):**

```bash
ssh username@yourdomain.com
~/bin/deploy.sh
```

### **Do You Need This?**

**For MVP:** ❌ No, skip it
**Reason:** GitHub Actions handles everything
**When to add it:** Later if you ever need manual deployment

**My recommendation:** Skip this script initially. Add it only if you find yourself needing manual deployment.

---

## 🔄 Typical Deployment Workflow

### **99% of the time (Normal Development):**

```bash
# Local development
docker compose exec app bash
claude-code
# [Claude builds features]

# Commit and push
git add .
git commit -m "feat: add video generation"
git push origin main

# GitHub Actions deploys automatically
# Wait 2-3 minutes
# Check: https://yourdomain.com
# New features live!

# You never SSH to server
# You never run scripts manually
```

### **1% of the time (Emergency):**

```bash
# GitHub is down or CI/CD is broken
# Need to deploy RIGHT NOW

ssh username@yourdomain.com
~/bin/deploy.sh

# Done in 2-3 minutes
```

---

## 📋 Initial Server Setup Checklist

When setting up A2 hosting for the first time:

```bash
# 1. Configure server (directories, permissions, etc.)
# 2. Setup database
# 3. Clone repository
# 4. Configure .env file
# 5. ✅ Setup database backup script (essential)
# 6. ✅ Setup GitHub Actions workflow (essential)
# 7. ⚠️ Optional: Create manual deploy script (only if you want it)
```

---

## 💡 Real-World Examples

### **Example 1: Normal Day**

```
Monday morning:
- You push 3 commits to main branch
- GitHub Actions deploys all 3 automatically
- You never SSH to server
- Scripts used: None (GitHub Actions only)
```

### **Example 2: Database Backup**

```
Every day at 2 AM:
- Backup script runs automatically (cron)
- Creates db-20260120-020001.sql.gz
- Deletes backups older than 14 days
- Logs to ~/logs/backup.log
- You're asleep, don't even know it happened
```

### **Example 3: Emergency Deployment**

```
Friday evening:
- Critical bug found in production
- GitHub.com is down (site-wide outage)
- You fix the bug locally
- SSH to server: ssh username@yourdomain.com
- Run: ~/bin/deploy.sh
- Bug fixed in 5 minutes
- GitHub Actions resumes Monday when GitHub is back
```

---

## 🎯 Recommendation Summary

### **For Your MVP Launch:**

**Setup these:**

1. ✅ GitHub Actions CI/CD workflow (primary deployment)
2. ✅ Database backup script (essential protection)

**Skip these:**

1. ❌ Manual deployment script (add later if needed)

**Why:**

- GitHub Actions handles 99% of deployments
- Database backups are essential for any production system
- Manual deployment script is rarely needed
- Simpler setup = fewer things to maintain

### **After MVP Validates:**

Consider adding:

- Manual deployment script (if you ever find yourself needing it)
- Staging environment deployment scripts
- More sophisticated backup strategy (off-site backups)

---

## ✅ Updated Deployment Guide Section

The deployment guide should be read as:

**"Automated Deployment with GitHub Actions"** → ✅ Primary method, use this

**"Server Management Scripts"** → Reference section, use only when needed:

- Database backup: ✅ Essential, set up once
- Manual deploy: ⚠️ Optional, emergency backup only

---

## 🎯 Bottom Line

**Question:** "What are the server scripts for?"

**Answer:**

- **Database backup script:** Essential. Runs automatically daily. Set it up once and forget about it.
- **Manual deploy script:** Optional backup method. Only use if GitHub Actions fails. Skip for MVP.

**Question:** "Isn't deployment done from GitHub Actions?"

**Answer:** Yes! 99% of deployments use GitHub Actions. The manual script is just an emergency backup option for the rare times when GitHub Actions isn't available.

**Your workflow:** Just push to git. GitHub Actions handles deployment. Scripts run in the background for backups. You rarely SSH to the server.

---

**Keep it simple for MVP: GitHub Actions for deployment + automated backups. That's it!** 🚀

# Deployment Guide for A2 Shared Hosting

## Laravel + React Application Deployment

This guide covers deploying your faceless video platform from development to production on A2 shared hosting.

---

## 🎯 Deployment Strategy Overview

### **Build Options:**

| Option                 | Description                               | Pros                   | Cons                            | Recommended? |
| ---------------------- | ----------------------------------------- | ---------------------- | ------------------------------- | ------------ |
| **1. Build on Server** | Git pull on server, run `npm build` there | Simple, direct         | Slow, consumes server resources | ❌ No        |
| **2. Build Locally**   | Build on dev machine, commit dist files   | Fast deployment        | Large git repo, messy commits   | ❌ No        |
| **3. Build in CI/CD**  | GitHub Actions builds, deploys artifact   | Clean, automated, fast | Requires setup                  | ✅ **YES**   |

**We'll use Option 3: GitHub Actions CI/CD**

---

## 🏗️ Architecture

### **Environments:**

```
Development (Local Docker)
    ↓ [git push]
GitHub Repository
    ↓ [GitHub Actions trigger]
Build & Test
    ↓ [Deploy via SSH/FTP]
Production (A2 Hosting)
```

**Optional Staging:**

```
Development → GitHub → Build → Staging (subdomain) → Production (main domain)
```

**Do you need staging?**

- **Yes** if: You want to test on real server before going live
- **No** if: Your local tests + CI tests are sufficient for MVP

**My recommendation for MVP: Skip staging initially, add it later if needed.**

---

## 📦 What Gets Deployed

### **What Goes to Production:**

```
/public_html/                    # A2 web root
├── .htaccess                   # Laravel routing
├── index.php                   # Laravel entry point
├── api/                        # Laravel API (symlink to ../application/public)
├── assets/                     # Compiled JS/CSS from Vite
├── favicon.ico
└── robots.txt

/application/                    # Outside web root (secure)
├── app/
├── bootstrap/
├── config/
├── database/
├── resources/
├── routes/
├── storage/
├── vendor/                     # PHP dependencies
├── .env                        # Production config (NOT in git)
├── artisan
└── composer.json

/logs/                          # Application logs
/backups/                       # Database backups
```

### **What Gets Built:**

- ✅ `vendor/` - PHP dependencies via Composer
- ✅ `node_modules/` - Node dependencies (build time only)
- ✅ `public/build/` - Compiled JS/CSS from Vite
- ✅ `bootstrap/cache/` - Laravel optimizations

### **What NEVER Goes to Git:**

- ❌ `node_modules/`
- ❌ `vendor/`
- ❌ `.env`
- ❌ `storage/logs/`
- ❌ `public/build/` (built by CI, not committed)

---

## 🚀 Setup: A2 Hosting Configuration

### **Step 1: Initial Server Setup**

**SSH into A2:**

```bash
ssh username@yourdomain.com
# Or use A2's SSH through cPanel
```

**Create directory structure:**

```bash
cd ~

# Create application directory (outside public_html for security)
mkdir -p application
mkdir -p logs
mkdir -p backups

# Check PHP version
php -v  # Should be 8.1+

# If not, create .htaccess to specify PHP version
echo "AddHandler application/x-httpd-php82 .php" > ~/public_html/.htaccess
```

### **Step 2: Install Composer (if not available)**

```bash
cd ~

# Download Composer
curl -sS https://getcomposer.org/installer | php

# Make it executable globally (for your account)
mkdir -p ~/bin
mv composer.phar ~/bin/composer
chmod +x ~/bin/composer

# Add to PATH (add to ~/.bashrc)
echo 'export PATH="$HOME/bin:$PATH"' >> ~/.bashrc
source ~/.bashrc

# Verify
composer --version
```

### **Step 3: Configure Database**

**In cPanel:**

1. Go to "MySQL Databases"
2. Create database: `username_faceless_prod`
3. Create database: `username_faceless_staging` (optional)
4. Create user: `username_faceless`
5. Set strong password
6. Grant all privileges to user on both databases

**Note the credentials:**

```
DB_HOST=localhost
DB_DATABASE=username_faceless_prod
DB_USERNAME=username_faceless
DB_PASSWORD=your_strong_password
```

### **Step 4: Setup Git on Server**

```bash
# Generate SSH key for GitHub
ssh-keygen -t ed25519 -C "deploy@yourdomain.com"
# Save to: /home/username/.ssh/id_deploy
# No passphrase (for automation)

# Copy public key
cat ~/.ssh/id_deploy.pub

# Add to GitHub:
# Repo Settings > Deploy keys > Add deploy key
# Paste the public key
# ✅ Check "Allow write access" if using git-based deployment
```

### **Step 5: Clone Repository**

```bash
cd ~/application

# Configure git to use deploy key
eval "$(ssh-agent -s)"
ssh-add ~/.ssh/id_deploy

# Clone your repository
git clone git@github.com:yourusername/itervel-platform.git .

# Or if using HTTPS:
git clone https://github.com/yourusername/itervel-platform.git .
```

### **Step 6: Initial Environment Configuration**

```bash
cd ~/application

# Create production .env file
cp .env.example .env
nano .env
```

**Production `.env` configuration:**

```env
APP_NAME="Faceless Video Platform"
APP_ENV=production
APP_KEY=  # Will generate below
APP_DEBUG=false  # IMPORTANT: false in production
APP_URL=https://yourdomain.com

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=username_faceless_prod
DB_USERNAME=username_faceless
DB_PASSWORD=your_strong_password

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_LIFETIME=120

# API Keys (add yours)
ANTHROPIC_API_KEY=your_key
OPENAI_API_KEY=your_key
ELEVENLABS_API_KEY=your_key
SHOTSTACK_API_KEY=your_key
PEXELS_API_KEY=your_key
PIXABAY_API_KEY=your_key

# Email (A2 usually supports SMTP)
MAIL_MAILER=smtp
MAIL_HOST=mail.yourdomain.com
MAIL_PORT=587
MAIL_USERNAME=noreply@yourdomain.com
MAIL_PASSWORD=your_email_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
```

### **Step 7: Configure Laravel**

```bash
cd ~/application

# Generate application key
php artisan key:generate

# Set proper permissions
chmod -R 755 storage bootstrap/cache
chmod -R 775 storage/logs
chmod -R 775 storage/framework/sessions
chmod -R 775 storage/framework/views
chmod -R 775 storage/framework/cache

# Create symbolic link to public directory
cd ~/public_html
ln -s ../application/public/* .
# Or manually:
ln -s ../application/public/index.php index.php
ln -s ../application/public/.htaccess .htaccess
```

### **Step 8: Configure .htaccess**

**Edit `~/public_html/.htaccess`:**

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Force HTTPS
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

    # Remove public from URL
    RewriteCond %{REQUEST_URI} !^/public/
    RewriteRule ^(.*)$ /public/$1 [L]

    # Laravel routing
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.php [L]
</IfModule>

# PHP Configuration
php_value upload_max_filesize 50M
php_value post_max_size 50M
php_value max_execution_time 300
php_value memory_limit 256M

# Security Headers
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>

# Disable directory browsing
Options -Indexes
```

---

## 🔄 Automated Deployment with GitHub Actions

### **Create `.github/workflows/deploy-production.yml`:**

```yaml
name: Deploy to Production

on:
    push:
        branches: [main]
    workflow_dispatch: # Manual trigger

jobs:
    build-and-deploy:
        name: Build and Deploy
        runs-on: ubuntu-latest

        steps:
            - name: Checkout code
              uses: actions/checkout@v4

            - name: Setup PHP
              uses: shivammathur/setup-php@v2
              with:
                  php-version: '8.2'
                  extensions: mbstring, xml, ctype, json, mysql

            - name: Setup Node
              uses: actions/setup-node@v4
              with:
                  node-version: '18'

            - name: Get Composer Cache Directory
              id: composer-cache
              run: echo "dir=$(composer config cache-files-dir)" >> $GITHUB_OUTPUT

            - name: Cache Composer dependencies
              uses: actions/cache@v3
              with:
                  path: ${{ steps.composer-cache.outputs.dir }}
                  key: ${{ runner.os }}-composer-${{ hashFiles('**/composer.lock') }}
                  restore-keys: ${{ runner.os }}-composer-

            - name: Cache Node modules
              uses: actions/cache@v3
              with:
                  path: node_modules
                  key: ${{ runner.os }}-node-${{ hashFiles('**/package-lock.json') }}
                  restore-keys: ${{ runner.os }}-node-

            - name: Install Composer dependencies
              run: composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

            - name: Install Node dependencies
              run: npm ci

            - name: Build frontend assets
              run: npm run build
              env:
                  NODE_ENV: production

            - name: Create deployment package
              run: |
                  mkdir -p deploy-package

                  # Copy application files
                  rsync -av --exclude-from='.deployignore' . deploy-package/

                  # Create .deployignore if it doesn't exist
                  cat > deploy-package/.deployignore << EOF
                  .git
                  .github
                  node_modules
                  tests
                  .env.example
                  .editorconfig
                  .gitignore
                  .gitattributes
                  docker-compose.yml
                  Dockerfile
                  README.md
                  DEVELOPMENT_PROGRESS.md
                  storage/logs/*
                  !storage/logs/.gitkeep
                  EOF

                  # Create version file
                  echo "Build: ${{ github.sha }}" > deploy-package/VERSION
                  echo "Date: $(date)" >> deploy-package/VERSION

                  # Create deployment archive
                  tar -czf deploy.tar.gz -C deploy-package .

            - name: Deploy to A2 Hosting via SSH
              uses: easingthemes/ssh-deploy@main
              env:
                  SSH_PRIVATE_KEY: ${{ secrets.A2_SSH_PRIVATE_KEY }}
                  REMOTE_HOST: ${{ secrets.A2_HOST }}
                  REMOTE_USER: ${{ secrets.A2_USERNAME }}
                  SOURCE: 'deploy.tar.gz'
                  TARGET: '/home/${{ secrets.A2_USERNAME }}/deploy-temp/'
                  SCRIPT_BEFORE: |
                      # Create temp directory
                      mkdir -p /home/${{ secrets.A2_USERNAME }}/deploy-temp

                      # Backup current deployment
                      cd /home/${{ secrets.A2_USERNAME }}
                      if [ -d "application" ]; then
                        tar -czf backups/backup-$(date +%Y%m%d-%H%M%S).tar.gz application/
                        # Keep only last 5 backups
                        cd backups && ls -t | tail -n +6 | xargs -r rm
                      fi

                  SCRIPT_AFTER: |
                      cd /home/${{ secrets.A2_USERNAME }}

                      # Extract new deployment
                      tar -xzf deploy-temp/deploy.tar.gz -C application/

                      # Run post-deployment commands
                      cd application

                      # Install/update Composer dependencies (in case composer.lock changed)
                      ~/bin/composer install --no-dev --optimize-autoloader

                      # Clear and cache Laravel config
                      php artisan config:cache
                      php artisan route:cache
                      php artisan view:cache

                      # Run migrations (with --force for production)
                      php artisan migrate --force

                      # Set permissions
                      chmod -R 755 storage bootstrap/cache

                      # Clear old cache
                      php artisan cache:clear

                      # Restart queue workers (if using queues)
                      php artisan queue:restart

                      # Clean up
                      rm -rf ~/deploy-temp

                      # Log deployment
                      echo "Deployed at $(date) - Build ${{ github.sha }}" >> ~/logs/deployments.log

            - name: Notify deployment status
              if: always()
              uses: actions/github-script@v7
              with:
                  script: |
                      const status = '${{ job.status }}';
                      const sha = '${{ github.sha }}';
                      const url = '${{ secrets.A2_URL }}';

                      let message = status === 'success' 
                        ? `✅ Deployment successful!\n\nBuild: ${sha}\nURL: ${url}`
                        : `❌ Deployment failed!\n\nBuild: ${sha}`;

                      github.rest.repos.createCommitComment({
                        owner: context.repo.owner,
                        repo: context.repo.repo,
                        commit_sha: sha,
                        body: message
                      });

    test-production:
        name: Test Production
        needs: build-and-deploy
        runs-on: ubuntu-latest

        steps:
            - name: Health check
              run: |
                  response=$(curl -s -o /dev/null -w "%{http_code}" ${{ secrets.A2_URL }})
                  if [ $response -eq 200 ]; then
                    echo "✅ Production site is up (HTTP $response)"
                  else
                    echo "❌ Production site returned HTTP $response"
                    exit 1
                  fi

            - name: Check API endpoint
              run: |
                  response=$(curl -s -o /dev/null -w "%{http_code}" ${{ secrets.A2_URL }}/api/health)
                  if [ $response -eq 200 ]; then
                    echo "✅ API is responding (HTTP $response)"
                  else
                    echo "⚠️ API returned HTTP $response"
                  fi
```

### **Setup GitHub Secrets:**

In your GitHub repo: **Settings > Secrets and variables > Actions**

Add these secrets:

```
A2_HOST=yourdomain.com
A2_USERNAME=your_cpanel_username
A2_SSH_PRIVATE_KEY=<paste entire private key>
A2_URL=https://yourdomain.com
```

**Generate the SSH private key:**

```bash
# On your local machine
ssh-keygen -t ed25519 -C "github-actions@deploy"
# Save to: ~/.ssh/github_actions_deploy

# Copy PRIVATE key (entire file including BEGIN/END lines)
cat ~/.ssh/github_actions_deploy

# Copy PUBLIC key to A2 server
cat ~/.ssh/github_actions_deploy.pub
# Paste into: A2 server ~/.ssh/authorized_keys
```

---

## 🔄 Deployment Workflow

### **Normal Development Flow:**

```bash
# 1. Develop locally in Docker
docker compose exec app bash
claude-code
# Agent builds features

# 2. Commit and push
git add .
git commit -m "feat: add video generation workflow"
git push origin main

# 3. GitHub Actions automatically:
# - Runs tests
# - Builds production assets
# - Deploys to A2
# - Runs health checks
# - Posts status to commit

# 4. Check deployment
# Visit: https://yourdomain.com
# Should see updated app!
```

### **Manual Deployment (if needed):**

```bash
# Trigger workflow manually
# GitHub repo > Actions > Deploy to Production > Run workflow

# Or deploy via SSH:
ssh username@yourdomain.com
cd ~/application
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 🧪 Optional: Staging Environment

**Only set this up if you want to test on real server before production.**

### **Setup Staging Subdomain:**

**In cPanel:**

1. Go to "Subdomains"
2. Create subdomain: `staging.yourdomain.com`
3. Point to: `~/staging_html`

### **Create Staging Deployment:**

```bash
# SSH to server
ssh username@yourdomain.com

# Create staging directories
mkdir -p ~/staging_html
mkdir -p ~/application-staging

# Clone repo to staging
cd ~/application-staging
git clone git@github.com:yourusername/itervel-platform.git .

# Create staging .env
cp .env.example .env
nano .env
# Configure with staging database: username_faceless_staging
# Set APP_ENV=staging

# Setup staging
composer install --no-dev
npm ci
npm run build
php artisan key:generate
php artisan migrate

# Link to staging_html
cd ~/staging_html
ln -s ../application-staging/public/* .
```

### **Create Staging Workflow:**

**`.github/workflows/deploy-staging.yml`:**

```yaml
name: Deploy to Staging

on:
    push:
        branches: [develop] # Separate branch for staging
    workflow_dispatch:

jobs:
    deploy-staging:
        # Same as production workflow but deploy to ~/application-staging
        # Use different secrets: A2_STAGING_*
```

### **Staging Workflow:**

```
develop branch → Deploy to staging.yourdomain.com → Test → Merge to main → Deploy to production
```

---

## 🛠️ Server Management Scripts

### **Create Deployment Helper Script on Server:**

**`~/bin/deploy.sh`:**

```bash
#!/bin/bash

cd ~/application

echo "🚀 Starting deployment..."

# Put application in maintenance mode
php artisan down --message="Deploying new version" --retry=60

# Pull latest changes
git pull origin main

# Update dependencies
~/bin/composer install --no-dev --optimize-autoloader

# Rebuild frontend
npm ci
npm run build

# Run migrations
php artisan migrate --force

# Clear and optimize cache
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# Bring application back up
php artisan up

echo "✅ Deployment complete!"
```

**Make it executable:**

```bash
chmod +x ~/bin/deploy.sh

# Use it:
~/bin/deploy.sh
```

### **Database Backup Script:**

**`~/bin/backup-db.sh`:**

```bash
#!/bin/bash

TIMESTAMP=$(date +%Y%m%d-%H%M%S)
BACKUP_DIR=~/backups
DB_NAME=username_faceless_prod
DB_USER=username_faceless
DB_PASS=your_password

mkdir -p $BACKUP_DIR

# Backup database
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/db-$TIMESTAMP.sql.gz

# Keep only last 14 days of backups
find $BACKUP_DIR -name "db-*.sql.gz" -mtime +14 -delete

echo "✅ Database backed up to: db-$TIMESTAMP.sql.gz"
```

**Automate daily backups with cron:**

```bash
# Edit crontab
crontab -e

# Add daily backup at 2 AM
0 2 * * * ~/bin/backup-db.sh
```

---

## 📊 Monitoring & Logging

### **Setup Health Check Endpoint:**

**`routes/api.php`:**

```php
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'version' => file_get_contents(base_path('VERSION')),
    ]);
});
```

### **Monitor Logs:**

```bash
# SSH to server
ssh username@yourdomain.com

# View Laravel logs
tail -f ~/application/storage/logs/laravel.log

# View deployment logs
tail -f ~/logs/deployments.log

# View web server errors (if accessible)
tail -f ~/logs/error.log
```

### **Setup UptimeRobot (Free Monitoring):**

1. Sign up at https://uptimerobot.com
2. Add monitor:
    - Type: HTTP(s)
    - URL: https://yourdomain.com/api/health
    - Interval: 5 minutes
3. Get email alerts if site goes down

---

## 🔒 Security Checklist

Before going live:

- [ ] `APP_DEBUG=false` in production `.env`
- [ ] Strong database password
- [ ] HTTPS enabled (A2 provides free SSL)
- [ ] `.env` file is NOT in git
- [ ] `storage/` and `bootstrap/cache/` are writable
- [ ] Security headers in `.htaccess`
- [ ] Rate limiting enabled on API routes
- [ ] Database backups automated
- [ ] Error logging configured
- [ ] File upload size limits set
- [ ] CORS configured correctly
- [ ] API keys secured in `.env`

---

## 🚨 Rollback Procedure

**If deployment breaks production:**

```bash
# SSH to server
ssh username@yourdomain.com

# List backups
ls -lah ~/backups/

# Restore from backup
cd ~
tar -xzf backups/backup-YYYYMMDD-HHMMSS.tar.gz

# Restart application
cd application
php artisan config:cache
php artisan up
```

---

## 📈 Performance Optimization

### **Enable OPcache (PHP):**

**In cPanel > MultiPHP INI Editor:**

```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
```

### **Enable Laravel Optimizations:**

```bash
# On server after deployment
cd ~/application
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

### **Setup CDN (Optional - CloudFlare Free):**

1. Add site to CloudFlare
2. Update nameservers at domain registrar
3. Enable caching for static assets
4. Enable Brotli compression

---

## ✅ Summary

### **Recommended Setup:**

1. **Development:** Docker containers locally
2. **CI/CD:** GitHub Actions for automated deployment
3. **Production:** A2 Shared Hosting
4. **Staging:** Skip for MVP, add later if needed

### **Deployment Flow:**

```
Local Development (Docker)
    ↓
Git Push to main branch
    ↓
GitHub Actions:
  - Run Tests
  - Build Assets
  - Deploy to A2
  - Health Check
    ↓
Production Live
    ↓
Monitor with UptimeRobot
```

### **Key Points:**

✅ **Build in CI/CD** (not on server, not in git)
✅ **Deploy via GitHub Actions** (automated, consistent)
✅ **Backup database daily** (automated cron job)
✅ **Skip staging for MVP** (add later if needed)
✅ **Monitor health** (UptimeRobot or similar)
✅ **Use maintenance mode** during deployments

### **Cost:**

- GitHub Actions: Free (2,000 minutes/month)
- A2 Hosting: Already paid
- UptimeRobot: Free (50 monitors)
- CloudFlare CDN: Free tier
- **Total extra cost: $0**

---

**You're ready to deploy! Follow the GitHub Actions setup and you'll have automated deployments with zero manual FTP uploads.** 🚀

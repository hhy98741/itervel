# Docker Compose + Exec Usage Guide

## AI-Powered Development in Isolated Containers

This guide covers the complete workflow for developing your faceless video platform using Claude Code inside Docker containers for safety and isolation.

---

## 🚀 Quick Reference Commands

```bash
# Start containers
docker compose up -d

# Enter container and run Claude Code
docker compose exec app bash
claude-code

# Run Laravel server (inside container)
php artisan serve --host=0.0.0.0

# Run Vite dev server (inside container)
npm run dev -- --host

# Run tests (inside container)
vendor/bin/phpunit
npm test
npx playwright test

# Stop containers
docker compose down

# Rebuild containers (after Dockerfile changes)
docker compose build --no-cache
docker compose up -d

# Destroy everything and start fresh
docker compose down -v
docker compose up -d
```

---

## 📁 Initial Setup (One-Time)

### **Step 1: Create Project Structure**

```bash
# Create project directory
mkdir ~/projects/itervel-platform
cd ~/projects/itervel-platform

# Initialize git
git init
git branch -M main

# Create initial Laravel project (temporarily on host)
composer create-project laravel/laravel .
```

### **Step 2: Create Docker Configuration Files**

**Create `Dockerfile`:**

```dockerfile
FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    default-mysql-client \
    vim \
    wget

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Node.js 18
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
RUN apt-get install -y nodejs

# Install Claude Code globally
RUN npm install -g @anthropic-ai/claude-code

# Install Playwright system dependencies
RUN npx playwright install-deps

# Set working directory
WORKDIR /var/www

# Install Playwright browsers (will run after code is copied)
RUN npx -y playwright@latest install chromium

# Set proper permissions
RUN chown -R www-data:www-data /var/www

EXPOSE 8000 5173

CMD ["bash"]
```

**Create `docker-compose.yml`:**

```yaml
version: '3.8'

services:
    app:
        build:
            context: .
            dockerfile: Dockerfile
        container_name: itervel-app
        volumes:
            - .:/var/www
            - /var/www/node_modules
            - /var/www/vendor
        ports:
            - '8000:8000' # Laravel
            - '5173:5173' # Vite
        networks:
            - faceless-network
        depends_on:
            - db
            - db_test
            - redis
        environment:
            - DB_HOST=db
            - DB_DATABASE=itervel_dev
            - DB_USERNAME=faceless_user
            - DB_PASSWORD=secret
            - DB_HOST_TESTING=db_test
            - DB_DATABASE_TESTING=itervel_test
            - REDIS_HOST=redis
        stdin_open: true
        tty: true

    db:
        image: mariadb:10.11
        container_name: itervel-db
        volumes:
            - db_data:/var/lib/mysql
        environment:
            MYSQL_ROOT_PASSWORD: rootpassword
            MYSQL_DATABASE: itervel_dev
            MYSQL_USER: faceless_user
            MYSQL_PASSWORD: secret
        ports:
            - '3306:3306'
        networks:
            - faceless-network
        healthcheck:
            test: ['CMD', 'mysqladmin', 'ping', '-h', 'localhost']
            interval: 10s
            timeout: 5s
            retries: 5

    db_test:
        image: mariadb:10.11
        container_name: itervel-db-test
        environment:
            MYSQL_ROOT_PASSWORD: rootpassword
            MYSQL_DATABASE: itervel_test
            MYSQL_USER: faceless_user
            MYSQL_PASSWORD: secret
        networks:
            - faceless-network
        tmpfs:
            - /var/lib/mysql # Faster test DB (in-memory)

    redis:
        image: redis:alpine
        container_name: itervel-redis
        ports:
            - '6379:6379'
        networks:
            - faceless-network

networks:
    faceless-network:
        driver: bridge

volumes:
    db_data:
```

**Create `.env` file:**

```bash
cp .env.example .env
```

**Edit `.env`:**

```env
APP_NAME="Faceless Video Platform"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=itervel_dev
DB_USERNAME=faceless_user
DB_PASSWORD=secret

# Testing database
DB_CONNECTION_TESTING=mysql
DB_HOST_TESTING=db_test
DB_DATABASE_TESTING=itervel_test

REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

**Create `.dockerignore`:**

```
node_modules
vendor
.git
.env
storage/logs/*
storage/framework/cache/*
storage/framework/sessions/*
storage/framework/views/*
```

### **Step 3: Build and Initialize**

```bash
# Build containers
docker compose build

# Start containers
docker compose up -d

# Wait for DB to be ready (check health)
docker compose ps

# Enter container
docker compose exec app bash

# Inside container - install dependencies
composer install
npm install

# Generate app key
php artisan key:generate

# Run migrations
php artisan migrate

# Install Playwright browsers
npx playwright install

# Exit container
exit
```

### **Step 4: Verify Setup**

```bash
# Enter container
docker compose exec app bash

# Run tests
vendor/bin/phpunit  # Should see Laravel's default tests pass
npm test -- --passWithNoTests  # Jest setup confirmation

# Start Laravel server
php artisan serve --host=0.0.0.0 &

# Start Vite (in another container terminal)
# Open new terminal:
docker compose exec app bash
npm run dev -- --host &

# Exit
exit
```

**Visit in browser:**

- http://localhost:8000 - Should see Laravel welcome page

---

## 🤖 Daily Development Workflow

### **Starting Your Day:**

```bash
# 1. Start all containers
cd ~/projects/itervel-platform
docker compose up -d

# 2. Enter the container
docker compose exec app bash

# 3. Start development servers (optional - for manual testing)
# In container:
php artisan serve --host=0.0.0.0 &
npm run dev -- --host &

# 4. Run Claude Code
claude-code

# 5. Paste your autonomous prompt
# Attach: DEVELOPMENT_PROGRESS.md, development-plan.md, prd-brief-for-opus.md
# Claude starts building features...
```

### **While Claude Works:**

**Terminal 1:** Claude Code running
**Terminal 2:** Monitor logs (optional)

```bash
docker compose logs -f app
```

**Browser:** Test features at http://localhost:8000 or http://localhost:5173

### **When Claude Reaches Checkpoint:**

```bash
# 1. Claude outputs:
# "🎯 CHECKPOINT 1 Complete - Updated DEVELOPMENT_PROGRESS.md"

# 2. Review the code
# Files are in your project folder (visible on host)

# 3. Test manually
# Visit http://localhost:8000 in browser
# Try the features Claude built

# 4. If all good, continue:
You: "continue to next checkpoint"

# Or pause:
You: "pause"
exit  # Exit container
docker compose down  # Stop containers
```

### **Ending Your Day:**

```bash
# Save your work (inside container)
git add .
git commit -m "End of day checkpoint"
git push origin main

# Exit container
exit

# Stop containers (optional)
docker compose down

# Or leave them running
# They use minimal resources when idle
```

---

## 🔄 Parallel Development with Git Worktrees (Recommended)

**Why Worktrees:**

- ✅ Better than branches for parallel work
- ✅ Separate working directories
- ✅ Share same git history
- ✅ Easier to manage than separate clones

### **Setup Parallel Agents with Worktrees:**

```bash
# Main project (this is your primary worktree)
cd ~/projects/itervel-platform

# Create worktree for backend track
git worktree add ../faceless-backend backend-track
# This creates a new branch 'backend-track' and checks it out in ../faceless-backend

# Create worktree for frontend track
git worktree add ../faceless-frontend frontend-track
# Creates 'frontend-track' branch in ../faceless-frontend

# Now you have:
# ~/projects/itervel-platform (main branch)
# ~/projects/faceless-backend (backend-track branch)
# ~/projects/faceless-frontend (frontend-track branch)
```

### **Configure Each Worktree:**

**Backend Worktree:**

```bash
cd ~/projects/faceless-backend

# Copy docker-compose.yml and edit ports
cp ../itervel-platform/docker-compose.yml .
nano docker-compose.yml

# Change:
# ports:
#   - "8001:8000"  # Laravel (external:internal)
#   - "5174:5173"  # Vite
# container_name: itervel-app-backend

# Change database container names to avoid conflicts
# container_name: itervel-db-backend
# container_name: itervel-db-test-backend

# Start backend containers
docker compose -p backend up -d
```

**Frontend Worktree:**

```bash
cd ~/projects/faceless-frontend

# Copy and edit docker-compose.yml
cp ../itervel-platform/docker-compose.yml .
nano docker-compose.yml

# Change:
# ports:
#   - "8002:8000"  # Laravel
#   - "5175:5173"  # Vite
# container_name: itervel-app-frontend
# container_name: itervel-db-frontend
# container_name: itervel-db-test-frontend

# Start frontend containers
docker compose -p frontend up -d
```

### **Run Parallel Agents:**

**Terminal 1: Backend Agent**

```bash
cd ~/projects/faceless-backend
docker compose -p backend exec app bash

# Inside container:
claude-code

# Tell Claude:
"Build backend features from CHECKPOINT 1 to CHECKPOINT 2:
- Feature 2.1: Video Input API
- Feature 2.2: URL Processing Service
- Feature 2.3: AI Model Router

When complete, commit to backend-track branch and push."
```

**Terminal 2: Frontend Agent**

```bash
cd ~/projects/faceless-frontend
docker compose -p frontend exec app bash

# Inside container:
claude-code

# Tell Claude:
"Build frontend features from CHECKPOINT 1 to CHECKPOINT 2:
- Feature 2.4: Video Creation Form UI
- Feature 2.5: Project Dashboard UI

When complete, commit to frontend-track branch and push."
```

### **Merge Worktrees Back:**

```bash
# After both agents finish and push their branches:

cd ~/projects/itervel-platform
git fetch origin

# Review backend changes
git diff main..backend-track

# Merge backend
git merge backend-track
# Or create PR on GitHub and merge there

# Review frontend changes
git diff main..frontend-track

# Merge frontend
git merge frontend-track

# Push merged result
git push origin main

# Clean up worktrees (optional)
git worktree remove ../faceless-backend
git worktree remove ../faceless-frontend

# Delete branches (optional)
git branch -d backend-track
git branch -d frontend-track
```

---

## 🤖 Automated Code Review with GitHub Actions

### **Approach: AI Code Reviewer + Automated Testing**

Create `.github/workflows/ai-code-review.yml`:

```yaml
name: AI Code Review & Tests

on:
  pull_request:
    branches: [ main ]
  push:
    branches: [ backend-track, frontend-track ]

jobs:
  ai-review:
    name: AI Code Review
    runs-on: ubuntu-latest

    steps:
      - name: Checkout code
        uses: actions/checkout@v4
        with:
          fetch-depth: 0  # Get full history for diff

      - name: Get changed files
        id: changed-files
        uses: tj-actions/changed-files@v40
        with:
          files: |
            **/*.php
            **/*.js
            **/*.jsx

      - name: AI Code Review with Claude
        if: steps.changed-files.outputs.any_changed == 'true'
        env:
          ANTHROPIC_API_KEY: ${{ secrets.ANTHROPIC_API_KEY }}
        run: |
          # Install dependencies
          npm install -g @anthropic-ai/sdk

          # Create review script
          cat > review.js << 'EOF'
          const Anthropic = require('@anthropic-ai/sdk');
          const { execSync } = require('child_process');
          const fs = require('fs');

          const anthropic = new Anthropic({
            apiKey: process.env.ANTHROPIC_API_KEY,
          });

          async function reviewCode() {
            // Get the diff
            const diff = execSync('git diff origin/main...HEAD').toString();

            if (!diff) {
              console.log('No changes to review');
              return;
            }

            const prompt = `You are an expert code reviewer. Review this code diff for:

          1. **Code Quality:**
             - Clean code principles
             - Proper error handling
             - Security vulnerabilities (SQL injection, XSS, CSRF)
             - Performance issues (N+1 queries, memory leaks)

          2. **Laravel Best Practices:**
             - Proper use of Eloquent
             - Validation rules
             - Middleware usage
             - API resource formatting

          3. **React Best Practices:**
             - Component structure
             - Hook usage
             - State management
             - Accessibility

          4. **Testing:**
             - Are there tests for new features?
             - Do tests cover edge cases?
             - Are tests well-structured?

          5. **Architecture:**
             - Follows SOLID principles?
             - Proper separation of concerns?
             - DRY principle applied?

          Provide:
          - Summary (2-3 sentences)
          - Critical Issues (must fix before merge)
          - Warnings (should fix)
          - Suggestions (nice to have)
          - Approval Status (APPROVE / REQUEST_CHANGES / COMMENT)

          Diff:
          \`\`\`
          ${diff}
          \`\`\``;

            const message = await anthropic.messages.create({
              model: 'claude-sonnet-4-20250514',
              max_tokens: 4000,
              messages: [{ role: 'user', content: prompt }],
            });

            const review = message.content[0].text;

            // Write review to file
            fs.writeFileSync('ai-review.md', review);
            console.log(review);

            // Post as PR comment (if PR)
            if (process.env.GITHUB_EVENT_NAME === 'pull_request') {
              execSync(`gh pr comment ${process.env.PR_NUMBER} --body-file ai-review.md`, {
                env: { ...process.env, GH_TOKEN: process.env.GITHUB_TOKEN }
              });
            }
          }

          reviewCode().catch(console.error);
          EOF

          # Run review
          node review.js
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
          PR_NUMBER: ${{ github.event.pull_request.number }}
          GITHUB_EVENT_NAME: ${{ github.event_name }}

      - name: Upload review
        if: always()
        uses: actions/upload-artifact@v3
        with:
          name: ai-code-review
          path: ai-review.md

  tests:
    name: Run Tests
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mariadb:10.11
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: testing
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3

      redis:
        image: redis:alpine
        ports:
          - 6379:6379

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: mbstring, xml, ctype, json, mysql, redis
          coverage: xdebug

      - name: Setup Node
        uses: actions/setup-node@v4
        with:
          node-version: '18'

      - name: Install PHP dependencies
        run: composer install --prefer-dist --no-interaction

      - name: Install Node dependencies
        run: npm ci

      - name: Prepare Laravel
        run: |
          cp .env.example .env
          php artisan key:generate
          php artisan migrate --force
        env:
          DB_CONNECTION: mysql
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_DATABASE: testing
          DB_USERNAME: root
          DB_PASSWORD: password

      - name: Run PHPUnit tests
        run: vendor/bin/phpunit --coverage-text --coverage-clover=coverage.xml

      - name: Run Jest tests
        run: npm test -- --coverage

      - name: Install Playwright
        run: npx playwright install --with-deps chromium

      - name: Run Playwright tests
        run: npx playwright test
        env:
          APP_URL: http://localhost:8000

      - name: Upload test results
        if: always()
        uses: actions/upload-artifact@v3
        with:
          name: test-results
          path: |
            coverage.xml
            playwright-report/
            test-results/

      - name: Comment test results on PR
        if: github.event_name == 'pull_request'
        uses: actions/github-script@v7
        with:
          script: |
            const fs = require('fs');

            // Read test results (simplified)
            let comment = '## Test Results\n\n';
            comment += '✅ All tests passed!\n\n';
            comment += 'See artifacts for detailed coverage reports.';

            github.rest.issues.createComment({
              issue_number: context.issue.number,
              owner: context.repo.owner,
              repo: context.repo.repo,
              body: comment
            });

  security-scan:
    name: Security Scan
    runs-on: ubuntu-latest

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Run PHP Security Checker
        run: |
          curl -sS https://get.symfony.com/cli/installer | bash
          ~/.symfony5/bin/symfony check:security

      - name: Run npm audit
        run: npm audit --audit-level=high
        continue-on-error: true

      - name: Run Snyk scan
        uses: snyk/actions/php@master
        continue-on-error: true
        env:
          SNYK_TOKEN: ${{ secrets.SNYK_TOKEN }}

  lint:
    name: Code Linting
    runs-on: ubuntu-latest

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Setup Node
        uses: actions/setup-node@v4
        with:
          node-version: '18'

      - name: Install dependencies
        run: |
          composer install
          npm ci

      - name: Run PHP CS Fixer
        run: vendor/bin/php-cs-fixer fix --dry-run --diff

      - name: Run ESLint
        run: npm run lint
```

### **Setup GitHub Secrets:**

```bash
# In your GitHub repo:
# Settings > Secrets and variables > Actions > New repository secret

# Add:
ANTHROPIC_API_KEY=your-anthropic-key-here
SNYK_TOKEN=your-snyk-token (optional)
```

### **Usage Workflow:**

```bash
# Agent finishes work and commits to backend-track branch
cd ~/projects/faceless-backend
docker compose -p backend exec app bash

# Inside container:
git add .
git commit -m "feat: implement video input API and URL processing"
git push origin backend-track
exit

# On GitHub:
# 1. GitHub Action automatically triggers
# 2. AI reviews the code diff
# 3. Runs all tests
# 4. Posts review comment on PR
# 5. Shows test results

# You review:
# - AI's code review (highlights issues)
# - Test results (all green?)
# - Security scan (any vulnerabilities?)

# If approved:
# - Merge PR on GitHub
# - Or locally: git merge backend-track
```

---

## 🛠️ Common Tasks

### **View Container Logs:**

```bash
# All services
docker compose logs -f

# Specific service
docker compose logs -f app
docker compose logs -f db
```

### **Run Commands Without Entering Container:**

```bash
# Run migrations
docker compose exec app php artisan migrate

# Run tests
docker compose exec app vendor/bin/phpunit

# Install package
docker compose exec app composer require package/name
```

### **Access Database:**

```bash
# Enter MySQL
docker compose exec db mysql -u faceless_user -p itervel_dev
# Password: secret

# Or use GUI tool:
# Host: localhost
# Port: 3306
# User: faceless_user
# Password: secret
# Database: itervel_dev
```

### **Reset Everything:**

```bash
# Stop and remove containers + volumes
docker compose down -v

# Rebuild
docker compose build --no-cache

# Start fresh
docker compose up -d

# Re-initialize
docker compose exec app bash
php artisan migrate
```

### **Update Dependencies:**

```bash
docker compose exec app bash

# Update PHP packages
composer update

# Update Node packages
npm update

# Update Claude Code
npm update -g @anthropic-ai/claude-code
```

---

## 🐛 Troubleshooting

### **Problem: Containers won't start**

```bash
# Check status
docker compose ps

# Check logs
docker compose logs

# Common fixes:
docker compose down
docker system prune  # Clean up Docker
docker compose up -d
```

### **Problem: Database connection refused**

```bash
# Wait for DB to be healthy
docker compose ps  # Check db status

# Restart DB
docker compose restart db

# Check DB logs
docker compose logs db
```

### **Problem: Port already in use**

```bash
# Find what's using port 8000
lsof -i :8000

# Kill it or change docker-compose.yml ports
# Change "8000:8000" to "8001:8000"
```

### **Problem: Claude Code not found in container**

```bash
# Rebuild container
docker compose build --no-cache app
docker compose up -d

# Or install manually
docker compose exec app npm install -g @anthropic-ai/claude-code
```

### **Problem: File permission issues**

```bash
# Fix ownership
docker compose exec app chown -R www-data:www-data /var/www

# Or run as root
docker compose exec --user root app bash
```

---

## 📊 Monitoring Resource Usage

```bash
# See resource usage
docker stats

# Container logs size
docker compose logs --tail=100

# Clean up unused resources
docker system prune -a
```

---

## ✅ Best Practices

1. **Always commit before major changes**

    ```bash
    git add .
    git commit -m "checkpoint before feature X"
    ```

2. **Use worktrees for parallel work** (not separate clones)

3. **Let GitHub Actions review code** (don't manually review every line)

4. **Review AI's review** (focus on critical issues it flags)

5. **Test manually at checkpoints** (AI tests aren't perfect)

6. **Rebuild containers weekly** (get updates, clean slate)

7. **Monitor container resource usage** (don't let it run wild)

8. **Use volumes for data** (database persists across container restarts)

---

## 📝 Summary

**Your workflow:**

1. `docker compose up -d` (start containers)
2. `docker compose exec app bash` (enter container)
3. `claude-code` (run AI agent)
4. Agent builds features, commits to branch
5. Push to GitHub
6. GitHub Actions review + test
7. You review the AI's review
8. Merge if good

**Parallel development:**

- Use git worktrees (not clones)
- Separate docker-compose stacks with different ports
- Merge back to main when done

**Automated review:**

- GitHub Actions runs AI review
- Runs all tests automatically
- Posts results as PR comment
- You just review the summary

**Safety:**

- Everything in containers (host is safe)
- Can destroy and rebuild anytime
- Git worktrees keep branches isolated
- AI reviews catch issues before you see them

---

**You're all set! This setup gives you:**
✅ Safe, isolated development environment
✅ Parallel development capability
✅ Automated code review and testing
✅ Easy cleanup and recovery
✅ Professional CI/CD workflow

Happy building! 🚀

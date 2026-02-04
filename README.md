# Template for Laravel/React Application

## Getting Started

### 1. Intall Dependencies

Install composer and npm dependencies:

```bash
composer install
npm install
```

### 2. Start Docker

Start the Docker containers:

```bash
docker compose build  # Build the Docker image
docker compose up -d  # Spin up the Docker containers
docker compose exec app bash  # Enter the Docker app container shell
```

### 3. Initial Setup

Run the setup command inside Docker:

```bash
cp .env.example .env  # Copy the environment file and configure your settings
php artisan key:generate  # Generates the app key
php artisan migrate --force  # Runs migrations
```

### 4. Install Laravel Boost

Before starting development, run the Laravel Boost MCP server so Claude can access project context:

```bash
php artisan boost:install
```

### 5. Development with Claude

Enter the Docker container shell for development or to run the servers:

```bash
docker compose start  # Start all the Docker containers
docker compose exec app bash  # Enter the Docker app container shell
composer run dev  # Starts the Laravel and Vite servers

claude  # Start Claude Code CLI
docker compose stop  # Stop all the Docker containers
```

From inside the container, you can use Claude Code to assist with development. All Laravel commands (artisan, composer, npm) should be run inside the container.

There are alias commands to run Claude inside the Docker container.

```bash
cld  # claude
cldc  # claude --continue
cldyolo  # claude --dangerously-skip-permissions
cldcyolo  # claude --continue --dangerously-skip-permissions
```

### Available Make Commands

Run `make help` to see all available commands.

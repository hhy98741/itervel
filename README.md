# Template for Laravel/React Application

## Getting Started

### 1. Start Laravel Boost

Before starting development, run the Laravel Boost MCP server so Claude can access project context:

```bash
make ai  # php artisan boost:install
```

### 2. Set Up Environment

Copy the environment file and configure your settings:

```bash
cp .env.example .env
```

### 3. Start Docker

Start the Docker containers:

```bash
make build  # docker compose build
make up  # docker compose up -d
```

### 4. Initial Setup

Run the setup command inside Docker:

```bash
make shell  # docker compose exec app bash
make setup  # composer setup
```

This installs dependencies, generates the app key, and runs migrations.

### 5. Development with Claude

Enter the Docker container shell for development:

```bash
make shell  # docker compose exec app bash
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

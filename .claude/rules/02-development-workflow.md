---
load: always
description: Docker setup, Makefile commands, and development workflow
---

# Development Workflow

## Docker Development

### Starting the Environment

```bash
make up          # Start Docker containers
make down        # Stop containers
make build       # Rebuild containers
make shell       # Access container shell
```

### Docker Services

- **app** - PHP 8.4-FPM with Laravel (port 8000)
- **mariadb** - MariaDB 10.5 database
- **redis** - Redis 7 for cache/queue

### Running Commands Inside Container

```bash
make shell                           # Enter container
php artisan migrate                  # Run migrations
php artisan test                     # Run tests
composer run dev                     # Laravel's combined dev command
```

### Makefile Commands

| Command             | Description                                       |
| ------------------- | ------------------------------------------------- |
| `make help`         | Show all available commands                       |
| `make setup`        | Initial project setup (composer, npm, migrations) |
| `make dependencies` | Install Composer and npm dependencies             |
| `make dev`          | Start development server                          |
| `make format`       | Run code formatting (Pint, ESLint, Prettier)      |
| `make test`         | Run tests with coverage                           |
| `make migrate`      | Run database migrations                           |

## Code Formatting

### PHP (Laravel Pint)

```bash
vendor/bin/pint              # Format all files
vendor/bin/pint --dirty      # Format changed files only
vendor/bin/pint --test       # Check without fixing
```

### JavaScript/TypeScript (ESLint + Prettier)

```bash
npm run lint                 # Check for issues
npm run lint:fix             # Fix issues
npm run format               # Format with Prettier
npm run types                # TypeScript type check
```

### Combined Formatting

```bash
make format                  # Run all formatters
```

## Testing

### Running Tests

```bash
php artisan test --compact                    # All tests
php artisan test --compact --filter=testName  # Specific test
php artisan test tests/Feature/Auth           # Directory
vendor/bin/pest --coverage                    # With coverage
```

### Test Organization

- `tests/Feature/` - Integration tests (database, HTTP)
- `tests/Unit/` - Isolated unit tests

## Database

### Migrations

```bash
php artisan migrate              # Run migrations
php artisan migrate:fresh        # Fresh database
php artisan migrate:fresh --seed # Fresh with seeders
```

### Seeding

```bash
php artisan db:seed              # Run seeders
php artisan db:seed --class=UserSeeder  # Specific seeder
```

## Artisan Commands

List available commands:

```bash
php artisan list
php artisan make:model --help    # Help for specific command
```

Common make commands:

```bash
php artisan make:model Post -mfs        # Model + migration + factory + seeder
php artisan make:controller PostController
php artisan make:request StorePostRequest
php artisan make:test PostTest --pest
php artisan make:test PostTest --pest --unit
```

## Vite Build

```bash
npm run dev      # Development server with HMR
npm run build    # Production build
```

Wayfinder routes are regenerated automatically when Vite runs.

## Environment Variables

Copy `.env.example` to `.env` and configure:

- `APP_URL` - Application URL
- `DB_*` - Database connection
- `MAIL_*` - Email configuration

Access environment values via config, not `env()` directly:

```php
config('app.name')    // Correct
env('APP_NAME')       // Only in config files
```

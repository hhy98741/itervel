---
load: conditional
paths:
    - app/Models/**
    - database/**
keywords:
    - model
    - migration
    - factory
    - seeder
    - eloquent
    - database
    - relationship
description: Eloquent models, factories, migrations, and database patterns
---

# Models and Database

## User Model

The User model includes Fortify's two-factor authentication:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
```

Key points:

- Uses `casts()` method (Laravel 12 style, not `$casts` property)
- Two-factor secrets hidden from serialization
- Password automatically hashed via cast

## Model Conventions

When creating new models:

1. Use `php artisan make:model ModelName -mfs` to create model with migration, factory, and seeder
2. Define `$fillable` or use `Model::unguard()` (configured in AppServiceProvider)
3. Use `casts()` method for attribute casting
4. Add factory states for common test scenarios
5. Hide sensitive attributes in `$hidden`

```php
class Post extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'content', 'published_at'];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    // Relationships
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
```

## Factory Patterns

Factories live in `database/factories/` and follow this pattern:

```php
namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt(app(TwoFactorAuthenticationProvider::class)->generateSecretKey()),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => encrypt(json_encode(Collection::times(8, fn () => RecoveryCode::generate())->all())),
        ]);
    }
}
```

### Factory States

Define states for common scenarios:

- `unverified()` - User without email verification
- `withTwoFactor()` - User with 2FA enabled and confirmed
- `admin()` - User with admin role (if applicable)

### Using Factories in Tests

```php
// Basic user
$user = User::factory()->create();

// Unverified user
$user = User::factory()->unverified()->create();

// User with 2FA
$user = User::factory()->withTwoFactor()->create();

// Custom attributes
$user = User::factory()->create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);
```

## Migrations

Migrations use timestamp naming and follow Laravel conventions:

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
        });
    }
};
```

Key conventions:

- Use anonymous class syntax
- Always define `down()` method for rollbacks
- Use `after()` for column positioning
- Group related columns in single migration

## Database Configuration

Default database is SQLite for simplicity. Docker setup uses MariaDB.

Session, cache, and queue use database drivers by default.

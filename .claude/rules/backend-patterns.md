---
load: conditional
paths:
    - app/Http/**
    - app/Actions/**
    - app/Concerns/**
    - app/Providers/**
    - routes/**
keywords:
    - controller
    - request
    - validation
    - middleware
    - provider
    - action
    - concern
    - trait
description: Validation traits, controllers, Form Requests, and middleware patterns
---

# Backend Patterns

## Validation Traits (Concerns)

This project uses traits in `app/Concerns/` to share validation rules between Form Requests and Fortify Actions.

### Creating a Validation Trait

```php
namespace App\Concerns;

use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    protected function emailRules(): array
    {
        return [
            'required',
            'string',
            'lowercase',
            'email',
            'max:255',
            Rule::unique('users')->ignore($this->user()->id),
        ];
    }

    protected function profileRules(): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules(),
        ];
    }
}
```

### Using in Form Requests

```php
namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    public function rules(): array
    {
        return $this->profileRules();
    }
}
```

### Using in Fortify Actions

```php
namespace App\Actions\Fortify;

use App\Concerns\ProfileValidationRules;
use Illuminate\Support\Facades\Validator;

class UpdateUserProfile implements UpdatesUserProfiles
{
    use ProfileValidationRules;

    public function update(User $user, array $input): void
    {
        Validator::make($input, $this->profileRules())->validate();
        // ...
    }
}
```

### Existing Validation Traits

- `ProfileValidationRules` - Name and email validation
- `PasswordValidationRules` - Password and current password validation

## Controller Patterns

### Settings Controllers

Controllers in `app/Http/Controllers/Settings/` follow this pattern:

```php
namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return to_route('profile.edit');
    }
}
```

Key conventions:

- Use Form Request classes for validation
- Return `Inertia::render()` for page responses
- Return `RedirectResponse` after form submissions
- Use `to_route()` helper for redirects

### Route Controller Binding

Routes bind to controller methods:

```php
Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
```

## Form Request Conventions

Form Requests live in `app/Http/Requests/` organized by domain:

```php
namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class ProfileDeleteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required', 'current_password'],
        ];
    }
}
```

Key conventions:

- Organize by domain subdirectory (`Settings/`, etc.)
- Use trait concerns for shared validation rules
- Do not override `authorize()` unless specific authorization needed
- Use string-based validation rules (not fluent)

## Middleware Configuration

Middleware is configured in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

    $middleware->web(append: [
        HandleAppearance::class,
        HandleInertiaRequests::class,
        AddLinkHeadersForPreloadedAssets::class,
    ]);
})
```

### Custom Middleware

`HandleAppearance` - Reads appearance cookie and shares with Inertia:

```php
class HandleAppearance
{
    public function handle(Request $request, Closure $next): Response
    {
        $appearance = $request->cookie('appearance', 'system');
        // Share with Inertia...
        return $next($request);
    }
}
```

## Service Provider Patterns

### AppServiceProvider

Configure application defaults:

```php
public function boot(): void
{
    Date::useImmutable();
    Model::unguard();
    Password::default(fn () => Password::min(8));
    DB::prohibitDestructiveCommands($this->app->isProduction());
}
```

### FortifyServiceProvider

Configure Fortify views and rate limiting:

```php
public function boot(): void
{
    Fortify::createUsersUsing(CreateNewUser::class);
    Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
    Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

    Fortify::loginView(fn () => Inertia::render('auth/login'));
    Fortify::registerView(fn () => Inertia::render('auth/register'));
    // etc.

    RateLimiter::for('login', function (Request $request) {
        return Limit::perMinute(5)->by($request->email.$request->ip());
    });
}
```

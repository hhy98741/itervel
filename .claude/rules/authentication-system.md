---
load: conditional
paths:
    - app/Actions/Fortify/**
    - app/Providers/FortifyServiceProvider.php
    - config/fortify.php
    - resources/js/pages/auth/**
    - tests/**/Auth/**
keywords:
    - auth
    - login
    - register
    - password
    - fortify
    - two-factor
    - 2fa
    - authentication
description: Laravel Fortify authentication system with 2FA support
---

# Authentication System

This project uses Laravel Fortify for headless authentication with Inertia.js rendering the frontend.

## Fortify Configuration

Configuration in `config/fortify.php`:

```php
return [
    'guard' => 'web',
    'passwords' => 'users',
    'username' => 'email',
    'home' => '/dashboard',
    'prefix' => '',
    'domain' => null,
    'middleware' => ['web'],
    'limiters' => [
        'login' => 'login',
        'two-factor' => 'two-factor',
    ],
    'features' => [
        Features::registration(),
        Features::resetPasswords(),
        Features::emailVerification(),
        Features::updateProfileInformation(),
        Features::updatePasswords(),
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]),
    ],
];
```

## Fortify Service Provider

`app/Providers/FortifyServiceProvider.php` configures views and actions:

```php
public function boot(): void
{
    // Action classes
    Fortify::createUsersUsing(CreateNewUser::class);
    Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
    Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
    Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

    // Inertia views
    Fortify::loginView(fn () => Inertia::render('auth/login'));
    Fortify::registerView(fn () => Inertia::render('auth/register'));
    Fortify::requestPasswordResetLinkView(fn () => Inertia::render('auth/forgot-password'));
    Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
        'email' => $request->email,
        'token' => $request->route('token'),
    ]));
    Fortify::verifyEmailView(fn () => Inertia::render('auth/verify-email'));
    Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

    // Rate limiters
    RateLimiter::for('login', function (Request $request) {
        $throttleKey = Str::transliterate(Str::lower($request->string('email')).'|'.$request->ip());
        return Limit::perMinute(5)->by($throttleKey);
    });

    RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by($request->session()->get('login.id')));
}
```

## Action Classes

Located in `app/Actions/Fortify/`:

### CreateNewUser

```php
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users'],
            'password' => $this->passwordRules(),
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);
    }
}
```

### ResetUserPassword

```php
class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();
    }
}
```

## Two-Factor Authentication

### Enabling 2FA

1. User navigates to `/settings/two-factor-authentication`
2. POST to `/user/two-factor-authentication` generates secret
3. User scans QR code with authenticator app
4. User confirms with code from authenticator
5. Recovery codes are displayed

### Frontend Integration

The `useTwoFactorAuth()` hook manages 2FA state:

```tsx
const {
    isEnabled,
    isConfirmed,
    qrCode,
    setupKey,
    recoveryCodes,
    enable,
    disable,
    confirm,
    regenerateRecoveryCodes,
} = useTwoFactorAuth();
```

### Testing 2FA

```php
// User with 2FA enabled
$user = User::factory()->withTwoFactor()->create();

// Login requires 2FA challenge
$response = $this->post('/login', [
    'email' => $user->email,
    'password' => 'password',
]);
$response->assertRedirect('/two-factor-challenge');
```

## Auth Pages

Frontend auth pages in `resources/js/pages/auth/`:

| Page                       | Route                     | Purpose                                |
| -------------------------- | ------------------------- | -------------------------------------- |
| `login.tsx`                | `/login`                  | User login form                        |
| `register.tsx`             | `/register`               | New user registration                  |
| `forgot-password.tsx`      | `/forgot-password`        | Request password reset                 |
| `reset-password.tsx`       | `/reset-password/{token}` | Set new password                       |
| `verify-email.tsx`         | `/email/verify`           | Email verification prompt              |
| `confirm-password.tsx`     | `/confirm-password`       | Confirm password for sensitive actions |
| `two-factor-challenge.tsx` | `/two-factor-challenge`   | Enter 2FA code                         |

## Middleware

Authentication middleware applied in routes:

```php
Route::middleware(['auth'])->group(function () {
    // Authenticated users only
});

Route::middleware(['auth', 'verified'])->group(function () {
    // Authenticated + email verified
});
```

## Session Configuration

Sessions stored in database by default. Configured in `config/session.php`:

```php
'driver' => env('SESSION_DRIVER', 'database'),
'lifetime' => env('SESSION_LIFETIME', 120),
'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', false),
```

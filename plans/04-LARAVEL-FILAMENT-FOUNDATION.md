# Laravel 12 + Filament v4 Foundation Setup

## Overview

This document outlines the step-by-step process to create the SeminaryOS foundation using Laravel 12 and FilamentPHP v4, optimized for ICDSoft shared hosting.

## Prerequisites

- PHP 8.4 installed locally
- Composer 2.x
- Node.js 20+ and npm (or Bun)
- Git
- MySQL 8.0+ or MariaDB 10.6+

## Step 1: Create Laravel 12 Project

```bash
# Create new Laravel project
composer create-project laravel/laravel seminaryos

# Navigate to project
cd seminaryos

# Verify Laravel version
php artisan --version
# Should show: Laravel Framework 12.x.x
```

## Step 2: Configure Environment

### Update .env

```env
APP_NAME=SeminaryOS
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=seminaryos
DB_USERNAME=root
DB_PASSWORD=

BROADCAST_DRIVER=log
CACHE_DRIVER=database
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_LIFETIME=120

MAIL_MAILER=log
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@seminaryos.test"
MAIL_FROM_NAME="${APP_NAME}"
```

### Generate Application Key

```bash
php artisan key:generate
```

### Create Database

```sql
CREATE DATABASE seminaryos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## Step 3: Install FilamentPHP v4

```bash
# Install Filament Panel Builder
composer require filament/filament:"^4.0"

# Install Filament
php artisan filament:install --panels

# When prompted:
# - Panel ID: admin
# - Create user: yes
# - Name: Super Admin
# - Email: admin@seminaryos.test
# - Password: (choose secure password)
```

### Additional Filament Packages

```bash
# Install useful Filament plugins
composer require filament/spatie-laravel-settings-plugin:"^4.0"
composer require filament/spatie-laravel-media-library-plugin:"^4.0"
```

## Step 4: Configure Database Drivers

### Cache Table

```bash
php artisan cache:table
```

### Queue Table

```bash
php artisan queue:table
php artisan queue:failed-table
php artisan queue:batches-table
```

### Session Table

```bash
php artisan session:table
```

### Run Migrations

```bash
php artisan migrate
```

## Step 5: Create Core Directory Structure

```bash
# Create core directories
mkdir -p app/Core/{Models,Traits,Scopes,Services,Contracts,Exceptions}
mkdir -p app/Modules
mkdir -p app/Http/Livewire
mkdir -p plans
mkdir -p storage/app/public/institutions
mkdir -p storage/app/public/avatars
mkdir -p storage/app/public/documents
```

## Step 6: Create Base Models and Traits

### HasInstitutionScope Trait

Create [`app/Core/Traits/HasInstitutionScope.php`](app/Core/Traits/HasInstitutionScope.php):

```php
<?php

namespace App\Core\Traits;

use App\Core\Scopes\InstitutionScope;
use Illuminate\Database\Eloquent\Model;

trait HasInstitutionScope
{
    protected static function bootHasInstitutionScope(): void
    {
        static::addGlobalScope(new InstitutionScope);
        
        static::creating(function (Model $model) {
            if (!$model->institution_id && auth()->check()) {
                $model->institution_id = auth()->user()->currentInstitution?->id;
            }
        });
    }
    
    public function institution()
    {
        return $this->belongsTo(\App\Models\Institution::class);
    }
}
```

### HasUuid Trait

Create [`app/Core/Traits/HasUuid.php`](app/Core/Traits/HasUuid.php):

```php
<?php

namespace App\Core\Traits;

use Illuminate\Support\Str;

trait HasUuid
{
    protected static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
```

### InstitutionScope Class

Create [`app/Core/Scopes/InstitutionScope.php`](app/Core/Scopes/InstitutionScope.php):

```php
<?php

namespace App\Core\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class InstitutionScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (auth()->check() && auth()->user()->currentInstitution) {
            $builder->where($model->getTable() . '.institution_id', auth()->user()->currentInstitution->id);
        }
    }
}
```

### BaseModel

Create [`app/Core/Models/BaseModel.php`](app/Core/Models/BaseModel.php):

```php
<?php

namespace App\Core\Models;

use App\Core\Traits\HasInstitutionScope;
use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

abstract class BaseModel extends Model
{
    use HasInstitutionScope, HasUuid, SoftDeletes;
    
    protected $guarded = ['id'];
    
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (property_exists($model, 'created_by') && !$model->created_by && auth()->check()) {
                $model->created_by = auth()->id();
            }
        });
        
        static::updating(function ($model) {
            if (property_exists($model, 'updated_by') && auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }
}
```

## Step 7: Create Core Migrations

### Institutions Migration

```bash
php artisan make:migration create_institutions_table
```

Edit [`database/migrations/YYYY_MM_DD_XXXXXX_create_institutions_table.php`](database/migrations):

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('type', ['seminary', 'university', 'college', 'institute']);
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            
            // Contact Information
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('website')->nullable();
            
            // Address
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 100)->nullable();
            
            // Settings
            $table->json('settings')->nullable();
            
            // Branding
            $table->string('logo_path')->nullable();
            $table->string('primary_color', 7)->nullable();
            $table->string('secondary_color', 7)->nullable();
            
            // Limits
            $table->unsignedInteger('max_users')->default(100);
            $table->unsignedInteger('max_students')->default(500);
            $table->unsignedInteger('max_storage_mb')->default(5120);
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('slug');
            $table->index('status');
            $table->index('type');
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('institutions');
    }
};
```

### Institution-User Pivot Migration

```bash
php artisan make:migration create_institution_user_table
```

Edit the migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('role', ['super_admin', 'admin', 'staff', 'faculty', 'student']);
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->timestamps();
            
            $table->unique(['institution_id', 'user_id']);
            $table->index('institution_id');
            $table->index('user_id');
            $table->index('role');
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('institution_user');
    }
};
```

### Update Users Migration

Modify [`database/migrations/0001_01_01_000000_create_users_table.php`](database/migrations):

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->string('phone', 50)->nullable();
    $table->string('avatar_path')->nullable();
    $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
    $table->string('timezone', 50)->default('UTC');
    $table->string('locale', 10)->default('en');
    $table->rememberToken();
    $table->timestamps();
    $table->softDeletes();
    
    $table->index('email');
    $table->index('status');
});
```

### Run Migrations

```bash
php artisan migrate
```

## Step 8: Create Core Models

### Institution Model

Create [`app/Models/Institution.php`](app/Models/Institution.php):

```php
<?php

namespace App\Models;

use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Institution extends Model
{
    use HasUuid, SoftDeletes;
    
    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'type',
        'status',
        'email',
        'phone',
        'website',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'settings',
        'logo_path',
        'primary_color',
        'secondary_color',
        'max_users',
        'max_students',
        'max_storage_mb',
    ];
    
    protected $casts = [
        'settings' => 'array',
        'max_users' => 'integer',
        'max_students' => 'integer',
        'max_storage_mb' => 'integer',
    ];
    
    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role', 'status')
            ->withTimestamps();
    }
    
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
```

### Update User Model

Update [`app/Models/User.php`](app/Models/User.php):

```php
<?php

namespace App\Models;

use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasUuid, SoftDeletes;
    
    protected $fillable = [
        'uuid',
        'name',
        'email',
        'password',
        'phone',
        'avatar_path',
        'status',
        'timezone',
        'locale',
    ];
    
    protected $hidden = [
        'password',
        'remember_token',
    ];
    
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];
    
    public function institutions()
    {
        return $this->belongsToMany(Institution::class)
            ->withPivot('role', 'status')
            ->withTimestamps();
    }
    
    public function currentInstitution()
    {
        return $this->belongsTo(Institution::class, 'current_institution_id');
    }
    
    public function setCurrentInstitution(Institution $institution): void
    {
        $this->current_institution_id = $institution->id;
        session(['current_institution_id' => $institution->id]);
    }
    
    public function getCurrentInstitutionAttribute(): ?Institution
    {
        $institutionId = session('current_institution_id') ?? $this->institutions()->first()?->id;
        
        if ($institutionId) {
            return Institution::find($institutionId);
        }
        
        return null;
    }
    
    public function isSuperAdmin(): bool
    {
        return $this->institutions()
            ->wherePivot('role', 'super_admin')
            ->exists();
    }
    
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->status === 'active' && $this->institutions()->exists();
    }
}
```

## Step 9: Create Middleware

### SetInstitutionContext Middleware

```bash
php artisan make:middleware SetInstitutionContext
```

Edit [`app/Http/Middleware/SetInstitutionContext.php`](app/Http/Middleware/SetInstitutionContext.php):

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetInstitutionContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            $user = auth()->user();
            
            // Get institution from session or default to first
            $institutionId = session('current_institution_id') 
                ?? $user->institutions()->first()?->id;
            
            if ($institutionId) {
                $institution = $user->institutions()
                    ->where('institutions.id', $institutionId)
                    ->first();
                
                if ($institution) {
                    $user->setCurrentInstitution($institution);
                }
            }
        }
        
        return $next($request);
    }
}
```

### Register Middleware

Edit [`bootstrap/app.php`](bootstrap/app.php):

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\SetInstitutionContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
```

## Step 10: Configure Filament Admin Panel

### Update Admin Panel Provider

Edit [`app/Providers/Filament/AdminPanelProvider.php`](app/Providers/Filament/AdminPanelProvider.php):

```php
<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Blue,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                \App\Http\Middleware\SetInstitutionContext::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->brandName('SeminaryOS')
            ->favicon(asset('favicon.ico'))
            ->darkMode(false)
            ->sidebarCollapsibleOnDesktop();
    }
}
```

## Step 11: Create Seeders

### Institution Seeder

```bash
php artisan make:seeder InstitutionSeeder
```

Edit [`database/seeders/InstitutionSeeder.php`](database/seeders/InstitutionSeeder.php):

```php
<?php

namespace Database\Seeders;

use App\Models\Institution;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InstitutionSeeder extends Seeder
{
    public function run(): void
    {
        Institution::create([
            'uuid' => Str::uuid(),
            'name' => 'Demo Seminary',
            'slug' => 'demo-seminary',
            'type' => 'seminary',
            'status' => 'active',
            'email' => 'info@demoseminary.edu',
            'phone' => '+1-555-0100',
            'website' => 'https://demoseminary.edu',
            'city' => 'Springfield',
            'state' => 'IL',
            'country' => 'United States',
            'primary_color' => '#3B82F6',
            'secondary_color' => '#1E40AF',
        ]);
    }
}
```

### Update DatabaseSeeder

Edit [`database/seeders/DatabaseSeeder.php`](database/seeders/DatabaseSeeder.php):

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Institution;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create institution
        $this->call(InstitutionSeeder::class);
        $institution = Institution::first();
        
        // Create super admin user
        $user = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@seminaryos.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        
        // Attach user to institution
        $user->institutions()->attach($institution, [
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }
}
```

### Run Seeders

```bash
php artisan db:seed
```

## Step 12: Create Filament Resources

### Institution Resource

```bash
php artisan make:filament-resource Institution --generate
```

This will create:
- [`app/Filament/Resources/InstitutionResource.php`](app/Filament/Resources/InstitutionResource.php)
- [`app/Filament/Resources/InstitutionResource/Pages/`](app/Filament/Resources/InstitutionResource/Pages/)

## Step 13: Configure Tailwind CSS

### Update tailwind.config.js

Edit [`tailwind.config.js`](tailwind.config.js):

```javascript
import preset from './vendor/filament/support/tailwind.config.preset'

export default {
    presets: [preset],
    content: [
        './app/Filament/**/*.php',
        './resources/views/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
    ],
}
```

### Build Assets

```bash
npm install
npm run build
```

## Step 14: Test the Installation

### Start Development Server

```bash
php artisan serve
```

### Access Admin Panel

Visit: `http://localhost:8000/admin`

Login with:
- Email: `admin@seminaryos.test`
- Password: `password`

### Verify Installation

- [ ] Can access admin panel
- [ ] Can see dashboard
- [ ] Can view institutions
- [ ] User is associated with institution
- [ ] No errors in console

## Step 15: Configure for Shared Hosting

### Update composer.json

Add production optimization scripts:

```json
{
    "scripts": {
        "post-install-cmd": [
            "@php artisan clear-compiled",
            "@php artisan optimize"
        ],
        "post-update-cmd": [
            "@php artisan clear-compiled",
            "@php artisan optimize"
        ],
        "deploy": [
            "@php artisan down",
            "git pull origin main",
            "composer install --no-dev --optimize-autoloader",
            "@php artisan migrate --force",
            "@php artisan config:cache",
            "@php artisan route:cache",
            "@php artisan view:cache",
            "@php artisan up"
        ]
    }
}
```

### Create .env.production Template

Create [`.env.production.example`](.env.production.example):

```env
APP_NAME=SeminaryOS
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://yourdomain.com

LOG_CHANNEL=daily
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

CACHE_DRIVER=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database
```

## Step 16: Create Documentation

### README.md

Create [`README.md`](README.md):

```markdown
# SeminaryOS

University in a Box - A comprehensive seminary and university management system.

## Features

- Multi-institution support
- Role-based access control
- FilamentPHP admin panel
- Shared hosting compatible
- Laravel 12 foundation

## Requirements

- PHP 8.4+
- MySQL 8.0+ or MariaDB 10.6+
- Composer 2.x
- Node.js 20+ (for asset compilation)

## Installation

See [plans/04-LARAVEL-FILAMENT-FOUNDATION.md](plans/04-LARAVEL-FILAMENT-FOUNDATION.md)

## Deployment

See [plans/02-SHARED-HOSTING-DEPLOYMENT.md](plans/02-SHARED-HOSTING-DEPLOYMENT.md)

## Architecture

See [plans/00-ARCHITECTURE-OVERVIEW.md](plans/00-ARCHITECTURE-OVERVIEW.md)

## License

Proprietary
```

## Verification Checklist

- [ ] Laravel 12 installed
- [ ] FilamentPHP v4 installed and configured
- [ ] Database migrations created and run
- [ ] Core models created (Institution, User)
- [ ] Core traits created (HasInstitutionScope, HasUuid)
- [ ] Middleware created (SetInstitutionContext)
- [ ] Seeders created and run
- [ ] Admin panel accessible
- [ ] Multi-institution foundation working
- [ ] Database queue configured
- [ ] Database cache configured
- [ ] Database sessions configured
- [ ] Tailwind CSS configured
- [ ] Assets compiled
- [ ] Documentation created

## Next Steps

After completing this foundation:

1. Create additional Filament resources for Institution management
2. Implement user management with institution roles
3. Add institution switching functionality
4. Create public-facing pages with Livewire
5. Implement business modules (Academic, Student, Finance, etc.)
6. Add comprehensive testing
7. Deploy to ICDSoft shared hosting

---

**Document Version**: 1.0  
**Last Updated**: 2026-06-02  
**Status**: Draft for Review

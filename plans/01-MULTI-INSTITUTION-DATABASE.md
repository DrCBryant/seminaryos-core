# Multi-Institution Database Architecture

## Overview

SeminaryOS uses a **single-database, multi-tenancy** approach where all institutions share the same database but data is isolated via `institution_id` foreign keys and Laravel global scopes.

## Design Principles

1. **Single Database**: All institutions in one database for shared hosting compatibility
2. **Data Isolation**: Automatic filtering via global scopes
3. **Shared Resources**: Users can belong to multiple institutions
4. **Performance**: Proper indexing on institution_id columns
5. **Security**: Middleware + policies enforce access control

## Core Tables

### institutions

The central table for multi-tenancy.

```sql
CREATE TABLE institutions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    type ENUM('seminary', 'university', 'college', 'institute') NOT NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    
    -- Contact Information
    email VARCHAR(255),
    phone VARCHAR(50),
    website VARCHAR(255),
    
    -- Address
    address_line1 VARCHAR(255),
    address_line2 VARCHAR(255),
    city VARCHAR(100),
    state VARCHAR(100),
    postal_code VARCHAR(20),
    country VARCHAR(100),
    
    -- Settings (JSON for flexibility)
    settings JSON,
    
    -- Branding
    logo_path VARCHAR(255),
    primary_color VARCHAR(7),
    secondary_color VARCHAR(7),
    
    -- Subscription/Limits (for future SaaS)
    max_users INT UNSIGNED DEFAULT 100,
    max_students INT UNSIGNED DEFAULT 500,
    max_storage_mb INT UNSIGNED DEFAULT 5120,
    
    -- Timestamps
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    
    INDEX idx_slug (slug),
    INDEX idx_status (status),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### users

Users can belong to multiple institutions with different roles.

```sql
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    avatar_path VARCHAR(255),
    
    -- Status
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    
    -- Preferences
    timezone VARCHAR(50) DEFAULT 'UTC',
    locale VARCHAR(10) DEFAULT 'en',
    
    -- Security
    two_factor_secret TEXT,
    two_factor_recovery_codes TEXT,
    remember_token VARCHAR(100),
    
    -- Timestamps
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    
    INDEX idx_email (email),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### institution_user (Pivot Table)

Links users to institutions with roles.

```sql
CREATE TABLE institution_user (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    institution_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    
    -- Role within this institution
    role ENUM('super_admin', 'admin', 'staff', 'faculty', 'student') NOT NULL,
    
    -- Status
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    
    -- Timestamps
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_institution_user (institution_id, user_id),
    INDEX idx_institution (institution_id),
    INDEX idx_user (user_id),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### roles

Flexible role-based access control.

```sql
CREATE TABLE roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    institution_id BIGINT UNSIGNED NULL, -- NULL = global role
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    description TEXT,
    permissions JSON,
    
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE CASCADE,
    INDEX idx_institution (institution_id),
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### role_user

```sql
CREATE TABLE role_user (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    institution_id BIGINT UNSIGNED NOT NULL,
    
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_role_user_institution (role_id, user_id, institution_id),
    INDEX idx_user (user_id),
    INDEX idx_institution (institution_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Institution-Scoped Tables Pattern

All business data tables follow this pattern:

```sql
CREATE TABLE {table_name} (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    institution_id BIGINT UNSIGNED NOT NULL,
    uuid CHAR(36) UNIQUE NOT NULL,
    
    -- Table-specific columns here
    
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE CASCADE,
    INDEX idx_institution (institution_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Application-enforced integrity rules

The following rules are intentionally enforced in application services and validation rather than through additional database-level enforcement:

1. In [`course_program`](database/migrations/2026_06_02_000014_create_course_program_table.php), the selected `institution_id`, `program_id`, and `course_id` must all belong to the same institution.
2. In [`catalogs`](database/migrations/2026_06_02_000015_create_catalogs_table.php), only one catalog per institution may be marked active at a time.

These rules should be checked in write services, admin form validation, and any bulk import or synchronization workflows.

### Example: Academic Programs

```sql
CREATE TABLE academic_programs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    institution_id BIGINT UNSIGNED NOT NULL,
    uuid CHAR(36) UNIQUE NOT NULL,
    
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) NOT NULL,
    description TEXT,
    degree_type ENUM('certificate', 'diploma', 'associate', 'bachelor', 'master', 'doctoral'),
    duration_years DECIMAL(3,1),
    credits_required INT UNSIGNED,
    status ENUM('active', 'inactive', 'archived') DEFAULT 'active',
    
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE CASCADE,
    INDEX idx_institution (institution_id),
    INDEX idx_code (code),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Global Scope Implementation

### InstitutionScope Trait

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

### InstitutionScope Class

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

## Model Implementation Example

```php
<?php

namespace App\Models;

use App\Core\Traits\HasInstitutionScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AcademicProgram extends Model
{
    use HasInstitutionScope, SoftDeletes;
    
    protected $fillable = [
        'institution_id',
        'uuid',
        'name',
        'code',
        'description',
        'degree_type',
        'duration_years',
        'credits_required',
        'status',
    ];
    
    protected $casts = [
        'duration_years' => 'decimal:1',
        'credits_required' => 'integer',
    ];
    
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
```

## Middleware for Institution Context

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetInstitutionContext
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check()) {
            $user = auth()->user();
            
            // Get institution from subdomain, session, or default
            $institution = $this->resolveInstitution($request, $user);
            
            if ($institution) {
                $user->setCurrentInstitution($institution);
                session(['current_institution_id' => $institution->id]);
            }
        }
        
        return $next($request);
    }
    
    protected function resolveInstitution(Request $request, $user)
    {
        // Priority 1: Explicit institution switch
        if ($request->has('switch_institution')) {
            $institution = $user->institutions()
                ->where('id', $request->input('switch_institution'))
                ->first();
            if ($institution) return $institution;
        }
        
        // Priority 2: Session
        if (session('current_institution_id')) {
            $institution = $user->institutions()
                ->where('id', session('current_institution_id'))
                ->first();
            if ($institution) return $institution;
        }
        
        // Priority 3: User's first institution
        return $user->institutions()->first();
    }
}
```

## Query Examples

### Automatic Scoping

```php
// Automatically filtered by current institution
$programs = AcademicProgram::where('status', 'active')->get();

// Bypass scope when needed (admin operations)
$allPrograms = AcademicProgram::withoutGlobalScope(InstitutionScope::class)->get();

// Query specific institution
$programs = AcademicProgram::withoutGlobalScope(InstitutionScope::class)
    ->where('institution_id', $institutionId)
    ->get();
```

### Cross-Institution Queries (Admin Only)

```php
// Super admin viewing all institutions
if (auth()->user()->isSuperAdmin()) {
    $stats = Institution::withCount(['users', 'students', 'programs'])->get();
}
```

## Performance Optimization

### Essential Indexes

Every institution-scoped table must have:
```sql
INDEX idx_institution (institution_id)
```

### Composite Indexes

For common queries:
```sql
INDEX idx_institution_status (institution_id, status)
INDEX idx_institution_created (institution_id, created_at)
```

### Query Optimization

```php
// Eager load institution relationship
$programs = AcademicProgram::with('institution')->get();

// Use select to limit columns
$programs = AcademicProgram::select('id', 'name', 'code')->get();

// Chunk large datasets
AcademicProgram::chunk(100, function ($programs) {
    // Process programs
});
```

## Data Isolation Testing

### Test Cases Required

1. User can only see data from their current institution
2. User cannot access data from institutions they don't belong to
3. Switching institutions changes visible data
4. Super admin can see all data when scope is bypassed
5. Creating records automatically sets institution_id
6. Foreign key constraints prevent orphaned records

### Example Test

```php
public function test_user_can_only_see_own_institution_programs()
{
    $institution1 = Institution::factory()->create();
    $institution2 = Institution::factory()->create();
    
    $user = User::factory()->create();
    $user->institutions()->attach($institution1);
    
    $program1 = AcademicProgram::factory()->create(['institution_id' => $institution1->id]);
    $program2 = AcademicProgram::factory()->create(['institution_id' => $institution2->id]);
    
    $this->actingAs($user);
    $user->setCurrentInstitution($institution1);
    
    $programs = AcademicProgram::all();
    
    $this->assertTrue($programs->contains($program1));
    $this->assertFalse($programs->contains($program2));
}
```

## Migration Strategy

### Initial Setup

1. Create `institutions` table first
2. Create `users` table
3. Create `institution_user` pivot table
4. Create `roles` and `role_user` tables
5. Seed with default institution and super admin user

### Adding New Tables

Always include:
- `institution_id BIGINT UNSIGNED NOT NULL`
- Foreign key constraint
- Index on `institution_id`
- UUID column for public references

## Future SaaS Considerations

### Database per Institution (Phase 4)

When scaling to full SaaS:
- Keep `institutions` table in central database
- Create separate database per institution
- Use Laravel's dynamic database connections
- Migrate data from shared to separate databases

### Current Design Benefits

- Easy to migrate to separate databases
- UUIDs allow cross-database references
- Clean separation already in place
- No code changes needed for business logic

---

**Document Version**: 1.0  
**Last Updated**: 2026-06-02  
**Status**: Draft for Review

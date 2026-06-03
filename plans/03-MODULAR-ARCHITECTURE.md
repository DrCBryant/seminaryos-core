# Modular Architecture Design

## Overview

SeminaryOS uses a modular architecture that organizes business logic into self-contained modules while maintaining Laravel conventions. This approach enables:

- Clear separation of concerns
- Easy feature addition/removal
- Independent module development
- Simplified testing
- Future SaaS scalability

## Module Organization Strategy

### Core vs. Modules

```
app/
├── Core/                    # Foundation code (shared across modules)
│   ├── Models/              # Base models
│   ├── Traits/              # Shared traits
│   ├── Scopes/              # Global scopes
│   ├── Services/            # Core services
│   ├── Contracts/           # Interfaces
│   └── Exceptions/          # Custom exceptions
│
├── Modules/                 # Business modules (future)
│   ├── Academic/
│   ├── Student/
│   ├── Finance/
│   ├── Library/
│   └── HumanResources/
│
├── Filament/                # Filament admin resources
│   ├── Resources/
│   ├── Pages/
│   ├── Widgets/
│   └── Clusters/
│
├── Http/
│   ├── Controllers/         # Public controllers
│   ├── Middleware/
│   └── Livewire/            # Public Livewire components
│
├── Models/                  # Eloquent models
├── Policies/                # Authorization policies
└── Providers/               # Service providers
```

## Module Structure Pattern

Each module follows a consistent structure:

```
app/Modules/Academic/
├── Models/                  # Module-specific models
│   ├── Program.php
│   ├── Course.php
│   ├── Enrollment.php
│   └── Grade.php
│
├── Services/                # Business logic services
│   ├── ProgramService.php
│   ├── EnrollmentService.php
│   └── GradeCalculationService.php
│
├── Actions/                 # Single-purpose action classes
│   ├── CreateProgram.php
│   ├── EnrollStudent.php
│   └── CalculateFinalGrade.php
│
├── DataTransferObjects/     # DTOs for data passing
│   ├── ProgramData.php
│   └── EnrollmentData.php
│
├── Enums/                   # Module-specific enums
│   ├── DegreeType.php
│   ├── GradeScale.php
│   └── EnrollmentStatus.php
│
├── Policies/                # Authorization policies
│   ├── ProgramPolicy.php
│   └── EnrollmentPolicy.php
│
├── Observers/               # Model observers
│   └── EnrollmentObserver.php
│
├── Events/                  # Domain events
│   ├── StudentEnrolled.php
│   └── GradeSubmitted.php
│
├── Listeners/               # Event listeners
│   ├── SendEnrollmentConfirmation.php
│   └── UpdateTranscript.php
│
├── Jobs/                    # Queued jobs
│   ├── ProcessBulkEnrollment.php
│   └── GenerateTranscripts.php
│
├── Rules/                   # Validation rules
│   └── ValidCoursePrerequisite.php
│
├── Filament/                # Filament resources for this module
│   ├── Resources/
│   │   ├── ProgramResource.php
│   │   └── CourseResource.php
│   └── Widgets/
│       └── AcademicStatsWidget.php
│
├── Database/
│   ├── Migrations/          # Module migrations
│   ├── Seeders/             # Module seeders
│   └── Factories/           # Model factories
│
├── Tests/                   # Module tests
│   ├── Unit/
│   └── Feature/
│
├── Routes/                  # Module routes (if needed)
│   ├── web.php
│   └── api.php
│
├── Config/                  # Module configuration
│   └── academic.php
│
└── AcademicServiceProvider.php  # Module service provider
```

## Module Service Provider Pattern

Each module has a service provider for registration and bootstrapping:

```php
<?php

namespace App\Modules\Academic;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class AcademicServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register module services
        $this->app->singleton(Services\ProgramService::class);
        $this->app->singleton(Services\EnrollmentService::class);
        
        // Merge module configuration
        $this->mergeConfigFrom(
            __DIR__.'/Config/academic.php', 'academic'
        );
    }
    
    public function boot(): void
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        
        // Register routes
        $this->registerRoutes();
        
        // Register policies
        $this->registerPolicies();
        
        // Register observers
        $this->registerObservers();
        
        // Register event listeners
        $this->registerEventListeners();
    }
    
    protected function registerRoutes(): void
    {
        if (file_exists(__DIR__.'/Routes/web.php')) {
            Route::middleware('web')
                ->group(__DIR__.'/Routes/web.php');
        }
        
        if (file_exists(__DIR__.'/Routes/api.php')) {
            Route::middleware('api')
                ->prefix('api')
                ->group(__DIR__.'/Routes/api.php');
        }
    }
    
    protected function registerPolicies(): void
    {
        // Policies are auto-discovered by Laravel
    }
    
    protected function registerObservers(): void
    {
        Models\Enrollment::observe(Observers\EnrollmentObserver::class);
    }
    
    protected function registerEventListeners(): void
    {
        // Event listeners are auto-discovered by Laravel
    }
}
```

### Register Module Provider

Add to [`config/app.php`](config/app.php):

```php
'providers' => [
    // ...
    App\Modules\Academic\AcademicServiceProvider::class,
    App\Modules\Student\StudentServiceProvider::class,
    App\Modules\Finance\FinanceServiceProvider::class,
    // ...
],
```

## Core Foundation Components

### Base Model

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
            if (!$model->created_by && auth()->check()) {
                $model->created_by = auth()->id();
            }
        });
        
        static::updating(function ($model) {
            if (!$model->updated_by && auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }
}
```

### Base Service

```php
<?php

namespace App\Core\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

abstract class BaseService
{
    protected function transaction(callable $callback)
    {
        return DB::transaction($callback);
    }
    
    protected function create(string $modelClass, array $data): Model
    {
        return $this->transaction(function () use ($modelClass, $data) {
            return $modelClass::create($data);
        });
    }
    
    protected function update(Model $model, array $data): Model
    {
        return $this->transaction(function () use ($model, $data) {
            $model->update($data);
            return $model->fresh();
        });
    }
    
    protected function delete(Model $model): bool
    {
        return $this->transaction(function () use ($model) {
            return $model->delete();
        });
    }
}
```

### Base Action

```php
<?php

namespace App\Core\Actions;

abstract class BaseAction
{
    abstract public function execute(...$params);
    
    public function __invoke(...$params)
    {
        return $this->execute(...$params);
    }
}
```

### Base DTO

```php
<?php

namespace App\Core\DataTransferObjects;

use Illuminate\Contracts\Support\Arrayable;

abstract class BaseDTO implements Arrayable
{
    public function toArray(): array
    {
        return get_object_vars($this);
    }
    
    public static function fromArray(array $data): static
    {
        return new static(...$data);
    }
    
    public static function fromRequest($request): static
    {
        return static::fromArray($request->validated());
    }
}
```

## Example Module Implementation

### Academic Module - Program Model

```php
<?php

namespace App\Modules\Academic\Models;

use App\Core\Models\BaseModel;
use App\Modules\Academic\Enums\DegreeType;

class Program extends BaseModel
{
    protected $table = 'academic_programs';
    
    protected $fillable = [
        'institution_id',
        'name',
        'code',
        'description',
        'degree_type',
        'duration_years',
        'credits_required',
        'status',
    ];
    
    protected $casts = [
        'degree_type' => DegreeType::class,
        'duration_years' => 'decimal:1',
        'credits_required' => 'integer',
    ];
    
    // Relationships
    public function courses()
    {
        return $this->hasMany(Course::class);
    }
    
    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }
    
    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
    
    // Accessors
    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }
}
```

### Academic Module - Program Service

```php
<?php

namespace App\Modules\Academic\Services;

use App\Core\Services\BaseService;
use App\Modules\Academic\Models\Program;
use App\Modules\Academic\DataTransferObjects\ProgramData;
use Illuminate\Database\Eloquent\Collection;

class ProgramService extends BaseService
{
    public function getAllPrograms(): Collection
    {
        return Program::with('courses')->active()->get();
    }
    
    public function getProgramById(int $id): ?Program
    {
        return Program::with('courses', 'enrollments')->find($id);
    }
    
    public function createProgram(ProgramData $data): Program
    {
        return $this->create(Program::class, $data->toArray());
    }
    
    public function updateProgram(Program $program, ProgramData $data): Program
    {
        return $this->update($program, $data->toArray());
    }
    
    public function deleteProgram(Program $program): bool
    {
        if ($program->enrollments()->exists()) {
            throw new \Exception('Cannot delete program with active enrollments');
        }
        
        return $this->delete($program);
    }
    
    public function archiveProgram(Program $program): Program
    {
        return $this->update($program, ['status' => 'archived']);
    }
}
```

### Academic Module - Create Program Action

```php
<?php

namespace App\Modules\Academic\Actions;

use App\Core\Actions\BaseAction;
use App\Modules\Academic\Models\Program;
use App\Modules\Academic\DataTransferObjects\ProgramData;
use App\Modules\Academic\Events\ProgramCreated;

class CreateProgram extends BaseAction
{
    public function execute(ProgramData $data): Program
    {
        $program = Program::create($data->toArray());
        
        event(new ProgramCreated($program));
        
        return $program;
    }
}
```

### Academic Module - Program DTO

```php
<?php

namespace App\Modules\Academic\DataTransferObjects;

use App\Core\DataTransferObjects\BaseDTO;
use App\Modules\Academic\Enums\DegreeType;

class ProgramData extends BaseDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $code,
        public readonly ?string $description,
        public readonly DegreeType $degree_type,
        public readonly float $duration_years,
        public readonly int $credits_required,
        public readonly string $status = 'active',
    ) {}
}
```

### Academic Module - Degree Type Enum

```php
<?php

namespace App\Modules\Academic\Enums;

enum DegreeType: string
{
    case CERTIFICATE = 'certificate';
    case DIPLOMA = 'diploma';
    case ASSOCIATE = 'associate';
    case BACHELOR = 'bachelor';
    case MASTER = 'master';
    case DOCTORAL = 'doctoral';
    
    public function label(): string
    {
        return match($this) {
            self::CERTIFICATE => 'Certificate',
            self::DIPLOMA => 'Diploma',
            self::ASSOCIATE => 'Associate Degree',
            self::BACHELOR => 'Bachelor\'s Degree',
            self::MASTER => 'Master\'s Degree',
            self::DOCTORAL => 'Doctoral Degree',
        };
    }
}
```

## Filament Integration

### Module Filament Resource

```php
<?php

namespace App\Modules\Academic\Filament\Resources;

use App\Modules\Academic\Models\Program;
use App\Modules\Academic\Enums\DegreeType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProgramResource extends Resource
{
    protected static ?string $model = Program::class;
    
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    
    protected static ?string $navigationGroup = 'Academic';
    
    protected static ?int $navigationSort = 1;
    
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Program Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('code')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true),
                        
                        Forms\Components\Select::make('degree_type')
                            ->options(DegreeType::class)
                            ->required(),
                        
                        Forms\Components\TextInput::make('duration_years')
                            ->numeric()
                            ->step(0.5)
                            ->required(),
                        
                        Forms\Components\TextInput::make('credits_required')
                            ->numeric()
                            ->required(),
                        
                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'archived' => 'Archived',
                            ])
                            ->default('active')
                            ->required(),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('Description')
                    ->schema([
                        Forms\Components\RichEditor::make('description')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
    
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('degree_type')
                    ->badge()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('duration_years')
                    ->suffix(' years')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('credits_required')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'warning',
                        'archived' => 'danger',
                    })
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('degree_type')
                    ->options(DegreeType::class),
                
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'archived' => 'Archived',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrograms::route('/'),
            'create' => Pages\CreateProgram::route('/create'),
            'edit' => Pages\EditProgram::route('/{record}/edit'),
        ];
    }
}
```

## Module Communication

### Events and Listeners

Modules communicate via events:

```php
// Academic Module - Event
namespace App\Modules\Academic\Events;

class StudentEnrolled
{
    public function __construct(
        public readonly Enrollment $enrollment
    ) {}
}

// Finance Module - Listener
namespace App\Modules\Finance\Listeners;

use App\Modules\Academic\Events\StudentEnrolled;
use App\Modules\Finance\Services\InvoiceService;

class CreateEnrollmentInvoice
{
    public function __construct(
        private InvoiceService $invoiceService
    ) {}
    
    public function handle(StudentEnrolled $event): void
    {
        $this->invoiceService->createEnrollmentInvoice($event->enrollment);
    }
}
```

### Service Dependencies

Modules can depend on other module services via dependency injection:

```php
namespace App\Modules\Student\Services;

use App\Modules\Academic\Services\ProgramService;
use App\Modules\Finance\Services\InvoiceService;

class StudentEnrollmentService
{
    public function __construct(
        private ProgramService $programService,
        private InvoiceService $invoiceService
    ) {}
    
    public function enrollStudent(Student $student, int $programId): Enrollment
    {
        $program = $this->programService->getProgramById($programId);
        
        // Create enrollment
        $enrollment = Enrollment::create([
            'student_id' => $student->id,
            'program_id' => $program->id,
            'status' => 'active',
        ]);
        
        // Create invoice
        $this->invoiceService->createEnrollmentInvoice($enrollment);
        
        return $enrollment;
    }
}
```

## Testing Strategy

### Module Unit Tests

```php
<?php

namespace App\Modules\Academic\Tests\Unit;

use App\Modules\Academic\Models\Program;
use App\Modules\Academic\Enums\DegreeType;
use Tests\TestCase;

class ProgramTest extends TestCase
{
    public function test_can_create_program(): void
    {
        $program = Program::factory()->create([
            'name' => 'Master of Divinity',
            'code' => 'MDIV',
            'degree_type' => DegreeType::MASTER,
        ]);
        
        $this->assertDatabaseHas('academic_programs', [
            'name' => 'Master of Divinity',
            'code' => 'MDIV',
        ]);
    }
    
    public function test_program_has_institution_scope(): void
    {
        $institution1 = Institution::factory()->create();
        $institution2 = Institution::factory()->create();
        
        $program1 = Program::factory()->create(['institution_id' => $institution1->id]);
        $program2 = Program::factory()->create(['institution_id' => $institution2->id]);
        
        $this->actingAs($user = User::factory()->create());
        $user->institutions()->attach($institution1);
        $user->setCurrentInstitution($institution1);
        
        $programs = Program::all();
        
        $this->assertTrue($programs->contains($program1));
        $this->assertFalse($programs->contains($program2));
    }
}
```

### Module Feature Tests

```php
<?php

namespace App\Modules\Academic\Tests\Feature;

use App\Modules\Academic\Models\Program;
use App\Modules\Academic\Services\ProgramService;
use Tests\TestCase;

class ProgramServiceTest extends TestCase
{
    private ProgramService $service;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProgramService::class);
    }
    
    public function test_can_create_program_via_service(): void
    {
        $data = new ProgramData(
            name: 'Master of Divinity',
            code: 'MDIV',
            description: 'A comprehensive theological degree',
            degree_type: DegreeType::MASTER,
            duration_years: 3.0,
            credits_required: 90
        );
        
        $program = $this->service->createProgram($data);
        
        $this->assertInstanceOf(Program::class, $program);
        $this->assertEquals('Master of Divinity', $program->name);
    }
}
```

## Module Discovery and Loading

### Automatic Module Registration

Create a module discovery service:

```php
<?php

namespace App\Core\Services;

use Illuminate\Support\Facades\File;

class ModuleDiscoveryService
{
    public function discoverModules(): array
    {
        $modulePath = app_path('Modules');
        
        if (!File::exists($modulePath)) {
            return [];
        }
        
        $modules = [];
        
        foreach (File::directories($modulePath) as $directory) {
            $moduleName = basename($directory);
            $providerClass = "App\\Modules\\{$moduleName}\\{$moduleName}ServiceProvider";
            
            if (class_exists($providerClass)) {
                $modules[] = $providerClass;
            }
        }
        
        return $modules;
    }
}
```

### Register in AppServiceProvider

```php
<?php

namespace App\Providers;

use App\Core\Services\ModuleDiscoveryService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Auto-discover and register modules
        $moduleDiscovery = new ModuleDiscoveryService();
        
        foreach ($moduleDiscovery->discoverModules() as $provider) {
            $this->app->register($provider);
        }
    }
}
```

## Benefits of This Architecture

1. **Maintainability**: Clear separation of concerns
2. **Scalability**: Easy to add new modules
3. **Testability**: Isolated module testing
4. **Reusability**: Shared core components
5. **Team Collaboration**: Multiple developers can work on different modules
6. **Future-Proof**: Ready for SaaS expansion
7. **Laravel Conventions**: Follows Laravel best practices
8. **Filament Integration**: Seamless admin panel integration

---

**Document Version**: 1.0  
**Last Updated**: 2026-06-02  
**Status**: Draft for Review

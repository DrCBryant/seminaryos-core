<?php

namespace App\Providers;

use App\Models\Faculty;
use App\Models\Student;
use App\Models\User;
use App\Support\Portals\AcademicIdentityAccess;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('view-own-student-identity', fn (User $user, Student $student): bool => AcademicIdentityAccess::student($user)?->is($student) === true);
        Gate::define('view-own-academic-record', AcademicIdentityAccess::viewAcademicRecord(...));
        Gate::define('view-own-faculty-identity', fn (User $user, Faculty $faculty): bool => AcademicIdentityAccess::faculty($user)?->is($faculty) === true);
        Gate::define('view-assigned-teaching-assignment', AcademicIdentityAccess::viewTeachingAssignment(...));
        Gate::define('view-assigned-teaching-section', AcademicIdentityAccess::viewTeachingSection(...));
    }
}

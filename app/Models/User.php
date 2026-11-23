<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\Portals\AcademicIdentityAccess;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'uuid',
    'current_institution_id',
    'phone',
    'avatar_path',
    'status',
    'timezone',
    'locale',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const INSTITUTION_ADMIN_ROLES = ['admin', 'super_admin'];

    protected $attributes = ['status' => 'active'];

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->deleted_at === null;
    }

    public function activeInstitutionMembership(?int $institutionId = null, bool $lock = false): ?Pivot
    {
        $institutionId ??= $this->current_institution_id;

        if (! $this->isActive() || $institutionId === null || (int) $this->current_institution_id !== (int) $institutionId) {
            return null;
        }

        $institution = $this->institutions()->whereKey($institutionId)
            ->where('institutions.status', 'active')->wherePivot('status', 'active')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->first(['institutions.id']);

        return $institution?->pivot;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->canAccessRegistrar(),
            'faculty' => AcademicIdentityAccess::faculty($this) !== null,
            'student' => AcademicIdentityAccess::student($this) !== null,
            default => false,
        };
    }

    public function canAccessRegistrar(?int $institutionId = null): bool
    {
        return in_array(
            $this->activeInstitutionMembership($institutionId)?->role,
            [...self::INSTITUTION_ADMIN_ROLES, 'staff'],
            true,
        );
    }

    public function currentInstitution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'current_institution_id');
    }

    public function institutions(): BelongsToMany
    {
        return $this->belongsToMany(Institution::class)
            ->withPivot('id', 'role', 'status')
            ->withTimestamps();
    }

    public function approvedStudentRequirementEvidence(): HasMany
    {
        return $this->hasMany(StudentRequirementEvidence::class, 'approved_by_user_id');
    }

    public function approvedProgramRequirementSubstitutions(): HasMany
    {
        return $this->hasMany(ProgramRequirementSubstitution::class, 'approved_by_user_id');
    }

    public function assessedStudentMasterAssessmentAttempts(): HasMany
    {
        return $this->hasMany(StudentMasterAssessmentAttempt::class, 'assessor_user_id');
    }

    public function reviewedStudentSectionSubmissions(): HasMany
    {
        return $this->hasMany(StudentSectionSubmission::class, 'reviewer_user_id');
    }

    public function approvedStudentSectionManualCompletions(): HasMany
    {
        return $this->hasMany(StudentSectionManualCompletion::class, 'approver_user_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'deleted_at' => 'datetime',
        ];
    }
}

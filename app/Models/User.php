<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function currentInstitution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'current_institution_id');
    }

    public function institutions(): BelongsToMany
    {
        return $this->belongsToMany(Institution::class)
            ->withPivot('role', 'status')
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

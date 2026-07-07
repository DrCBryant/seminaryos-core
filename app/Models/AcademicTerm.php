<?php

namespace App\Models;

use App\Core\Models\BaseModel;
use App\Core\Traits\HasInstitutionScope;
use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcademicTerm extends BaseModel
{
    use HasInstitutionScope, HasUuid, SoftDeletes;

    public const TERM_TYPE_OPTIONS = [
        'fall' => 'Fall',
        'spring' => 'Spring',
        'summer' => 'Summer',
        'winter' => 'Winter',
        'intensive' => 'Intensive',
        'module' => 'Module',
        'custom' => 'Custom',
    ];

    protected $fillable = [
        'institution_id',
        'uuid',
        'name',
        'code',
        'academic_year',
        'term_type',
        'start_date',
        'end_date',
        'registration_start_date',
        'registration_end_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'registration_start_date' => 'date',
        'registration_end_date' => 'date',
    ];

    public static function termTypeOptions(): array
    {
        return self::TERM_TYPE_OPTIONS;
    }

    public function getDisplayLabelAttribute(): string
    {
        return "{$this->name} ({$this->academic_year})";
    }

    public function scopeOrderedForSelection(Builder $query): Builder
    {
        return $query
            ->orderByDesc('academic_year')
            ->orderBy('start_date');
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function courseEnrollments()
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    public function academicRecords(): HasMany
    {
        return $this->hasMany(AcademicRecord::class);
    }

    public function courseOfferings(): HasMany
    {
        return $this->hasMany(CourseOffering::class);
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class);
    }
}

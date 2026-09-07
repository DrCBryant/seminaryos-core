<?php

namespace App\Models;

use App\Core\Models\BaseModel;
use App\Core\Traits\HasInstitutionScope;
use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends BaseModel
{
    use HasInstitutionScope, HasUuid, SoftDeletes;

    public const SCHEDULING_BASIS_TERM = 'term';

    public const SCHEDULING_BASIS_COMPLETION_DATE = 'completion_date';

    public const SCHEDULING_BASIS_OPTIONS = [
        self::SCHEDULING_BASIS_TERM => 'Term-bound',
        self::SCHEDULING_BASIS_COMPLETION_DATE => 'Completion-date grouped',
    ];

    protected $attributes = [
        'scheduling_basis' => self::SCHEDULING_BASIS_TERM,
    ];

    protected $fillable = [
        'uuid',
        'institution_id',
        'code',
        'title',
        'slug',
        'description',
        'credit_hours',
        'delivery_method',
        'scheduling_basis',
        'status',
        'is_public',
        'seo_title',
        'seo_description',
        'published_at',
    ];

    public static function schedulingBasisOptions(): array
    {
        return self::SCHEDULING_BASIS_OPTIONS;
    }

    public function isCompletionDateGrouped(): bool
    {
        return $this->scheduling_basis === self::SCHEDULING_BASIS_COMPLETION_DATE;
    }

    protected $casts = [
        'credit_hours' => 'decimal:2',
        'is_public' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function programs()
    {
        return $this->belongsToMany(Program::class, 'course_program')
            ->using(CourseProgram::class)
            ->withPivot(['id', 'institution_id', 'requirement_type', 'sequence_order', 'credits_applied'])
            ->withTimestamps();
    }

    public function coursePrograms()
    {
        return $this->hasMany(CourseProgram::class);
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

    public function programRequirements(): HasMany
    {
        return $this->hasMany(ProgramRequirement::class);
    }

    public function courseOfferings(): HasMany
    {
        return $this->hasMany(CourseOffering::class);
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class);
    }

    public function substituteProgramRequirementSubstitutions(): HasMany
    {
        return $this->hasMany(ProgramRequirementSubstitution::class, 'substitute_course_id');
    }
}

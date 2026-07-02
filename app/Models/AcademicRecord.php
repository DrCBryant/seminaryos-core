<?php

namespace App\Models;

use App\Core\Models\BaseModel;
use App\Core\Traits\HasInstitutionScope;
use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcademicRecord extends BaseModel
{
    use HasInstitutionScope, HasUuid, SoftDeletes;

    protected $fillable = [
        'institution_id',
        'uuid',
        'student_id',
        'course_id',
        'academic_term_id',
        'course_enrollment_id',
        'course_code',
        'course_title',
        'credits_attempted',
        'credits_earned',
        'final_grade',
        'grade_points',
        'grade_scale_id',
        'grade_value_id',
        'grade_label',
        'earns_credit',
        'affects_gpa',
        'is_passing',
        'status',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'credits_attempted' => 'decimal:2',
        'credits_earned' => 'decimal:2',
        'grade_points' => 'decimal:2',
        'earns_credit' => 'boolean',
        'affects_gpa' => 'boolean',
        'is_passing' => 'boolean',
        'completed_at' => 'date',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function gradeScale(): BelongsTo
    {
        return $this->belongsTo(GradeScale::class);
    }

    public function gradeValue(): BelongsTo
    {
        return $this->belongsTo(GradeValue::class);
    }

    public function courseEnrollment(): BelongsTo
    {
        return $this->belongsTo(CourseEnrollment::class);
    }

    public function programRequirementSubstitutions(): HasMany
    {
        return $this->hasMany(ProgramRequirementSubstitution::class);
    }
}

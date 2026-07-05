<?php

namespace App\Models;

use App\Core\Models\BaseModel;
use App\Core\Traits\HasInstitutionScope;
use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseEnrollment extends BaseModel
{
    use HasInstitutionScope, HasUuid, SoftDeletes;

    protected $fillable = [
        'institution_id',
        'uuid',
        'student_id',
        'course_id',
        'academic_term_id',
        'course_offering_id',
        'status',
        'enrolled_at',
        'completed_at',
        'final_grade',
        'completion_progress_basis',
        'completion_progress_status',
        'completion_evidence_summary',
        'completion_override_reason',
        'completion_reviewed_at',
        'completion_reviewed_by_user_id',
        'notes',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'completed_at' => 'datetime',
        'completion_reviewed_at' => 'datetime',
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

    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    public function completionReviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completion_reviewed_by_user_id');
    }

    public function academicRecord(): HasOne
    {
        return $this->hasOne(AcademicRecord::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function studentMasterAssessmentAttempts(): HasMany
    {
        return $this->hasMany(StudentMasterAssessmentAttempt::class);
    }

    public function studentSectionSubmissions(): HasMany
    {
        return $this->hasMany(StudentSectionSubmission::class);
    }

    public function studentSectionManualCompletions(): HasMany
    {
        return $this->hasMany(StudentSectionManualCompletion::class);
    }
}

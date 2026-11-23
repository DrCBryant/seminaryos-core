<?php

namespace App\Models;

use App\Core\Models\BaseModel;
use App\Core\Traits\HasInstitutionScope;
use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StudentMasterAssessmentAttempt extends BaseModel
{
    use HasInstitutionScope, HasUuid;

    public const STATUS_NOT_STARTED = 'not_started';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_PASSED = 'passed';

    public const STATUS_REVISION_NEEDED = 'revision_needed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUS_OPTIONS = [
        self::STATUS_NOT_STARTED => 'Not Started',
        self::STATUS_SUBMITTED => 'Submitted',
        self::STATUS_PASSED => 'Passed',
        self::STATUS_REVISION_NEEDED => 'Revision Needed',
        self::STATUS_FAILED => 'Failed',
        self::STATUS_ARCHIVED => 'Archived',
    ];

    public const MENTOR_RESULTS = [
        self::STATUS_PASSED => self::STATUS_OPTIONS[self::STATUS_PASSED],
        self::STATUS_REVISION_NEEDED => self::STATUS_OPTIONS[self::STATUS_REVISION_NEEDED],
        self::STATUS_FAILED => self::STATUS_OPTIONS[self::STATUS_FAILED],
    ];

    protected $fillable = [
        'institution_id',
        'uuid',
        'master_assessment_id',
        'course_offering_id',
        'course_enrollment_id',
        'student_id',
        'status',
        'submitted_at',
        'assessed_at',
        'assessor_user_id',
        'assessor_notes',
        'notes',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'assessed_at' => 'datetime',
    ];

    public function mentorResult(): HasOne
    {
        return $this->hasOne(MentorAssessmentResult::class, 'attempt_id');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function masterAssessment(): BelongsTo
    {
        return $this->belongsTo(MasterAssessment::class);
    }

    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    public function courseEnrollment(): BelongsTo
    {
        return $this->belongsTo(CourseEnrollment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function assessorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessor_user_id');
    }
}

<?php

namespace App\Models;

use App\Core\Models\BaseModel;
use App\Core\Traits\HasInstitutionScope;
use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentSectionSubmission extends BaseModel
{
    use HasInstitutionScope, HasUuid;

    public const STATUS_NOT_STARTED = 'not_started';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REVISION_NEEDED = 'revision_needed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_WAIVED = 'waived';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUS_OPTIONS = [
        self::STATUS_NOT_STARTED => 'Not Started',
        self::STATUS_SUBMITTED => 'Submitted',
        self::STATUS_ACCEPTED => 'Accepted',
        self::STATUS_REVISION_NEEDED => 'Revision Needed',
        self::STATUS_REJECTED => 'Rejected',
        self::STATUS_WAIVED => 'Waived',
        self::STATUS_ARCHIVED => 'Archived',
    ];

    protected $fillable = [
        'institution_id',
        'uuid',
        'course_offering_id',
        'section_assignment_id',
        'course_enrollment_id',
        'student_id',
        'status',
        'submitted_at',
        'reviewed_at',
        'reviewer_user_id',
        'submission_text',
        'submission_reference',
        'score',
        'passed',
        'reviewer_notes',
        'notes',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'score' => 'decimal:2',
        'passed' => 'boolean',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    public function sectionAssignment(): BelongsTo
    {
        return $this->belongsTo(SectionAssignment::class);
    }

    public function courseEnrollment(): BelongsTo
    {
        return $this->belongsTo(CourseEnrollment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function reviewerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }
}

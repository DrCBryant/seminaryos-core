<?php

namespace App\Models;

use App\Core\Models\BaseModel;
use App\Core\Traits\HasInstitutionScope;
use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentSectionManualCompletion extends BaseModel
{
    use HasInstitutionScope, HasUuid;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REVISION_NEEDED = 'revision_needed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_WAIVED = 'waived';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUS_OPTIONS = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_REVISION_NEEDED => 'Revision Needed',
        self::STATUS_REJECTED => 'Rejected',
        self::STATUS_WAIVED => 'Waived',
        self::STATUS_ARCHIVED => 'Archived',
    ];

    protected $fillable = [
        'institution_id',
        'uuid',
        'course_offering_id',
        'course_enrollment_id',
        'student_id',
        'status',
        'approved_at',
        'approver_user_id',
        'completion_summary',
        'evidence_reference',
        'approver_notes',
        'notes',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
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

    public function approverUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}

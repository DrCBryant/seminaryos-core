<?php

namespace App\Models;

use App\Core\Models\BaseModel;
use App\Core\Traits\HasInstitutionScope;
use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeachingAssignment extends BaseModel
{
    use HasInstitutionScope, HasUuid, SoftDeletes;

    public const ROLE_OPTIONS = [
        'primary_instructor' => 'Primary Instructor',
        'co_instructor' => 'Co-Instructor',
        'teaching_assistant' => 'Teaching Assistant',
        'mentor' => 'Mentor',
        'supervisor' => 'Supervisor',
        'guest_lecturer' => 'Guest Lecturer',
    ];

    public const STATUS_OPTIONS = [
        'assigned' => 'Assigned',
        'active' => 'Active',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'archived' => 'Archived',
    ];

    protected $fillable = [
        'institution_id',
        'uuid',
        'faculty_id',
        'course_id',
        'academic_term_id',
        'course_offering_id',
        'role',
        'status',
        'assigned_at',
        'ended_at',
        'notes',
    ];

    protected $casts = [
        'assigned_at' => 'date',
        'ended_at' => 'date',
    ];

    public static function roleOptions(): array
    {
        return self::ROLE_OPTIONS;
    }

    public static function statusOptions(): array
    {
        return self::STATUS_OPTIONS;
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
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
}

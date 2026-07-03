<?php

namespace App\Models;

use App\Core\Models\BaseModel;
use App\Core\Traits\HasInstitutionScope;
use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends BaseModel
{
    use HasInstitutionScope, HasUuid;

    public const STATUS_OPTIONS = [
        'present' => 'Present',
        'absent' => 'Absent',
        'tardy' => 'Tardy',
        'excused' => 'Excused',
        'left_early' => 'Left Early',
        'not_marked' => 'Not Marked',
    ];

    protected $fillable = [
        'institution_id',
        'uuid',
        'attendance_session_id',
        'course_offering_id',
        'course_enrollment_id',
        'student_id',
        'status',
        'minutes_present',
        'marked_at',
        'notes',
    ];

    protected $casts = [
        'minutes_present' => 'integer',
        'marked_at' => 'datetime',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function attendanceSession(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class);
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
}

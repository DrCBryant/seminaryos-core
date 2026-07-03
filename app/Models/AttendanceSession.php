<?php

namespace App\Models;

use App\Core\Models\BaseModel;
use App\Core\Traits\HasInstitutionScope;
use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSession extends BaseModel
{
    use HasInstitutionScope, HasUuid;

    public const STATUS_OPTIONS = [
        'planned' => 'Planned',
        'held' => 'Held',
        'cancelled' => 'Cancelled',
        'archived' => 'Archived',
    ];

    protected $fillable = [
        'institution_id',
        'uuid',
        'course_offering_id',
        'academic_term_id',
        'course_id',
        'session_date',
        'start_time',
        'end_time',
        'topic',
        'status',
        'notes',
    ];

    protected $casts = [
        'session_date' => 'date',
        'start_time' => 'datetime:H:i:s',
        'end_time' => 'datetime:H:i:s',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }
}

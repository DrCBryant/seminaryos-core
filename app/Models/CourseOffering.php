<?php

namespace App\Models;

use App\Core\Models\BaseModel;
use App\Core\Traits\HasInstitutionScope;
use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseOffering extends BaseModel
{
    use HasInstitutionScope, HasUuid;

    public const DELIVERY_MODE_OPTIONS = [
        'in_person' => 'In Person',
        'online' => 'Online',
        'hybrid' => 'Hybrid',
        'directed_study' => 'Directed Study',
        'intensive' => 'Intensive',
        'asynchronous' => 'Asynchronous',
        'other' => 'Other',
    ];

    public const STATUS_OPTIONS = [
        'planned' => 'Planned',
        'open' => 'Open',
        'closed' => 'Closed',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'archived' => 'Archived',
    ];

    protected $fillable = [
        'institution_id',
        'uuid',
        'course_id',
        'academic_term_id',
        'section_code',
        'title',
        'delivery_mode',
        'location',
        'meeting_pattern',
        'start_date',
        'end_date',
        'capacity',
        'status',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'capacity' => 'integer',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }
}

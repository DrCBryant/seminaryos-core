<?php

namespace App\Models;

use App\Core\Models\BaseModel;
use App\Core\Traits\HasInstitutionScope;
use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterAssessment extends BaseModel
{
    use HasInstitutionScope, HasUuid;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUS_OPTIONS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_ARCHIVED => 'Archived',
    ];

    protected $fillable = [
        'institution_id',
        'uuid',
        'course_offering_id',
        'title',
        'description',
        'competency_outcomes',
        'rubric',
        'passing_threshold',
        'status',
        'notes',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    public function studentMasterAssessmentAttempts(): HasMany
    {
        return $this->hasMany(StudentMasterAssessmentAttempt::class);
    }
}

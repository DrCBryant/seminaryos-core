<?php

namespace App\Models;

use App\Core\Models\BaseModel;
use App\Core\Traits\HasInstitutionScope;
use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SectionAssignment extends BaseModel
{
    use HasInstitutionScope, HasUuid;

    public const TYPE_ASSIGNMENT = 'assignment';

    public const TYPE_REFLECTION = 'reflection';

    public const TYPE_READING_RESPONSE = 'reading_response';

    public const TYPE_QUIZ = 'quiz';

    public const TYPE_EXAM = 'exam';

    public const TYPE_PROJECT = 'project';

    public const TYPE_PRACTICUM_LOG = 'practicum_log';

    public const TYPE_FIELD_REPORT = 'field_report';

    public const TYPE_PORTFOLIO_ITEM = 'portfolio_item';

    public const TYPE_DISCUSSION = 'discussion';

    public const TYPE_CUSTOM = 'custom';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    public const REQUIREMENT_BASIS_COMPLETION = 'completion';

    public const REQUIREMENT_BASIS_POINTS = 'points';

    public const REQUIREMENT_BASIS_PASS_FAIL = 'pass_fail';

    public const REQUIREMENT_BASIS_RUBRIC = 'rubric';

    public const REQUIREMENT_BASIS_INSTRUCTOR_REVIEW = 'instructor_review';

    public const ASSIGNMENT_TYPE_OPTIONS = [
        self::TYPE_ASSIGNMENT => 'Assignment',
        self::TYPE_REFLECTION => 'Reflection',
        self::TYPE_READING_RESPONSE => 'Reading Response',
        self::TYPE_QUIZ => 'Quiz',
        self::TYPE_EXAM => 'Exam',
        self::TYPE_PROJECT => 'Project',
        self::TYPE_PRACTICUM_LOG => 'Practicum Log',
        self::TYPE_FIELD_REPORT => 'Field Report',
        self::TYPE_PORTFOLIO_ITEM => 'Portfolio Item',
        self::TYPE_DISCUSSION => 'Discussion',
        self::TYPE_CUSTOM => 'Custom',
    ];

    public const STATUS_OPTIONS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_ARCHIVED => 'Archived',
    ];

    public const REQUIREMENT_BASIS_OPTIONS = [
        self::REQUIREMENT_BASIS_COMPLETION => 'Completion',
        self::REQUIREMENT_BASIS_POINTS => 'Points',
        self::REQUIREMENT_BASIS_PASS_FAIL => 'Pass / Fail',
        self::REQUIREMENT_BASIS_RUBRIC => 'Rubric',
        self::REQUIREMENT_BASIS_INSTRUCTOR_REVIEW => 'Instructor Review',
    ];

    protected $fillable = [
        'institution_id',
        'uuid',
        'course_offering_id',
        'title',
        'description',
        'assignment_type',
        'requirement_basis',
        'instructions',
        'due_at',
        'available_from',
        'available_until',
        'points_possible',
        'passing_threshold',
        'is_required',
        'sort_order',
        'status',
        'notes',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'available_from' => 'datetime',
        'available_until' => 'datetime',
        'points_possible' => 'decimal:2',
        'is_required' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeRequired(Builder $query): Builder
    {
        return $query->where('is_required', true);
    }

    public function scopeActiveRequired(Builder $query): Builder
    {
        return $query->active()->required();
    }
}

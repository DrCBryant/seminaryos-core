<?php

namespace App\Models;

use App\Core\Models\BaseModel;
use App\Core\Traits\HasInstitutionScope;
use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgramRequirement extends BaseModel
{
    use HasInstitutionScope, HasUuid, SoftDeletes;

    public const TYPES = [
        'specific_course' => 'Specific Course',
        'elective_credits' => 'Elective Credits',
        'transfer_credits' => 'Transfer Credits',
        'non_course_requirement' => 'Non-Course Requirement',
        'practicum' => 'Practicum',
        'capstone' => 'Capstone',
        'field_education' => 'Field Education',
        'custom' => 'Custom',
    ];

    protected $fillable = [
        'institution_id',
        'uuid',
        'program_id',
        'program_requirement_group_id',
        'course_id',
        'requirement_type',
        'name',
        'description',
        'required_credits',
        'minimum_grade',
        'minimum_grade_points',
        'allow_substitution',
        'is_required',
        'sort_order',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'required_credits' => 'decimal:2',
        'minimum_grade_points' => 'decimal:2',
        'allow_substitution' => 'boolean',
        'is_required' => 'boolean',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function programRequirementGroup(): BelongsTo
    {
        return $this->belongsTo(ProgramRequirementGroup::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function studentRequirementEvidence(): HasMany
    {
        return $this->hasMany(StudentRequirementEvidence::class);
    }

    public function programRequirementSubstitutions(): HasMany
    {
        return $this->hasMany(ProgramRequirementSubstitution::class);
    }
}

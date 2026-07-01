<?php

namespace App\Models;

use App\Core\Models\BaseModel;
use App\Core\Traits\HasInstitutionScope;
use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgramRequirementGroup extends BaseModel
{
    use HasInstitutionScope, HasUuid, SoftDeletes;

    public const TYPES = [
        'core' => 'Core',
        'concentration' => 'Concentration',
        'elective' => 'Elective',
        'practicum' => 'Practicum',
        'capstone' => 'Capstone',
        'transfer' => 'Transfer',
        'general' => 'General',
        'custom' => 'Custom',
    ];

    protected $fillable = [
        'institution_id',
        'uuid',
        'program_id',
        'name',
        'description',
        'group_type',
        'required_credits',
        'minimum_gpa',
        'sort_order',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'required_credits' => 'decimal:2',
        'minimum_gpa' => 'decimal:3',
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

    public function programRequirements(): HasMany
    {
        return $this->hasMany(ProgramRequirement::class)->orderBy('sort_order');
    }
}

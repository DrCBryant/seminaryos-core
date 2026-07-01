<?php

namespace App\Models;

use App\Core\Models\BaseModel;
use App\Core\Traits\HasInstitutionScope;
use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GradeValue extends BaseModel
{
    use HasInstitutionScope, HasUuid, SoftDeletes;

    protected $fillable = [
        'institution_id',
        'uuid',
        'grade_scale_id',
        'grade',
        'label',
        'grade_points',
        'min_percentage',
        'max_percentage',
        'earns_credit',
        'affects_gpa',
        'is_passing',
        'sort_order',
        'notes',
    ];

    protected $casts = [
        'grade_points' => 'decimal:2',
        'min_percentage' => 'decimal:2',
        'max_percentage' => 'decimal:2',
        'earns_credit' => 'boolean',
        'affects_gpa' => 'boolean',
        'is_passing' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function gradeScale(): BelongsTo
    {
        return $this->belongsTo(GradeScale::class);
    }
}

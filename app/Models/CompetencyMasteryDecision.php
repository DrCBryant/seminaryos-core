<?php

namespace App\Models;

use App\Core\Models\BaseModel;
use App\Core\Traits\HasInstitutionScope;

class CompetencyMasteryDecision extends BaseModel
{
    use HasInstitutionScope;

    public $timestamps = false;

    protected $fillable = [
        'institution_id', 'student_id', 'program_id', 'master_assessment_id', 'attempt_id',
        'mentor_faculty_id', 'decided_by_user_id', 'decided_at', 'snapshot',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
        'snapshot' => 'array',
    ];
}

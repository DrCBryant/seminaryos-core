<?php

namespace App\Models;

use App\Core\Models\BaseModel;
use App\Core\Traits\HasInstitutionScope;
use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentRequirementEvidence extends BaseModel
{
    use HasInstitutionScope, HasUuid;

    public const STATUS_OPTIONS = [
        'pending' => 'Pending',
        'submitted' => 'Submitted',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'waived' => 'Waived',
        'archived' => 'Archived',
    ];

    protected $fillable = [
        'institution_id',
        'uuid',
        'student_id',
        'program_id',
        'program_requirement_id',
        'status',
        'evidence_title',
        'evidence_description',
        'completed_at',
        'approved_at',
        'approved_by_user_id',
        'notes',
    ];

    protected $casts = [
        'completed_at' => 'date',
        'approved_at' => 'date',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function programRequirement(): BelongsTo
    {
        return $this->belongsTo(ProgramRequirement::class);
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}

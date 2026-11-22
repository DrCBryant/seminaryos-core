<?php

namespace App\Models;

use App\Core\Models\BaseModel;
use App\Core\Traits\HasInstitutionScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DegreeConferral extends BaseModel
{
    use HasInstitutionScope;

    public $timestamps = false;

    protected $fillable = [
        'institution_id',
        'student_id',
        'program_id',
        'conferred_by_user_id',
        'program_code',
        'program_title',
        'credential_type',
        'conferred_on',
        'decided_at',
        'eligibility_snapshot',
        'registrar_remarks',
    ];

    protected $casts = [
        'conferred_on' => 'date',
        'decided_at' => 'datetime',
        'eligibility_snapshot' => 'array',
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

    public function conferredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conferred_by_user_id');
    }
}

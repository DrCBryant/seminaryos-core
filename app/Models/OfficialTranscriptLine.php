<?php

namespace App\Models;

use App\Core\Models\BaseModel;
use App\Core\Traits\HasInstitutionScope;
use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OfficialTranscriptLine extends BaseModel
{
    use HasInstitutionScope, HasUuid, SoftDeletes;

    protected $fillable = [
        'institution_id',
        'uuid',
        'official_transcript_id',
        'academic_record_id',
        'student_id',
        'academic_term_id',
        'term_label',
        'course_code',
        'course_title',
        'credits_attempted',
        'credits_earned',
        'final_grade',
        'grade_label',
        'grade_points',
        'status',
        'completed_at',
        'sort_order',
        'notes',
    ];

    protected $casts = [
        'credits_attempted' => 'decimal:2',
        'credits_earned' => 'decimal:2',
        'grade_points' => 'decimal:2',
        'completed_at' => 'date',
        'sort_order' => 'integer',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function officialTranscript(): BelongsTo
    {
        return $this->belongsTo(OfficialTranscript::class);
    }

    public function academicRecord(): BelongsTo
    {
        return $this->belongsTo(AcademicRecord::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }
}

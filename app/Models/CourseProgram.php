<?php

namespace App\Models;

use App\Core\Traits\HasInstitutionScope;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CourseProgram extends Pivot
{
    use HasInstitutionScope;

    protected $table = 'course_program';

    public $incrementing = true;

    protected $fillable = [
        'institution_id',
        'program_id',
        'course_id',
        'requirement_type',
        'sequence_order',
        'credits_applied',
    ];

    protected $casts = [
        'sequence_order' => 'integer',
        'credits_applied' => 'decimal:2',
    ];

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}

<?php

namespace App\Models;

use App\Core\Models\BaseModel;
use App\Core\Traits\HasInstitutionScope;
use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\SoftDeletes;

class Program extends BaseModel
{
    use HasInstitutionScope, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'institution_id',
        'code',
        'title',
        'slug',
        'credential_type',
        'short_description',
        'description',
        'credit_hours',
        'duration_text',
        'delivery_method',
        'tuition_text',
        'admissions_requirements',
        'learning_outcomes',
        'status',
        'is_public',
        'seo_title',
        'seo_description',
        'published_at',
    ];

    protected $casts = [
        'credit_hours' => 'decimal:2',
        'is_public' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'course_program')
            ->using(CourseProgram::class)
            ->withPivot(['id', 'institution_id', 'requirement_type', 'sequence_order', 'credits_applied'])
            ->withTimestamps();
    }

    public function coursePrograms()
    {
        return $this->hasMany(CourseProgram::class);
    }

    public function applicants()
    {
        return $this->hasMany(Applicant::class);
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }
}

<?php

namespace App\Models;

use App\Core\Models\BaseModel;
use App\Core\Traits\HasInstitutionScope;
use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends BaseModel
{
    use HasInstitutionScope, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'institution_id',
        'program_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'student_number',
        'status',
        'enrollment_date',
        'notes',
    ];

    protected $casts = [
        'enrollment_date' => 'date',
    ];

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => trim("{$this->first_name} {$this->last_name}"),
        );
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function courseEnrollments()
    {
        return $this->hasMany(CourseEnrollment::class);
    }
}

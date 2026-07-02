<?php

namespace App\Models;

use App\Core\Models\BaseModel;
use App\Core\Traits\HasInstitutionScope;
use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseOffering extends BaseModel
{
    use HasInstitutionScope, HasUuid;

    public const DEFAULT_SECTION_CODE = 'MAIN';

    public const DELIVERY_MODE_OPTIONS = [
        'in_person' => 'In Person',
        'online' => 'Online',
        'hybrid' => 'Hybrid',
        'directed_study' => 'Directed Study',
        'intensive' => 'Intensive',
        'asynchronous' => 'Asynchronous',
        'other' => 'Other',
    ];

    public const STATUS_OPTIONS = [
        'planned' => 'Planned',
        'open' => 'Open',
        'closed' => 'Closed',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'archived' => 'Archived',
    ];

    protected $fillable = [
        'institution_id',
        'uuid',
        'course_id',
        'academic_term_id',
        'section_code',
        'title',
        'delivery_mode',
        'location',
        'meeting_pattern',
        'start_date',
        'end_date',
        'capacity',
        'status',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'capacity' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $courseOffering): void {
            $courseOffering->section_code = self::normalizeSectionCode($courseOffering->section_code);
        });
    }

    protected function sectionCode(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value): string => self::normalizeSectionCode($value),
        );
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function courseEnrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    protected static function normalizeSectionCode(mixed $value): string
    {
        $normalized = strtoupper(trim((string) ($value ?? '')));

        return $normalized !== '' ? $normalized : self::DEFAULT_SECTION_CODE;
    }
}

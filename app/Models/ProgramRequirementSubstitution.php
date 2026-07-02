<?php

namespace App\Models;

use App\Core\Models\BaseModel;
use App\Core\Traits\HasInstitutionScope;
use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ProgramRequirementSubstitution extends BaseModel
{
    use HasInstitutionScope, HasUuid;

    public const STATUS_OPTIONS = [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'revoked' => 'Revoked',
        'archived' => 'Archived',
    ];

    /**
     * @var array<int, string>
     */
    public const ACTIVE_DUPLICATE_PROTECTED_STATUSES = [
        'pending',
        'approved',
    ];

    protected $fillable = [
        'institution_id',
        'uuid',
        'student_id',
        'program_id',
        'program_requirement_id',
        'substitute_course_id',
        'academic_record_id',
        'status',
        'reason',
        'approved_at',
        'approved_by_user_id',
        'notes',
    ];

    protected $casts = [
        'approved_at' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $substitution): void {
            $substitution->validateDuplicateProtection();
        });
    }

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

    public function substituteCourse(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'substitute_course_id');
    }

    public function academicRecord(): BelongsTo
    {
        return $this->belongsTo(AcademicRecord::class);
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    protected function validateDuplicateProtection(): void
    {
        if (! in_array($this->status, self::ACTIVE_DUPLICATE_PROTECTED_STATUSES, true)) {
            return;
        }

        $existingStatuses = Collection::make(self::ACTIVE_DUPLICATE_PROTECTED_STATUSES)
            ->when($this->status === 'approved', fn (Collection $statuses): Collection => $statuses->filter(fn (string $status): bool => $status === 'approved'))
            ->values()
            ->all();

        $duplicateExists = self::query()
            ->where('student_id', $this->student_id)
            ->where('program_requirement_id', $this->program_requirement_id)
            ->whereIn('status', $existingStatuses)
            ->when($this->exists, fn ($query) => $query->whereKeyNot($this->getKey()))
            ->exists();

        if ($duplicateExists) {
            throw ValidationException::withMessages([
                'program_requirement_id' => 'An active substitution already exists for this student and requirement.',
            ]);
        }
    }
}

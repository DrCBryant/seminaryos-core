<?php

namespace App\Models;

use App\Core\Models\BaseModel;
use App\Core\Scopes\InstitutionScope;
use App\Core\Traits\HasInstitutionScope;
use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CourseEnrollment extends BaseModel
{
    use HasInstitutionScope, HasUuid, SoftDeletes;

    public const STATUS_OPTIONS = [
        'enrolled' => 'Enrolled',
        'dropped' => 'Dropped',
        'withdrawn' => 'Withdrawn',
        'completed' => 'Completed',
        'failed' => 'Failed',
        'incomplete' => 'Incomplete',
    ];

    public static function statusOptions(): array
    {
        return self::STATUS_OPTIONS;
    }

    public static function administrativeStatusOptions(): array
    {
        return Arr::except(self::STATUS_OPTIONS, ['completed']);
    }

    public function hasFinalizedOutcome(): bool
    {
        return $this->status === 'completed'
            || $this->completed_at !== null
            || $this->academicRecord()->withoutGlobalScope(InstitutionScope::class)->withTrashed()->exists();
    }

    /** @param array<string, mixed> $data */
    public static function validateAdministrativeData(array $data): void
    {
        if (($data['status'] ?? null) === 'completed' || filled($data['completed_at'] ?? null)) {
            throw ValidationException::withMessages(['data.status' => 'Use guided completion to record an academic outcome.']);
        }

        $institutionId = $data['institution_id'] ?? null;
        $currentInstitutionId = auth()->user()?->current_institution_id;

        if ($currentInstitutionId !== null && (int) $institutionId !== (int) $currentInstitutionId) {
            throw ValidationException::withMessages(['data.institution_id' => 'Select your current institution.']);
        }

        foreach (['student_id' => Student::class, 'course_id' => Course::class, 'academic_term_id' => AcademicTerm::class, 'course_offering_id' => CourseOffering::class] as $field => $model) {
            if (filled($data[$field] ?? null) && ! $model::query()->where('institution_id', $institutionId)->whereKey($data[$field])->exists()) {
                throw ValidationException::withMessages(["data.{$field}" => 'Select a record from the enrollment institution.']);
            }
        }
    }

    /** @param array<string, mixed> $data */
    public function saveAdministrativeChanges(array $data): self
    {
        return DB::transaction(function () use ($data): self {
            $current = self::query()->withTrashed()->lockForUpdate()->findOrFail($this->getKey());

            if ($current->hasFinalizedOutcome()) {
                $data = Arr::only($data, ['notes']);
            } else {
                $changes = $current->fill($data)->getDirty();
                $associations = ['student_id', 'course_id', 'academic_term_id', 'course_offering_id'];
                self::validateAdministrativeData($changes + ['institution_id' => $current->institution_id]
                    + ($current->isDirty('institution_id') ? $current->only($associations) : []));
            }

            $current->update($data);
            $this->setRawAttributes($current->getAttributes(), true);

            return $this;
        }, 3);
    }

    public function delete(): ?bool
    {
        return DB::transaction(function (): ?bool {
            $current = self::query()->withTrashed()->lockForUpdate()->find($this->getKey());

            if (! $current || $current->hasFinalizedOutcome()) {
                return false;
            }

            return parent::delete();
        }, 3);
    }

    protected $fillable = [
        'institution_id',
        'uuid',
        'student_id',
        'course_id',
        'academic_term_id',
        'course_offering_id',
        'status',
        'enrolled_at',
        'completed_at',
        'final_grade',
        'completion_progress_basis',
        'completion_progress_status',
        'completion_evidence_summary',
        'completion_override_reason',
        'completion_reviewed_at',
        'completion_reviewed_by_user_id',
        'notes',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'completed_at' => 'datetime',
        'completion_reviewed_at' => 'datetime',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    public function completionReviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completion_reviewed_by_user_id');
    }

    public function academicRecord(): HasOne
    {
        return $this->hasOne(AcademicRecord::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function studentMasterAssessmentAttempts(): HasMany
    {
        return $this->hasMany(StudentMasterAssessmentAttempt::class);
    }

    public function studentSectionSubmissions(): HasMany
    {
        return $this->hasMany(StudentSectionSubmission::class);
    }

    public function studentSectionManualCompletions(): HasMany
    {
        return $this->hasMany(StudentSectionManualCompletion::class);
    }
}

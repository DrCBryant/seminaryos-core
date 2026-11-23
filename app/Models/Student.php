<?php

namespace App\Models;

use App\Core\Models\BaseModel;
use App\Core\Scopes\InstitutionScope;
use App\Core\Traits\HasInstitutionScope;
use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Student extends BaseModel
{
    use HasInstitutionScope, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'institution_id',
        'program_id',
        'applicant_id',
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

    public function delete(): ?bool
    {
        return DB::transaction(function (): ?bool {
            $current = self::query()->withTrashed()->lockForUpdate()->find($this->getKey());

            if (! $current || $current->hasAcademicHistory()) {
                return false;
            }

            return parent::delete();
        }, 3);
    }

    public function hasAcademicHistory(): bool
    {
        return $this->degreeConferrals()->withoutGlobalScope(InstitutionScope::class)->exists()
            || $this->academicRecords()->withoutGlobalScope(InstitutionScope::class)->withTrashed()->exists()
            || $this->officialTranscriptLines()->withoutGlobalScope(InstitutionScope::class)->withTrashed()->exists()
            || $this->officialTranscripts()->withoutGlobalScope(InstitutionScope::class)->withTrashed()->exists();
    }

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => trim("{$this->first_name} {$this->last_name}"),
        );
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function courseEnrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function degreeConferrals(): HasMany
    {
        return $this->hasMany(DegreeConferral::class);
    }

    public function academicRecords(): HasMany
    {
        return $this->hasMany(AcademicRecord::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function officialTranscripts(): HasMany
    {
        return $this->hasMany(OfficialTranscript::class);
    }

    public function officialTranscriptLines(): HasMany
    {
        return $this->hasMany(OfficialTranscriptLine::class);
    }

    public function studentRequirementEvidence(): HasMany
    {
        return $this->hasMany(StudentRequirementEvidence::class);
    }

    public function programRequirementSubstitutions(): HasMany
    {
        return $this->hasMany(ProgramRequirementSubstitution::class);
    }

    public function competencyMasteryDecisions(): HasMany
    {
        return $this->hasMany(CompetencyMasteryDecision::class);
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

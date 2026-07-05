<?php

namespace App\Models;

use App\Core\Models\BaseModel;
use App\Core\Traits\HasInstitutionScope;
use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseOffering extends BaseModel
{
    use HasInstitutionScope, HasUuid;

    public const PROGRESS_BASIS_ATTENDANCE = 'attendance';

    public const PROGRESS_BASIS_SUBMISSIONS = 'submissions';

    public const PROGRESS_BASIS_HYBRID = 'hybrid';

    public const PROGRESS_BASIS_MANUAL = 'manual';

    public const PROGRESS_BASIS_MASTER_ASSESSMENT = 'master_assessment';

    public const CAPACITY_STATUS_AVAILABLE = 'Available';

    public const CAPACITY_STATUS_NEARLY_FULL = 'Nearly Full';

    public const CAPACITY_STATUS_FULL = 'Full';

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

    public const PROGRESS_BASIS_OPTIONS = [
        self::PROGRESS_BASIS_ATTENDANCE => 'Attendance',
        self::PROGRESS_BASIS_SUBMISSIONS => 'Submissions',
        self::PROGRESS_BASIS_HYBRID => 'Hybrid',
        self::PROGRESS_BASIS_MANUAL => 'Manual Approval',
        self::PROGRESS_BASIS_MASTER_ASSESSMENT => 'Master Assessment',
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
        'progress_basis',
        'progress_notes',
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

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function masterAssessments(): HasMany
    {
        return $this->hasMany(MasterAssessment::class);
    }

    public function sectionAssignments(): HasMany
    {
        return $this->hasMany(SectionAssignment::class);
    }

    public function studentSectionSubmissions(): HasMany
    {
        return $this->hasMany(StudentSectionSubmission::class);
    }

    public function studentSectionManualCompletions(): HasMany
    {
        return $this->hasMany(StudentSectionManualCompletion::class);
    }

    public function studentMasterAssessmentAttempts(): HasMany
    {
        return $this->hasMany(StudentMasterAssessmentAttempt::class);
    }

    public function usesAttendance(): bool
    {
        return in_array($this->progress_basis, [
            self::PROGRESS_BASIS_ATTENDANCE,
            self::PROGRESS_BASIS_HYBRID,
        ], true);
    }

    public function usesSubmissions(): bool
    {
        return in_array($this->progress_basis, [
            self::PROGRESS_BASIS_SUBMISSIONS,
            self::PROGRESS_BASIS_HYBRID,
        ], true);
    }

    public function usesManualApproval(): bool
    {
        return $this->progress_basis === self::PROGRESS_BASIS_MANUAL;
    }

    public function usesMasterAssessment(): bool
    {
        return $this->progress_basis === self::PROGRESS_BASIS_MASTER_ASSESSMENT;
    }

    public function enrolledCount(): int
    {
        $relation = $this->relationLoaded('courseEnrollments')
            ? $this->courseEnrollments
            : $this->courseEnrollments()->get();

        return $relation
            ->whereIn('status', self::countedEnrollmentStatuses())
            ->count();
    }

    public function availableSeats(): string|int
    {
        if ($this->capacity === null) {
            return 'Unlimited';
        }

        return max($this->capacity - $this->enrolledCount(), 0);
    }

    public function isAtCapacity(): bool
    {
        if ($this->capacity === null) {
            return false;
        }

        return $this->enrolledCount() >= $this->capacity;
    }

    public function capacityStatus(): string
    {
        if ($this->isAtCapacity()) {
            return self::CAPACITY_STATUS_FULL;
        }

        if ($this->capacity !== null && $this->capacity > 0 && $this->enrolledCount() >= (int) ceil($this->capacity * 0.8)) {
            return self::CAPACITY_STATUS_NEARLY_FULL;
        }

        return self::CAPACITY_STATUS_AVAILABLE;
    }

    public function scopeWithCapacityAwareness(Builder $query): Builder
    {
        return $query->withCount([
            'courseEnrollments as enrolled_count' => fn (Builder $query): Builder => $query
                ->whereIn('status', self::countedEnrollmentStatuses()),
        ]);
    }

    public static function countedEnrollmentStatuses(): array
    {
        return ['enrolled', 'completed', 'in_progress'];
    }

    protected static function normalizeSectionCode(mixed $value): string
    {
        $normalized = strtoupper(trim((string) ($value ?? '')));

        return $normalized !== '' ? $normalized : self::DEFAULT_SECTION_CODE;
    }
}

<?php

namespace App\Filament\Resources\CourseOfferings\Support;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\CourseEnrollment;
use App\Models\CourseOffering;
use App\Models\MasterAssessment;
use App\Models\StudentMasterAssessmentAttempt;
use Filament\Actions\Action;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SectionProgressPreview
{
    protected const ATTENDANCE_STATUSES_COUNTED_AS_ATTENDED = [
        'present',
        'tardy',
        'left_early',
    ];

    protected const PROGRESS_STATUS_LABELS = [
        'not_started' => 'Not Started',
        'in_progress' => 'In Progress',
        'satisfied' => 'Satisfied',
        'needs_attention' => 'Needs Attention',
        'not_evaluable' => 'Not Evaluable',
    ];

    protected const PROGRESS_STATUS_BADGE_CLASSES = [
        'not_started' => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
        'in_progress' => 'bg-blue-100 text-blue-800 dark:bg-blue-500/20 dark:text-blue-100',
        'satisfied' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-100',
        'needs_attention' => 'bg-rose-100 text-rose-800 dark:bg-rose-500/20 dark:text-rose-100',
        'not_evaluable' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-100',
    ];

    public static function make(): Action
    {
        return Action::make('viewSectionProgress')
            ->label('View Section Progress')
            ->icon('heroicon-o-chart-bar-square')
            ->color('gray')
            ->modalHeading('Section Progress Preview')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalWidth('7xl')
            ->modalContent(fn (CourseOffering $record) => view('filament.course-offerings.section-progress-preview', self::getViewData($record)));
    }

    /**
     * @return array<string, mixed>
     */
    protected static function getViewData(CourseOffering $courseOffering): array
    {
        $courseOffering->loadMissing([
            'institution',
            'course',
            'academicTerm',
            'courseEnrollments.student' => fn ($query) => $query->withTrashed(),
            'attendanceSessions' => fn ($query) => $query->orderBy('session_date')->orderBy('id'),
            'attendanceRecords' => fn ($query) => $query
                ->with('attendanceSession')
                ->orderBy('marked_at')
                ->orderBy('created_at'),
            'masterAssessments' => fn ($query) => $query->orderBy('title')->orderBy('id'),
            'studentMasterAssessmentAttempts.masterAssessment',
        ]);

        $heldSessions = $courseOffering->attendanceSessions
            ->where('status', 'held')
            ->sortBy([
                fn (AttendanceSession $session): int => $session->session_date?->timestamp ?? PHP_INT_MAX,
                fn (AttendanceSession $session): int => $session->id,
            ])
            ->values();

        $heldSessionIds = $heldSessions
            ->pluck('id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $activeMasterAssessments = $courseOffering->masterAssessments
            ->where('status', MasterAssessment::STATUS_ACTIVE)
            ->values();

        $activeMasterAssessmentIds = $activeMasterAssessments
            ->pluck('id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $attendanceRecordsByEnrollmentId = $courseOffering->attendanceRecords
            ->filter(fn (AttendanceRecord $record): bool => $record->course_enrollment_id !== null)
            ->groupBy('course_enrollment_id');

        $attendanceRecordsByStudentId = $courseOffering->attendanceRecords
            ->filter(fn (AttendanceRecord $record): bool => $record->student_id !== null)
            ->groupBy('student_id');

        $attemptsByEnrollmentId = $courseOffering->studentMasterAssessmentAttempts
            ->filter(fn (StudentMasterAssessmentAttempt $attempt): bool => $attempt->course_enrollment_id !== null)
            ->groupBy('course_enrollment_id');

        $attemptsByStudentId = $courseOffering->studentMasterAssessmentAttempts
            ->filter(fn (StudentMasterAssessmentAttempt $attempt): bool => $attempt->student_id !== null)
            ->groupBy('student_id');

        $studentRows = $courseOffering->courseEnrollments
            ->sortBy([
                fn (CourseEnrollment $enrollment): string => strtolower((string) $enrollment->student?->last_name),
                fn (CourseEnrollment $enrollment): string => strtolower((string) $enrollment->student?->first_name),
                fn (CourseEnrollment $enrollment): string => strtolower((string) ($enrollment->student?->student_number ?? 'zzzzzzzz')),
                fn (CourseEnrollment $enrollment): int => $enrollment->id,
            ])
            ->values()
            ->map(function (CourseEnrollment $enrollment) use (
                $courseOffering,
                $heldSessions,
                $heldSessionIds,
                $activeMasterAssessments,
                $activeMasterAssessmentIds,
                $attendanceRecordsByEnrollmentId,
                $attendanceRecordsByStudentId,
                $attemptsByEnrollmentId,
                $attemptsByStudentId,
            ): array {
                $attendanceRecords = self::resolveAttendanceRecordsForEnrollment(
                    $enrollment,
                    $attendanceRecordsByEnrollmentId,
                    $attendanceRecordsByStudentId,
                    $heldSessionIds,
                );

                $masterAssessmentAttempts = self::resolveMasterAssessmentAttemptsForEnrollment(
                    $enrollment,
                    $attemptsByEnrollmentId,
                    $attemptsByStudentId,
                    $activeMasterAssessmentIds,
                );

                $progress = match ($courseOffering->progress_basis) {
                    CourseOffering::PROGRESS_BASIS_ATTENDANCE => self::evaluateAttendanceProgress($heldSessions, $attendanceRecords),
                    CourseOffering::PROGRESS_BASIS_MASTER_ASSESSMENT => self::evaluateMasterAssessmentProgress($activeMasterAssessments, $masterAssessmentAttempts),
                    CourseOffering::PROGRESS_BASIS_MANUAL => self::evaluateManualProgress(),
                    CourseOffering::PROGRESS_BASIS_SUBMISSIONS => self::evaluateSubmissionsProgress(),
                    CourseOffering::PROGRESS_BASIS_HYBRID => self::evaluateHybridProgress($heldSessions, $attendanceRecords),
                    default => self::evaluateNotEvaluableProgress(
                        self::formatProgressBasisLabel($courseOffering->progress_basis),
                        'This section progress basis is not recognized by the preview.',
                    ),
                };

                return [
                    'student_name' => $enrollment->student?->full_name ?? 'Unknown Student',
                    'student_number' => $enrollment->student?->student_number ?? '—',
                    'enrollment_status' => $enrollment->status ?? '—',
                    'enrollment_status_label' => self::formatGenericStatusLabel($enrollment->status),
                    'progress_status' => $progress['progress_status'],
                    'progress_status_label' => self::formatProgressStatusLabel($progress['progress_status']),
                    'progress_status_badge_classes' => self::progressStatusBadgeClasses($progress['progress_status']),
                    'progress_basis_used' => $progress['progress_basis_used'],
                    'evidence_summary' => $progress['evidence_summary'],
                    'last_activity_date' => $progress['last_activity_date'],
                ];
            })
            ->values();

        return [
            'courseOffering' => $courseOffering,
            'summary' => [
                'institution_name' => $courseOffering->institution?->name ?? '—',
                'course_label' => trim(($courseOffering->course?->code ?? '—').' — '.($courseOffering->course?->title ?? '—')),
                'academic_term' => $courseOffering->academicTerm
                    ? "{$courseOffering->academicTerm->name} ({$courseOffering->academicTerm->academic_year})"
                    : '—',
                'section_code' => $courseOffering->section_code ?: '—',
                'progress_basis' => self::formatProgressBasisLabel($courseOffering->progress_basis),
                'delivery_mode' => self::formatDeliveryModeLabel($courseOffering->delivery_mode),
                'capacity' => $courseOffering->capacity === null ? 'Unlimited' : (string) $courseOffering->capacity,
                'enrolled_count' => $courseOffering->enrolledCount(),
            ],
            'studentRows' => $studentRows,
            'progressStatusLabels' => self::PROGRESS_STATUS_LABELS,
            'progressStatusBadgeClasses' => self::PROGRESS_STATUS_BADGE_CLASSES,
        ];
    }

    protected static function resolveAttendanceRecordsForEnrollment(
        CourseEnrollment $enrollment,
        Collection $attendanceRecordsByEnrollmentId,
        Collection $attendanceRecordsByStudentId,
        array $heldSessionIds,
    ): Collection {
        $records = collect($attendanceRecordsByEnrollmentId->get($enrollment->id, []));

        if ($records->isEmpty() && $enrollment->student_id !== null) {
            $records = collect($attendanceRecordsByStudentId->get($enrollment->student_id, []));
        }

        return $records
            ->filter(fn (AttendanceRecord $record): bool => $record->attendance_session_id !== null
                && in_array((int) $record->attendance_session_id, $heldSessionIds, true))
            ->values();
    }

    protected static function resolveMasterAssessmentAttemptsForEnrollment(
        CourseEnrollment $enrollment,
        Collection $attemptsByEnrollmentId,
        Collection $attemptsByStudentId,
        array $activeMasterAssessmentIds,
    ): Collection {
        $attempts = collect($attemptsByEnrollmentId->get($enrollment->id, []));

        if ($attempts->isEmpty() && $enrollment->student_id !== null) {
            $attempts = collect($attemptsByStudentId->get($enrollment->student_id, []));
        }

        return $attempts
            ->filter(fn (StudentMasterAssessmentAttempt $attempt): bool => $attempt->master_assessment_id !== null
                && in_array((int) $attempt->master_assessment_id, $activeMasterAssessmentIds, true))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    protected static function evaluateAttendanceProgress(Collection $heldSessions, Collection $attendanceRecords): array
    {
        $heldSessionCount = $heldSessions->count();

        if ($heldSessionCount === 0) {
            return [
                'progress_status' => 'not_started',
                'progress_basis_used' => self::formatProgressBasisLabel(CourseOffering::PROGRESS_BASIS_ATTENDANCE),
                'evidence_summary' => 'No held attendance sessions exist yet, so attendance progress has not started.',
                'last_activity_date' => null,
                'has_attendance_evidence' => false,
            ];
        }

        $recordsBySessionId = $attendanceRecords
            ->filter(fn (AttendanceRecord $record): bool => $record->attendance_session_id !== null)
            ->groupBy('attendance_session_id')
            ->map(fn (Collection $records): ?AttendanceRecord => self::sortAttemptsOrRecordsByRecency($records)->first());

        $counts = [
            'attended' => 0,
            'excused' => 0,
            'absent' => 0,
            'not_marked' => 0,
            'missing' => 0,
        ];

        $lastActivityDate = null;

        foreach ($heldSessions as $session) {
            /** @var AttendanceRecord|null $record */
            $record = $recordsBySessionId->get($session->id);

            if ($record === null) {
                $counts['missing']++;

                continue;
            }

            if (in_array($record->status, self::ATTENDANCE_STATUSES_COUNTED_AS_ATTENDED, true)) {
                $counts['attended']++;
                $lastActivityDate = self::maxCarbon($lastActivityDate, self::resolveAttendanceActivityDate($record));

                continue;
            }

            if ($record->status === 'excused') {
                $counts['excused']++;
                $lastActivityDate = self::maxCarbon($lastActivityDate, self::resolveAttendanceActivityDate($record));

                continue;
            }

            if ($record->status === 'absent') {
                $counts['absent']++;
                $lastActivityDate = self::maxCarbon($lastActivityDate, self::resolveAttendanceActivityDate($record));

                continue;
            }

            $counts['not_marked']++;
        }

        $markedAttendanceCount = $counts['attended'] + $counts['excused'] + $counts['absent'];

        if ($markedAttendanceCount === 0) {
            return [
                'progress_status' => 'not_started',
                'progress_basis_used' => self::formatProgressBasisLabel(CourseOffering::PROGRESS_BASIS_ATTENDANCE),
                'evidence_summary' => "Held sessions: {$heldSessionCount}. No attendance has been marked for this student yet.",
                'last_activity_date' => null,
                'has_attendance_evidence' => false,
            ];
        }

        $isFullyAttendedOrExcused = ($counts['attended'] + $counts['excused']) === $heldSessionCount
            && $counts['absent'] === 0
            && $counts['not_marked'] === 0
            && $counts['missing'] === 0;

        $evidenceSummary = "Held: {$heldSessionCount} · Attended: {$counts['attended']} · Excused: {$counts['excused']} · Absent: {$counts['absent']} · Not Marked: {$counts['not_marked']} · Missing Records: {$counts['missing']}.";

        if ($isFullyAttendedOrExcused) {
            return [
                'progress_status' => 'satisfied',
                'progress_basis_used' => self::formatProgressBasisLabel(CourseOffering::PROGRESS_BASIS_ATTENDANCE),
                'evidence_summary' => $evidenceSummary.' All held sessions are currently marked attended or excused. Because no separate attendance threshold is configured, this preview treats complete held-session attendance coverage as satisfied.',
                'last_activity_date' => $lastActivityDate,
                'has_attendance_evidence' => true,
            ];
        }

        return [
            'progress_status' => 'in_progress',
            'progress_basis_used' => self::formatProgressBasisLabel(CourseOffering::PROGRESS_BASIS_ATTENDANCE),
            'evidence_summary' => $evidenceSummary.' No section attendance threshold is configured, so this preview remains conservative and keeps the student in progress unless all held sessions are attended or excused.',
            'last_activity_date' => $lastActivityDate,
            'has_attendance_evidence' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function evaluateMasterAssessmentProgress(Collection $activeMasterAssessments, Collection $attempts): array
    {
        $activeAssessmentCount = $activeMasterAssessments->count();

        if ($activeAssessmentCount === 0) {
            return self::evaluateNotEvaluableProgress(
                self::formatProgressBasisLabel(CourseOffering::PROGRESS_BASIS_MASTER_ASSESSMENT),
                'No active master assessment exists for this section.',
            );
        }

        $attemptsByAssessmentId = $attempts
            ->filter(fn (StudentMasterAssessmentAttempt $attempt): bool => $attempt->master_assessment_id !== null)
            ->groupBy('master_assessment_id');

        $representativeAttempts = $activeMasterAssessments
            ->mapWithKeys(function (MasterAssessment $assessment) use ($attemptsByAssessmentId): array {
                $attempt = self::resolveRepresentativeAttempt(collect($attemptsByAssessmentId->get($assessment->id, [])));

                return [$assessment->id => $attempt];
            });

        $nonArchivedAttempts = $representativeAttempts
            ->filter(fn (mixed $attempt): bool => $attempt instanceof StudentMasterAssessmentAttempt
                && $attempt->status !== StudentMasterAssessmentAttempt::STATUS_ARCHIVED);

        if ($nonArchivedAttempts->isEmpty()) {
            $archivedOnlyCount = $representativeAttempts
                ->filter(fn (mixed $attempt): bool => $attempt instanceof StudentMasterAssessmentAttempt
                    && $attempt->status === StudentMasterAssessmentAttempt::STATUS_ARCHIVED)
                ->count();

            if ($archivedOnlyCount > 0) {
                return self::evaluateNotEvaluableProgress(
                    self::formatProgressBasisLabel(CourseOffering::PROGRESS_BASIS_MASTER_ASSESSMENT),
                    "{$activeAssessmentCount} active master assessment(s) exist, but only archived attempt evidence is available for this student.",
                );
            }

            return [
                'progress_status' => 'not_started',
                'progress_basis_used' => self::formatProgressBasisLabel(CourseOffering::PROGRESS_BASIS_MASTER_ASSESSMENT),
                'evidence_summary' => "{$activeAssessmentCount} active master assessment(s) exist, but no student attempt was found.",
                'last_activity_date' => null,
            ];
        }

        $statusCounts = [
            StudentMasterAssessmentAttempt::STATUS_PASSED => 0,
            StudentMasterAssessmentAttempt::STATUS_SUBMITTED => 0,
            StudentMasterAssessmentAttempt::STATUS_REVISION_NEEDED => 0,
            StudentMasterAssessmentAttempt::STATUS_FAILED => 0,
            StudentMasterAssessmentAttempt::STATUS_NOT_STARTED => 0,
        ];

        $lastActivityDate = null;

        foreach ($nonArchivedAttempts as $attempt) {
            $statusCounts[$attempt->status] = ($statusCounts[$attempt->status] ?? 0) + 1;
            $lastActivityDate = self::maxCarbon($lastActivityDate, self::resolveMasterAssessmentActivityDate($attempt));
        }

        $assessmentsWithoutCurrentAttempt = $activeAssessmentCount - $nonArchivedAttempts->count();

        $evidenceSummary = implode(' · ', [
            "Active Assessments: {$activeAssessmentCount}",
            'Passed: '.$statusCounts[StudentMasterAssessmentAttempt::STATUS_PASSED],
            'Submitted: '.$statusCounts[StudentMasterAssessmentAttempt::STATUS_SUBMITTED],
            'Revision Needed: '.$statusCounts[StudentMasterAssessmentAttempt::STATUS_REVISION_NEEDED],
            'Failed: '.$statusCounts[StudentMasterAssessmentAttempt::STATUS_FAILED],
            'Not Started: '.($statusCounts[StudentMasterAssessmentAttempt::STATUS_NOT_STARTED] + max($assessmentsWithoutCurrentAttempt, 0)),
        ]);

        if ($statusCounts[StudentMasterAssessmentAttempt::STATUS_PASSED] === $activeAssessmentCount) {
            return [
                'progress_status' => 'satisfied',
                'progress_basis_used' => self::formatProgressBasisLabel(CourseOffering::PROGRESS_BASIS_MASTER_ASSESSMENT),
                'evidence_summary' => $evidenceSummary.'. All active master assessments currently show passed attempts.',
                'last_activity_date' => $lastActivityDate,
            ];
        }

        if ($statusCounts[StudentMasterAssessmentAttempt::STATUS_REVISION_NEEDED] > 0 || $statusCounts[StudentMasterAssessmentAttempt::STATUS_FAILED] > 0) {
            return [
                'progress_status' => 'needs_attention',
                'progress_basis_used' => self::formatProgressBasisLabel(CourseOffering::PROGRESS_BASIS_MASTER_ASSESSMENT),
                'evidence_summary' => $evidenceSummary.'. At least one active master assessment attempt needs revision or has failed.',
                'last_activity_date' => $lastActivityDate,
            ];
        }

        if ($statusCounts[StudentMasterAssessmentAttempt::STATUS_SUBMITTED] > 0 || $statusCounts[StudentMasterAssessmentAttempt::STATUS_PASSED] > 0) {
            return [
                'progress_status' => 'in_progress',
                'progress_basis_used' => self::formatProgressBasisLabel(CourseOffering::PROGRESS_BASIS_MASTER_ASSESSMENT),
                'evidence_summary' => $evidenceSummary.'. Active master assessment evidence exists, but not every active assessment is passed yet.',
                'last_activity_date' => $lastActivityDate,
            ];
        }

        return [
            'progress_status' => 'not_started',
            'progress_basis_used' => self::formatProgressBasisLabel(CourseOffering::PROGRESS_BASIS_MASTER_ASSESSMENT),
            'evidence_summary' => $evidenceSummary.'. Active master assessments exist, but the student has not started a current attempt.',
            'last_activity_date' => $lastActivityDate,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function evaluateManualProgress(): array
    {
        return self::evaluateNotEvaluableProgress(
            self::formatProgressBasisLabel(CourseOffering::PROGRESS_BASIS_MANUAL),
            'Manual completion evidence is not built yet, so this section cannot be evaluated by preview.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected static function evaluateSubmissionsProgress(): array
    {
        return self::evaluateNotEvaluableProgress(
            self::formatProgressBasisLabel(CourseOffering::PROGRESS_BASIS_SUBMISSIONS),
            'Section assignments and student submissions are not built yet, so this section cannot be evaluated by preview.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected static function evaluateHybridProgress(Collection $heldSessions, Collection $attendanceRecords): array
    {
        $attendanceProgress = self::evaluateAttendanceProgress($heldSessions, $attendanceRecords);

        if (($attendanceProgress['has_attendance_evidence'] ?? false) !== true) {
            return self::evaluateNotEvaluableProgress(
                'Hybrid (Attendance Evidence Only)',
                'Attendance evidence is not yet available for this student, and submissions evidence is not built yet for hybrid sections.',
            );
        }

        return [
            'progress_status' => 'in_progress',
            'progress_basis_used' => 'Hybrid (Attendance Evidence Only)',
            'evidence_summary' => 'Attendance preview: '.$attendanceProgress['evidence_summary'].' Submissions evidence is not built yet, so hybrid sections cannot be marked satisfied by this preview.',
            'last_activity_date' => $attendanceProgress['last_activity_date'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function evaluateNotEvaluableProgress(string $basisLabel, string $reason): array
    {
        return [
            'progress_status' => 'not_evaluable',
            'progress_basis_used' => $basisLabel,
            'evidence_summary' => $reason,
            'last_activity_date' => null,
        ];
    }

    protected static function resolveRepresentativeAttempt(Collection $attempts): ?StudentMasterAssessmentAttempt
    {
        if ($attempts->isEmpty()) {
            return null;
        }

        $nonArchivedAttempts = $attempts
            ->filter(fn (StudentMasterAssessmentAttempt $attempt): bool => $attempt->status !== StudentMasterAssessmentAttempt::STATUS_ARCHIVED)
            ->values();

        if ($nonArchivedAttempts->isNotEmpty()) {
            return self::sortAttemptsOrRecordsByRecency($nonArchivedAttempts)->first();
        }

        return self::sortAttemptsOrRecordsByRecency($attempts)->first();
    }

    protected static function resolveAttendanceActivityDate(AttendanceRecord $record): ?Carbon
    {
        return self::firstCarbonValue([
            $record->marked_at,
            $record->attendanceSession?->session_date,
            $record->updated_at,
            $record->created_at,
        ]);
    }

    protected static function resolveMasterAssessmentActivityDate(StudentMasterAssessmentAttempt $attempt): ?Carbon
    {
        return self::firstCarbonValue([
            $attempt->assessed_at,
            $attempt->submitted_at,
            $attempt->updated_at,
            $attempt->created_at,
        ]);
    }

    protected static function firstCarbonValue(array $values): ?Carbon
    {
        foreach ($values as $value) {
            if ($value instanceof Carbon) {
                return $value;
            }

            if ($value !== null) {
                return Carbon::parse($value);
            }
        }

        return null;
    }

    protected static function maxCarbon(?Carbon $current, ?Carbon $candidate): ?Carbon
    {
        if ($current === null) {
            return $candidate;
        }

        if ($candidate === null) {
            return $current;
        }

        return $candidate->greaterThan($current) ? $candidate : $current;
    }

    protected static function sortAttemptsOrRecordsByRecency(Collection $items): Collection
    {
        return $items
            ->sortByDesc(function (mixed $item): int {
                foreach (['assessed_at', 'submitted_at', 'marked_at', 'updated_at', 'created_at'] as $attribute) {
                    $value = $item->{$attribute} ?? null;

                    if ($value instanceof Carbon) {
                        return $value->timestamp;
                    }

                    if ($value !== null) {
                        return Carbon::parse($value)->timestamp;
                    }
                }

                return 0;
            })
            ->values();
    }

    protected static function formatProgressBasisLabel(?string $value): string
    {
        return CourseOffering::PROGRESS_BASIS_OPTIONS[$value] ?? self::formatGenericStatusLabel($value);
    }

    protected static function formatDeliveryModeLabel(?string $value): string
    {
        return CourseOffering::DELIVERY_MODE_OPTIONS[$value] ?? self::formatGenericStatusLabel($value);
    }

    protected static function formatProgressStatusLabel(string $value): string
    {
        return self::PROGRESS_STATUS_LABELS[$value] ?? self::formatGenericStatusLabel($value);
    }

    protected static function progressStatusBadgeClasses(string $value): string
    {
        return self::PROGRESS_STATUS_BADGE_CLASSES[$value] ?? self::PROGRESS_STATUS_BADGE_CLASSES['not_evaluable'];
    }

    protected static function formatGenericStatusLabel(?string $value): string
    {
        if (blank($value)) {
            return '—';
        }

        return str((string) $value)
            ->replace('_', ' ')
            ->title()
            ->toString();
    }
}

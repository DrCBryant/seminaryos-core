<?php

namespace App\Filament\Resources\CourseOfferings\Support;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\CourseEnrollment;
use App\Models\CourseOffering;
use App\Models\MasterAssessment;
use App\Models\SectionAssignment;
use App\Models\StudentMasterAssessmentAttempt;
use App\Models\StudentSectionManualCompletion;
use App\Models\StudentSectionSubmission;
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
            'sectionAssignments' => fn ($query) => $query
                ->orderBy('sort_order')
                ->orderBy('due_at')
                ->orderBy('id'),
            'studentSectionManualCompletions',
            'studentSectionSubmissions.sectionAssignment',
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

        $activeRequiredAssignments = $courseOffering->sectionAssignments
            ->filter(fn (SectionAssignment $assignment): bool => $assignment->status === SectionAssignment::STATUS_ACTIVE
                && $assignment->is_required)
            ->values();

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

        $submissionsByEnrollmentId = $courseOffering->studentSectionSubmissions
            ->filter(fn (StudentSectionSubmission $submission): bool => $submission->course_enrollment_id !== null)
            ->groupBy('course_enrollment_id');

        $submissionsByStudentId = $courseOffering->studentSectionSubmissions
            ->filter(fn (StudentSectionSubmission $submission): bool => $submission->student_id !== null)
            ->groupBy('student_id');

        $manualCompletionsByEnrollmentId = $courseOffering->studentSectionManualCompletions
            ->filter(fn (StudentSectionManualCompletion $completion): bool => $completion->course_enrollment_id !== null)
            ->groupBy('course_enrollment_id');

        $manualCompletionsByStudentId = $courseOffering->studentSectionManualCompletions
            ->filter(fn (StudentSectionManualCompletion $completion): bool => $completion->student_id !== null)
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
                $activeRequiredAssignments,
                $attendanceRecordsByEnrollmentId,
                $attendanceRecordsByStudentId,
                $attemptsByEnrollmentId,
                $attemptsByStudentId,
                $manualCompletionsByEnrollmentId,
                $manualCompletionsByStudentId,
                $submissionsByEnrollmentId,
                $submissionsByStudentId,
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

                $sectionSubmissions = self::resolveSectionSubmissionsForEnrollment(
                    $enrollment,
                    $submissionsByEnrollmentId,
                    $submissionsByStudentId,
                    $activeRequiredAssignments->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(),
                );

                $manualCompletion = self::resolveManualCompletionForEnrollment(
                    $enrollment,
                    $manualCompletionsByEnrollmentId,
                    $manualCompletionsByStudentId,
                );

                $progress = match ($courseOffering->progress_basis) {
                    CourseOffering::PROGRESS_BASIS_ATTENDANCE => self::evaluateAttendanceProgress($heldSessions, $attendanceRecords),
                    CourseOffering::PROGRESS_BASIS_MASTER_ASSESSMENT => self::evaluateMasterAssessmentProgress($activeMasterAssessments, $masterAssessmentAttempts),
                    CourseOffering::PROGRESS_BASIS_MANUAL => self::evaluateManualProgress($manualCompletion),
                    CourseOffering::PROGRESS_BASIS_SUBMISSIONS => self::evaluateSubmissionsProgress($activeRequiredAssignments, $sectionSubmissions),
                    CourseOffering::PROGRESS_BASIS_HYBRID => self::evaluateHybridProgress($heldSessions, $attendanceRecords, $activeRequiredAssignments, $sectionSubmissions),
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

    protected static function resolveSectionSubmissionsForEnrollment(
        CourseEnrollment $enrollment,
        Collection $submissionsByEnrollmentId,
        Collection $submissionsByStudentId,
        array $activeRequiredAssignmentIds,
    ): Collection {
        $submissions = collect($submissionsByEnrollmentId->get($enrollment->id, []));

        if ($submissions->isEmpty() && $enrollment->student_id !== null) {
            $submissions = collect($submissionsByStudentId->get($enrollment->student_id, []));
        }

        return $submissions
            ->filter(fn (StudentSectionSubmission $submission): bool => $submission->section_assignment_id !== null
                && in_array((int) $submission->section_assignment_id, $activeRequiredAssignmentIds, true))
            ->values();
    }

    protected static function resolveManualCompletionForEnrollment(
        CourseEnrollment $enrollment,
        Collection $manualCompletionsByEnrollmentId,
        Collection $manualCompletionsByStudentId,
    ): ?StudentSectionManualCompletion {
        $manualCompletions = collect($manualCompletionsByEnrollmentId->get($enrollment->id, []));

        if ($manualCompletions->isEmpty() && $enrollment->student_id !== null) {
            $manualCompletions = collect($manualCompletionsByStudentId->get($enrollment->student_id, []));
        }

        return self::resolveRepresentativeManualCompletion($manualCompletions);
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
    protected static function evaluateManualProgress(?StudentSectionManualCompletion $manualCompletion): array
    {
        $basisLabel = self::formatProgressBasisLabel(CourseOffering::PROGRESS_BASIS_MANUAL);

        if ($manualCompletion === null) {
            return [
                'progress_status' => 'not_started',
                'progress_basis_used' => $basisLabel,
                'evidence_summary' => 'No manual completion evidence record exists for this student yet.',
                'last_activity_date' => null,
            ];
        }

        if ($manualCompletion->status === StudentSectionManualCompletion::STATUS_ARCHIVED) {
            return self::evaluateNotEvaluableProgress(
                $basisLabel,
                'Only archived manual completion evidence exists for this student.',
            );
        }

        $hasEvidenceContent = filled($manualCompletion->completion_summary)
            || filled($manualCompletion->evidence_reference)
            || filled($manualCompletion->approver_notes);

        $lastActivityDate = self::resolveManualCompletionActivityDate($manualCompletion);

        return match ($manualCompletion->status) {
            StudentSectionManualCompletion::STATUS_APPROVED,
            StudentSectionManualCompletion::STATUS_WAIVED => [
                'progress_status' => 'satisfied',
                'progress_basis_used' => $basisLabel,
                'evidence_summary' => 'Manual completion evidence is approved or waived for this student.',
                'last_activity_date' => $lastActivityDate,
            ],
            StudentSectionManualCompletion::STATUS_REVISION_NEEDED,
            StudentSectionManualCompletion::STATUS_REJECTED => [
                'progress_status' => 'needs_attention',
                'progress_basis_used' => $basisLabel,
                'evidence_summary' => 'Manual completion evidence needs revision or has been rejected for this student.',
                'last_activity_date' => $lastActivityDate,
            ],
            StudentSectionManualCompletion::STATUS_PENDING => [
                'progress_status' => $hasEvidenceContent ? 'in_progress' : 'not_started',
                'progress_basis_used' => $basisLabel,
                'evidence_summary' => $hasEvidenceContent
                    ? 'Manual completion evidence is pending approval and contains submitted evidence details.'
                    : 'Manual completion record exists but no completion evidence has been entered yet.',
                'last_activity_date' => $lastActivityDate,
            ],
            default => self::evaluateNotEvaluableProgress(
                $basisLabel,
                'This manual completion status is not recognized by the preview.',
            ),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected static function evaluateSubmissionsProgress(Collection $activeRequiredAssignments, Collection $submissions): array
    {
        return self::evaluateSubmissionEvidence($activeRequiredAssignments, $submissions, self::formatProgressBasisLabel(CourseOffering::PROGRESS_BASIS_SUBMISSIONS));
    }

    /**
     * @return array<string, mixed>
     */
    protected static function evaluateHybridProgress(Collection $heldSessions, Collection $attendanceRecords, Collection $activeRequiredAssignments, Collection $submissions): array
    {
        $attendanceProgress = self::evaluateAttendanceProgress($heldSessions, $attendanceRecords);
        $submissionProgress = self::evaluateSubmissionEvidence($activeRequiredAssignments, $submissions, 'Hybrid (Submission Evidence)');

        if ($submissionProgress['progress_status'] === 'not_evaluable') {
            return [
                'progress_status' => 'not_evaluable',
                'progress_basis_used' => 'Hybrid',
                'evidence_summary' => 'Attendance preview: '.$attendanceProgress['evidence_summary'].' Submission preview: '.$submissionProgress['evidence_summary'].' Hybrid sections cannot be satisfied without both evidence streams.',
                'last_activity_date' => self::maxCarbon($attendanceProgress['last_activity_date'] ?? null, $submissionProgress['last_activity_date'] ?? null),
            ];
        }

        if (in_array($attendanceProgress['progress_status'], ['needs_attention'], true)
            || in_array($submissionProgress['progress_status'], ['needs_attention'], true)) {
            return [
                'progress_status' => 'needs_attention',
                'progress_basis_used' => 'Hybrid',
                'evidence_summary' => 'Attendance preview: '.$attendanceProgress['evidence_summary'].' Submission preview: '.$submissionProgress['evidence_summary'],
                'last_activity_date' => self::maxCarbon($attendanceProgress['last_activity_date'] ?? null, $submissionProgress['last_activity_date'] ?? null),
            ];
        }

        if ($attendanceProgress['progress_status'] === 'satisfied' && $submissionProgress['progress_status'] === 'satisfied') {
            return [
                'progress_status' => 'satisfied',
                'progress_basis_used' => 'Hybrid',
                'evidence_summary' => 'Attendance preview: '.$attendanceProgress['evidence_summary'].' Submission preview: '.$submissionProgress['evidence_summary'],
                'last_activity_date' => self::maxCarbon($attendanceProgress['last_activity_date'] ?? null, $submissionProgress['last_activity_date'] ?? null),
            ];
        }

        if ($attendanceProgress['progress_status'] === 'not_started' && $submissionProgress['progress_status'] === 'not_started') {
            return [
                'progress_status' => 'not_started',
                'progress_basis_used' => 'Hybrid',
                'evidence_summary' => 'Attendance preview: '.$attendanceProgress['evidence_summary'].' Submission preview: '.$submissionProgress['evidence_summary'],
                'last_activity_date' => self::maxCarbon($attendanceProgress['last_activity_date'] ?? null, $submissionProgress['last_activity_date'] ?? null),
            ];
        }

        return [
            'progress_status' => 'in_progress',
            'progress_basis_used' => 'Hybrid',
            'evidence_summary' => 'Attendance preview: '.$attendanceProgress['evidence_summary'].' Submission preview: '.$submissionProgress['evidence_summary'].' Hybrid sections remain conservative until both evidence streams are satisfied.',
            'last_activity_date' => self::maxCarbon($attendanceProgress['last_activity_date'] ?? null, $submissionProgress['last_activity_date'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function evaluateSubmissionEvidence(Collection $activeRequiredAssignments, Collection $submissions, string $basisLabel): array
    {
        $activeRequiredAssignmentCount = $activeRequiredAssignments->count();

        if ($activeRequiredAssignmentCount === 0) {
            return self::evaluateNotEvaluableProgress(
                $basisLabel,
                'No active required assignments are defined yet for this section.',
            );
        }

        $submissionsByAssignmentId = $submissions
            ->filter(fn (StudentSectionSubmission $submission): bool => $submission->section_assignment_id !== null)
            ->groupBy('section_assignment_id');

        $counts = [
            StudentSectionSubmission::STATUS_ACCEPTED => 0,
            StudentSectionSubmission::STATUS_WAIVED => 0,
            StudentSectionSubmission::STATUS_SUBMITTED => 0,
            StudentSectionSubmission::STATUS_REVISION_NEEDED => 0,
            StudentSectionSubmission::STATUS_REJECTED => 0,
            StudentSectionSubmission::STATUS_NOT_STARTED => 0,
            'archived_only' => 0,
            'missing' => 0,
        ];

        $lastActivityDate = null;

        foreach ($activeRequiredAssignments as $assignment) {
            $representativeSubmission = self::resolveRepresentativeSubmission(collect($submissionsByAssignmentId->get($assignment->id, [])));

            if ($representativeSubmission === null) {
                $counts['missing']++;

                continue;
            }

            if ($representativeSubmission->status === StudentSectionSubmission::STATUS_ARCHIVED) {
                $counts['archived_only']++;

                continue;
            }

            $counts[$representativeSubmission->status] = ($counts[$representativeSubmission->status] ?? 0) + 1;
            $lastActivityDate = self::maxCarbon($lastActivityDate, self::resolveSubmissionActivityDate($representativeSubmission));
        }

        $startedCount = $counts[StudentSectionSubmission::STATUS_ACCEPTED]
            + $counts[StudentSectionSubmission::STATUS_WAIVED]
            + $counts[StudentSectionSubmission::STATUS_SUBMITTED]
            + $counts[StudentSectionSubmission::STATUS_REVISION_NEEDED]
            + $counts[StudentSectionSubmission::STATUS_REJECTED];

        $notStartedCount = $counts[StudentSectionSubmission::STATUS_NOT_STARTED]
            + $counts['missing'];

        $evidenceSummary = implode(' · ', [
            "Required Assignments: {$activeRequiredAssignmentCount}",
            'Accepted: '.$counts[StudentSectionSubmission::STATUS_ACCEPTED],
            'Waived: '.$counts[StudentSectionSubmission::STATUS_WAIVED],
            'Submitted: '.$counts[StudentSectionSubmission::STATUS_SUBMITTED],
            'Revision Needed: '.$counts[StudentSectionSubmission::STATUS_REVISION_NEEDED],
            'Rejected: '.$counts[StudentSectionSubmission::STATUS_REJECTED],
            'Not Started: '.$notStartedCount,
            'Archived Only: '.$counts['archived_only'],
        ]);

        if (($counts['archived_only'] > 0) && $startedCount === 0 && $notStartedCount === 0) {
            return self::evaluateNotEvaluableProgress(
                $basisLabel,
                $evidenceSummary.'. Only archived submission evidence exists for this student.',
            );
        }

        if (($counts[StudentSectionSubmission::STATUS_ACCEPTED] + $counts[StudentSectionSubmission::STATUS_WAIVED]) === $activeRequiredAssignmentCount) {
            return [
                'progress_status' => 'satisfied',
                'progress_basis_used' => $basisLabel,
                'evidence_summary' => $evidenceSummary.'. All active required assignments are accepted or waived.',
                'last_activity_date' => $lastActivityDate,
            ];
        }

        if ($counts[StudentSectionSubmission::STATUS_REVISION_NEEDED] > 0 || $counts[StudentSectionSubmission::STATUS_REJECTED] > 0) {
            return [
                'progress_status' => 'needs_attention',
                'progress_basis_used' => $basisLabel,
                'evidence_summary' => $evidenceSummary.'. At least one required assignment needs revision or was rejected.',
                'last_activity_date' => $lastActivityDate,
            ];
        }

        if ($counts[StudentSectionSubmission::STATUS_SUBMITTED] > 0 || $counts[StudentSectionSubmission::STATUS_ACCEPTED] > 0 || $counts[StudentSectionSubmission::STATUS_WAIVED] > 0) {
            return [
                'progress_status' => 'in_progress',
                'progress_basis_used' => $basisLabel,
                'evidence_summary' => $evidenceSummary.'. Submission evidence exists, but not every required assignment is accepted or waived yet.',
                'last_activity_date' => $lastActivityDate,
            ];
        }

        if ($startedCount === 0) {
            return [
                'progress_status' => 'not_started',
                'progress_basis_used' => $basisLabel,
                'evidence_summary' => $evidenceSummary.'. No required assignment has been started yet.',
                'last_activity_date' => $lastActivityDate,
            ];
        }

        return [
            'progress_status' => 'in_progress',
            'progress_basis_used' => $basisLabel,
            'evidence_summary' => $evidenceSummary.'. Required submission work has begun but is not yet fully satisfied.',
            'last_activity_date' => $lastActivityDate,
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

    protected static function resolveRepresentativeSubmission(Collection $submissions): ?StudentSectionSubmission
    {
        if ($submissions->isEmpty()) {
            return null;
        }

        $nonArchivedSubmissions = $submissions
            ->filter(fn (StudentSectionSubmission $submission): bool => $submission->status !== StudentSectionSubmission::STATUS_ARCHIVED)
            ->values();

        if ($nonArchivedSubmissions->isNotEmpty()) {
            return self::sortAttemptsOrRecordsByRecency($nonArchivedSubmissions)->first();
        }

        return self::sortAttemptsOrRecordsByRecency($submissions)->first();
    }

    protected static function resolveRepresentativeManualCompletion(Collection $manualCompletions): ?StudentSectionManualCompletion
    {
        if ($manualCompletions->isEmpty()) {
            return null;
        }

        $nonArchivedCompletions = $manualCompletions
            ->filter(fn (StudentSectionManualCompletion $completion): bool => $completion->status !== StudentSectionManualCompletion::STATUS_ARCHIVED)
            ->values();

        if ($nonArchivedCompletions->isNotEmpty()) {
            return self::sortAttemptsOrRecordsByRecency($nonArchivedCompletions)->first();
        }

        return self::sortAttemptsOrRecordsByRecency($manualCompletions)->first();
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

    protected static function resolveSubmissionActivityDate(StudentSectionSubmission $submission): ?Carbon
    {
        return self::firstCarbonValue([
            $submission->reviewed_at,
            $submission->submitted_at,
            $submission->updated_at,
            $submission->created_at,
        ]);
    }

    protected static function resolveManualCompletionActivityDate(StudentSectionManualCompletion $manualCompletion): ?Carbon
    {
        return self::firstCarbonValue([
            $manualCompletion->approved_at,
            $manualCompletion->updated_at,
            $manualCompletion->created_at,
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

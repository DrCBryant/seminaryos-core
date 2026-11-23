<?php

namespace App\Filament\Faculty\Pages;

use App\Filament\Resources\Students\Support\DegreeAuditPreview;
use App\Models\AcademicRecord;
use App\Models\CourseEnrollment;
use App\Models\CourseOffering;
use App\Models\MasterAssessment;
use App\Models\ProgramRequirement;
use App\Models\Student;
use App\Models\StudentMasterAssessmentAttempt;
use App\Models\StudentRequirementEvidence;
use App\Models\StudentSectionSubmission;
use App\Models\TeachingAssignment;
use App\Support\Assessments\FinalizeCompetencyMastery;
use App\Support\Portals\AcademicIdentityAccess;
use App\Support\SectionProgress\SectionProgressEvaluator;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class FacultyPortal extends Page
{
    private const SECTIONS = [
        'overview' => 'Overview',
        'mentees' => 'My Mentees',
        'courses' => 'My Courses',
    ];

    protected static ?string $slug = 'overview';

    protected string $view = 'filament.faculty-portal.index';

    #[Locked]
    public string $section = 'overview';

    #[Locked]
    public ?int $selection = null;

    public static function getRoutePath(Panel $panel): string
    {
        return '/';
    }

    public function mount(): void
    {
        $section = request()->query('section', 'overview');
        abort_unless(is_string($section) && isset(self::SECTIONS[$section]), 404);
        $this->section = $section;

        $selection = request()->query('selection');
        if ($selection !== null) {
            $id = filter_var($selection, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            abort_unless($section !== 'overview' && $id !== false, 404);
            $this->selection = $id;
        }
    }

    public function getTitle(): string
    {
        return self::SECTIONS[$this->section];
    }

    protected function getViewData(): array
    {
        $actor = auth()->user();
        $faculty = $actor === null ? null : AcademicIdentityAccess::faculty($actor);
        abort_unless($faculty !== null, 403);
        Gate::authorize('view-own-faculty-identity', $faculty);
        $faculty->loadMissing('institution');

        $mentees = $faculty->mentees()->with('program')->orderBy('last_name')->orderBy('first_name')->orderBy('students.id');
        $assignments = $faculty->teachingAssignments()
            ->whereIn('status', ['assigned', 'active'])
            ->whereHas('course')
            ->where(fn (Builder $query) => $query->whereNull('course_offering_id')
                ->orWhereHas('courseOffering', fn (Builder $offering) => $offering
                    ->whereColumn('course_offerings.course_id', 'teaching_assignments.course_id')))
            ->with(['course', 'courseOffering', 'academicTerm', 'courseOffering.academicTerm'])
            ->orderBy('course_id')->orderBy('id');

        $mentee = $this->section === 'mentees' && $this->selection !== null ? (clone $mentees)->findOrFail($this->selection) : null;
        $assignment = $this->section === 'courses' && $this->selection !== null ? (clone $assignments)->findOrFail($this->selection) : null;

        return [
            'name' => $faculty->full_name,
            'institutionName' => $faculty->institution->name,
            'studentUrl' => AcademicIdentityAccess::student($actor) ? route('filament.student.pages.overview') : null,
            'registrarUrl' => $actor->canAccessRegistrar() ? route('filament.admin.pages.dashboard') : null,
            'navigation' => self::SECTIONS,
            'section' => $this->section,
            'mentees' => $this->section === 'courses' ? collect() : $mentees->get()->map(fn (Student $student): array => [
                'id' => $student->id,
                'name' => $student->full_name,
                'program' => $student->program?->title,
            ]),
            'assignments' => $this->section === 'mentees' ? collect() : $assignments->get()->map(self::assignmentSummary(...)),
            'mentee' => $mentee ? $this->menteeDetail($mentee) : null,
            'course' => $assignment ? $this->courseDetail($assignment) : null,
            ...($this->section === 'overview' ? [
                'pendingAssessments' => StudentMasterAssessmentAttempt::query()
                    ->whereIn('student_id', $faculty->mentees()->select('students.id')->getQuery())
                    ->where('status', StudentMasterAssessmentAttempt::STATUS_SUBMITTED)
                    ->whereHas('masterAssessment', fn (Builder $query) => $query
                        ->whereColumn('master_assessments.course_offering_id', 'student_master_assessment_attempts.course_offering_id')
                        ->where('status', MasterAssessment::STATUS_ACTIVE))
                    ->count(),
                'pendingSubmissions' => StudentSectionSubmission::query()
                    ->whereIn('course_offering_id', (clone $assignments->getQuery())->reorder()->select('course_offering_id'))
                    ->where('status', StudentSectionSubmission::STATUS_SUBMITTED)
                    ->whereHas('sectionAssignment', fn (Builder $query) => $query
                        ->whereColumn('section_assignments.course_offering_id', 'student_section_submissions.course_offering_id'))
                    ->whereHas('courseOffering.courseEnrollments', fn (Builder $query) => $query
                        ->whereColumn('course_enrollments.student_id', 'student_section_submissions.student_id')
                        ->whereColumn('course_enrollments.course_id', 'course_offerings.course_id')
                        ->where(fn (Builder $query) => $query->whereNull('student_section_submissions.course_enrollment_id')
                            ->orWhereColumn('course_enrollments.id', 'student_section_submissions.course_enrollment_id')))
                    ->count(),
            ] : []),
        ];
    }

    public function finalizeMasteryAction(): Action
    {
        return Action::make('finalizeMastery')
            ->label('Finalize mastery')
            ->authorize(fn (): bool => $this->section === 'mentees' && $this->selection !== null)
            ->requiresConfirmation()
            ->modalDescription('Record this passed attempt as verified mastery for the applicable mapped competencies. The decision and its evidence are permanent.')
            ->action(function (Action $action, array $arguments, FinalizeCompetencyMastery $service): void {
                $attemptId = filter_var($arguments['attempt'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                abort_unless($this->section === 'mentees' && $this->selection !== null && $attemptId !== false, 404);
                try {
                    $service->finalize(auth()->user(), $this->selection, $attemptId);
                } catch (ValidationException $exception) {
                    Notification::make()->title('Mastery could not be finalized')
                        ->body($exception->validator->errors()->first())->warning()->send();
                    $action->halt();
                }
            })
            ->successNotificationTitle('Mastery finalized');
    }

    private function menteeDetail(Student $student): array
    {
        Gate::authorize('view-assigned-mentee', $student);

        $competencies = DegreeAuditPreview::getViewData($student)['groupResults']
            ->flatMap(fn (array $group): Collection => $group['requirements'])
            ->filter(fn (array $result): bool => $result['requirement']->requirement_type === ProgramRequirement::TYPE_COMPETENCY);
        $requirements = new \Illuminate\Database\Eloquent\Collection($competencies->pluck('requirement')->all());
        $requirements->load('masterAssessments.courseOffering');
        $applicableAssessments = $requirements->flatMap(fn (ProgramRequirement $requirement) => $requirement->masterAssessments)
            ->where('status', MasterAssessment::STATUS_ACTIVE)->pluck('id');
        $decisions = $student->competencyMasteryDecisions()->get()->keyBy('attempt_id');

        $evidence = $student->studentRequirementEvidence
            ->where('program_id', $student->program_id)->where('status', '!=', 'archived')
            ->groupBy('program_requirement_id');
        $records = $student->academicRecords->whereIn('status', ['completed', 'transfer'])->groupBy('course_id');

        return [
            'name' => $student->full_name,
            'program' => $student->program?->title,
            'competencies' => $competencies->map(fn (array $result): array => [
                'title' => $result['requirement']->name,
                'status' => self::label($result['status']),
                'assessments' => $result['requirement']->masterAssessments
                    ->map(fn (MasterAssessment $assessment): string => $assessment->title.' · '.($assessment->courseOffering?->section_code ?? 'Section unavailable'))
                    ->implode(', '),
                'decision' => isset($result['mastery']) ? $result['mastery']->snapshot['assessment']['title']
                    .' · '.$result['mastery']->snapshot['mentor_name'].' · '.$result['mastery']->decided_at->toDateString() : null,
                'evidence' => $evidence->get($result['requirement']->id, collect())
                    ->map(fn (StudentRequirementEvidence $item): string => $item->evidence_title.' ('.self::label($item->status).')')->implode(', '),
                'course' => $result['requirement']->course_id === null ? null : $records->get($result['requirement']->course_id, collect())
                    ->pluck('course_title')->unique()->implode(', '),
            ]),
            'attempts' => $student->studentMasterAssessmentAttempts()->with('masterAssessment')
                ->whereHas('masterAssessment', fn (Builder $query) => $query
                    ->whereColumn('master_assessments.course_offering_id', 'student_master_assessment_attempts.course_offering_id'))
                ->latest('assessed_at')->latest('id')->get()->map(fn (StudentMasterAssessmentAttempt $attempt): array => [
                    'id' => $attempt->id,
                    'title' => $attempt->masterAssessment->title,
                    'status' => self::label($attempt->status),
                    'submitted' => $attempt->submitted_at?->toDateString(),
                    'assessed' => $attempt->assessed_at?->toDateString(),
                    'finalized' => $decisions->get($attempt->id)?->decided_at->toDateString(),
                    'canFinalize' => ! $decisions->has($attempt->id)
                        && $attempt->status === StudentMasterAssessmentAttempt::STATUS_PASSED
                        && $applicableAssessments->contains($attempt->master_assessment_id),
                ]),
        ];
    }

    private function courseDetail(TeachingAssignment $assignment): array
    {
        Gate::authorize('view-assigned-teaching-assignment', $assignment);
        $offering = $assignment->courseOffering;
        if ($offering instanceof CourseOffering) {
            Gate::authorize('view-assigned-teaching-section', $offering);
        }

        $enrollments = CourseEnrollment::query()->where('course_id', $assignment->course_id)
            ->where('course_offering_id', $assignment->course_offering_id)
            ->when($offering === null, fn (Builder $query) => $query->where('academic_term_id', $assignment->academic_term_id))
            ->whereHas('student')->with(['student', 'academicRecord'])->orderBy('student_id')->orderBy('id')->get();

        $offering?->loadMissing(SectionProgressEvaluator::courseOfferingRelations());

        return self::assignmentSummary($assignment) + [
            'enrollments' => $enrollments->map(function (CourseEnrollment $enrollment) use ($offering): array {
                // Give the shared evaluator only this enrollment's evidence, including matching legacy rows.
                $section = $offering === null ? null : clone $offering;
                if ($section !== null) {
                    foreach (['attendanceRecords', 'studentSectionSubmissions', 'studentMasterAssessmentAttempts', 'studentSectionManualCompletions'] as $relation) {
                        $section->setRelation($relation, $section->{$relation}->filter(fn ($item): bool => (int) $item->student_id === (int) $enrollment->student_id
                            && ($item->course_enrollment_id === null || (int) $item->course_enrollment_id === (int) $enrollment->id)));
                    }
                }
                $enrollment->setRelation('courseOffering', $section);
                $progress = SectionProgressEvaluator::evaluateEnrollment($enrollment);
                $record = $enrollment->academicRecord;
                $outcome = $record instanceof AcademicRecord
                    && (int) $record->student_id === (int) $enrollment->student_id
                    && (int) $record->course_id === (int) $enrollment->course_id
                    && in_array($record->status, ['completed', 'failed', 'withdrawn', 'transfer', 'waived'], true)
                    ? ($record->grade_label ?: $record->final_grade ?: self::label($record->status))
                    : null;

                return [
                    'title' => $enrollment->student->full_name,
                    'status' => self::label($enrollment->status),
                    'progress' => SectionProgressEvaluator::formatProgressStatusLabel($progress['progress_status']),
                    'attendance' => $section?->attendanceRecords
                        ->filter(fn ($record): bool => $record->attendanceSession?->status === 'held'
                            && (int) $record->attendanceSession->course_offering_id === (int) $section->id)
                        ->map(fn ($record): string => $record->attendanceSession->session_date->toDateString().' · '.self::label($record->status))->implode(', '),
                    'submissions' => $section?->studentSectionSubmissions
                        ->filter(fn ($submission): bool => (int) $submission->sectionAssignment?->course_offering_id === (int) $section->id)
                        ->map(fn ($submission): string => $submission->sectionAssignment->title.' · '.self::label($submission->status))->implode(', '),
                    'outcome' => $outcome,
                    'completed' => $enrollment->completed_at?->toDateString(),
                ];
            }),
        ];
    }

    private static function assignmentSummary(TeachingAssignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'title' => $assignment->course->code.' · '.$assignment->course->title,
            'section' => $assignment->courseOffering?->section_code,
            'term' => ($assignment->courseOffering?->academicTerm ?? $assignment->academicTerm)?->display_label,
            'role' => TeachingAssignment::ROLE_OPTIONS[$assignment->role] ?? self::label($assignment->role),
        ];
    }

    private static function label(?string $value): string
    {
        return SectionProgressEvaluator::formatGenericStatusLabel($value);
    }
}

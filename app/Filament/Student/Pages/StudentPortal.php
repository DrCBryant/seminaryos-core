<?php

namespace App\Filament\Student\Pages;

use App\Filament\Resources\Students\Support\DegreeAuditPreview;
use App\Models\AcademicRecord;
use App\Models\CourseEnrollment;
use App\Models\DegreeConferral;
use App\Models\Student;
use App\Support\AcademicTerms\ReportingTermResolver;
use App\Support\Portals\AcademicIdentityAccess;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;

class StudentPortal extends Page
{
    private const SECTIONS = [
        'overview' => 'Overview',
        'courses' => 'My Courses',
        'academic-record' => 'Academic Record',
        'degree-progress' => 'Degree Progress',
        'awards' => 'Awarded Credentials',
    ];

    private const FINALIZED_STATUSES = ['completed', 'failed', 'withdrawn', 'transfer', 'waived'];

    protected static ?string $slug = 'overview';

    protected string $view = 'filament.student-portal.index';

    #[Locked]
    public string $section = 'overview';

    public static function getRoutePath(Panel $panel): string
    {
        return '/';
    }

    public function mount(): void
    {
        $section = request()->query('section', 'overview');
        abort_unless(is_string($section) && isset(self::SECTIONS[$section]), 404);

        $this->section = $section;
    }

    public function getTitle(): string
    {
        return self::SECTIONS[$this->section];
    }

    protected function getViewData(): array
    {
        $actor = auth()->user();
        $student = $actor === null ? null : AcademicIdentityAccess::student($actor);
        abort_unless($student !== null, 403);
        Gate::authorize('view-own-student-identity', $student);
        $student->loadMissing(['institution', 'program']);

        return [
            'section' => $this->section,
            'navigation' => self::SECTIONS,
            'name' => $student->full_name,
            'institutionName' => $student->institution->name,
            'programTitle' => $student->program?->title,
            'facultyUrl' => AcademicIdentityAccess::faculty($actor) ? route('filament.faculty.pages.overview') : null,
            'registrarUrl' => $actor->canAccessRegistrar() ? route('filament.admin.pages.dashboard') : null,
            ...match ($this->section) {
                'overview' => [
                    'studentNumber' => $student->student_number,
                    'administrativeStatus' => str($student->status)->headline()->value(),
                    'currentCourses' => $student->courseEnrollments()
                        ->whereIn('status', ['enrolled', 'in_progress', 'active'])->count(),
                    'completedCourses' => $this->finalizedRecords($student)->where('status', 'completed')->count(),
                    'awards' => $this->awards($student),
                ],
                'courses' => ['courses' => $this->courses($student)],
                'academic-record' => ['records' => $this->academicHistory($student)],
                'degree-progress' => ['groups' => $this->degreeProgress($student)],
                'awards' => ['awards' => $this->awards($student)],
            },
        ];
    }

    private function finalizedRecords(Student $student): HasMany
    {
        return $student->academicRecords()->whereIn('status', self::FINALIZED_STATUSES);
    }

    private function courses(Student $student): Collection
    {
        return $student->courseEnrollments()
            ->with([
                'course' => fn ($query) => $query->withTrashed(),
                'academicTerm' => fn ($query) => $query->withTrashed(),
                'courseOffering.academicTerm' => fn ($query) => $query->withTrashed(),
                'academicRecord' => fn ($query) => $query->where('student_id', $student->id)
                    ->whereIn('status', self::FINALIZED_STATUSES),
            ])
            ->orderByDesc('enrolled_at')->orderByDesc('id')->get()
            ->map(function (CourseEnrollment $enrollment): array {
                $outcome = $enrollment->academicRecord;

                return [
                    'code' => $outcome?->course_code ?? $enrollment->course?->code,
                    'title' => $outcome?->course_title ?? $enrollment->course?->title,
                    'section' => $enrollment->courseOffering?->section_code,
                    'term' => ($enrollment->academicTerm ?? $enrollment->courseOffering?->academicTerm)?->display_label,
                    'status' => str($enrollment->status)->headline()->value(),
                    'enrolled' => $enrollment->enrolled_at?->toFormattedDateString(),
                    'completed' => ($outcome?->completed_at ?? $enrollment->completed_at)?->toFormattedDateString(),
                    'outcome' => $outcome === null ? null : ($outcome->final_grade ?? str($outcome->status)->headline()->value()),
                ];
            });
    }

    private function academicHistory(Student $student): Collection
    {
        $resolver = app(ReportingTermResolver::class);

        return $this->finalizedRecords($student)
            ->with([
                'course' => fn ($query) => $query->withTrashed(),
                'academicTerm' => fn ($query) => $query->withTrashed(),
            ])
            ->orderByDesc('completed_at')->orderByDesc('id')->get()
            ->map(function (AcademicRecord $record) use ($resolver): array {
                $reporting = $resolver->resolveAcademicRecord($record);

                return [
                    'code' => $record->course_code,
                    'title' => $record->course_title,
                    'term' => $reporting['status'] === ReportingTermResolver::RESOLVED
                        ? $reporting['term']?->display_label
                        : 'Reporting term pending registrar review',
                    'outcome' => $record->final_grade ?? $record->grade_label,
                    'attempted' => $record->credits_attempted,
                    'earned' => $record->credits_earned,
                    'completed' => $record->completed_at?->toFormattedDateString(),
                    'status' => str($record->status)->headline()->value(),
                ];
            });
    }

    private function degreeProgress(Student $student): Collection
    {
        $student->load([
            'studentRequirementEvidence' => fn ($query) => $query->where('program_id', $student->program_id),
            'programRequirementSubstitutions' => fn ($query) => $query->where('program_id', $student->program_id),
        ]);

        return DegreeAuditPreview::getViewData($student)['groupResults']->map(fn (array $group): array => [
            'name' => $group['group']->name,
            'status' => str($group['status'])->headline()->value(),
            'earned' => $group['earnedCredits'],
            'required' => $group['requiredCredits'],
            'requirements' => $group['requirements']->map(fn (array $result): array => [
                'name' => $result['requirement']->name,
                'status' => str($result['status'])->headline()->value(),
                'basis' => match (true) {
                    isset($result['substitution']) && $result['requirement']->allow_substitution => 'Approved substitution',
                    isset($result['evidence']) => 'Approved or waived evidence',
                    default => null,
                },
            ]),
        ]);
    }

    private function awards(Student $student): Collection
    {
        return $student->degreeConferrals()->orderByDesc('conferred_on')->orderByDesc('id')
            ->get(['program_title', 'program_code', 'credential_type', 'conferred_on'])
            ->map(fn (DegreeConferral $award): array => [
                'program' => $award->program_title,
                'code' => $award->program_code,
                'credential' => $award->credential_type,
                'date' => $award->conferred_on->toFormattedDateString(),
            ]);
    }
}

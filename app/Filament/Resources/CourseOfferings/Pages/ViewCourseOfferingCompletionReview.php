<?php

namespace App\Filament\Resources\CourseOfferings\Pages;

use App\Filament\Resources\CourseEnrollments\CourseEnrollmentResource;
use App\Filament\Resources\CourseOfferings\CourseOfferingResource;
use App\Models\CourseEnrollment;
use App\Models\CourseOffering;
use App\Support\SectionProgress\SectionProgressEvaluator;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ViewCourseOfferingCompletionReview extends Page
{
    use InteractsWithRecord {
        getRecord as getBaseRecord;
    }

    protected static string $resource = CourseOfferingResource::class;

    protected static ?string $breadcrumb = 'Completion Review';

    protected static ?string $navigationLabel = 'Completion Review';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.resources.course-offerings.pages.view-course-offering-completion-review';

    public CourseOffering $courseOffering;

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $enrollmentReviews = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->authorizeAccess();

        /** @var CourseOffering $courseOffering */
        $courseOffering = $this->getRecord()->load([
            'institution',
            'course',
            'academicTerm',
            'courseEnrollments.student' => fn ($query) => $query->withTrashed(),
        ]);

        $courseOffering->loadMissing(SectionProgressEvaluator::courseOfferingRelations());

        $this->courseOffering = $courseOffering;
        $this->enrollmentReviews = $this->buildEnrollmentReviews();
    }

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canEdit($this->getRecord()), 403);
    }

    public function getTitle(): string|Htmlable
    {
        $course = $this->courseOffering->course;

        return trim("Completion Review · {$course?->code} · {$this->courseOffering->section_code}");
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->label('Edit Offering')
                ->icon('heroicon-o-pencil-square')
                ->color('primary')
                ->url($this->getResourceUrl('edit')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        $reviews = collect($this->enrollmentReviews);

        $completedEnrollmentCount = $reviews
            ->where('readiness', 'already_completed')
            ->count();

        $readyToCompleteCount = $reviews
            ->where('readiness', 'ready_to_complete')
            ->count();

        $overrideRequiredCount = $reviews
            ->where('readiness', 'override_required')
            ->count();

        $notEvaluableCount = $reviews
            ->where('readiness', 'not_evaluable')
            ->count();

        $notStartedCount = $reviews
            ->where('readiness', 'not_started')
            ->count();

        $inProgressCount = $reviews
            ->where('readiness', 'in_progress')
            ->count();

        return [
            'institution_name' => $this->courseOffering->institution?->name ?? '—',
            'course_code_title' => trim(($this->courseOffering->course?->code ?? '—').' — '.($this->courseOffering->course?->title ?? $this->courseOffering->title ?? '—')),
            'academic_term' => $this->courseOffering->academicTerm?->name ?? '—',
            'section_code' => $this->courseOffering->section_code ?? '—',
            'progress_basis' => SectionProgressEvaluator::formatProgressBasisLabel($this->courseOffering->progress_basis),
            'delivery_mode' => SectionProgressEvaluator::formatDeliveryModeLabel($this->courseOffering->delivery_mode),
            'capacity' => $this->courseOffering->capacity === null ? 'Unlimited' : (string) $this->courseOffering->capacity,
            'enrolled_count' => $this->courseOffering->enrolledCount(),
            'completed_enrollment_count' => $completedEnrollmentCount,
            'already_completed_count' => $completedEnrollmentCount,
            'ready_to_complete_count' => $readyToCompleteCount,
            'override_required_count' => $overrideRequiredCount,
            'needs_override_count' => $overrideRequiredCount,
            'not_evaluable_count' => $notEvaluableCount,
            'not_started_count' => $notStartedCount,
            'in_progress_count' => $inProgressCount,
        ];
    }

    public function getEnrollmentReviews(): Collection
    {
        return collect($this->enrollmentReviews);
    }

    public function formatDate(mixed $value, string $format = 'M j, Y'): string
    {
        return $value?->format($format) ?? '—';
    }

    public function formatDateTime(mixed $value, string $format = 'M j, Y g:i A'): string
    {
        return $value?->format($format) ?? '—';
    }

    public function formatEnrollmentStatus(?string $value): string
    {
        return filled($value)
            ? str((string) $value)->replace('_', ' ')->title()->toString()
            : '—';
    }

    public function formatReadinessLabel(string $value): string
    {
        return match ($value) {
            'already_completed' => 'Already Completed',
            'ready_to_complete' => 'Ready to Complete',
            'override_required' => 'Override Required',
            'not_evaluable' => 'Not Evaluable',
            'not_started' => 'Not Started',
            'in_progress' => 'In Progress',
            default => SectionProgressEvaluator::formatGenericStatusLabel($value),
        };
    }

    public function readinessBadgeClasses(string $value): string
    {
        return match ($value) {
            'already_completed' => 'background: #ecfeff; color: #155e75; border-color: #a5f3fc;',
            'ready_to_complete' => 'background: #dcfce7; color: #166534; border-color: #86efac;',
            'override_required' => 'background: #fee2e2; color: #991b1b; border-color: #fca5a5;',
            'not_evaluable' => 'background: #fef3c7; color: #92400e; border-color: #fcd34d;',
            'not_started' => 'background: #e5e7eb; color: #374151; border-color: #d1d5db;',
            'in_progress' => 'background: #dbeafe; color: #1d4ed8; border-color: #93c5fd;',
            default => 'background: #f3f4f6; color: #111827; border-color: #d1d5db;',
        };
    }

    public function readinessCountLabel(string $value): string
    {
        return match ($value) {
            'already_completed' => 'Already Completed',
            'ready_to_complete' => 'Ready to Complete',
            'override_required' => 'Override Required',
            'not_evaluable' => 'Not Evaluable',
            'not_started' => 'Not Started',
            'in_progress' => 'In Progress',
            default => $this->formatReadinessLabel($value),
        };
    }

    public function nextStepLabel(string $value): string
    {
        return match ($value) {
            'already_completed' => 'No action needed.',
            'ready_to_complete' => 'Open enrollment and run Complete Enrollment.',
            'override_required' => 'Review evidence. Completion will require an override reason.',
            'not_evaluable' => 'Progress cannot be evaluated yet. Completion will require an override reason.',
            'not_started' => 'No sufficient progress evidence yet.',
            'in_progress' => 'Progress evidence exists but is not yet satisfied.',
            default => 'Review enrollment details before taking action.',
        };
    }

    public function getRecord(): Model
    {
        return $this->courseOffering ?? $this->getBaseRecord();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildEnrollmentReviews(): array
    {
        return $this->courseOffering->courseEnrollments
            ->sortBy([
                fn (CourseEnrollment $enrollment): string => strtolower((string) $enrollment->student?->last_name),
                fn (CourseEnrollment $enrollment): string => strtolower((string) $enrollment->student?->first_name),
                fn (CourseEnrollment $enrollment): string => strtolower((string) ($enrollment->student?->student_number ?? 'zzzzzzzz')),
            ])
            ->map(function (CourseEnrollment $enrollment): array {
                $evaluation = SectionProgressEvaluator::evaluateEnrollment($enrollment);
                $progressStatus = $evaluation['progress_status'] ?? null;
                $readiness = $this->resolveReadiness($enrollment, $progressStatus);

                return [
                    'id' => $enrollment->id,
                    'student_name' => $enrollment->student?->full_name ?? '—',
                    'student_number' => $enrollment->student?->student_number ?? '—',
                    'enrollment_status' => $enrollment->status,
                    'completed_at' => $enrollment->completed_at,
                    'progress_basis' => $evaluation['progress_basis_used'] ?? SectionProgressEvaluator::formatProgressBasisLabel($this->courseOffering->progress_basis),
                    'progress_status' => $progressStatus,
                    'progress_status_label' => filled($progressStatus) ? SectionProgressEvaluator::formatProgressStatusLabel($progressStatus) : '—',
                    'progress_badge_classes' => filled($progressStatus) ? SectionProgressEvaluator::progressStatusBadgeClasses($progressStatus) : SectionProgressEvaluator::progressStatusBadgeClasses('not_evaluable'),
                    'evidence_summary' => $evaluation['evidence_summary'] ?? '—',
                    'last_activity_date' => $evaluation['last_activity_date'] ?? null,
                    'readiness' => $readiness,
                    'requires_override' => $this->requiresOverride($enrollment, $progressStatus),
                    'edit_url' => CourseEnrollmentResource::getUrl('edit', ['record' => $enrollment]),
                ];
            })
            ->values()
            ->all();
    }

    protected function resolveReadiness(CourseEnrollment $enrollment, ?string $progressStatus): string
    {
        if ($enrollment->status === 'completed' || $enrollment->completed_at !== null) {
            return 'already_completed';
        }

        return match ($progressStatus) {
            'satisfied' => 'ready_to_complete',
            'not_evaluable' => 'not_evaluable',
            'not_started' => 'not_started',
            'in_progress' => 'in_progress',
            'needs_attention' => 'override_required',
            default => 'override_required',
        };
    }

    protected function requiresOverride(CourseEnrollment $enrollment, ?string $progressStatus): bool
    {
        if ($enrollment->status === 'completed' || $enrollment->completed_at !== null) {
            return false;
        }

        return in_array($progressStatus, ['not_started', 'in_progress', 'needs_attention', 'not_evaluable'], true);
    }
}

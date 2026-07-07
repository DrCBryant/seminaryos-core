<?php

namespace App\Filament\Resources\CourseOfferings\Pages;

use App\Filament\Resources\CourseEnrollments\CourseEnrollmentResource;
use App\Filament\Resources\CourseOfferings\CourseOfferingResource;
use App\Models\CourseEnrollment;
use App\Models\CourseOffering;
use App\Models\GradeScale;
use App\Models\GradeValue;
use App\Support\Enrollments\EnrollmentCompletionService;
use App\Support\SectionProgress\SectionProgressEvaluator;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ViewCourseOfferingCompletionReview extends Page
{
    use InteractsWithRecord {
        getRecord as getBaseRecord;
    }

    protected const OVERRIDABLE_PROGRESS_STATUSES = [
        'not_started',
        'in_progress',
        'needs_attention',
        'not_evaluable',
    ];

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
            'courseEnrollments.completionReviewedByUser',
            'courseEnrollments.academicRecord',
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
        $summary = $this->getSummary();
        $hasReadyToCompleteEnrollments = $summary['ready_to_complete_count'] > 0;

        return [
            Action::make('completeReadyEnrollments')
                ->label('Complete Ready Enrollments')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(true)
                ->disabled(! $hasReadyToCompleteEnrollments)
                ->tooltip(! $hasReadyToCompleteEnrollments
                    ? 'No ready_to_complete enrollments are currently eligible for guarded bulk completion.'
                    : 'Only ready_to_complete enrollments are included. Override-based, in-progress, not-started, not-evaluable, and already-completed enrollments are excluded.')
                ->modalHeading('Complete Ready Enrollments')
                ->modalDescription('Guarded bulk completion only permits ready_to_complete enrollments and excludes override-required enrollments. Each selected enrollment still requires official completion inputs so no grade or academic record data is invented.')
                ->modalWidth('7xl')
                ->fillForm(fn (): array => [
                    'enrollments' => $this->bulkCompletionEnrollmentDefaults(),
                ])
                ->form([
                    Placeholder::make('bulk_completion_ready_count')
                        ->label('Ready enrollments that will be completed')
                        ->content(fn (): string => (string) $this->getReadyToCompleteEnrollments()->count()),
                    Placeholder::make('bulk_completion_excluded_count')
                        ->label('Enrollments excluded from bulk completion')
                        ->content(fn (): string => (string) $this->getExcludedEnrollmentCount()),
                    Placeholder::make('bulk_completion_warning')
                        ->label('Guard rails')
                        ->content('Only ready_to_complete enrollments will be completed. No override completions are included.'),
                    Repeater::make('enrollments')
                        ->label('Ready enrollments')
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->collapsed(false)
                        ->schema([
                            Hidden::make('enrollment_id')->required(),
                            Placeholder::make('student')
                                ->label('Student')
                                ->content(fn (Get $get): string => (string) ($get('student_name') ?? '—')),
                            Placeholder::make('progress_status')
                                ->label('Progress status')
                                ->content(fn (Get $get): string => (string) ($get('progress_status_label') ?? '—')),
                            Grid::make(2)
                                ->schema([
                                    Select::make('grade_scale_id')
                                        ->label('Grade scale')
                                        ->options(function (Get $get): array {
                                            $enrollment = $this->findBulkEnrollmentById($get('enrollment_id'));

                                            if (! $enrollment) {
                                                return [];
                                            }

                                            return GradeScale::query()
                                                ->where('institution_id', $enrollment->institution_id)
                                                ->where('is_active', true)
                                                ->orderBy('name')
                                                ->pluck('name', 'id')
                                                ->all();
                                        })
                                        ->searchable()
                                        ->preload()
                                        ->live(),
                                    Select::make('grade_value_id')
                                        ->label('Grade value')
                                        ->options(function (Get $get): array {
                                            $enrollment = $this->findBulkEnrollmentById($get('enrollment_id'));
                                            $gradeScaleId = $get('grade_scale_id');

                                            if (! $enrollment || blank($gradeScaleId)) {
                                                return [];
                                            }

                                            return GradeValue::query()
                                                ->where('institution_id', $enrollment->institution_id)
                                                ->where('grade_scale_id', $gradeScaleId)
                                                ->orderByRaw('CASE WHEN sort_order IS NULL THEN 1 ELSE 0 END')
                                                ->orderBy('sort_order')
                                                ->orderBy('grade')
                                                ->get()
                                                ->mapWithKeys(fn (GradeValue $gradeValue) => [
                                                    $gradeValue->id => $gradeValue->label
                                                        ? "{$gradeValue->grade} — {$gradeValue->label}"
                                                        : $gradeValue->grade,
                                                ])
                                                ->all();
                                        })
                                        ->searchable()
                                        ->preload()
                                        ->live()
                                        ->required(fn (Get $get): bool => filled($get('grade_scale_id'))),
                                    TextInput::make('final_grade')
                                        ->label('Final grade')
                                        ->helperText('Use manual final grade only when no grade scale/value is selected.')
                                        ->required(fn (Get $get): bool => blank($get('grade_scale_id')) && blank($get('grade_value_id')))
                                        ->visible(fn (Get $get): bool => blank($get('grade_scale_id')) && blank($get('grade_value_id')))
                                        ->maxLength(20),
                                    TextInput::make('credits_attempted')
                                        ->numeric()
                                        ->inputMode('decimal')
                                        ->required(),
                                    TextInput::make('credits_earned')
                                        ->numeric()
                                        ->inputMode('decimal')
                                        ->required(),
                                    TextInput::make('grade_points')
                                        ->numeric()
                                        ->inputMode('decimal'),
                                    DatePicker::make('completed_at')
                                        ->label('Completed date')
                                        ->default(Carbon::today())
                                        ->required(),
                                    Textarea::make('notes')
                                        ->rows(3)
                                        ->columnSpanFull(),
                                ]),
                        ])
                        ->columns(1)
                        ->itemLabel(fn (array $state): ?string => $state['student_name'] ?? null),
                ])
                ->action(function (array $data): void {
                    $readyEnrollments = $this->getReadyToCompleteEnrollments()->keyBy(fn (CourseEnrollment $enrollment): int => (int) $enrollment->id);

                    if ($readyEnrollments->isEmpty()) {
                        Notification::make()
                            ->title('No ready enrollments available')
                            ->body('No ready_to_complete enrollments are currently eligible for guarded bulk completion.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $rows = collect($data['enrollments'] ?? []);

                    if ($rows->isEmpty()) {
                        Notification::make()
                            ->title('No ready enrollments were submitted')
                            ->body('The bulk completion form did not include any ready_to_complete enrollments.')
                            ->warning()
                            ->send();

                        return;
                    }

                    try {
                        DB::transaction(function () use ($rows, $readyEnrollments): void {
                            $completionService = app(EnrollmentCompletionService::class);

                            foreach ($rows as $index => $row) {
                                $enrollmentId = (int) ($row['enrollment_id'] ?? 0);
                                /** @var CourseEnrollment|null $enrollment */
                                $enrollment = $readyEnrollments->get($enrollmentId);

                                if (! $enrollment) {
                                    throw ValidationException::withMessages([
                                        "enrollments.{$index}.enrollment_id" => 'This enrollment is no longer eligible for guarded bulk completion.',
                                    ]);
                                }

                                $progressEvaluation = $this->progressEvaluationSnapshot($enrollment);

                                if ($progressEvaluation['requires_override']) {
                                    throw ValidationException::withMessages([
                                        "enrollments.{$index}.enrollment_id" => 'Override-based enrollments cannot be completed in bulk.',
                                    ]);
                                }

                                $completionService->complete($enrollment, $row, $progressEvaluation);
                            }
                        });
                    } catch (ValidationException $exception) {
                        $message = collect($exception->errors())
                            ->flatten()
                            ->filter()
                            ->first() ?? 'Guarded bulk completion was blocked.';

                        Notification::make()
                            ->title('Bulk completion blocked')
                            ->body((string) $message)
                            ->warning()
                            ->send();

                        return;
                    } catch (\Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title('Bulk completion failed')
                            ->body('No enrollments were completed because the guarded bulk completion transaction failed.')
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Ready enrollments completed')
                        ->body('Only ready_to_complete enrollments were completed. Override-based enrollments were excluded.')
                        ->success()
                        ->send();

                    $this->courseOffering->refresh()->load([
                        'institution',
                        'course',
                        'academicTerm',
                        'courseEnrollments.student' => fn ($query) => $query->withTrashed(),
                    ]);
                    $this->courseOffering->loadMissing(SectionProgressEvaluator::courseOfferingRelations());
                    $this->enrollmentReviews = $this->buildEnrollmentReviews();
                }),
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
            'ready_to_complete' => 'Use Complete Ready Enrollments or open the enrollment for individual completion.',
            'override_required' => 'Review evidence. Completion will require an override reason.',
            'not_evaluable' => 'Progress cannot be evaluated yet. Completion will require an override reason.',
            'not_started' => 'No sufficient progress evidence yet.',
            'in_progress' => 'Progress evidence exists but is not yet satisfied.',
            default => 'Review enrollment details before taking action.',
        };
    }

    public function bulkCompletionConfirmationSummary(): string
    {
        $reviews = $this->getEnrollmentReviews();
        $readyCount = $reviews->where('readiness', 'ready_to_complete')->count();
        $excludedCount = $reviews->count() - $readyCount;

        return implode("\n", [
            "Ready enrollments that would be completed: {$readyCount}",
            "Enrollments excluded from bulk completion: {$excludedCount}",
            'Only ready_to_complete enrollments would ever be included.',
            'No override completions are included.',
            'Each included enrollment must still provide official grade and credit inputs before completion is saved.',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function bulkCompletionEnrollmentDefaults(): array
    {
        return $this->getReadyToCompleteEnrollments()
            ->map(function (CourseEnrollment $enrollment): array {
                $progressEvaluation = $this->progressEvaluationSnapshot($enrollment);

                return [
                    'enrollment_id' => $enrollment->id,
                    'student_name' => $enrollment->student?->full_name ?? '—',
                    'progress_status_label' => $progressEvaluation['progress_status'],
                    'grade_scale_id' => null,
                    'grade_value_id' => null,
                    'final_grade' => $enrollment->final_grade,
                    'credits_attempted' => $enrollment->course?->credit_hours,
                    'credits_earned' => $enrollment->course?->credit_hours,
                    'grade_points' => null,
                    'completed_at' => Carbon::today(),
                    'notes' => null,
                ];
            })
            ->values()
            ->all();
    }

    public function getExcludedEnrollmentCount(): int
    {
        return $this->getEnrollmentReviews()->count() - $this->getReadyToCompleteEnrollments()->count();
    }

    public function getRecord(): Model
    {
        return $this->courseOffering ?? $this->getBaseRecord();
    }

    protected function findBulkEnrollmentById(mixed $id): ?CourseEnrollment
    {
        if (blank($id)) {
            return null;
        }

        return $this->courseOffering->courseEnrollments
            ->first(fn (CourseEnrollment $enrollment): bool => (int) $enrollment->id === (int) $id);
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
                    'completion_snapshot_status' => $this->completionSnapshotStatus($enrollment),
                    'completion_reviewed_at' => $enrollment->completion_reviewed_at,
                    'completion_reviewed_by' => $this->completionReviewerSummary($enrollment),
                    'academic_record_linked' => $enrollment->academicRecord !== null,
                    'edit_url' => CourseEnrollmentResource::getUrl('edit', ['record' => $enrollment]),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, CourseEnrollment>
     */
    protected function getReadyToCompleteEnrollments(): Collection
    {
        $this->courseOffering->loadMissing([
            'courseEnrollments' => fn (Builder $query) => $query
                ->with([
                    'student' => fn ($studentQuery) => $studentQuery->withTrashed(),
                    'course',
                    'academicRecord',
                    'courseOffering',
                ]),
        ]);

        $this->courseOffering->loadMissing(SectionProgressEvaluator::courseOfferingRelations());

        return $this->courseOffering->courseEnrollments
            ->filter(function (CourseEnrollment $enrollment): bool {
                if ($enrollment->status === 'completed' || $enrollment->completed_at !== null) {
                    return false;
                }

                if ($enrollment->academicRecord !== null) {
                    return false;
                }

                $progressEvaluation = $this->progressEvaluationSnapshot($enrollment);

                return $progressEvaluation['progress_status_raw'] === 'satisfied';
            })
            ->sortBy([
                fn (CourseEnrollment $enrollment): string => strtolower((string) $enrollment->student?->last_name),
                fn (CourseEnrollment $enrollment): string => strtolower((string) $enrollment->student?->first_name),
                fn (CourseEnrollment $enrollment): string => strtolower((string) ($enrollment->student?->student_number ?? 'zzzzzzzz')),
            ])
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    protected function progressEvaluationSnapshot(CourseEnrollment $enrollment): array
    {
        $enrollment->loadMissing([
            'courseOffering',
            'course',
        ]);

        if (! $enrollment->course_offering_id || ! $enrollment->courseOffering) {
            return [
                'progress_basis' => 'Legacy Enrollment',
                'progress_basis_raw' => null,
                'progress_status' => 'Not Available',
                'progress_status_raw' => null,
                'evidence_summary' => 'No section progress evaluation is available because this enrollment is not linked to a course offering. Legacy completion behavior is preserved.',
                'evidence_summary_raw' => 'No section progress evaluation is available because this enrollment is not linked to a course offering. Legacy completion behavior is preserved.',
                'last_activity_at' => '—',
                'requires_override' => false,
            ];
        }

        $enrollment->courseOffering->loadMissing(SectionProgressEvaluator::courseOfferingRelations());

        $evaluation = SectionProgressEvaluator::evaluateEnrollment($enrollment);
        $rawStatus = $evaluation['progress_status'] ?? null;

        return [
            'progress_basis' => $evaluation['progress_basis_used'] ?? '—',
            'progress_basis_raw' => $enrollment->courseOffering->progress_basis,
            'progress_status' => filled($rawStatus) ? SectionProgressEvaluator::formatProgressStatusLabel($rawStatus) : '—',
            'progress_status_raw' => $rawStatus,
            'evidence_summary' => $evaluation['evidence_summary'] ?? '—',
            'evidence_summary_raw' => $evaluation['evidence_summary'] ?? null,
            'last_activity_at' => ($evaluation['last_activity_date'] ?? null)?->toDateTimeString() ?? '—',
            'requires_override' => in_array($rawStatus, self::OVERRIDABLE_PROGRESS_STATUSES, true),
        ];
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

    protected function completionSnapshotStatus(CourseEnrollment $enrollment): string
    {
        $hasSnapshot = filled($enrollment->completion_progress_basis)
            || filled($enrollment->completion_progress_status)
            || filled($enrollment->completion_evidence_summary);

        return $hasSnapshot ? 'Snapshot exists' : 'Snapshot missing';
    }

    protected function completionReviewerSummary(CourseEnrollment $enrollment): string
    {
        $reviewer = $enrollment->completionReviewedByUser;

        if (! $reviewer) {
            return '—';
        }

        return trim(collect([$reviewer->name, $reviewer->email])->filter()->implode(' · ')) ?: '—';
    }
}

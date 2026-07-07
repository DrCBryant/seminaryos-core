<?php

namespace App\Filament\Resources\CourseEnrollments\Tables;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\GradeScale;
use App\Models\GradeValue;
use App\Support\Enrollments\EnrollmentCompletionService;
use App\Support\SectionProgress\SectionProgressEvaluator;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CourseEnrollmentsTable
{
    protected const COMPLETABLE_STATUSES = ['enrolled', 'active'];

    protected const OVERRIDABLE_PROGRESS_STATUSES = [
        'not_started',
        'in_progress',
        'needs_attention',
        'not_evaluable',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student.full_name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('course.title')
                    ->label('Course')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('courseOffering.section_code')
                    ->label('Course offering')
                    ->formatStateUsing(fn (?string $state, CourseEnrollment $record): string => $record->courseOffering
                        ? trim("{$record->courseOffering->section_code} — {$record->courseOffering->academicTerm?->name}")
                        : '—')
                    ->toggleable(),
                TextColumn::make('academicTerm.name')
                    ->label('Term')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('final_grade')
                    ->label('Final grade')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('enrolled_at')
                    ->label('Enrolled date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('academic_term_id')
                    ->label('Term')
                    ->options(fn () => AcademicTerm::query()
                        ->orderedForSelection()
                        ->get()
                        ->mapWithKeys(fn (AcademicTerm $term) => [$term->id => $term->display_label])
                        ->all())
                    ->searchable(),
                SelectFilter::make('status')
                    ->options([
                        'enrolled' => 'Enrolled',
                        'dropped' => 'Dropped',
                        'withdrawn' => 'Withdrawn',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'incomplete' => 'Incomplete',
                    ]),
                SelectFilter::make('course_id')
                    ->label('Course')
                    ->options(fn () => Course::query()
                        ->orderBy('title')
                        ->get()
                        ->mapWithKeys(fn (Course $course) => [$course->id => "{$course->code} — {$course->title}"])
                        ->all())
                    ->searchable(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('completeEnrollment')
                    ->label('Complete Enrollment')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (CourseEnrollment $record): bool => in_array($record->status, self::COMPLETABLE_STATUSES, true)
                        && ! $record->academicRecord()->exists())
                    ->requiresConfirmation()
                    ->modalHeading('Complete enrollment')
                    ->modalDescription('This will mark the enrollment as completed and create a durable academic record snapshot.')
                    ->form([
                        Placeholder::make('progress_basis_preview')
                            ->label('Progress basis')
                            ->content(fn (CourseEnrollment $record): string => self::progressEvaluationForModal($record)['progress_basis']),
                        Placeholder::make('progress_status_preview')
                            ->label('Progress status')
                            ->content(fn (CourseEnrollment $record): string => self::progressEvaluationForModal($record)['progress_status']),
                        Placeholder::make('progress_evidence_preview')
                            ->label('Evidence summary')
                            ->content(fn (CourseEnrollment $record): string => self::progressEvaluationForModal($record)['evidence_summary']),
                        Placeholder::make('progress_last_activity_preview')
                            ->label('Last activity')
                            ->content(fn (CourseEnrollment $record): string => self::progressEvaluationForModal($record)['last_activity_at']),
                        Checkbox::make('confirm_override_completion')
                            ->label('Complete with override')
                            ->helperText('Required when section progress is not satisfied or not evaluable.')
                            ->visible(fn (CourseEnrollment $record): bool => self::progressEvaluationForModal($record)['requires_override'])
                            ->required(fn (CourseEnrollment $record): bool => self::progressEvaluationForModal($record)['requires_override']),
                        Textarea::make('completion_override_reason')
                            ->label('Override reason')
                            ->rows(3)
                            ->visible(fn (CourseEnrollment $record): bool => self::progressEvaluationForModal($record)['requires_override'])
                            ->required(fn (CourseEnrollment $record): bool => self::progressEvaluationForModal($record)['requires_override'])
                            ->rule(function (CourseEnrollment $record): \Closure {
                                return function (string $attribute, $value, \Closure $fail) use ($record): void {
                                    $evaluation = self::progressEvaluationForModal($record);

                                    if ($evaluation['requires_override'] && blank($value)) {
                                        $fail('An override reason is required when progress is not satisfied or not evaluable.');
                                    }
                                };
                            }),
                        Select::make('grade_scale_id')
                            ->label('Grade scale')
                            ->options(fn (CourseEnrollment $record): array => GradeScale::query()
                                ->where('institution_id', $record->institution_id)
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->live(),
                        Select::make('grade_value_id')
                            ->label('Grade value')
                            ->options(function (CourseEnrollment $record, Get $get): array {
                                $gradeScaleId = $get('grade_scale_id');

                                if (blank($gradeScaleId)) {
                                    return [];
                                }

                                return GradeValue::query()
                                    ->where('institution_id', $record->institution_id)
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
                            ->rows(4),
                    ])
                    ->action(function (CourseEnrollment $record, array $data): void {
                        $progressEvaluation = self::progressEvaluationSnapshot($record);

                        if ($progressEvaluation['requires_override'] && ! ($data['confirm_override_completion'] ?? false)) {
                            Notification::make()
                                ->title('Override confirmation is required')
                                ->body('This enrollment can be completed, but only with an explicit override because section progress is not satisfied or not evaluable.')
                                ->warning()
                                ->send();

                            return;
                        }

                        if ($progressEvaluation['requires_override'] && blank($data['completion_override_reason'] ?? null)) {
                            Notification::make()
                                ->title('Override reason is required')
                                ->body('Enter an override reason before completing an enrollment whose evaluated progress is not satisfied or not evaluable.')
                                ->warning()
                                ->send();

                            return;
                        }

                        try {
                            app(EnrollmentCompletionService::class)->complete($record, $data, $progressEvaluation);
                        } catch (ValidationException $exception) {
                            $message = collect($exception->errors())
                                ->flatten()
                                ->filter()
                                ->first() ?? 'The enrollment could not be completed.';

                            Notification::make()
                                ->title('Enrollment completion blocked')
                                ->body((string) $message)
                                ->warning()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Enrollment completed')
                            ->body('The course enrollment was completed and an academic record was created.')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function progressEvaluationForModal(CourseEnrollment $record): array
    {
        $evaluation = self::progressEvaluationSnapshot($record);

        return [
            'progress_basis' => $evaluation['progress_basis'],
            'progress_status' => $evaluation['progress_status'],
            'evidence_summary' => $evaluation['evidence_summary'],
            'last_activity_at' => $evaluation['last_activity_at'],
            'requires_override' => $evaluation['requires_override'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function progressEvaluationSnapshot(CourseEnrollment $record): array
    {
        $record->loadMissing([
            'courseOffering',
        ]);

        if (! $record->course_offering_id || ! $record->courseOffering) {
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

        $record->courseOffering->loadMissing(SectionProgressEvaluator::courseOfferingRelations());

        $evaluation = SectionProgressEvaluator::evaluateEnrollment($record);
        $rawStatus = $evaluation['progress_status'] ?? null;

        return [
            'progress_basis' => $evaluation['progress_basis_used'] ?? '—',
            'progress_basis_raw' => $record->courseOffering->progress_basis,
            'progress_status' => filled($rawStatus) ? SectionProgressEvaluator::formatProgressStatusLabel($rawStatus) : '—',
            'progress_status_raw' => $rawStatus,
            'evidence_summary' => $evaluation['evidence_summary'] ?? '—',
            'evidence_summary_raw' => $evaluation['evidence_summary'] ?? null,
            'last_activity_at' => ($evaluation['last_activity_date'] ?? null)?->toDateTimeString() ?? '—',
            'requires_override' => in_array($rawStatus, self::OVERRIDABLE_PROGRESS_STATUSES, true),
        ];
    }
}

<?php

namespace App\Filament\Resources\CourseEnrollments\Tables;

use App\Models\AcademicRecord;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CourseEnrollment;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CourseEnrollmentsTable
{
    protected const COMPLETABLE_STATUSES = ['enrolled', 'active'];

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
                        ->orderByDesc('academic_year')
                        ->orderBy('start_date')
                        ->get()
                        ->mapWithKeys(fn (AcademicTerm $term) => [$term->id => "{$term->name} ({$term->academic_year})"])
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
                        TextInput::make('final_grade')
                            ->label('Final grade')
                            ->required()
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
                        if ($record->academicRecord()->exists()) {
                            Notification::make()
                                ->title('Academic record already exists')
                                ->body('This course enrollment already has an academic record. No duplicate record was created.')
                                ->warning()
                                ->send();

                            return;
                        }

                        DB::transaction(function () use ($record, $data): void {
                            $record->forceFill([
                                'status' => 'completed',
                                'final_grade' => $data['final_grade'],
                                'completed_at' => $data['completed_at'],
                            ])->save();

                            AcademicRecord::create([
                                'institution_id' => $record->institution_id,
                                'student_id' => $record->student_id,
                                'course_id' => $record->course_id,
                                'academic_term_id' => $record->academic_term_id,
                                'course_enrollment_id' => $record->id,
                                'course_code' => $record->course->code,
                                'course_title' => $record->course->title,
                                'credits_attempted' => $data['credits_attempted'],
                                'credits_earned' => $data['credits_earned'],
                                'final_grade' => $data['final_grade'],
                                'grade_points' => $data['grade_points'] ?: null,
                                'status' => 'completed',
                                'completed_at' => $data['completed_at'],
                                'notes' => $data['notes'] ?: null,
                            ]);
                        });

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
}

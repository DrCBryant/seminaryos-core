<?php

namespace App\Filament\Resources\Applicants\Tables;

use App\Models\Applicant;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApplicantsTable
{
    protected const CONVERTIBLE_STATUS = 'accepted';

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label('Applicant')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('source')
                    ->toggleable(),
                TextColumn::make('program.title')
                    ->label('Program Applied For')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('submitted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('program')
                    ->relationship('program', 'title')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->options([
                        'inquiry' => 'Inquiry',
                        'applied' => 'Applied',
                        'under_review' => 'Under Review',
                        'accepted' => 'Accepted',
                        'denied' => 'Denied',
                        'enrolled' => 'Enrolled',
                    ])
                    ->multiple(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('convertToStudent')
                    ->label('Convert to Student')
                    ->icon('heroicon-o-academic-cap')
                    ->color('success')
                    ->visible(fn (Applicant $record): bool => $record->status === self::CONVERTIBLE_STATUS)
                    ->requiresConfirmation()
                    ->modalHeading('Convert applicant to student')
                    ->modalDescription('This will create a student record, set the student as active, and update the applicant status to enrolled.')
                    ->action(function (Applicant $record): void {
                        $existingStudent = Student::query()
                            ->where('institution_id', $record->institution_id)
                            ->where('email', $record->email)
                            ->first();

                        if ($existingStudent) {
                            Notification::make()
                                ->title('Student already exists')
                                ->body('A student with this institution and email already exists. No duplicate student was created.')
                                ->warning()
                                ->send();

                            return;
                        }

                        DB::transaction(function () use ($record): void {
                            $student = Student::create([
                                'institution_id' => $record->institution_id,
                                'program_id' => $record->program_id,
                                'applicant_id' => $record->id,
                                'first_name' => $record->first_name,
                                'last_name' => $record->last_name,
                                'email' => $record->email,
                                'phone' => $record->phone,
                                'student_number' => self::generateStudentNumber($record),
                                'status' => 'active',
                                'enrollment_date' => Carbon::today(),
                                'notes' => $record->notes,
                            ]);

                            $record->forceFill([
                                'status' => 'enrolled',
                                'converted_at' => now(),
                            ])->save();

                            Notification::make()
                                ->title('Applicant converted to student')
                                ->body("{$student->full_name} was created as an active student and the applicant was updated to enrolled.")
                                ->success()
                                ->send();
                        });
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

    protected static function generateStudentNumber(Applicant $applicant): string
    {
        do {
            $studentNumber = sprintf(
                'S-%d-%s-%s',
                $applicant->institution_id,
                now()->format('Ymd'),
                Str::upper(Str::random(6)),
            );
        } while (Student::query()
            ->where('institution_id', $applicant->institution_id)
            ->where('student_number', $studentNumber)
            ->exists());

        return $studentNumber;
    }
}

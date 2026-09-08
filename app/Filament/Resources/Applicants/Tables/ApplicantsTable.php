<?php

namespace App\Filament\Resources\Applicants\Tables;

use App\Models\Applicant;
use App\Support\Admissions\ApplicantStudentConversionService;
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
use Illuminate\Validation\ValidationException;

class ApplicantsTable
{
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
                    ->formatStateUsing(fn (string $state): string => Applicant::statusOptions()[$state] ?? $state)
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
                    ->options(Applicant::statusOptions())
                    ->multiple(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('convertToStudent')
                    ->label('Convert to Student')
                    ->icon('heroicon-o-academic-cap')
                    ->color('success')
                    ->visible(fn (Applicant $record): bool => $record->status === 'accepted')
                    ->requiresConfirmation()
                    ->modalHeading('Convert applicant to student')
                    ->modalDescription('This will create a student record, set the student as active, and update the applicant status to enrolled.')
                    ->action(function (Applicant $record): void {
                        try {
                            $result = app(ApplicantStudentConversionService::class)->convert($record);
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title('Conversion requires registrar review')
                                ->body((string) (collect($exception->errors())->flatten()->first() ?? 'The applicant could not be converted.'))
                                ->warning()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title($result['already_converted'] ? 'Applicant already converted' : 'Applicant converted to student')
                            ->body("{$result['student']->full_name} — Student {$result['student']->student_number}")
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

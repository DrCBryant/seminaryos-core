<?php

namespace App\Filament\Resources\ProgramRequirementSubstitutions\Tables;

use App\Models\Program;
use App\Models\ProgramRequirement;
use App\Models\ProgramRequirementSubstitution;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProgramRequirementSubstitutionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student.full_name')
                    ->label('Student')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                TextColumn::make('program.title')
                    ->label('Program')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('programRequirement.name')
                    ->label('Requirement')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('substituteCourse.code')
                    ->label('Substitute course')
                    ->formatStateUsing(fn (?string $state, $record): string => $record->substituteCourse?->code
                        ? "{$record->substituteCourse->code} — {$record->substituteCourse->title}"
                        : '—')
                    ->toggleable(),
                TextColumn::make('academicRecord.course_code')
                    ->label('Academic record')
                    ->formatStateUsing(fn (?string $state, $record): string => $record->academicRecord?->course_code
                        ? "{$record->academicRecord->course_code} — {$record->academicRecord->course_title}"
                        : '—')
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ProgramRequirementSubstitution::STATUS_OPTIONS[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('approved_at')
                    ->label('Approved date')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('program_id')
                    ->label('Program')
                    ->options(fn (): array => Program::query()->orderBy('title')->pluck('title', 'id')->all())
                    ->searchable(),
                SelectFilter::make('program_requirement_id')
                    ->label('Requirement')
                    ->options(fn (): array => ProgramRequirement::query()->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
                SelectFilter::make('status')
                    ->options(ProgramRequirementSubstitution::STATUS_OPTIONS),
                Filter::make('approved_at')
                    ->label('Approved date')
                    ->form([
                        DatePicker::make('approved_from')->label('Approved from'),
                        DatePicker::make('approved_until')->label('Approved until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['approved_from'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('approved_at', '>=', $date))
                            ->when($data['approved_until'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('approved_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

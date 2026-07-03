<?php

namespace App\Filament\Resources\AttendanceSessions\Tables;

use App\Filament\Resources\AttendanceSessions\AttendanceSessionResource;
use App\Models\AttendanceSession;
use App\Models\CourseOffering;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttendanceSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('courseOffering.section_code')
                    ->label('Course offering')
                    ->formatStateUsing(fn (?string $state, AttendanceSession $record): string => trim("{$record->courseOffering?->course?->code} — {$record->courseOffering?->academicTerm?->name} — {$record->courseOffering?->section_code}"))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('session_date')
                    ->label('Session date')
                    ->date()
                    ->sortable(),
                TextColumn::make('topic')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AttendanceSession::STATUS_OPTIONS[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('attendance_records_count')
                    ->label('Records')
                    ->counts('attendanceRecords')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('course_offering_id')
                    ->label('Course offering')
                    ->options(fn (): array => CourseOffering::query()
                        ->with(['course', 'academicTerm'])
                        ->orderByDesc('academic_term_id')
                        ->orderBy('section_code')
                        ->get()
                        ->mapWithKeys(fn (CourseOffering $offering) => [$offering->id => trim("{$offering->course?->code} — {$offering->academicTerm?->name} — {$offering->section_code}")])
                        ->all())
                    ->searchable(),
                Filter::make('session_date')
                    ->form([
                        DatePicker::make('session_date')->label('Session date'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when($data['session_date'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('session_date', $date))),
                SelectFilter::make('status')
                    ->options(AttendanceSession::STATUS_OPTIONS),
            ])
            ->recordActions([
                AttendanceSessionResource::generateRosterRecordsAction(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

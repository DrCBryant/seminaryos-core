<?php

namespace App\Filament\Resources\AttendanceSessions\RelationManagers;

use App\Models\AttendanceRecord;
use App\Models\CourseEnrollment;
use App\Models\Student;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttendanceRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'attendanceRecords';

    protected static ?string $title = 'Attendance Records';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('student.full_name')
            ->columns([
                TextColumn::make('student.full_name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AttendanceRecord::STATUS_OPTIONS[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('minutes_present')
                    ->label('Minutes present')
                    ->sortable(),
                TextColumn::make('marked_at')
                    ->label('Marked at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('notes')
                    ->limit(50)
                    ->wrap()
                    ->toggleable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $ownerRecord = $this->getOwnerRecord();

                        $data['institution_id'] = $ownerRecord->institution_id;
                        $data['attendance_session_id'] = $ownerRecord->id;
                        $data['course_offering_id'] = $ownerRecord->course_offering_id;

                        return $data;
                    })
                    ->form($this->formComponents()),
            ])
            ->recordActions([
                EditAction::make()->form($this->formComponents()),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected function formComponents(): array
    {
        return [
            Hidden::make('institution_id')
                ->default(fn (): ?int => $this->getOwnerRecord()->institution_id),
            Hidden::make('attendance_session_id')
                ->default(fn (): ?int => $this->getOwnerRecord()->id),
            Hidden::make('course_offering_id')
                ->default(fn (): ?int => $this->getOwnerRecord()->course_offering_id),
            Select::make('student_id')
                ->label('Student')
                ->relationship('student', 'first_name', fn ($query) => $query->orderBy('first_name')->orderBy('last_name'))
                ->getOptionLabelFromRecordUsing(fn (Student $record): string => $record->full_name)
                ->searchable()
                ->preload()
                ->required(),
            Select::make('course_enrollment_id')
                ->label('Course enrollment')
                ->relationship('courseEnrollment', 'uuid', fn ($query) => $query->where('course_offering_id', $this->getOwnerRecord()->course_offering_id)->with('student')->orderByDesc('enrolled_at'))
                ->getOptionLabelFromRecordUsing(fn (CourseEnrollment $record): string => trim("{$record->student?->full_name} — {$record->status}"))
                ->searchable()
                ->preload(),
            Select::make('status')
                ->options(AttendanceRecord::STATUS_OPTIONS)
                ->default('not_marked')
                ->required(),
            TextInput::make('minutes_present')
                ->numeric()
                ->integer()
                ->minValue(0),
            DateTimePicker::make('marked_at')
                ->label('Marked at'),
            Textarea::make('notes')
                ->rows(4)
                ->columnSpanFull(),
        ];
    }
}

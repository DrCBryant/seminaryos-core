<?php

namespace App\Filament\Resources\CourseOfferings\RelationManagers;

use App\Filament\Resources\AttendanceSessions\AttendanceSessionResource;
use App\Models\AttendanceSession;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttendanceSessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'attendanceSessions';

    protected static ?string $title = 'Attendance Sessions';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('topic')
            ->columns([
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
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $ownerRecord = $this->getOwnerRecord();

                        $data['institution_id'] = $ownerRecord->institution_id;
                        $data['course_offering_id'] = $ownerRecord->id;
                        $data['course_id'] = $ownerRecord->course_id;
                        $data['academic_term_id'] = $ownerRecord->academic_term_id;

                        return $data;
                    })
                    ->form([
                        Hidden::make('institution_id')->default(fn (): ?int => $this->getOwnerRecord()->institution_id),
                        Hidden::make('course_offering_id')->default(fn (): ?int => $this->getOwnerRecord()->id),
                        Hidden::make('course_id')->default(fn (): ?int => $this->getOwnerRecord()->course_id),
                        Hidden::make('academic_term_id')->default(fn (): ?int => $this->getOwnerRecord()->academic_term_id),
                        DatePicker::make('session_date')->required(),
                        TimePicker::make('start_time')->seconds(false),
                        TimePicker::make('end_time')->seconds(false),
                        TextInput::make('topic')->maxLength(255),
                        Select::make('status')
                            ->options(AttendanceSession::STATUS_OPTIONS)
                            ->default('planned')
                            ->required(),
                        Textarea::make('notes')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ])
            ->recordActions([
                AttendanceSessionResource::generateRosterRecordsAction(),
                EditAction::make()
                    ->url(fn (AttendanceSession $record): string => AttendanceSessionResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}

<?php

namespace App\Filament\Resources\CourseOfferings\RelationManagers;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Institution;
use App\Models\Student;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
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

class CourseEnrollmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'courseEnrollments';

    protected static ?string $title = 'Enrollments';

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
                    ->sortable(),
                TextColumn::make('final_grade')
                    ->label('Final grade')
                    ->sortable(),
                TextColumn::make('enrolled_at')
                    ->label('Enrolled at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('completed_at')
                    ->label('Completed at')
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
                        $data['course_offering_id'] = $ownerRecord->id;
                        $data['course_id'] = $ownerRecord->course_id;
                        $data['academic_term_id'] = $ownerRecord->academic_term_id;

                        return $data;
                    })
                    ->form([
                        Hidden::make('institution_id')
                            ->default(fn (): ?int => $this->getOwnerRecord()->institution_id),
                        Hidden::make('course_offering_id')
                            ->default(fn (): ?int => $this->getOwnerRecord()->id),
                        Hidden::make('course_id')
                            ->default(fn (): ?int => $this->getOwnerRecord()->course_id),
                        Hidden::make('academic_term_id')
                            ->default(fn (): ?int => $this->getOwnerRecord()->academic_term_id),
                        Select::make('student_id')
                            ->label('Student')
                            ->relationship('student', 'first_name', fn ($query) => $query->orderBy('first_name')->orderBy('last_name'))
                            ->getOptionLabelFromRecordUsing(fn (Student $record): string => $record->full_name)
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('status')
                            ->options([
                                'enrolled' => 'Enrolled',
                                'dropped' => 'Dropped',
                                'withdrawn' => 'Withdrawn',
                                'completed' => 'Completed',
                                'failed' => 'Failed',
                                'incomplete' => 'Incomplete',
                            ])
                            ->default('enrolled')
                            ->required(),
                        TextInput::make('final_grade')
                            ->label('Final grade')
                            ->maxLength(20),
                        DateTimePicker::make('enrolled_at')
                            ->label('Enrolled at'),
                        DateTimePicker::make('completed_at')
                            ->label('Completed at')
                            ->afterOrEqual('enrolled_at'),
                        Textarea::make('notes')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->form([
                        Select::make('institution_id')
                            ->label('Institution')
                            ->relationship('institution', 'name', fn ($query) => $query->orderBy('name'))
                            ->getOptionLabelFromRecordUsing(fn (Institution $record): string => $record->name)
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('student_id')
                            ->label('Student')
                            ->relationship('student', 'first_name', fn ($query) => $query->orderBy('first_name')->orderBy('last_name'))
                            ->getOptionLabelFromRecordUsing(fn (Student $record): string => $record->full_name)
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('course_offering_id')
                            ->label('Course offering')
                            ->relationship('courseOffering', 'section_code', fn ($query) => $query->orderByDesc('academic_term_id')->orderBy('section_code'))
                            ->getOptionLabelFromRecordUsing(fn (CourseOffering $record): string => trim("{$record->course?->code} — {$record->academicTerm?->name} — {$record->section_code}"))
                            ->searchable()
                            ->preload(),
                        Select::make('course_id')
                            ->label('Course')
                            ->relationship('course', 'title', fn ($query) => $query->orderBy('title'))
                            ->getOptionLabelFromRecordUsing(fn (Course $record): string => "{$record->code} — {$record->title}")
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('academic_term_id')
                            ->label('Academic term')
                            ->relationship('academicTerm', 'name', fn ($query) => $query->orderByDesc('academic_year')->orderBy('start_date'))
                            ->getOptionLabelFromRecordUsing(fn (AcademicTerm $record): string => "{$record->name} ({$record->academic_year})")
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('status')
                            ->options([
                                'enrolled' => 'Enrolled',
                                'dropped' => 'Dropped',
                                'withdrawn' => 'Withdrawn',
                                'completed' => 'Completed',
                                'failed' => 'Failed',
                                'incomplete' => 'Incomplete',
                            ])
                            ->required(),
                        TextInput::make('final_grade')
                            ->label('Final grade')
                            ->maxLength(20),
                        DateTimePicker::make('enrolled_at')
                            ->label('Enrolled at'),
                        DateTimePicker::make('completed_at')
                            ->label('Completed at')
                            ->afterOrEqual('enrolled_at'),
                        Textarea::make('notes')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

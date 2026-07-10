<?php

namespace App\Filament\Resources\CourseOfferings\Schemas;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Institution;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CourseOfferingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Course Offering Details')
                    ->schema([
                        Placeholder::make('capacity_summary')
                            ->label('Capacity summary')
                            ->content(function (?CourseOffering $record): string {
                                if (! $record) {
                                    return 'Capacity summary will appear after this course offering is created.';
                                }

                                $capacity = $record->capacity === null ? 'Unlimited' : (string) $record->capacity;
                                $enrolled = $record->enrolledCount();
                                $availableSeats = $record->availableSeats();
                                $status = $record->capacityStatus();

                                return "Capacity: {$capacity} | Enrolled: {$enrolled} | Available seats: {$availableSeats} | Status: {$status}";
                            })
                            ->hidden(fn (?CourseOffering $record): bool => $record === null),
                        Placeholder::make('academic_term_boundary_warning')
                            ->label('Registrar review notice')
                            ->content(fn (Get $get): string => self::academicTermBoundaryWarningMessage($get) ?? '')
                            ->hidden(fn (Get $get): bool => self::academicTermBoundaryWarningMessage($get) === null)
                            ->columnSpanFull(),
                        Grid::make(2)
                            ->schema([
                                Select::make('institution_id')
                                    ->label('Institution')
                                    ->relationship('institution', 'name', fn ($query) => $query->orderBy('name'))
                                    ->getOptionLabelFromRecordUsing(fn (Institution $record): string => $record->name)
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Select::make('course_id')
                                    ->label('Course')
                                    ->relationship('course', 'title', fn ($query) => $query->orderBy('code')->orderBy('title'))
                                    ->getOptionLabelFromRecordUsing(fn (Course $record): string => "{$record->code} — {$record->title}")
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Select::make('academic_term_id')
                                    ->label('Academic term')
                                    ->relationship('academicTerm', 'name', fn ($query) => $query->orderedForSelection())
                                    ->getOptionLabelFromRecordUsing(fn (AcademicTerm $record): string => $record->display_label)
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->required(),
                                TextInput::make('section_code')
                                    ->label('Section code')
                                    ->default(CourseOffering::DEFAULT_SECTION_CODE)
                                    ->helperText('Use MAIN for the primary/default section, or A, B, INTENSIVE, ONLINE, etc.')
                                    ->required()
                                    ->dehydrateStateUsing(fn (?string $state): string => CourseOffering::DEFAULT_SECTION_CODE !== ''
                                        ? strtoupper(trim($state ?: CourseOffering::DEFAULT_SECTION_CODE))
                                        : strtoupper(trim((string) $state)))
                                    ->maxLength(255),
                                TextInput::make('title')
                                    ->maxLength(255),
                                Select::make('delivery_mode')
                                    ->options(CourseOffering::DELIVERY_MODE_OPTIONS)
                                    ->required(),
                                TextInput::make('location')
                                    ->maxLength(255),
                                TextInput::make('meeting_pattern')
                                    ->maxLength(255),
                                DatePicker::make('start_date')
                                    ->label('Start date')
                                    ->live(),
                                DatePicker::make('end_date')
                                    ->label('End date')
                                    ->live(),
                                TextInput::make('capacity')
                                    ->numeric(),
                                Select::make('status')
                                    ->options(CourseOffering::STATUS_OPTIONS)
                                    ->default('planned')
                                    ->required(),
                                Select::make('progress_basis')
                                    ->label('Progress Basis')
                                    ->options(CourseOffering::PROGRESS_BASIS_OPTIONS)
                                    ->default(CourseOffering::PROGRESS_BASIS_ATTENDANCE)
                                    ->helperText('Determines how section completion will be evaluated. Attendance only governs completion for attendance and hybrid sections.')
                                    ->required(),
                            ]),
                        Textarea::make('progress_notes')
                            ->label('Progress Notes')
                            ->rows(4)
                            ->helperText('Use this for section-specific completion guidance, including manual approval expectations or master assessment criteria.')
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected static function academicTermBoundaryWarningMessage(Get $get): ?string
    {
        $academicTermId = $get('academic_term_id');

        if (blank($academicTermId)) {
            return null;
        }

        $academicTerm = AcademicTerm::query()->find($academicTermId);

        if (! $academicTerm) {
            return null;
        }

        return CourseOffering::academicTermBoundaryWarningMessage(
            $academicTerm,
            $get('start_date'),
            $get('end_date'),
        );
    }
}

<?php

namespace App\Filament\Resources\TeachingAssignments\Schemas;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\Institution;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TeachingAssignmentForm
{
    public const ROLE_OPTIONS = [
        'primary_instructor' => 'Primary Instructor',
        'co_instructor' => 'Co-Instructor',
        'teaching_assistant' => 'Teaching Assistant',
        'mentor' => 'Mentor',
        'supervisor' => 'Supervisor',
        'guest_lecturer' => 'Guest Lecturer',
    ];

    public const STATUS_OPTIONS = [
        'assigned' => 'Assigned',
        'active' => 'Active',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'archived' => 'Archived',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Teaching Assignment Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('institution_id')
                                    ->label('Institution')
                                    ->relationship('institution', 'name', fn ($query) => $query->orderBy('name'))
                                    ->getOptionLabelFromRecordUsing(fn (Institution $record): string => $record->name)
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Select::make('faculty_id')
                                    ->label('Faculty')
                                    ->relationship('faculty', 'first_name', fn ($query) => $query->orderBy('first_name')->orderBy('last_name'))
                                    ->getOptionLabelFromRecordUsing(fn (Faculty $record): string => $record->full_name)
                                    ->searchable()
                                    ->preload()
                                    ->required(),
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
                                Select::make('role')
                                    ->options(self::ROLE_OPTIONS)
                                    ->required(),
                                Select::make('status')
                                    ->options(self::STATUS_OPTIONS)
                                    ->default('assigned')
                                    ->required(),
                                DatePicker::make('assigned_at')
                                    ->label('Assigned date'),
                                DatePicker::make('ended_at')
                                    ->label('Ended date')
                                    ->afterOrEqual('assigned_at'),
                            ]),
                        Textarea::make('notes')
                            ->rows(4),
                    ]),
            ]);
    }
}

<?php

namespace App\Filament\Resources\CourseEnrollments\Schemas;

use App\Models\AcademicRecord;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseOffering;
use App\Models\Institution;
use App\Models\Student;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Set;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CourseEnrollmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Course Enrollment Details')
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
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function (?string $state, Set $set): void {
                                        if (blank($state)) {
                                            return;
                                        }

                                        $offering = CourseOffering::query()->find($state);

                                        if (! $offering) {
                                            return;
                                        }

                                        $set('institution_id', $offering->institution_id);
                                        $set('course_id', $offering->course_id);
                                        $set('academic_term_id', $offering->academic_term_id);
                                    }),
                                Select::make('course_id')
                                    ->label('Course')
                                    ->relationship('course', 'title', fn ($query) => $query->orderBy('title'))
                                    ->getOptionLabelFromRecordUsing(fn (Course $record): string => "{$record->code} — {$record->title}")
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->helperText('Used for legacy/manual enrollments when no course offering is selected.'),
                                Select::make('academic_term_id')
                                    ->label('Academic term')
                                    ->relationship('academicTerm', 'name', fn ($query) => $query->orderByDesc('academic_year')->orderBy('start_date'))
                                    ->getOptionLabelFromRecordUsing(fn (AcademicTerm $record): string => "{$record->name} ({$record->academic_year})")
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->helperText('Used for legacy/manual enrollments when no course offering is selected.'),
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
                                    ->label('Enrolled date'),
                                DateTimePicker::make('completed_at')
                                    ->label('Completed date')
                                    ->afterOrEqual('enrolled_at'),
                            ]),
                        Textarea::make('notes')
                            ->rows(4),
                    ]),
                Section::make('Completion Audit')
                    ->visible(fn (?CourseEnrollment $record): bool => $record !== null)
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('status')
                                    ->label('Enrollment status')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->formatStateUsing(fn (?string $state): string => self::formatValue(filled($state) ? str($state)->replace('_', ' ')->title()->toString() : null)),
                                TextInput::make('completed_at')
                                    ->label('Completed at')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->formatStateUsing(fn ($state): string => self::formatDateTimeValue($state)),
                                TextInput::make('completion_progress_basis')
                                    ->label('Completion progress basis')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->formatStateUsing(fn (?string $state): string => self::formatValue($state)),
                                TextInput::make('completion_progress_status')
                                    ->label('Completion progress status')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->formatStateUsing(fn (?string $state): string => self::formatValue(filled($state) ? str($state)->replace('_', ' ')->title()->toString() : null)),
                                TextInput::make('completion_reviewed_at')
                                    ->label('Reviewed at')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->formatStateUsing(fn ($state): string => self::formatDateTimeValue($state)),
                                TextInput::make('completion_reviewed_by_user')
                                    ->label('Reviewed by')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->formatStateUsing(fn (?CourseEnrollment $record): string => self::reviewerSummary($record)),
                            ]),
                        Textarea::make('completion_evidence_summary')
                            ->label('Completion evidence summary')
                            ->disabled()
                            ->dehydrated(false)
                            ->rows(3)
                            ->formatStateUsing(fn (?string $state): string => self::formatValue($state)),
                        Textarea::make('completion_override_reason')
                            ->label('Completion override reason')
                            ->disabled()
                            ->dehydrated(false)
                            ->rows(3)
                            ->formatStateUsing(fn (?string $state): string => self::formatValue($state)),
                        Placeholder::make('academic_record_link_status')
                            ->label('Academic record')
                            ->content(fn (?CourseEnrollment $record): string => self::academicRecordLinkStatus($record)),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('academic_record_course')
                                    ->label('AcademicRecord course')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->formatStateUsing(fn (?CourseEnrollment $record): string => self::academicRecordCourseSummary($record)),
                                TextInput::make('academic_record_final_grade')
                                    ->label('AcademicRecord final grade')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->formatStateUsing(fn (?CourseEnrollment $record): string => self::academicRecordValue($record, 'final_grade')),
                                TextInput::make('academic_record_grade_label')
                                    ->label('AcademicRecord grade label')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->formatStateUsing(fn (?CourseEnrollment $record): string => self::academicRecordValue($record, 'grade_label')),
                                TextInput::make('academic_record_status')
                                    ->label('AcademicRecord status')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->formatStateUsing(fn (?CourseEnrollment $record): string => self::academicRecordValue($record, 'status', true)),
                                TextInput::make('academic_record_credits_attempted')
                                    ->label('AcademicRecord credits attempted')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->formatStateUsing(fn (?CourseEnrollment $record): string => self::academicRecordValue($record, 'credits_attempted')),
                                TextInput::make('academic_record_credits_earned')
                                    ->label('AcademicRecord credits earned')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->formatStateUsing(fn (?CourseEnrollment $record): string => self::academicRecordValue($record, 'credits_earned')),
                                TextInput::make('academic_record_completed_at')
                                    ->label('AcademicRecord completed at')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->formatStateUsing(fn (?CourseEnrollment $record): string => self::academicRecordCompletedAt($record)),
                            ]),
                    ]),
            ]);
    }

    protected static function reviewerSummary(?CourseEnrollment $record): string
    {
        if (! $record) {
            return '—';
        }

        $record->loadMissing('completionReviewedByUser');

        $reviewer = $record->completionReviewedByUser;

        if (! $reviewer) {
            return '—';
        }

        return trim(collect([$reviewer->name, $reviewer->email])->filter()->implode(' · ')) ?: '—';
    }

    protected static function academicRecordLinkStatus(?CourseEnrollment $record): string
    {
        return self::resolveAcademicRecord($record)
            ? 'AcademicRecord linked to this enrollment.'
            : 'No AcademicRecord is linked to this enrollment yet.';
    }

    protected static function academicRecordCourseSummary(?CourseEnrollment $record): string
    {
        $academicRecord = self::resolveAcademicRecord($record);

        if (! $academicRecord) {
            return '—';
        }

        return trim(collect([$academicRecord->course_code, $academicRecord->course_title])->filter()->implode(' — ')) ?: '—';
    }

    protected static function academicRecordValue(?CourseEnrollment $record, string $attribute, bool $titleCase = false): string
    {
        $academicRecord = self::resolveAcademicRecord($record);

        if (! $academicRecord) {
            return '—';
        }

        $value = $academicRecord->{$attribute};

        if ($titleCase && filled($value)) {
            return str((string) $value)->replace('_', ' ')->title()->toString();
        }

        return self::formatValue($value);
    }

    protected static function academicRecordCompletedAt(?CourseEnrollment $record): string
    {
        $academicRecord = self::resolveAcademicRecord($record);

        return self::formatValue($academicRecord?->completed_at?->format('M j, Y'));
    }

    protected static function resolveAcademicRecord(?CourseEnrollment $record): ?AcademicRecord
    {
        if (! $record) {
            return null;
        }

        $record->loadMissing('academicRecord');

        return $record->academicRecord;
    }

    protected static function formatDateTimeValue(mixed $value): string
    {
        return $value?->format('M j, Y g:i A') ?? '—';
    }

    protected static function formatValue(mixed $value): string
    {
        return filled($value) ? (string) $value : '—';
    }
}

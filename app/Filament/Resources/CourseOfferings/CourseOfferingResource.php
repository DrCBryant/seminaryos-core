<?php

namespace App\Filament\Resources\CourseOfferings;

use App\Filament\Resources\CourseOfferings\Pages\CreateCourseOffering;
use App\Filament\Resources\CourseOfferings\Pages\EditCourseOffering;
use App\Filament\Resources\CourseOfferings\Pages\ListCourseOfferings;
use App\Filament\Resources\CourseOfferings\Pages\ViewCourseOfferingCompletionReview;
use App\Filament\Resources\CourseOfferings\Pages\ViewCourseOfferingRoster;
use App\Filament\Resources\CourseOfferings\RelationManagers\AttendanceSessionsRelationManager;
use App\Filament\Resources\CourseOfferings\RelationManagers\CourseEnrollmentsRelationManager;
use App\Filament\Resources\CourseOfferings\RelationManagers\MasterAssessmentsRelationManager;
use App\Filament\Resources\CourseOfferings\RelationManagers\SectionAssignmentsRelationManager;
use App\Filament\Resources\CourseOfferings\RelationManagers\StudentSectionManualCompletionsRelationManager;
use App\Filament\Resources\CourseOfferings\RelationManagers\StudentSectionSubmissionsRelationManager;
use App\Filament\Resources\CourseOfferings\RelationManagers\TeachingAssignmentsRelationManager;
use App\Filament\Resources\CourseOfferings\Schemas\CourseOfferingForm;
use App\Filament\Resources\CourseOfferings\Support\SectionProgressPreview;
use App\Filament\Resources\CourseOfferings\Tables\CourseOfferingsTable;
use App\Models\CourseOffering;
use App\Models\SectionAssignment;
use App\Models\StudentSectionManualCompletion;
use App\Models\StudentSectionSubmission;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class CourseOfferingResource extends Resource
{
    protected static ?string $model = CourseOffering::class;

    protected static ?string $slug = 'course-offerings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Academic Operations';

    protected static ?string $navigationLabel = 'Course Offerings';

    protected static ?string $modelLabel = 'Course Offering';

    protected static ?string $pluralModelLabel = 'Course Offerings';

    protected static ?string $recordTitleAttribute = 'section_code';

    public static function form(Schema $schema): Schema
    {
        return CourseOfferingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CourseOfferingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AttendanceSessionsRelationManager::class,
            CourseEnrollmentsRelationManager::class,
            TeachingAssignmentsRelationManager::class,
            SectionAssignmentsRelationManager::class,
            StudentSectionManualCompletionsRelationManager::class,
            StudentSectionSubmissionsRelationManager::class,
            MasterAssessmentsRelationManager::class,
        ];
    }

    public static function rosterAction(): Action
    {
        return Action::make('viewRoster')
            ->label('View Roster')
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->url(fn (CourseOffering $record): string => static::getUrl('roster', ['record' => $record]))
            ->openUrlInNewTab();
    }

    public static function completionReviewAction(): Action
    {
        return Action::make('reviewCompletion')
            ->label('Review Completion')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('gray')
            ->url(fn (CourseOffering $record): string => static::getUrl('completion-review', ['record' => $record]));
    }

    public static function sectionProgressAction(): Action
    {
        return SectionProgressPreview::make();
    }

    public static function generateSubmissionChecklistAction(): Action
    {
        return Action::make('generateSubmissionChecklist')
            ->label('Generate Submission Checklist')
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->color('gray')
            ->requiresConfirmation()
            ->action(function (CourseOffering $record): void {
                DB::transaction(function () use ($record): void {
                    $requiredAssignments = $record->sectionAssignments()
                        ->where('status', SectionAssignment::STATUS_ACTIVE)
                        ->where('is_required', true)
                        ->get();

                    $enrollments = $record->courseEnrollments()
                        ->whereNotIn('status', ['dropped', 'withdrawn'])
                        ->whereNotNull('student_id')
                        ->get();

                    foreach ($enrollments as $enrollment) {
                        foreach ($requiredAssignments as $assignment) {
                            StudentSectionSubmission::query()->firstOrCreate(
                                [
                                    'section_assignment_id' => $assignment->id,
                                    'student_id' => $enrollment->student_id,
                                ],
                                [
                                    'institution_id' => $record->institution_id,
                                    'course_offering_id' => $record->id,
                                    'course_enrollment_id' => $enrollment->id,
                                    'status' => StudentSectionSubmission::STATUS_NOT_STARTED,
                                ],
                            );
                        }
                    }
                });
            });
    }

    public static function generateManualCompletionChecklistAction(): Action
    {
        return Action::make('generateManualCompletionChecklist')
            ->label('Generate Manual Completion Checklist')
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->color('gray')
            ->requiresConfirmation()
            ->action(function (CourseOffering $record): void {
                DB::transaction(function () use ($record): void {
                    $enrollments = $record->courseEnrollments()
                        ->whereNotIn('status', ['dropped', 'withdrawn'])
                        ->whereNotNull('student_id')
                        ->get();

                    foreach ($enrollments as $enrollment) {
                        StudentSectionManualCompletion::query()->firstOrCreate(
                            [
                                'course_offering_id' => $record->id,
                                'student_id' => $enrollment->student_id,
                            ],
                            [
                                'institution_id' => $record->institution_id,
                                'course_enrollment_id' => $enrollment->id,
                                'status' => StudentSectionManualCompletion::STATUS_PENDING,
                            ],
                        );
                    }
                });
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCourseOfferings::route('/'),
            'create' => CreateCourseOffering::route('/create'),
            'edit' => EditCourseOffering::route('/{record}/edit'),
            'roster' => ViewCourseOfferingRoster::route('/{record}/roster'),
            'completion-review' => ViewCourseOfferingCompletionReview::route('/{record}/completion-review'),
        ];
    }
}

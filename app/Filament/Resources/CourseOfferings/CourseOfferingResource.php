<?php

namespace App\Filament\Resources\CourseOfferings;

use App\Filament\Resources\CourseOfferings\Pages\CreateCourseOffering;
use App\Filament\Resources\CourseOfferings\Pages\EditCourseOffering;
use App\Filament\Resources\CourseOfferings\Pages\ListCourseOfferings;
use App\Filament\Resources\CourseOfferings\Pages\ViewCourseOfferingRoster;
use App\Filament\Resources\CourseOfferings\RelationManagers\AttendanceSessionsRelationManager;
use App\Filament\Resources\CourseOfferings\RelationManagers\CourseEnrollmentsRelationManager;
use App\Filament\Resources\CourseOfferings\RelationManagers\MasterAssessmentsRelationManager;
use App\Filament\Resources\CourseOfferings\RelationManagers\SectionAssignmentsRelationManager;
use App\Filament\Resources\CourseOfferings\RelationManagers\TeachingAssignmentsRelationManager;
use App\Filament\Resources\CourseOfferings\Schemas\CourseOfferingForm;
use App\Filament\Resources\CourseOfferings\Support\SectionProgressPreview;
use App\Filament\Resources\CourseOfferings\Tables\CourseOfferingsTable;
use App\Models\CourseOffering;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

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

    public static function sectionProgressAction(): Action
    {
        return SectionProgressPreview::make();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCourseOfferings::route('/'),
            'create' => CreateCourseOffering::route('/create'),
            'edit' => EditCourseOffering::route('/{record}/edit'),
            'roster' => ViewCourseOfferingRoster::route('/{record}/roster'),
        ];
    }
}

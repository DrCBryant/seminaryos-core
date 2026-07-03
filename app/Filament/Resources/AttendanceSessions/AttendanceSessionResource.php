<?php

namespace App\Filament\Resources\AttendanceSessions;

use App\Filament\Resources\AttendanceSessions\Pages\CreateAttendanceSession;
use App\Filament\Resources\AttendanceSessions\Pages\EditAttendanceSession;
use App\Filament\Resources\AttendanceSessions\Pages\ListAttendanceSessions;
use App\Filament\Resources\AttendanceSessions\RelationManagers\AttendanceRecordsRelationManager;
use App\Filament\Resources\AttendanceSessions\Schemas\AttendanceSessionForm;
use App\Filament\Resources\AttendanceSessions\Tables\AttendanceSessionsTable;
use App\Models\AttendanceSession;
use App\Models\CourseEnrollment;
use App\Models\CourseOffering;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Facades\DB;

class AttendanceSessionResource extends Resource
{
    protected static ?string $model = AttendanceSession::class;

    protected static ?string $slug = 'attendance-sessions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Academic Operations';

    protected static ?string $navigationLabel = 'Attendance Sessions';

    protected static ?string $modelLabel = 'Attendance Session';

    protected static ?string $pluralModelLabel = 'Attendance Sessions';

    protected static ?string $recordTitleAttribute = 'topic';

    public static function form(Schema $schema): Schema
    {
        return AttendanceSessionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttendanceSessionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AttendanceRecordsRelationManager::class,
        ];
    }

    public static function generateRosterRecordsAction(): Action
    {
        return Action::make('generateRosterRecords')
            ->label('Generate Roster Records')
            ->icon(Heroicon::OutlinedUserGroup)
            ->color('primary')
            ->requiresConfirmation()
            ->visible(fn (): bool => static::userCanManageAttendance())
            ->authorize(fn (): bool => static::userCanManageAttendance())
            ->action(function (AttendanceSession $record): void {
                $createdCount = DB::transaction(function () use ($record): int {
                    $record->loadMissing('courseOffering');

                    $enrollments = CourseEnrollment::query()
                        ->where('course_offering_id', $record->course_offering_id)
                        ->whereIn('status', CourseOffering::countedEnrollmentStatuses())
                        ->with('student')
                        ->get();

                    $created = 0;

                    foreach ($enrollments as $enrollment) {
                        $attendanceRecord = $record->attendanceRecords()->firstOrCreate(
                            [
                                'student_id' => $enrollment->student_id,
                            ],
                            [
                                'institution_id' => $record->institution_id,
                                'course_offering_id' => $record->course_offering_id,
                                'course_enrollment_id' => $enrollment->id,
                                'status' => 'not_marked',
                            ],
                        );

                        if ($attendanceRecord->wasRecentlyCreated) {
                            $created++;
                        }
                    }

                    return $created;
                });

                Notification::make()
                    ->title('Roster records generated')
                    ->body("Created {$createdCount} attendance record(s). Existing records were left unchanged.")
                    ->success()
                    ->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttendanceSessions::route('/'),
            'create' => CreateAttendanceSession::route('/create'),
            'edit' => EditAttendanceSession::route('/{record}/edit'),
        ];
    }

    protected static function userCanManageAttendance(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user || ! $user->currentInstitution) {
            return false;
        }

        $membership = $user->institutions()
            ->where('institutions.id', $user->currentInstitution->id)
            ->first();

        if (! $membership) {
            return false;
        }

        $role = strtolower((string) $membership->pivot->role);
        $status = strtolower((string) $membership->pivot->status);

        return in_array($role, ['owner', 'admin'], true) && ($status === '' || $status === 'active');
    }
}

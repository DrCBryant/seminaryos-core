<?php

namespace App\Filament\Resources\AttendanceSessions;

use App\Filament\Resources\AttendanceSessions\Pages\CreateAttendanceSession;
use App\Filament\Resources\AttendanceSessions\Pages\EditAttendanceSession;
use App\Filament\Resources\AttendanceSessions\Pages\ListAttendanceSessions;
use App\Filament\Resources\AttendanceSessions\RelationManagers\AttendanceRecordsRelationManager;
use App\Filament\Resources\AttendanceSessions\Schemas\AttendanceSessionForm;
use App\Filament\Resources\AttendanceSessions\Tables\AttendanceSessionsTable;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\CourseEnrollment;
use App\Models\CourseOffering;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Facades\DB;
use Illuminate\Support\Collection;

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
                $createdCount = static::ensureRosterRecords($record);

                Notification::make()
                    ->title('Roster records generated')
                    ->body("Created {$createdCount} attendance record(s). Existing records were left unchanged.")
                    ->success()
                    ->send();
            });
    }

    public static function quickMarkAttendanceAction(): Action
    {
        return Action::make('quickMarkAttendance')
            ->label('Quick Mark Attendance')
            ->icon('heroicon-o-pencil-square')
            ->color('success')
            ->visible(fn (): bool => static::userCanManageAttendance())
            ->authorize(fn (): bool => static::userCanManageAttendance())
            ->modalHeading('Quick Mark Attendance')
            ->modalDescription('Mark the full roster for this attendance session from one screen.')
            ->modalSubmitActionLabel('Save Attendance')
            ->modalWidth(Width::SevenExtraLarge)
            ->slideOver()
            ->fillForm(fn (AttendanceSession $record): array => [
                'bulk_status' => null,
                'records' => static::buildQuickMarkAttendanceRows($record),
            ])
            ->form([
                Grid::make(2)
                    ->schema([
                        Select::make('bulk_status')
                            ->label('Quick fill')
                            ->placeholder('Choose a bulk option')
                            ->options([
                                'present' => 'Mark all present',
                                'not_marked' => 'Mark all not marked',
                            ])
                            ->live()
                            ->dehydrated(false)
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                if (! in_array($state, ['present', 'not_marked'], true)) {
                                    return;
                                }

                                $rows = $get('records') ?? [];

                                foreach (array_keys($rows) as $index) {
                                    $set("records.{$index}.status", $state);

                                    if ($state === 'not_marked') {
                                        $set("records.{$index}.minutes_present", null);
                                    }
                                }

                                $set('bulk_status', null);
                            }),
                        Placeholder::make('quick_mark_help')
                            ->label('Bulk shortcuts')
                            ->content('Use Quick fill for safe bulk updates, then adjust individual rows before saving.'),
                    ]),
                Repeater::make('records')
                    ->label('Attendance records')
                    ->schema([
                        Hidden::make('attendance_record_id'),
                        Hidden::make('student_id'),
                        Hidden::make('course_enrollment_id'),
                        Grid::make(6)
                            ->schema([
                                TextInput::make('student_name')
                                    ->label('Student name')
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('student_number')
                                    ->label('Student number')
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('enrollment_status')
                                    ->label('Enrollment status')
                                    ->disabled()
                                    ->dehydrated(false),
                                Select::make('status')
                                    ->label('Attendance status')
                                    ->options(AttendanceRecord::STATUS_OPTIONS)
                                    ->required(),
                                TextInput::make('minutes_present')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0),
                                Textarea::make('notes')
                                    ->rows(2),
                            ]),
                    ])
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->columnSpanFull(),
            ])
            ->action(function (AttendanceSession $record, array $data): void {
                $result = static::saveQuickMarkedAttendance($record, $data['records'] ?? []);

                Notification::make()
                    ->title('Attendance saved')
                    ->body("Updated {$result['updated']} attendance record(s). Generated {$result['created']} roster record(s) for this session.")
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

    protected static function ensureRosterRecords(AttendanceSession $record): int
    {
        return DB::transaction(function () use ($record): int {
            $enrollments = CourseEnrollment::query()
                ->where('course_offering_id', $record->course_offering_id)
                ->whereIn('status', CourseOffering::countedEnrollmentStatuses())
                ->with(['student' => fn ($query) => $query->withTrashed()])
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
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function buildQuickMarkAttendanceRows(AttendanceSession $record): array
    {
        static::ensureRosterRecords($record);

        /** @var Collection<int, AttendanceRecord> $attendanceRecords */
        $attendanceRecords = $record->attendanceRecords()
            ->with([
                'student' => fn ($query) => $query->withTrashed(),
                'courseEnrollment',
            ])
            ->get()
            ->sortBy([
                fn (AttendanceRecord $attendanceRecord): string => strtolower((string) ($attendanceRecord->student?->last_name ?? '')),
                fn (AttendanceRecord $attendanceRecord): string => strtolower((string) ($attendanceRecord->student?->first_name ?? '')),
                fn (AttendanceRecord $attendanceRecord): string => strtolower((string) ($attendanceRecord->student?->student_number ?? 'zzzzzzzz')),
            ])
            ->values();

        return $attendanceRecords
            ->map(function (AttendanceRecord $attendanceRecord): array {
                $student = $attendanceRecord->student;

                return [
                    'attendance_record_id' => $attendanceRecord->id,
                    'student_id' => $attendanceRecord->student_id,
                    'course_enrollment_id' => $attendanceRecord->course_enrollment_id,
                    'student_name' => $student?->full_name ?? 'Unknown student',
                    'student_number' => $student?->student_number ?: '—',
                    'enrollment_status' => $attendanceRecord->courseEnrollment?->status ?: '—',
                    'status' => $attendanceRecord->status,
                    'minutes_present' => $attendanceRecord->minutes_present,
                    'notes' => $attendanceRecord->notes,
                ];
            })
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{created:int,updated:int}
     */
    protected static function saveQuickMarkedAttendance(AttendanceSession $record, array $rows): array
    {
        return DB::transaction(function () use ($record, $rows): array {
            $created = static::ensureRosterRecords($record);

            /** @var Collection<int, AttendanceRecord> $attendanceRecords */
            $attendanceRecords = $record->attendanceRecords()
                ->get()
                ->keyBy('id');

            $updated = 0;

            foreach ($rows as $row) {
                $attendanceRecord = $attendanceRecords->get($row['attendance_record_id'] ?? null);

                if (! $attendanceRecord) {
                    continue;
                }

                $status = array_key_exists($row['status'] ?? '', AttendanceRecord::STATUS_OPTIONS)
                    ? $row['status']
                    : 'not_marked';

                $minutesPresent = filled($row['minutes_present'] ?? null)
                    ? (int) $row['minutes_present']
                    : null;

                $notes = filled($row['notes'] ?? null)
                    ? trim((string) $row['notes'])
                    : null;

                $courseEnrollmentId = $row['course_enrollment_id'] ?? $attendanceRecord->course_enrollment_id;

                $hasChanges = $attendanceRecord->status !== $status
                    || $attendanceRecord->minutes_present !== $minutesPresent
                    || $attendanceRecord->notes !== $notes
                    || $attendanceRecord->course_enrollment_id !== $courseEnrollmentId;

                if (! $hasChanges) {
                    continue;
                }

                $markedAt = match (true) {
                    $status === 'not_marked' => null,
                    $attendanceRecord->status === 'not_marked' => now(),
                    default => now(),
                };

                $attendanceRecord->forceFill([
                    'course_enrollment_id' => $courseEnrollmentId,
                    'status' => $status,
                    'minutes_present' => $minutesPresent,
                    'notes' => $notes,
                    'marked_at' => $markedAt,
                ])->save();

                $updated++;
            }

            return [
                'created' => $created,
                'updated' => $updated,
            ];
        });
    }
}

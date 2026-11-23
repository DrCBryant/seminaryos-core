<?php

namespace App\Filament\Actions;

use App\Filament\Resources\Faculty\FacultyResource;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageFacultyMentorship
{
    public static function make(bool $assign): Action
    {
        return Action::make($assign ? 'assignMentee' : 'removeMentee')
            ->label($assign ? 'Assign Mentee' : 'Remove Mentee')
            ->authorize(self::authorized(...))
            ->schema([
                Select::make('student_id')
                    ->label('Student')
                    ->required()
                    ->rule('integer')
                    ->searchable()
                    ->getSearchResultsUsing(function (Faculty $record, string $search) use ($assign): array {
                        return self::students($record, $assign)
                            ->where(fn (Builder $query) => $query->whereLike('first_name', '%'.$search.'%')
                                ->orWhereLike('last_name', '%'.$search.'%')
                                ->orWhereLike('student_number', '%'.$search.'%'))
                            ->orderBy('last_name')->orderBy('first_name')->limit(25)->get()
                            ->mapWithKeys(fn (Student $student): array => [$student->id => self::label($student)])
                            ->all();
                    })
                    ->getOptionLabelUsing(function (Faculty $record, $value) use ($assign): ?string {
                        $student = self::students($record, $assign)->find($value);

                        return $student instanceof Student ? self::label($student) : null;
                    }),
            ])
            ->action(function (Faculty $record, array $data) use ($assign): void {
                abort_unless(self::authorized($record), 403);

                DB::transaction(function () use ($record, $data, $assign): void {
                    $faculty = Faculty::query()->lockForUpdate()->findOrFail($record->id);
                    $student = self::students($faculty, $assign)->lockForUpdate()->find($data['student_id']);

                    if (! $student instanceof Student) {
                        throw ValidationException::withMessages(['student_id' => 'Select an eligible student in this institution.']);
                    }

                    if ($assign) {
                        $faculty->mentees()->syncWithoutDetaching([$student->id]);
                    } else {
                        $faculty->mentees()->detach($student->id);
                    }
                }, 3);
            })
            ->successNotificationTitle($assign ? 'Mentee assigned' : 'Mentorship removed');
    }

    private static function authorized(Faculty $record): bool
    {
        return ! $record->trashed() && FacultyResource::canEdit($record)
            && in_array(auth()->user()?->activeInstitutionMembership((int) $record->institution_id)?->role, User::INSTITUTION_ADMIN_ROLES, true);
    }

    private static function students(Faculty $record, bool $assign): Builder
    {
        abort_unless(self::authorized($record), 403);

        return Student::query()->where('students.institution_id', $record->institution_id)
            ->whereIn('students.id', DB::table('faculty_mentorships')->select('student_id')
                ->where('faculty_id', $record->id)->where('institution_id', $record->institution_id), not: $assign);
    }

    private static function label(Student $student): string
    {
        return "{$student->full_name} ({$student->student_number})";
    }
}

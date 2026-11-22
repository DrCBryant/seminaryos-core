<?php

namespace App\Filament\Actions;

use App\Filament\Resources\Faculty\FacultyResource;
use App\Filament\Resources\Students\StudentResource;
use App\Models\Faculty;
use App\Models\Student;
use App\Support\Portals\AcademicIdentityAccess;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;
use stdClass;

class LinkAcademicAccount
{
    public static function make(): Action
    {
        return Action::make('linkAcademicAccount')
            ->label('Link Account')
            ->icon('heroicon-o-link')
            ->authorize(fn (Student|Faculty $record): bool => self::authorized($record))
            ->schema([
                Select::make('membership_id')
                    ->label('Institutional account')
                    ->helperText('Search for an active member of this institution. Clear the selection to unlink the account.')
                    ->default(fn (Student|Faculty $record): ?int => AcademicIdentityAccess::linkedMembershipId($record))
                    ->searchable()
                    ->getSearchResultsUsing(function (Student|Faculty $record, string $search): array {
                        abort_unless(self::authorized($record), 403);

                        return AcademicIdentityAccess::membershipAccounts($record, activeOnly: true)
                            ->where(fn ($query) => $query->whereLike('users.name', '%'.$search.'%')
                                ->orWhereLike('users.email', '%'.$search.'%'))
                            ->orderBy('users.name')->limit(25)->get()
                            ->mapWithKeys(fn (stdClass $account): array => [$account->id => self::accountLabel($account)])
                            ->all();
                    })
                    ->getOptionLabelUsing(function (Student|Faculty $record, $value): ?string {
                        abort_unless(self::authorized($record), 403);

                        $account = AcademicIdentityAccess::membershipAccounts($record)->where('memberships.id', $value)->first();

                        return $account === null ? null : self::accountLabel($account);
                    }),
            ])
            ->action(function (Action $action, Student|Faculty $record, array $data): void {
                abort_unless(self::authorized($record), 403);
                $selected = $data['membership_id'] ?? null;

                try {
                    if ($selected !== null && filter_var($selected, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                        throw ValidationException::withMessages(['membership_id' => 'Select an institutional account.']);
                    }

                    AcademicIdentityAccess::link(auth()->user(), $record, $selected === null ? null : (int) $selected);
                } catch (ValidationException $exception) {
                    Notification::make()->title('Account link could not be saved')
                        ->body($exception->validator->errors()->first())->warning()->send();
                    $action->halt();
                }

                Notification::make()->title('Account link saved')->success()->send();
            });
    }

    private static function authorized(Student|Faculty $record): bool
    {
        $actor = auth()->user();
        $resource = $record instanceof Student ? StudentResource::class : FacultyResource::class;

        return $actor !== null && AcademicIdentityAccess::canManageLinks($actor, $record) && $resource::canEdit($record);
    }

    private static function accountLabel(stdClass $account): string
    {
        $status = $account->deleted_at !== null
            ? 'account removed'
            : "account {$account->account_status}, membership {$account->membership_status}";

        return "{$account->name} ({$account->email}) · {$status}";
    }
}

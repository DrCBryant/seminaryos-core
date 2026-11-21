<?php

namespace App\Filament\Resources\OfficialTranscripts\Pages;

use App\Filament\Resources\OfficialTranscripts\OfficialTranscriptResource;
use App\Filament\Resources\OfficialTranscripts\Schemas\OfficialTranscriptForm;
use App\Models\OfficialTranscript;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EditOfficialTranscript extends EditRecord
{
    protected static string $resource = OfficialTranscriptResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data): Model {
            $current = OfficialTranscript::query()->withTrashed()->lockForUpdate()->findOrFail($record->getKey());
            abort_unless(OfficialTranscriptResource::canEdit($current), 403);

            if ($current->status === 'issued') {
                if ($record->status !== 'issued') {
                    throw ValidationException::withMessages(['data.status' => 'This transcript was issued while you were editing it. Reload before editing notes.']);
                }

                $data = Arr::only($data, ['registrar_notes', 'internal_notes']);
            } else {
                OfficialTranscriptForm::validateForSave($data + $current->getAttributes());
            }

            $current = parent::handleRecordUpdate($current, $data);
            $record->setRawAttributes($current->getAttributes(), true);

            return $record;
        }, 3);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (OfficialTranscript $record): bool => $record->status !== 'issued')
                ->using(fn (OfficialTranscript $record): bool => DB::transaction(function () use ($record): bool {
                    $current = OfficialTranscript::query()->lockForUpdate()->findOrFail($record->getKey());
                    abort_unless(OfficialTranscriptResource::canDelete($current), 403);

                    if ($current->status === 'issued') {
                        return false;
                    }

                    return (bool) $record->delete();
                }, 3)),
            RestoreAction::make(),
        ];
    }
}

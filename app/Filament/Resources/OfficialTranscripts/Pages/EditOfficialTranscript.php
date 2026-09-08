<?php

namespace App\Filament\Resources\OfficialTranscripts\Pages;

use App\Filament\Resources\OfficialTranscripts\OfficialTranscriptResource;
use App\Models\OfficialTranscript;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditOfficialTranscript extends EditRecord
{
    protected static string $resource = OfficialTranscriptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (OfficialTranscript $record): bool => $record->status !== 'issued'),
            RestoreAction::make(),
        ];
    }
}

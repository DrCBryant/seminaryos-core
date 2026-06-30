<?php

namespace App\Filament\Resources\OfficialTranscripts\Pages;

use App\Filament\Resources\OfficialTranscripts\OfficialTranscriptResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditOfficialTranscript extends EditRecord
{
    protected static string $resource = OfficialTranscriptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}

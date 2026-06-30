<?php

namespace App\Filament\Resources\OfficialTranscripts\Pages;

use App\Filament\Resources\OfficialTranscripts\OfficialTranscriptResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOfficialTranscripts extends ListRecords
{
    protected static string $resource = OfficialTranscriptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

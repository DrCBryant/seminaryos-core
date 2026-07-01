<?php

namespace App\Filament\Resources\TranscriptSettings\Pages;

use App\Filament\Resources\TranscriptSettings\TranscriptSettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTranscriptSettings extends ListRecords
{
    protected static string $resource = TranscriptSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

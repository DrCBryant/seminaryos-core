<?php

namespace App\Filament\Resources\OfficialTranscripts\Pages;

use App\Filament\Resources\OfficialTranscripts\OfficialTranscriptResource;
use App\Filament\Resources\OfficialTranscripts\Schemas\OfficialTranscriptForm;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateOfficialTranscript extends CreateRecord
{
    protected static string $resource = OfficialTranscriptResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        OfficialTranscriptForm::validateForSave($data);

        return parent::handleRecordCreation($data);
    }
}

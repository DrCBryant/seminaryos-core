<?php

namespace App\Filament\Resources\AcademicRecords\Pages;

use App\Filament\Resources\AcademicRecords\AcademicRecordResource;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class EditAcademicRecord extends EditRecord
{
    protected static string $resource = AcademicRecordResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return parent::handleRecordUpdate($record, Arr::only($data, ['notes']));
    }

    protected function getHeaderActions(): array
    {
        return [
            RestoreAction::make(),
        ];
    }
}

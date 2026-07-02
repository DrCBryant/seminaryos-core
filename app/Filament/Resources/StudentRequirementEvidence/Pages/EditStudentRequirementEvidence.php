<?php

namespace App\Filament\Resources\StudentRequirementEvidence\Pages;

use App\Filament\Resources\StudentRequirementEvidence\StudentRequirementEvidenceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStudentRequirementEvidence extends EditRecord
{
    protected static string $resource = StudentRequirementEvidenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

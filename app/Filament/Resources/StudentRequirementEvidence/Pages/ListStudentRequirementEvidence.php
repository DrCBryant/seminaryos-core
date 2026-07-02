<?php

namespace App\Filament\Resources\StudentRequirementEvidence\Pages;

use App\Filament\Resources\StudentRequirementEvidence\StudentRequirementEvidenceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStudentRequirementEvidence extends ListRecords
{
    protected static string $resource = StudentRequirementEvidenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

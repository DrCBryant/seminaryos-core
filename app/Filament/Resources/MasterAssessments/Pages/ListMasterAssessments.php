<?php

namespace App\Filament\Resources\MasterAssessments\Pages;

use App\Filament\Resources\MasterAssessments\MasterAssessmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMasterAssessments extends ListRecords
{
    protected static string $resource = MasterAssessmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

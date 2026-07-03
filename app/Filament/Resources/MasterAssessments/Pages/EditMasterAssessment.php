<?php

namespace App\Filament\Resources\MasterAssessments\Pages;

use App\Filament\Resources\MasterAssessments\MasterAssessmentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMasterAssessment extends EditRecord
{
    protected static string $resource = MasterAssessmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

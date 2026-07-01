<?php

namespace App\Filament\Resources\ProgramRequirementGroups\Pages;

use App\Filament\Resources\ProgramRequirementGroups\ProgramRequirementGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProgramRequirementGroup extends EditRecord
{
    protected static string $resource = ProgramRequirementGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\ProgramRequirementSubstitutions\Pages;

use App\Filament\Resources\ProgramRequirementSubstitutions\ProgramRequirementSubstitutionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProgramRequirementSubstitution extends EditRecord
{
    protected static string $resource = ProgramRequirementSubstitutionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

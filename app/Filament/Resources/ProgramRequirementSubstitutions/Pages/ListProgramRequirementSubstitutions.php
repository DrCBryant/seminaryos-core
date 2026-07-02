<?php

namespace App\Filament\Resources\ProgramRequirementSubstitutions\Pages;

use App\Filament\Resources\ProgramRequirementSubstitutions\ProgramRequirementSubstitutionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProgramRequirementSubstitutions extends ListRecords
{
    protected static string $resource = ProgramRequirementSubstitutionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

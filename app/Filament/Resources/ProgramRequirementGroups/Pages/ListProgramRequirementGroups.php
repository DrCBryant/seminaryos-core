<?php

namespace App\Filament\Resources\ProgramRequirementGroups\Pages;

use App\Filament\Resources\ProgramRequirementGroups\ProgramRequirementGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProgramRequirementGroups extends ListRecords
{
    protected static string $resource = ProgramRequirementGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

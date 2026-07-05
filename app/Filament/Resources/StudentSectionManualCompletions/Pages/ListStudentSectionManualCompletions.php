<?php

namespace App\Filament\Resources\StudentSectionManualCompletions\Pages;

use App\Filament\Resources\StudentSectionManualCompletions\StudentSectionManualCompletionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStudentSectionManualCompletions extends ListRecords
{
    protected static string $resource = StudentSectionManualCompletionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

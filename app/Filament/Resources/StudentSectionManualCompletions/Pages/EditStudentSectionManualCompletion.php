<?php

namespace App\Filament\Resources\StudentSectionManualCompletions\Pages;

use App\Filament\Resources\StudentSectionManualCompletions\StudentSectionManualCompletionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStudentSectionManualCompletion extends EditRecord
{
    protected static string $resource = StudentSectionManualCompletionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\StudentSectionSubmissions\Pages;

use App\Filament\Resources\StudentSectionSubmissions\StudentSectionSubmissionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStudentSectionSubmission extends EditRecord
{
    protected static string $resource = StudentSectionSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

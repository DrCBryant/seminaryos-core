<?php

namespace App\Filament\Resources\StudentSectionSubmissions\Pages;

use App\Filament\Resources\StudentSectionSubmissions\StudentSectionSubmissionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStudentSectionSubmissions extends ListRecords
{
    protected static string $resource = StudentSectionSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\Faculty\Pages;

use App\Filament\Actions\LinkAcademicAccount;
use App\Filament\Actions\ManageFacultyMentorship;
use App\Filament\Resources\Faculty\FacultyResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditFaculty extends EditRecord
{
    protected static string $resource = FacultyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LinkAcademicAccount::make(),
            ManageFacultyMentorship::make(assign: true),
            ManageFacultyMentorship::make(assign: false),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}

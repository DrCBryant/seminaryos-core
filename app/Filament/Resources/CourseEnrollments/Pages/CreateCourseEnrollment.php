<?php

namespace App\Filament\Resources\CourseEnrollments\Pages;

use App\Filament\Resources\CourseEnrollments\CourseEnrollmentResource;
use App\Models\CourseEnrollment;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCourseEnrollment extends CreateRecord
{
    protected static string $resource = CourseEnrollmentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        CourseEnrollment::validateAdministrativeData($data);

        return parent::handleRecordCreation($data);
    }
}

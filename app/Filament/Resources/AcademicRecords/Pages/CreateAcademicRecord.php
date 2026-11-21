<?php

namespace App\Filament\Resources\AcademicRecords\Pages;

use App\Filament\Resources\AcademicRecords\AcademicRecordResource;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\GradeScale;
use App\Models\GradeValue;
use App\Models\Student;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateAcademicRecord extends CreateRecord
{
    protected static string $resource = AcademicRecordResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Model {
            $institutionId = $data['institution_id'];
            $currentInstitutionId = auth()->user()?->current_institution_id;

            if ($currentInstitutionId !== null && (int) $institutionId !== (int) $currentInstitutionId) {
                throw ValidationException::withMessages(['data.institution_id' => 'Select your current institution.']);
            }

            foreach (['student_id' => Student::class, 'course_id' => Course::class, 'academic_term_id' => AcademicTerm::class, 'grade_scale_id' => GradeScale::class, 'grade_value_id' => GradeValue::class] as $field => $model) {
                if (filled($data[$field] ?? null) && ! $model::query()->where('institution_id', $institutionId)->whereKey($data[$field])->exists()) {
                    throw ValidationException::withMessages(["data.{$field}" => 'Select a record from the academic record institution.']);
                }
            }

            if (filled($data['course_enrollment_id'] ?? null)) {
                $enrollment = CourseEnrollment::query()->where('institution_id', $institutionId)
                    ->where('student_id', $data['student_id'])->where('course_id', $data['course_id'])
                    ->lockForUpdate()->find($data['course_enrollment_id']);

                if (! $enrollment || $enrollment->status !== 'completed' || $enrollment->academicRecord()->withTrashed()->exists()) {
                    throw ValidationException::withMessages(['data.course_enrollment_id' => 'Select a matching historical completed enrollment without an academic record, or leave it blank.']);
                }
            }

            return parent::handleRecordCreation($data);
        }, 3);
    }
}

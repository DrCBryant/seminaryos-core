<?php

namespace App\Support\Admissions;

use App\Models\Applicant;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ApplicantStudentConversionService
{
    /**
     * @return array{student: Student, already_converted: bool}
     */
    public function convert(Applicant $applicant): array
    {
        return DB::transaction(function () use ($applicant): array {
            $applicant = Applicant::query()->whereKey($applicant->getKey())->lockForUpdate()->firstOrFail();
            $linkedStudent = $applicant->student()->first();

            if ($linkedStudent) {
                return ['student' => $linkedStudent, 'already_converted' => true];
            }

            if ($applicant->status !== 'accepted') {
                throw ValidationException::withMessages(['status' => 'Only accepted applicants can be converted to students.']);
            }

            $duplicate = Student::query()
                ->where('institution_id', $applicant->institution_id)
                ->where('email', $applicant->email)
                ->first();

            if ($duplicate) {
                throw ValidationException::withMessages(['student' => 'A student with this institution and email already exists. Registrar review is required before conversion.']);
            }

            $student = Student::query()->create([
                'institution_id' => $applicant->institution_id,
                'program_id' => $applicant->program_id,
                'applicant_id' => $applicant->id,
                'first_name' => $applicant->first_name,
                'last_name' => $applicant->last_name,
                'email' => $applicant->email,
                'phone' => $applicant->phone,
                'student_number' => $this->generateStudentNumber($applicant),
                'status' => 'active',
                'enrollment_date' => now()->toDateString(),
                'notes' => $applicant->notes,
            ]);

            $applicant->forceFill(['status' => 'enrolled', 'converted_at' => now()])->save();

            return ['student' => $student, 'already_converted' => false];
        });
    }

    protected function generateStudentNumber(Applicant $applicant): string
    {
        do {
            $studentNumber = sprintf('S-%d-%s-%s', $applicant->institution_id, now()->format('Ymd'), Str::upper(Str::random(6)));
        } while (Student::query()->where('institution_id', $applicant->institution_id)->where('student_number', $studentNumber)->exists());

        return $studentNumber;
    }
}

<?php

namespace App\Support\Enrollments;

use App\Models\AcademicRecord;
use App\Models\CourseEnrollment;
use App\Models\GradeValue;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnrollmentCompletionService
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $progressEvaluation
     */
    public function complete(CourseEnrollment $enrollment, array $data, array $progressEvaluation): void
    {
        $payload = $this->validateAndNormalizePayload($enrollment, $data);

        DB::transaction(function () use ($enrollment, $payload, $progressEvaluation): void {
            if ($enrollment->academicRecord()->exists()) {
                throw ValidationException::withMessages([
                    'academic_record' => 'This course enrollment already has an academic record. No duplicate record was created.',
                ]);
            }

            $enrollment->forceFill([
                'status' => 'completed',
                'final_grade' => $payload['final_grade'],
                'completed_at' => $payload['completed_at'],
                'completion_progress_basis' => $progressEvaluation['progress_basis_raw'],
                'completion_progress_status' => $progressEvaluation['progress_status_raw'],
                'completion_evidence_summary' => $progressEvaluation['evidence_summary_raw'],
                'completion_override_reason' => $progressEvaluation['requires_override'] ? ($payload['completion_override_reason'] ?? null) : null,
                'completion_reviewed_at' => now(),
                'completion_reviewed_by_user_id' => auth()->id(),
            ])->save();

            AcademicRecord::create([
                'institution_id' => $enrollment->institution_id,
                'student_id' => $enrollment->student_id,
                'course_id' => $enrollment->course_id,
                'academic_term_id' => $enrollment->academic_term_id,
                'course_enrollment_id' => $enrollment->id,
                'course_code' => $enrollment->course->code,
                'course_title' => $enrollment->course->title,
                'credits_attempted' => $payload['credits_attempted'],
                'credits_earned' => $payload['credits_earned'],
                'final_grade' => $payload['final_grade'],
                'grade_points' => $payload['grade_points'],
                'grade_scale_id' => $payload['grade_scale_id'],
                'grade_value_id' => $payload['grade_value_id'],
                'grade_label' => $payload['grade_label'],
                'earns_credit' => $payload['earns_credit'],
                'affects_gpa' => $payload['affects_gpa'],
                'is_passing' => $payload['is_passing'],
                'status' => 'completed',
                'completed_at' => $payload['completed_at'],
                'notes' => $payload['notes'],
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function validateAndNormalizePayload(CourseEnrollment $enrollment, array $data): array
    {
        $selectedGradeValue = $this->resolveSelectedGradeValue($enrollment, $data);

        if (filled($data['grade_value_id'] ?? null) && ! $selectedGradeValue) {
            throw ValidationException::withMessages([
                'grade_value_id' => 'The chosen grade value does not belong to the selected active grade scale for this institution.',
            ]);
        }

        $finalGrade = $selectedGradeValue?->grade ?? ($data['final_grade'] ?? null);

        if (blank($finalGrade)) {
            throw ValidationException::withMessages([
                'final_grade' => 'Select a grade value or enter a manual final grade to complete the enrollment.',
            ]);
        }

        return [
            'completed_at' => $data['completed_at'],
            'completion_override_reason' => filled($data['completion_override_reason'] ?? null) ? $data['completion_override_reason'] : null,
            'credits_attempted' => $data['credits_attempted'],
            'credits_earned' => $data['credits_earned'],
            'final_grade' => $finalGrade,
            'grade_points' => $selectedGradeValue?->grade_points ?? (($data['grade_points'] ?? null) ?: null),
            'grade_scale_id' => ($data['grade_scale_id'] ?? null) ?: null,
            'grade_value_id' => $selectedGradeValue?->id,
            'grade_label' => $selectedGradeValue?->label,
            'earns_credit' => $selectedGradeValue?->earns_credit,
            'affects_gpa' => $selectedGradeValue?->affects_gpa,
            'is_passing' => $selectedGradeValue?->is_passing,
            'notes' => ($data['notes'] ?? null) ?: null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolveSelectedGradeValue(CourseEnrollment $enrollment, array $data): ?GradeValue
    {
        if (! filled($data['grade_value_id'] ?? null)) {
            return null;
        }

        return GradeValue::query()
            ->where('institution_id', $enrollment->institution_id)
            ->where('grade_scale_id', $data['grade_scale_id'] ?? null)
            ->find($data['grade_value_id']);
    }
}

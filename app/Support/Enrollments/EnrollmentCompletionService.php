<?php

namespace App\Support\Enrollments;

use App\Models\AcademicRecord;
use App\Models\CourseEnrollment;
use App\Models\GradeValue;
use Illuminate\Support\Facades\DB;

class EnrollmentCompletionService
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $progressEvaluation
     */
    public function complete(CourseEnrollment $enrollment, array $data, array $progressEvaluation): void
    {
        DB::transaction(function () use ($enrollment, $data, $progressEvaluation): void {
            $selectedGradeValue = $this->resolveSelectedGradeValue($enrollment, $data);
            $finalGrade = $selectedGradeValue?->grade ?? ($data['final_grade'] ?? null);

            $enrollment->forceFill([
                'status' => 'completed',
                'final_grade' => $finalGrade,
                'completed_at' => $data['completed_at'],
                'completion_progress_basis' => $progressEvaluation['progress_basis_raw'],
                'completion_progress_status' => $progressEvaluation['progress_status_raw'],
                'completion_evidence_summary' => $progressEvaluation['evidence_summary_raw'],
                'completion_override_reason' => $progressEvaluation['requires_override'] ? ($data['completion_override_reason'] ?? null) : null,
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
                'credits_attempted' => $data['credits_attempted'],
                'credits_earned' => $data['credits_earned'],
                'final_grade' => $finalGrade,
                'grade_points' => $selectedGradeValue?->grade_points ?? ($data['grade_points'] ?: null),
                'grade_scale_id' => $data['grade_scale_id'] ?: null,
                'grade_value_id' => $selectedGradeValue?->id,
                'grade_label' => $selectedGradeValue?->label,
                'earns_credit' => $selectedGradeValue?->earns_credit,
                'affects_gpa' => $selectedGradeValue?->affects_gpa,
                'is_passing' => $selectedGradeValue?->is_passing,
                'status' => 'completed',
                'completed_at' => $data['completed_at'],
                'notes' => $data['notes'] ?: null,
            ]);
        });
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

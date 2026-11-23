<?php

namespace App\Support\Assessments;

use App\Models\CompetencyMasteryDecision;
use App\Models\MasterAssessment;
use App\Models\ProgramRequirement;
use App\Models\Student;
use App\Models\StudentMasterAssessmentAttempt;
use App\Models\User;
use App\Support\Portals\AcademicIdentityAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinalizeCompetencyMastery
{
    public function finalize(User $actor, int $studentId, int $attemptId): CompetencyMasteryDecision
    {
        return DB::transaction(function () use ($actor, $studentId, $attemptId): CompetencyMasteryDecision {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $membership = $actor->activeInstitutionMembership(lock: true);
            abort_unless($membership !== null, 403);

            $student = Student::query()->where('institution_id', $membership->institution_id)
                ->lockForUpdate()->findOrFail($studentId);
            abort_unless(AcademicIdentityAccess::viewMentee($actor, $student), 403);

            $mentor = AcademicIdentityAccess::faculty($actor);
            abort_unless($mentor !== null && DB::table('faculty_identity_links')->where('membership_id', $membership->id)
                ->where('identity_id', $mentor->id)->lockForUpdate()->first() !== null, 403);
            abort_unless(DB::table('faculty_mentorships')->where('institution_id', $student->institution_id)
                ->where('faculty_id', $mentor->id)->where('student_id', $student->id)->lockForUpdate()->first() !== null, 403);

            $attempt = $student->studentMasterAssessmentAttempts()->where('institution_id', $student->institution_id)
                ->lockForUpdate()->findOrFail($attemptId);

            $existing = $student->competencyMasteryDecisions()->where('attempt_id', $attempt->id)->lockForUpdate()->first();
            if ($existing !== null) {
                return $existing;
            }

            $assessment = MasterAssessment::query()->where('institution_id', $student->institution_id)
                ->where('course_offering_id', $attempt->course_offering_id)
                ->whereHas('courseOffering', fn (Builder $query) => $query->where('institution_id', $student->institution_id))
                ->lockForUpdate()->findOrFail($attempt->master_assessment_id);

            if ($attempt->status !== StudentMasterAssessmentAttempt::STATUS_PASSED || $assessment->status !== MasterAssessment::STATUS_ACTIVE) {
                throw ValidationException::withMessages(['attempt' => 'Mastery can be finalized only from a passed attempt on an active Master Assessment.']);
            }

            $requirements = $assessment->competencyRequirements()
                ->where('program_requirements.institution_id', $student->institution_id)
                ->where('program_requirements.program_id', $student->program_id)
                ->where('program_requirements.requirement_type', ProgramRequirement::TYPE_COMPETENCY)
                ->where('program_requirements.is_active', true)
                ->whereHas('program', fn (Builder $query) => $query->where('institution_id', $student->institution_id))
                ->whereHas('programRequirementGroup', fn (Builder $query) => $query
                    ->where('institution_id', $student->institution_id)->where('program_id', $student->program_id)->where('is_active', true))
                ->orderBy('program_requirements.id')->lockForUpdate()->get();

            if ($requirements->isEmpty()) {
                throw ValidationException::withMessages(['attempt' => 'This assessment has no active competency mapping in the mentee’s assigned program.']);
            }

            return $student->competencyMasteryDecisions()->create([
                'institution_id' => $student->institution_id,
                'program_id' => $student->program_id,
                'master_assessment_id' => $assessment->id,
                'attempt_id' => $attempt->id,
                'mentor_faculty_id' => $mentor->id,
                'decided_by_user_id' => $actor->id,
                'decided_at' => now(),
                'snapshot' => [
                    'assessment' => $assessment->only(['id', 'title', 'course_offering_id', 'description', 'competency_outcomes', 'rubric', 'passing_threshold']),
                    'attempt' => $attempt->only(['id', 'status', 'course_enrollment_id', 'submitted_at', 'assessed_at', 'assessor_user_id', 'assessor_notes', 'notes']),
                    'mentor_name' => $mentor->full_name,
                    'requirements' => $requirements->map(fn (ProgramRequirement $requirement): array => $requirement->only(['id', 'name']))->all(),
                ],
            ]);
        }, 3);
    }
}

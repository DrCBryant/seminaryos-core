<?php

namespace App\Support\Assessments;

use App\Models\CompetencyMasteryDecision;
use App\Models\Faculty;
use App\Models\MasterAssessment;
use App\Models\MentorAssessmentResult;
use App\Models\ProgramRequirement;
use App\Models\Student;
use App\Models\StudentMasterAssessmentAttempt;
use App\Models\User;
use App\Support\Portals\AcademicIdentityAccess;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FinalizeCompetencyMastery
{
    public function finalize(User $actor, int $studentId, int $attemptId): CompetencyMasteryDecision
    {
        return DB::transaction(function () use ($actor, $studentId, $attemptId): CompetencyMasteryDecision {
            [$student, $mentor, $attempt] = $this->lockAttempt($actor, $studentId, $attemptId);
            $existing = $this->decision($student, $attempt);
            if ($existing !== null) {
                return $existing;
            }

            $assessment = $this->assessment($student, $attempt);
            if ($attempt->status !== StudentMasterAssessmentAttempt::STATUS_PASSED) {
                throw ValidationException::withMessages(['attempt' => 'Mastery can be finalized only from a passed attempt on an active Master Assessment.']);
            }

            return $this->recordMastery($actor, $student, $mentor, $attempt, $assessment, $this->requirements($student, $assessment), now());
        }, 3);
    }

    public function review(User $actor, int $studentId, int $attemptId): array
    {
        return DB::transaction(function () use ($actor, $studentId, $attemptId): array {
            [$student, , $attempt] = $this->lockAttempt($actor, $studentId, $attemptId);
            [$assessment, $requirements] = $this->reviewableAssessment($student, $attempt);

            return [
                'assessment' => $assessment->only(['title', 'description', 'rubric', 'passing_threshold']),
                'competencies' => $requirements->pluck('name'),
                'submittedAt' => $attempt->submitted_at,
                'evidence' => $attempt->notes,
                'feedback' => $attempt->assessor_notes,
            ];
        }, 3);
    }

    public function recordResult(User $actor, int $studentId, int $attemptId, array $data): MentorAssessmentResult
    {
        $data = Validator::make($data, [
            'status' => ['required', Rule::in(array_keys(StudentMasterAssessmentAttempt::MENTOR_RESULTS))],
            'feedback' => ['nullable', 'string', 'max:10000'],
        ])->validate();
        $feedback = $data['feedback'] ?? null;

        return DB::transaction(function () use ($actor, $studentId, $attemptId, $data, $feedback): MentorAssessmentResult {
            [$student, $mentor, $attempt] = $this->lockAttempt($actor, $studentId, $attemptId);
            $existing = $attempt->mentorResult()->lockForUpdate()->first();

            if ($existing !== null && (int) $existing->decided_by_user_id === (int) $actor->id
                && $existing->status === $data['status'] && $existing->feedback === $feedback) {
                return $existing;
            }

            [$assessment, $requirements] = $this->reviewableAssessment($student, $attempt);
            $decidedAt = now();
            $attempt->update([
                'status' => $data['status'],
                'assessor_notes' => $feedback,
                'assessor_user_id' => $actor->id,
                'assessed_at' => $decidedAt,
            ]);

            if ($attempt->status === StudentMasterAssessmentAttempt::STATUS_PASSED) {
                $this->recordMastery($actor, $student, $mentor, $attempt, $assessment, $requirements, $decidedAt);
            }

            return $attempt->mentorResult()->create([
                'mentor_faculty_id' => $mentor->id,
                'decided_by_user_id' => $actor->id,
                'decided_at' => $decidedAt,
                'status' => $attempt->status,
                'feedback' => $feedback,
            ]);
        }, 3);
    }

    /** @return array{Student, Faculty, StudentMasterAssessmentAttempt} */
    private function lockAttempt(User $actor, int $studentId, int $attemptId): array
    {
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

        return [$student, $mentor, $attempt];
    }

    private function decision(Student $student, StudentMasterAssessmentAttempt $attempt): ?CompetencyMasteryDecision
    {
        return $student->competencyMasteryDecisions()->where('attempt_id', $attempt->id)->lockForUpdate()->first();
    }

    /** @return array{MasterAssessment, Collection} */
    private function reviewableAssessment(Student $student, StudentMasterAssessmentAttempt $attempt): array
    {
        if ($attempt->status !== StudentMasterAssessmentAttempt::STATUS_SUBMITTED
            || $attempt->mentorResult()->lockForUpdate()->first() !== null || $this->decision($student, $attempt) !== null) {
            throw ValidationException::withMessages(['attempt' => 'Only a submitted attempt without a finalized result can be reviewed.']);
        }

        $assessment = $this->assessment($student, $attempt);

        return [$assessment, $this->requirements($student, $assessment)];
    }

    private function assessment(Student $student, StudentMasterAssessmentAttempt $attempt): MasterAssessment
    {
        $assessment = MasterAssessment::query()->where('institution_id', $student->institution_id)
            ->where('course_offering_id', $attempt->course_offering_id)
            ->whereHas('courseOffering', fn (Builder $query) => $query->where('institution_id', $student->institution_id))
            ->lockForUpdate()->findOrFail($attempt->master_assessment_id);

        if ($assessment->status !== MasterAssessment::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['attempt' => 'The Master Assessment must be active.']);
        }

        return $assessment;
    }

    private function requirements(Student $student, MasterAssessment $assessment): Collection
    {
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

        return $requirements;
    }

    private function recordMastery(User $actor, Student $student, Faculty $mentor, StudentMasterAssessmentAttempt $attempt, MasterAssessment $assessment, Collection $requirements, CarbonInterface $decidedAt): CompetencyMasteryDecision
    {
        return $student->competencyMasteryDecisions()->create([
            'institution_id' => $student->institution_id,
            'program_id' => $student->program_id,
            'master_assessment_id' => $assessment->id,
            'attempt_id' => $attempt->id,
            'mentor_faculty_id' => $mentor->id,
            'decided_by_user_id' => $actor->id,
            'decided_at' => $decidedAt,
            'snapshot' => [
                'assessment' => $assessment->only(['id', 'title', 'course_offering_id', 'description', 'competency_outcomes', 'rubric', 'passing_threshold']),
                'attempt' => $attempt->only(['id', 'status', 'course_enrollment_id', 'submitted_at', 'assessed_at', 'assessor_user_id', 'assessor_notes', 'notes']),
                'mentor_name' => $mentor->full_name,
                'requirements' => $requirements->map(fn (ProgramRequirement $requirement): array => $requirement->only(['id', 'name']))->all(),
            ],
        ]);
    }
}

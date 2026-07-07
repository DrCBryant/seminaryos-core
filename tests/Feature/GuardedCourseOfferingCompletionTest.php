<?php

namespace Tests\Feature;

use App\Models\AcademicRecord;
use App\Models\AcademicTerm;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseOffering;
use App\Models\GradeScale;
use App\Models\GradeValue;
use App\Models\Institution;
use App\Models\MasterAssessment;
use App\Models\Program;
use App\Models\SectionAssignment;
use App\Models\Student;
use App\Models\StudentMasterAssessmentAttempt;
use App\Models\StudentSectionSubmission;
use App\Models\User;
use App\Support\Enrollments\EnrollmentCompletionService;
use App\Support\SectionProgress\SectionProgressEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GuardedCourseOfferingCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_satisfied_enrollment_can_be_completed_once_and_stores_progress_snapshot_fields(): void
    {
        $context = $this->createEnrollmentContext(CourseOffering::PROGRESS_BASIS_SUBMISSIONS);
        $gradeValue = $this->createGradeValue($context['institution']);

        $this->actingAs($context['user']);

        $service = app(EnrollmentCompletionService::class);

        $progressEvaluation = [
            'progress_basis_raw' => CourseOffering::PROGRESS_BASIS_SUBMISSIONS,
            'progress_status_raw' => 'satisfied',
            'evidence_summary_raw' => 'Required submissions satisfied.',
            'requires_override' => false,
        ];

        $service->complete($context['enrollment'], [
            'grade_scale_id' => $gradeValue->grade_scale_id,
            'grade_value_id' => $gradeValue->id,
            'final_grade' => null,
            'credits_attempted' => '3.00',
            'credits_earned' => '3.00',
            'grade_points' => null,
            'completed_at' => '2026-07-07',
            'notes' => 'Registrar completion note.',
        ], $progressEvaluation);

        $context['enrollment']->refresh();

        $this->assertSame('completed', $context['enrollment']->status);
        $this->assertSame(CourseOffering::PROGRESS_BASIS_SUBMISSIONS, $context['enrollment']->completion_progress_basis);
        $this->assertSame('satisfied', $context['enrollment']->completion_progress_status);
        $this->assertSame('Required submissions satisfied.', $context['enrollment']->completion_evidence_summary);
        $this->assertNull($context['enrollment']->completion_override_reason);
        $this->assertSame($context['user']->id, $context['enrollment']->completion_reviewed_by_user_id);
        $this->assertNotNull($context['enrollment']->completion_reviewed_at);

        $this->assertDatabaseCount('academic_records', 1);

        $record = AcademicRecord::query()->firstOrFail();

        $this->assertSame($context['enrollment']->id, $record->course_enrollment_id);
        $this->assertSame('A', $record->final_grade);
        $this->assertSame('Excellent', $record->grade_label);
        $this->assertSame('4.00', $record->grade_points);
        $this->assertTrue($record->earns_credit);
        $this->assertTrue($record->affects_gpa);
        $this->assertTrue($record->is_passing);
        $this->assertSame('3.00', $record->credits_attempted);
        $this->assertSame('3.00', $record->credits_earned);

        try {
            $service->complete($context['enrollment']->fresh(), [
                'grade_scale_id' => $gradeValue->grade_scale_id,
                'grade_value_id' => $gradeValue->id,
                'final_grade' => null,
                'credits_attempted' => '3.00',
                'credits_earned' => '3.00',
                'grade_points' => null,
                'completed_at' => '2026-07-07',
                'notes' => 'Second attempt should fail.',
            ], $progressEvaluation);

            $this->fail('Expected duplicate completion attempt to be blocked.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'This course enrollment already has an academic record. No duplicate record was created.',
                $exception->errors()['academic_record'][0] ?? null,
            );
        }

        $this->assertDatabaseCount('academic_records', 1);
    }

    public function test_submissions_progress_basis_maps_submission_statuses_to_expected_progress_states(): void
    {
        $accepted = $this->createEnrollmentContext(CourseOffering::PROGRESS_BASIS_SUBMISSIONS);
        $acceptedAssignment = $this->createRequiredAssignment($accepted['courseOffering']);
        $this->createSubmission($accepted['enrollment'], StudentSectionSubmission::STATUS_ACCEPTED, $acceptedAssignment);
        $this->assertSame('satisfied', SectionProgressEvaluator::evaluateEnrollment($accepted['enrollment'])['progress_status']);

        $submitted = $this->createEnrollmentContext(CourseOffering::PROGRESS_BASIS_SUBMISSIONS);
        $this->createRequiredAssignment($submitted['courseOffering']);
        $this->createSubmission($submitted['enrollment'], StudentSectionSubmission::STATUS_SUBMITTED);
        $this->assertSame('in_progress', SectionProgressEvaluator::evaluateEnrollment($submitted['enrollment'])['progress_status']);

        $revisionNeeded = $this->createEnrollmentContext(CourseOffering::PROGRESS_BASIS_SUBMISSIONS);
        $this->createRequiredAssignment($revisionNeeded['courseOffering']);
        $this->createSubmission($revisionNeeded['enrollment'], StudentSectionSubmission::STATUS_REVISION_NEEDED);
        $this->assertSame('needs_attention', SectionProgressEvaluator::evaluateEnrollment($revisionNeeded['enrollment'])['progress_status']);

        $rejected = $this->createEnrollmentContext(CourseOffering::PROGRESS_BASIS_SUBMISSIONS);
        $this->createRequiredAssignment($rejected['courseOffering']);
        $this->createSubmission($rejected['enrollment'], StudentSectionSubmission::STATUS_REJECTED);
        $this->assertSame('needs_attention', SectionProgressEvaluator::evaluateEnrollment($rejected['enrollment'])['progress_status']);

        $missing = $this->createEnrollmentContext(CourseOffering::PROGRESS_BASIS_SUBMISSIONS);
        $this->createRequiredAssignment($missing['courseOffering']);
        $this->assertSame('not_started', SectionProgressEvaluator::evaluateEnrollment($missing['enrollment'])['progress_status']);
    }

    public function test_master_assessment_progress_basis_maps_attempt_statuses_to_expected_progress_states(): void
    {
        $passed = $this->createEnrollmentContext(CourseOffering::PROGRESS_BASIS_MASTER_ASSESSMENT);
        $passedAssessment = $this->createMasterAssessment($passed['courseOffering']);
        $this->createMasterAssessmentAttempt($passed['enrollment'], StudentMasterAssessmentAttempt::STATUS_PASSED, $passedAssessment);
        $this->assertSame('satisfied', SectionProgressEvaluator::evaluateEnrollment($passed['enrollment'])['progress_status']);

        $submitted = $this->createEnrollmentContext(CourseOffering::PROGRESS_BASIS_MASTER_ASSESSMENT);
        $this->createMasterAssessment($submitted['courseOffering']);
        $this->createMasterAssessmentAttempt($submitted['enrollment'], StudentMasterAssessmentAttempt::STATUS_SUBMITTED);
        $this->assertSame('in_progress', SectionProgressEvaluator::evaluateEnrollment($submitted['enrollment'])['progress_status']);

        $failed = $this->createEnrollmentContext(CourseOffering::PROGRESS_BASIS_MASTER_ASSESSMENT);
        $this->createMasterAssessment($failed['courseOffering']);
        $this->createMasterAssessmentAttempt($failed['enrollment'], StudentMasterAssessmentAttempt::STATUS_FAILED);
        $this->assertSame('needs_attention', SectionProgressEvaluator::evaluateEnrollment($failed['enrollment'])['progress_status']);

        $revisionNeeded = $this->createEnrollmentContext(CourseOffering::PROGRESS_BASIS_MASTER_ASSESSMENT);
        $this->createMasterAssessment($revisionNeeded['courseOffering']);
        $this->createMasterAssessmentAttempt($revisionNeeded['enrollment'], StudentMasterAssessmentAttempt::STATUS_REVISION_NEEDED);
        $this->assertSame('needs_attention', SectionProgressEvaluator::evaluateEnrollment($revisionNeeded['enrollment'])['progress_status']);

        $noAttempt = $this->createEnrollmentContext(CourseOffering::PROGRESS_BASIS_MASTER_ASSESSMENT);
        $this->createMasterAssessment($noAttempt['courseOffering']);
        $this->assertSame('not_started', SectionProgressEvaluator::evaluateEnrollment($noAttempt['enrollment'])['progress_status']);
    }

    public function test_hybrid_progress_requires_both_attendance_and_submission_evidence_to_be_satisfied(): void
    {
        $fullySatisfied = $this->createEnrollmentContext(CourseOffering::PROGRESS_BASIS_HYBRID);
        $this->createHeldAttendanceSessionWithRecord($fullySatisfied['enrollment'], 'present');
        $this->createSubmission($fullySatisfied['enrollment'], StudentSectionSubmission::STATUS_ACCEPTED, $this->createRequiredAssignment($fullySatisfied['courseOffering']));
        $this->assertSame('satisfied', SectionProgressEvaluator::evaluateEnrollment($fullySatisfied['enrollment'])['progress_status']);

        $attendanceOnly = $this->createEnrollmentContext(CourseOffering::PROGRESS_BASIS_HYBRID);
        $this->createHeldAttendanceSessionWithRecord($attendanceOnly['enrollment'], 'present');
        $this->createRequiredAssignment($attendanceOnly['courseOffering']);
        $this->assertSame('in_progress', SectionProgressEvaluator::evaluateEnrollment($attendanceOnly['enrollment'])['progress_status']);

        $submissionOnly = $this->createEnrollmentContext(CourseOffering::PROGRESS_BASIS_HYBRID);
        $this->createSubmission($submissionOnly['enrollment'], StudentSectionSubmission::STATUS_ACCEPTED, $this->createRequiredAssignment($submissionOnly['courseOffering']));
        $this->createHeldAttendanceSessionWithRecord($submissionOnly['enrollment'], 'absent');
        $this->assertSame('in_progress', SectionProgressEvaluator::evaluateEnrollment($submissionOnly['enrollment'])['progress_status']);
    }

    /**
     * @return array{institution: Institution, user: User, program: Program, course: Course, academicTerm: AcademicTerm, student: Student, courseOffering: CourseOffering, enrollment: CourseEnrollment}
     */
    protected function createEnrollmentContext(string $progressBasis): array
    {
        $institution = Institution::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Test Seminary '.Str::random(6),
            'slug' => 'test-seminary-'.Str::lower(Str::random(8)),
            'type' => 'seminary',
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'current_institution_id' => $institution->id,
            'email' => 'user-'.Str::lower(Str::random(8)).'@example.test',
        ]);
        $user->institutions()->attach($institution->id, ['role' => 'admin', 'status' => 'active']);

        $program = Program::query()->create([
            'institution_id' => $institution->id,
            'uuid' => (string) Str::uuid(),
            'code' => 'MDIV-'.Str::upper(Str::random(4)),
            'title' => 'Master of Divinity '.Str::random(4),
            'slug' => 'master-of-divinity-'.Str::lower(Str::random(8)),
            'status' => 'active',
            'is_public' => false,
        ]);

        $course = Course::query()->create([
            'institution_id' => $institution->id,
            'uuid' => (string) Str::uuid(),
            'code' => 'BIB-'.random_int(100, 999),
            'title' => 'Biblical Studies '.Str::random(4),
            'slug' => 'biblical-studies-'.Str::lower(Str::random(8)),
            'credit_hours' => '3.00',
            'status' => 'active',
            'is_public' => false,
        ]);

        $academicTerm = AcademicTerm::query()->create([
            'institution_id' => $institution->id,
            'uuid' => (string) Str::uuid(),
            'name' => 'Fall '.random_int(2026, 2035),
            'code' => 'FALL-'.Str::upper(Str::random(4)),
            'academic_year' => '2026-2027',
            'term_type' => 'semester',
            'start_date' => '2026-08-15',
            'end_date' => '2026-12-15',
            'status' => 'active',
        ]);

        $student = Student::query()->create([
            'institution_id' => $institution->id,
            'program_id' => $program->id,
            'uuid' => (string) Str::uuid(),
            'first_name' => 'Test',
            'last_name' => 'Student '.Str::random(4),
            'email' => 'student-'.Str::lower(Str::random(8)).'@example.test',
            'student_number' => 'S'.random_int(100000, 999999),
            'status' => 'active',
        ]);

        $courseOffering = CourseOffering::query()->create([
            'institution_id' => $institution->id,
            'uuid' => (string) Str::uuid(),
            'course_id' => $course->id,
            'academic_term_id' => $academicTerm->id,
            'section_code' => 'SEC'.Str::upper(Str::random(4)),
            'delivery_mode' => 'online',
            'status' => 'in_progress',
            'progress_basis' => $progressBasis,
        ]);

        $enrollment = CourseEnrollment::query()->create([
            'institution_id' => $institution->id,
            'uuid' => (string) Str::uuid(),
            'student_id' => $student->id,
            'course_id' => $course->id,
            'academic_term_id' => $academicTerm->id,
            'course_offering_id' => $courseOffering->id,
            'status' => 'enrolled',
            'enrolled_at' => Carbon::parse('2026-07-01 09:00:00'),
        ]);

        return compact('institution', 'user', 'program', 'course', 'academicTerm', 'student', 'courseOffering', 'enrollment');
    }

    protected function createGradeValue(Institution $institution): GradeValue
    {
        $gradeScale = GradeScale::query()->create([
            'institution_id' => $institution->id,
            'uuid' => (string) Str::uuid(),
            'name' => 'Standard Grade Scale',
            'is_active' => true,
        ]);

        return GradeValue::query()->create([
            'institution_id' => $institution->id,
            'uuid' => (string) Str::uuid(),
            'grade_scale_id' => $gradeScale->id,
            'grade' => 'A',
            'label' => 'Excellent',
            'grade_points' => '4.00',
            'earns_credit' => true,
            'affects_gpa' => true,
            'is_passing' => true,
            'sort_order' => 1,
        ]);
    }

    protected function createRequiredAssignment(CourseOffering $courseOffering): SectionAssignment
    {
        return SectionAssignment::query()->create([
            'institution_id' => $courseOffering->institution_id,
            'uuid' => (string) Str::uuid(),
            'course_offering_id' => $courseOffering->id,
            'title' => 'Required Assignment '.Str::random(4),
            'assignment_type' => SectionAssignment::TYPE_ASSIGNMENT,
            'requirement_basis' => SectionAssignment::REQUIREMENT_BASIS_COMPLETION,
            'is_required' => true,
            'sort_order' => 1,
            'status' => SectionAssignment::STATUS_ACTIVE,
        ]);
    }

    protected function createSubmission(CourseEnrollment $enrollment, string $status, ?SectionAssignment $assignment = null): StudentSectionSubmission
    {
        $assignment ??= $this->createRequiredAssignment($enrollment->courseOffering);

        return StudentSectionSubmission::query()->create([
            'institution_id' => $enrollment->institution_id,
            'uuid' => (string) Str::uuid(),
            'course_offering_id' => $enrollment->course_offering_id,
            'section_assignment_id' => $assignment->id,
            'course_enrollment_id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'status' => $status,
            'submitted_at' => in_array($status, [StudentSectionSubmission::STATUS_NOT_STARTED], true) ? null : Carbon::parse('2026-07-06 10:00:00'),
            'reviewed_at' => in_array($status, [StudentSectionSubmission::STATUS_ACCEPTED, StudentSectionSubmission::STATUS_REJECTED, StudentSectionSubmission::STATUS_REVISION_NEEDED], true) ? Carbon::parse('2026-07-06 12:00:00') : null,
        ]);
    }

    protected function createMasterAssessment(CourseOffering $courseOffering): MasterAssessment
    {
        return MasterAssessment::query()->create([
            'institution_id' => $courseOffering->institution_id,
            'uuid' => (string) Str::uuid(),
            'course_offering_id' => $courseOffering->id,
            'title' => 'Master Assessment '.Str::random(4),
            'status' => MasterAssessment::STATUS_ACTIVE,
        ]);
    }

    protected function createMasterAssessmentAttempt(CourseEnrollment $enrollment, string $status, ?MasterAssessment $assessment = null): StudentMasterAssessmentAttempt
    {
        $assessment ??= $this->createMasterAssessment($enrollment->courseOffering);

        return StudentMasterAssessmentAttempt::query()->create([
            'institution_id' => $enrollment->institution_id,
            'uuid' => (string) Str::uuid(),
            'master_assessment_id' => $assessment->id,
            'course_offering_id' => $enrollment->course_offering_id,
            'course_enrollment_id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'status' => $status,
            'submitted_at' => $status === StudentMasterAssessmentAttempt::STATUS_NOT_STARTED ? null : Carbon::parse('2026-07-06 09:00:00'),
            'assessed_at' => in_array($status, [StudentMasterAssessmentAttempt::STATUS_PASSED, StudentMasterAssessmentAttempt::STATUS_FAILED, StudentMasterAssessmentAttempt::STATUS_REVISION_NEEDED], true) ? Carbon::parse('2026-07-06 13:00:00') : null,
        ]);
    }

    protected function createHeldAttendanceSessionWithRecord(CourseEnrollment $enrollment, string $status): AttendanceRecord
    {
        $session = AttendanceSession::query()->create([
            'institution_id' => $enrollment->institution_id,
            'uuid' => (string) Str::uuid(),
            'course_offering_id' => $enrollment->course_offering_id,
            'academic_term_id' => $enrollment->academic_term_id,
            'course_id' => $enrollment->course_id,
            'session_date' => '2026-07-05',
            'status' => 'held',
        ]);

        return AttendanceRecord::query()->create([
            'institution_id' => $enrollment->institution_id,
            'uuid' => (string) Str::uuid(),
            'attendance_session_id' => $session->id,
            'course_offering_id' => $enrollment->course_offering_id,
            'course_enrollment_id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'status' => $status,
            'marked_at' => Carbon::parse('2026-07-05 11:00:00'),
        ]);
    }
}

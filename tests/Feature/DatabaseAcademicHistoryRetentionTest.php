<?php

namespace Tests\Feature;

use App\Models\AcademicRecord;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Institution;
use App\Models\OfficialTranscript;
use App\Models\OfficialTranscriptLine;
use App\Models\Student;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseAcademicHistoryRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_parent_deletion_cannot_cascade_through_durable_or_soft_deleted_history(): void
    {
        $history = $this->createHistory(softDeleted: true);

        foreach ([
            'institutions' => 'institution',
            'students' => 'student',
            'courses' => 'course',
            'course_enrollments' => 'enrollment',
        ] as $table => $key) {
            $this->assertDeleteRejected(
                fn () => DB::table($table)->where('id', $history[$key]->id)->delete(),
                'foreign key constraint',
            );
        }

        $this->assertSoftDeleted($history['record']);
        $this->assertSoftDeleted($history['transcript']);
        $this->assertSoftDeleted($history['line']);
    }

    public function test_legacy_completed_enrollment_survives_direct_and_indirect_deletes(): void
    {
        $history = $this->createHistory(transcriptStatus: 'draft', withLine: false, withRecord: false);
        DB::table('official_transcripts')->where('id', $history['transcript']->id)->delete();
        $history['enrollment']->update(['completed_at' => '2026-10-02']);

        foreach ([
            'institutions' => 'institution',
            'students' => 'student',
            'courses' => 'course',
            'course_enrollments' => 'enrollment',
        ] as $table => $key) {
            $this->assertDeleteRejected(
                fn () => DB::table($table)->where('id', $history[$key]->id)->delete(),
                "Academic history retention protects {$table}",
            );
        }

        $this->assertDeleteRejected(
            fn () => CourseEnrollment::query()->whereKey($history['enrollment']->id)->delete(),
            'Academic history retention protects course_enrollments',
        );
        $this->assertNotSoftDeleted($history['enrollment']);
        $this->assertDatabaseCount('academic_records', 0);
    }

    public function test_mass_hard_and_soft_deletes_cannot_erase_or_hide_current_history(): void
    {
        $history = $this->createHistory();

        foreach (['academic_records' => 'record', 'official_transcripts' => 'transcript', 'official_transcript_lines' => 'line'] as $table => $key) {
            $model = $history[$key];
            $message = "Academic history retention protects {$table}";

            $this->assertDeleteRejected(fn () => DB::table($table)->where('id', $model->id)->delete(), $message);
            $this->assertDeleteRejected(fn () => $model->newQuery()->whereKey($model->id)->forceDelete(), $message);
            $this->assertDeleteRejected(fn () => $model->newQuery()->whereKey($model->id)->delete(), $message);

            $this->assertNotSoftDeleted($model);
        }
    }

    public function test_issued_transcript_without_lines_is_protected_even_when_saved_without_an_issue_date(): void
    {
        $history = $this->createHistory(transcriptStatus: 'issued', withLine: false);

        $this->assertDeleteRejected(
            fn () => DB::table('official_transcripts')->where('id', $history['transcript']->id)->delete(),
            'Academic history retention protects official_transcripts',
        );
    }

    public function test_nullable_term_links_clear_without_erasing_snapshots(): void
    {
        $history = $this->createHistory();
        $this->assertSame(1, DB::table('academic_terms')->where('id', $history['term']->id)->delete());

        $this->assertNull($history['record']->fresh()->academic_term_id);
        $this->assertNull($history['enrollment']->fresh()->academic_term_id);
        $this->assertNull($history['line']->fresh()->academic_term_id);
        $this->assertSame('Fall 2026', $history['line']->fresh()->term_label);
        $this->assertSame('BIB101', $history['record']->fresh()->course_code);
    }

    public function test_operational_deletion_and_draft_snapshot_replacement_remain_available(): void
    {
        $history = $this->createHistory(transcriptStatus: 'draft', withRecord: false);

        $this->assertSame(1, $history['transcript']->lines()->delete());
        $this->assertSoftDeleted($history['line']);
        $this->assertDeleteRejected(
            fn () => DB::table('official_transcripts')->where('id', $history['transcript']->id)->delete(),
            'foreign key constraint',
        );
        $this->assertSame(1, DB::table('course_enrollments')->where('id', $history['enrollment']->id)->delete());

        $draftWithoutLines = OfficialTranscript::create([
            'institution_id' => $history['institution']->id, 'student_id' => $history['student']->id,
            'transcript_number' => 'DRAFT-2', 'status' => 'draft',
        ]);
        $this->assertSame(1, DB::table('official_transcripts')->where('id', $draftWithoutLines->id)->delete());

        $empty = Institution::create(['name' => 'Operational Seminary', 'slug' => 'operational', 'type' => 'seminary']);
        $student = Student::create([
            'institution_id' => $empty->id, 'first_name' => 'New', 'last_name' => 'Student',
            'email' => 'new@example.test', 'student_number' => 'S-NEW',
        ]);
        $this->assertSame(1, DB::table('institutions')->where('id', $empty->id)->delete());
        $this->assertModelMissing($student);
    }

    private function assertDeleteRejected(callable $delete, string $message): void
    {
        try {
            $delete();
            $this->fail('Expected the database to reject the deletion.');
        } catch (QueryException $exception) {
            $this->assertStringContainsStringIgnoringCase($message, $exception->getMessage());
        }
    }

    private function createHistory(
        bool $softDeleted = false,
        string $transcriptStatus = 'issued',
        bool $withLine = true,
        bool $withRecord = true,
    ): array {
        $institution = Institution::create(['name' => 'Retention Seminary', 'slug' => 'retention', 'type' => 'seminary']);
        $student = Student::create([
            'institution_id' => $institution->id, 'first_name' => 'Ada', 'last_name' => 'Student',
            'email' => 'ada@example.test', 'student_number' => 'S1',
        ]);
        $course = Course::create(['institution_id' => $institution->id, 'code' => 'BIB101', 'title' => 'Bible', 'slug' => 'bible']);
        $term = AcademicTerm::create([
            'institution_id' => $institution->id, 'name' => 'Fall', 'code' => 'F26', 'academic_year' => '2026-2027',
            'term_type' => 'fall', 'start_date' => '2026-08-01', 'end_date' => '2026-12-31',
        ]);
        $associations = [
            'institution_id' => $institution->id, 'student_id' => $student->id,
            'course_id' => $course->id, 'academic_term_id' => $term->id,
        ];
        $enrollment = CourseEnrollment::create($associations + ['status' => $withRecord ? 'completed' : 'enrolled']);
        $retentionState = $softDeleted ? ['deleted_at' => now()] : [];
        $record = $withRecord ? AcademicRecord::forceCreate($retentionState + $associations + [
            'course_enrollment_id' => $enrollment->id, 'course_code' => $course->code, 'course_title' => $course->title,
            'status' => 'completed',
        ]) : null;

        $transcript = OfficialTranscript::forceCreate($retentionState + [
            'institution_id' => $institution->id, 'student_id' => $student->id,
            'transcript_number' => 'OT-RETENTION', 'status' => $transcriptStatus,
        ]);
        $line = $withLine ? OfficialTranscriptLine::forceCreate($retentionState + [
            'institution_id' => $institution->id, 'student_id' => $student->id,
            'official_transcript_id' => $transcript->id, 'academic_record_id' => $record?->id,
            'academic_term_id' => $term->id, 'course_code' => $course->code, 'course_title' => $course->title,
            'status' => 'completed', 'term_label' => 'Fall 2026',
        ]) : null;

        return compact('institution', 'student', 'course', 'term', 'enrollment', 'record', 'transcript', 'line');
    }
}

<?php

namespace Tests\Feature;

use App\Filament\Resources\OfficialTranscripts\Support\OfficialTranscriptView;
use App\Models\AcademicRecord;
use App\Models\Course;
use App\Models\Institution;
use App\Models\OfficialTranscript;
use App\Models\OfficialTranscriptLine;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

class TranscriptEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_issued_transcript_view_reads_snapshot_lines_after_academic_record_changes(): void
    {
        $institution = Institution::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Transcript Seminary',
            'slug' => 'transcript-'.Str::lower(Str::random(8)),
            'type' => 'seminary',
            'status' => 'active',
        ]);
        $student = Student::create([
            'institution_id' => $institution->id,
            'uuid' => (string) Str::uuid(),
            'first_name' => 'Ada',
            'last_name' => 'Student',
            'email' => 'ada-'.Str::lower(Str::random(8)).'@example.test',
            'student_number' => 'T'.random_int(100000, 999999),
            'status' => 'active',
        ]);
        $record = AcademicRecord::create([
            'institution_id' => $institution->id,
            'uuid' => (string) Str::uuid(),
            'student_id' => $student->id,
            'course_id' => $this->course($institution)->id,
            'course_code' => 'BIB-101',
            'course_title' => 'Biblical Studies',
            'final_grade' => 'A',
            'grade_points' => '4.00',
            'status' => 'completed',
            'completed_at' => '2026-05-01',
        ]);
        $transcript = OfficialTranscript::create([
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'transcript_number' => 'OT-TEST-1',
            'status' => 'issued',
            'issued_at' => '2026-05-02 10:00:00',
        ]);
        OfficialTranscriptLine::create([
            'institution_id' => $institution->id,
            'official_transcript_id' => $transcript->id,
            'academic_record_id' => $record->id,
            'student_id' => $student->id,
            'term_label' => 'Spring 2026 (2025–2026)',
            'course_code' => 'BIB-101',
            'course_title' => 'Biblical Studies',
            'final_grade' => 'A',
            'grade_points' => '4.00',
            'status' => 'completed',
            'completed_at' => '2026-05-01',
            'sort_order' => 1,
        ]);

        $record->update(['course_code' => 'BIB-999', 'course_title' => 'Renamed', 'final_grade' => 'B']);

        $method = new ReflectionMethod(OfficialTranscriptView::class, 'getViewData');
        $method->setAccessible(true);
        $viewData = $method->invoke(null, $transcript->fresh());
        $line = $viewData['termGroups']->first()['lines']->first();

        $this->assertSame('BIB-101', $line->course_code);
        $this->assertSame('Biblical Studies', $line->course_title);
        $this->assertSame('A', $line->final_grade);
        $this->assertSame('Spring 2026 (2025–2026)', $line->term_label);
    }

    public function test_transcript_status_options_are_centralized(): void
    {
        $this->assertSame('Issued', OfficialTranscript::statusOptions()['issued']);
        $this->assertSame('Voided', OfficialTranscript::statusOptions()['voided']);
    }

    private function course(Institution $institution): Course
    {
        return Course::create([
            'institution_id' => $institution->id,
            'uuid' => (string) Str::uuid(),
            'code' => 'BIB-101',
            'title' => 'Biblical Studies',
            'slug' => 'biblical-studies-'.Str::lower(Str::random(6)),
            'status' => 'active',
        ]);
    }
}

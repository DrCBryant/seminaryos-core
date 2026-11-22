<?php

namespace Tests\Feature;

use App\Filament\Resources\OfficialTranscripts\Pages\CreateOfficialTranscript;
use App\Filament\Resources\OfficialTranscripts\Pages\EditOfficialTranscript;
use App\Filament\Resources\OfficialTranscripts\Pages\ListOfficialTranscripts;
use App\Filament\Resources\OfficialTranscripts\Support\OfficialTranscriptView;
use App\Filament\Resources\OfficialTranscripts\Tables\OfficialTranscriptsTable;
use App\Filament\Resources\Students\Pages\EditStudent;
use App\Filament\Resources\Students\Support\GpaPreview;
use App\Models\AcademicRecord;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\DegreeConferral;
use App\Models\Institution;
use App\Models\OfficialTranscript;
use App\Models\OfficialTranscriptLine;
use App\Models\Program;
use App\Models\Student;
use App\Models\TranscriptSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use ReflectionMethod;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OfficialTranscriptIssuanceTest extends TestCase
{
    use RefreshDatabase;

    private Institution $institution;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institution = $this->institution('home');
        $this->student = $this->student($this->institution);
        $user = User::factory()->create(['current_institution_id' => $this->institution->id]);
        $user->institutions()->attach($this->institution, ['role' => 'admin', 'status' => 'active']);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_ordinary_pages_can_save_drafts_but_cannot_finalize_them(): void
    {
        $data = [
            'institution_id' => $this->institution->id,
            'student_id' => $this->student->id,
            'transcript_number' => 'FORM',
        ];

        Livewire::test(CreateOfficialTranscript::class)
            ->fillForm($data + ['status' => 'issued'])
            ->call('create')->assertHasFormErrors(['status']);
        $this->assertDatabaseCount('official_transcripts', 0);

        Livewire::test(CreateOfficialTranscript::class)
            ->fillForm($data + ['status' => 'draft'])
            ->call('create')->assertHasNoFormErrors();
        $draft = OfficialTranscript::query()->sole();

        Livewire::test(EditOfficialTranscript::class, ['record' => $draft->getRouteKey()])
            ->fillForm(['status' => 'issued'])
            ->call('save')->assertHasFormErrors(['status']);

        $this->assertSame('draft', $draft->fresh()->status);
        $this->assertDatabaseCount('official_transcript_lines', 0);
    }

    public function test_issue_action_creates_all_lines_and_keeps_them_on_retry(): void
    {
        $first = $this->academicRecord('BIB-101');
        $second = $this->academicRecord('BIB-102');
        $draft = $this->transcript();

        Livewire::test(ListOfficialTranscripts::class)
            ->callTableAction('issueTranscript', $draft, $this->issueData())
            ->assertHasNoTableActionErrors();

        $issued = $draft->fresh();
        $this->assertSame('issued', $issued->status);
        $this->assertSame('FINAL', $issued->transcript_number);
        $this->assertSame([$first->id, $second->id], $issued->lines()->pluck('academic_record_id')->all());
        $lines = $issued->lines()->pluck('id')->all();
        $document = $issued->document_snapshot;

        $first->update(['final_grade' => 'B']);
        $this->assertFalse($this->issue($draft, ['transcript_number' => 'RETRY'] + $this->issueData()));

        $this->assertSame('FINAL', $draft->fresh()->transcript_number);
        $this->assertSame($document, $draft->fresh()->document_snapshot);
        $this->assertSame($lines, $issued->lines()->pluck('id')->all());
        $this->assertSame('A', $issued->lines()->first()->final_grade);
        $this->assertDatabaseCount('official_transcript_lines', 2);
    }

    public function test_unresolved_coursework_leaves_the_draft_and_lines_untouched(): void
    {
        $this->academicRecord('BIB-101');
        $this->academicRecord('BIB-102', 'completion_date');
        $draft = $this->transcript();

        Livewire::test(ListOfficialTranscripts::class)
            ->callTableAction('issueTranscript', $draft, $this->issueData());

        $this->assertSame('draft', $draft->fresh()->status);
        $this->assertNull($draft->fresh()->issued_at);
        $this->assertNull($draft->fresh()->document_snapshot);
        $this->assertDatabaseCount('official_transcript_lines', 0);
    }

    public function test_failure_after_the_first_line_rolls_back_all_writes(): void
    {
        $this->academicRecord('BIB-101');
        $this->academicRecord('BIB-102');
        $draft = $this->transcript();
        OfficialTranscriptLine::creating(function (OfficialTranscriptLine $line): void {
            if ($line->sort_order === 2) {
                throw new \RuntimeException('Simulated snapshot write failure');
            }
        });

        try {
            $this->issue($draft, $this->issueData());
            $this->fail('Expected the snapshot write to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated snapshot write failure', $exception->getMessage());
        }

        $this->assertSame('draft', $draft->fresh()->status);
        $this->assertNull($draft->fresh()->document_snapshot);
        $this->assertDatabaseCount('official_transcript_lines', 0);
    }

    public function test_competing_mounted_issue_requests_and_an_open_edit_preserve_first_issuance(): void
    {
        $this->academicRecord('BIB-101');
        $draft = $this->transcript();
        $first = Livewire::test(ListOfficialTranscripts::class)->mountTableAction('issueTranscript', $draft);
        $second = Livewire::test(ListOfficialTranscripts::class)->mountTableAction('issueTranscript', $draft);
        $edit = Livewire::test(EditOfficialTranscript::class, ['record' => $draft->getRouteKey()]);

        $first->setTableActionData($this->issueData())->callMountedTableAction();
        $line = $draft->lines()->sole();
        $second->setTableActionData(['transcript_number' => 'SECOND'] + $this->issueData())
            ->callMountedTableAction();
        $edit->fillForm(['status' => 'archived', 'transcript_number' => 'STALE'])->call('save');

        $this->assertSame('issued', $draft->fresh()->status);
        $this->assertSame('FINAL', $draft->fresh()->transcript_number);
        $this->assertSame($line->id, $draft->lines()->sole()->id);
        $this->assertDatabaseCount('official_transcript_lines', 1);
    }

    public function test_issued_notes_can_be_edited_without_altering_the_snapshot(): void
    {
        $this->academicRecord('BIB-101');
        $draft = $this->transcript();
        $this->assertTrue($this->issue($draft, $this->issueData()));
        $line = $draft->lines()->sole();

        Livewire::test(EditOfficialTranscript::class, ['record' => $draft->getRouteKey()])
            ->fillForm(['internal_notes' => 'Registrar follow-up'])
            ->call('save')->assertHasNoFormErrors();

        $this->assertSame('Registrar follow-up', $draft->fresh()->internal_notes);
        $this->assertSame('issued', $draft->fresh()->status);
        $this->assertSame($line->id, $draft->lines()->sole()->id);
    }

    public function test_pending_delete_does_not_hide_a_transcript_that_has_since_been_issued(): void
    {
        $this->academicRecord('BIB-101');
        $draft = $this->transcript();
        $edit = Livewire::test(EditOfficialTranscript::class, ['record' => $draft->getRouteKey()])
            ->mountAction('delete');

        $this->assertTrue($this->issue($draft, $this->issueData()));
        $edit->callMountedAction();

        $this->assertNotSoftDeleted($draft);
        $this->assertSame('issued', $draft->fresh()->status);
        $this->assertDatabaseCount('official_transcript_lines', 1);
    }

    public function test_another_transcript_cannot_claim_the_same_number(): void
    {
        $this->academicRecord('BIB-101');
        $first = $this->transcript();
        $second = $this->transcript(number: 'SECOND');

        $this->assertTrue($this->issue($first, $this->issueData()));
        $this->assertFalse($this->issue($second, $this->issueData()));

        $this->assertSame('FINAL', $first->fresh()->transcript_number);
        $this->assertSame('draft', $second->fresh()->status);
        $this->assertSame('SECOND', $second->fresh()->transcript_number);
        $this->assertDatabaseCount('official_transcript_lines', 1);
    }

    public function test_issuance_does_not_resolve_a_transcript_from_another_institution(): void
    {
        $otherStudent = $this->student($this->institution('other'));
        $other = $this->transcript($otherStudent);

        Livewire::test(ListOfficialTranscripts::class)->assertCanNotSeeTableRecords([$other]);

        $this->expectException(ModelNotFoundException::class);
        $this->issue($other, $this->issueData());
    }

    public function test_issuance_requires_existing_record_update_permission(): void
    {
        $draft = $this->transcript();
        Gate::policy(OfficialTranscript::class, DenyTranscriptUpdatePolicy::class);

        try {
            $this->issue($draft, $this->issueData());
            $this->fail('Expected a forbidden response.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertSame('draft', $draft->fresh()->status);
    }

    public function test_missing_lines_filter_is_read_only_and_institution_scoped(): void
    {
        $this->academicRecord('BIB-101');
        $complete = $this->transcript(number: 'COMPLETE');
        $this->assertTrue($this->issue($complete, $this->issueData()));
        $missing = $this->transcript(number: 'MISSING', status: 'issued');
        $draft = $this->transcript(number: 'DRAFT');
        $foreign = $this->transcript($this->student($this->institution('other')), status: 'issued');

        Livewire::test(ListOfficialTranscripts::class)
            ->filterTable('issued_without_lines')
            ->assertCanSeeTableRecords([$missing])
            ->assertCanNotSeeTableRecords([$complete, $draft, $foreign]);

        $this->assertSame('issued', $missing->fresh()->status);
        $this->assertDatabaseCount('official_transcript_lines', 1);
    }

    public function test_issued_view_preserves_identity_settings_and_academic_content_after_source_edits(): void
    {
        $program = $this->program($this->institution);
        $this->student->update(['program_id' => $program->id, 'enrollment_date' => '2025-09-01']);
        $setting = TranscriptSetting::create([
            'institution_id' => $this->institution->id, 'transcript_title' => 'Captured Transcript Title',
            'registrar_name' => 'Original Registrar', 'certification_statement' => 'Original certification.',
            'show_recipient_info' => false, 'show_grade_points' => true, 'is_active' => true,
        ]);
        $record = $this->academicRecord('BIB-101');
        $record->update(['credits_attempted' => 3, 'credits_earned' => 3, 'grade_points' => 4, 'affects_gpa' => true]);

        DegreeConferral::create([
            'institution_id' => $this->institution->id,
            'student_id' => $this->student->id, 'program_id' => $program->id,
            'program_code' => 'AWARD', 'program_title' => 'Unapproved Transcript Award',
            'credential_type' => 'M.Div.', 'conferred_on' => '2026-04-30', 'conferred_by_user_id' => auth()->id(),
            'decided_at' => now(), 'eligibility_snapshot' => [],
        ]);

        $draft = $this->transcript();
        $this->assertTrue($this->issue($draft, ['recipient_name' => 'Private Recipient'] + $this->issueData()));
        $before = $this->render($draft);
        $captured = $draft->fresh()->document_snapshot;

        foreach (['Home Seminary', 'Ada Student', 'Original Program', 'Captured Transcript Title', 'Original Registrar', 'Original certification.', 'Sep 1, 2025'] as $text) {
            $this->assertStringContainsString($text, $before);
        }

        $this->assertStringNotContainsString('Private Recipient', $before);
        $this->assertStringNotContainsString('Unapproved Transcript Award', $before);
        $this->assertSame([], $captured['awards']);
        $this->assertSame('M.Div.', $captured['program']['credential_type']);

        $record->update(['final_grade' => 'C', 'grade_points' => 2, 'affects_gpa' => false]);
        $this->student->update(['first_name' => 'Renamed', 'student_number' => 'S2', 'enrollment_date' => '2026-01-01']);
        $this->institution->update(['name' => 'Renamed Seminary']);
        $program->update(['title' => 'New Program', 'credential_type' => 'Changed']);
        $setting->update(['transcript_title' => 'New Title', 'show_recipient_info' => true]);

        $this->assertSame($before, $this->render($draft));
        $this->assertSame($captured, $draft->fresh()->document_snapshot);
    }

    public function test_renderer_can_display_award_data_already_captured_in_a_document_snapshot(): void
    {
        $program = $this->program($this->institution);
        $this->student->update(['program_id' => $program->id]);
        $award = DegreeConferral::create([
            'institution_id' => $this->institution->id,
            'student_id' => $this->student->id, 'program_id' => $program->id,
            'program_code' => 'AWARD', 'program_title' => 'Captured Award Name',
            'credential_type' => 'M.Div.', 'conferred_on' => '2026-04-30', 'conferred_by_user_id' => auth()->id(),
            'decided_at' => now(), 'eligibility_snapshot' => [],
        ]);
        $snapshot = OfficialTranscriptView::captureSnapshot($this->institution, $this->student, $program, 'printed', collect());
        $snapshot['awards'] = [[
            'degree_conferral_id' => $award->id,
            ...$award->only(['program_code', 'program_title', 'credential_type']),
            'conferred_on' => $award->conferred_on->toDateString(),
        ]];
        $transcript = OfficialTranscript::forceCreate([
            'institution_id' => $this->institution->id, 'student_id' => $this->student->id,
            'transcript_number' => 'AWARD-EXAMPLE', 'status' => 'issued', 'document_snapshot' => $snapshot,
        ]);

        $before = $this->render($transcript);
        $this->assertStringContainsString('Captured Award Name (AWARD)', $before);
        $this->assertStringContainsString('2026-04-30', $before);

        $program->update(['title' => 'Changed Catalog Title', 'credential_type' => 'Revised']);
        $this->assertSame($before, $this->render($transcript));
    }

    public function test_same_label_terms_stay_distinct_and_gpa_remains_reproducible(): void
    {
        $earlier = $this->term($this->institution, 'EARLY', '2025-09-01');
        $later = $this->term($this->institution, 'LATE', '2026-01-01');
        $first = $this->academicRecord('Z-101');
        $first->update(['academic_term_id' => $earlier->id, 'affects_gpa' => true, 'credits_attempted' => 3, 'grade_points' => 4]);
        $second = $this->academicRecord('A-101');
        $second->update(['academic_term_id' => $later->id, 'affects_gpa' => true, 'credits_attempted' => 1, 'grade_points' => 2]);
        $third = $this->academicRecord('A-102');
        $third->update(['academic_term_id' => $later->id, 'affects_gpa' => true, 'credits_attempted' => 1]);
        $fourth = $this->academicRecord('A-103');
        $fourth->update(['academic_term_id' => $later->id, 'affects_gpa' => false, 'credits_attempted' => 1, 'grade_points' => 1]);

        $draft = $this->transcript();
        $this->assertTrue($this->issue($draft, $this->issueData()));
        $before = $this->render($draft);
        $snapshot = $draft->fresh()->document_snapshot;

        $this->assertSame(3.5, $snapshot['gpa']['cumulative']['gpa']);
        $this->assertSame(1, $snapshot['gpa']['cumulative']['incomplete_records']);
        $this->assertEquals(4, $snapshot['gpa']['terms'][$earlier->id]['gpa']);
        $this->assertEquals(2, $snapshot['gpa']['terms'][$later->id]['gpa']);
        $this->assertEquals($snapshot['gpa']['cumulative'], GpaPreview::summarize($draft->lines()->get()));

        $first->update(['grade_points' => 0, 'affects_gpa' => false]);
        $later->update(['name' => 'Different term label']);
        DB::table('academic_terms')->whereIn('id', [$earlier->id, $later->id])->delete();

        $data = $this->viewData($draft);
        $this->assertCount(2, $data['termGroups']);
        $this->assertSame($data['termGroups'][0]['label'], $data['termGroups'][1]['label']);
        $this->assertSame(['Z-101', 'A-101'], $data['termGroups']->map(fn (array $group) => $group['lines']->first()->course_code)->all());
        $this->assertSame([$earlier->id, $later->id, $later->id, $later->id], $draft->lines()->pluck('term_snapshot_id')->all());
        $this->assertSame($before, $this->render($draft));
        $this->assertSame($snapshot, $draft->fresh()->document_snapshot);
    }

    public function test_failure_saving_the_document_rolls_back_previously_inserted_lines(): void
    {
        $this->academicRecord('BIB-101');
        $draft = $this->transcript();
        OfficialTranscript::updated(function (OfficialTranscript $record): void {
            if ($record->wasChanged('document_snapshot')) {
                throw new \RuntimeException('Simulated document snapshot failure');
            }
        });

        try {
            $this->issue($draft, $this->issueData());
            $this->fail('Expected the issuance transaction to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated document snapshot failure', $exception->getMessage());
        }

        $this->assertSame('draft', $draft->fresh()->status);
        $this->assertNull($draft->fresh()->issued_at);
        $this->assertNull($draft->fresh()->document_snapshot);
        $this->assertDatabaseCount('official_transcript_lines', 0);
    }

    public function test_document_and_line_values_are_immutable_through_query_builder_writes(): void
    {
        $this->academicRecord('BIB-101');
        $draft = $this->transcript();
        $this->assertTrue($this->issue($draft, $this->issueData()));
        $line = $draft->lines()->sole();
        $duplicate = $line->getAttributes();
        unset($duplicate['id']);
        $duplicate['uuid'] = (string) str()->uuid();

        foreach ([
            fn () => DB::table('official_transcripts')->where('id', $draft->id)->update(['transcript_number' => 'final']),
            fn () => DB::table('official_transcript_lines')->where('id', $line->id)->update(['affects_gpa' => true]),
            fn () => DB::table('official_transcript_lines')->insert($duplicate),
        ] as $write) {
            try {
                $write();
                $this->fail('An issued snapshot write should have been rejected.');
            } catch (QueryException $exception) {
                $this->assertStringContainsString('document snapshot is immutable', $exception->getMessage());
            }
        }

        $draft->fresh()->update(['registrar_notes' => 'Private administrative follow-up']);
        $this->assertSame('Private administrative follow-up', $draft->fresh()->registrar_notes);
        $this->assertSame('FINAL', $draft->fresh()->transcript_number);
    }

    public function test_cross_institution_program_and_reporting_term_cannot_enter_the_document(): void
    {
        $foreign = $this->institution('foreign');
        $this->student->update(['program_id' => $this->program($foreign)->id]);
        $record = $this->academicRecord('BIB-101');
        $draft = $this->transcript();
        $this->assertFalse($this->issue($draft, $this->issueData()));

        $this->student->update(['program_id' => null]);
        $record->update(['academic_term_id' => $this->term($foreign, 'OTHER', '2026-01-01')->id]);
        $this->assertFalse($this->issue($draft, $this->issueData()));
        $this->assertNull($draft->fresh()->document_snapshot);
        $this->assertDatabaseCount('official_transcript_lines', 0);
    }

    public function test_legacy_view_uses_surviving_fields_and_student_previews_remain_live(): void
    {
        $record = $this->academicRecord('BIB-101');
        $record->update(['affects_gpa' => true, 'credits_attempted' => 3, 'grade_points' => null]);
        $legacy = $this->transcript(number: 'LEGACY', status: 'issued');
        $before = $legacy->fresh()->getAttributes();

        $this->assertStringContainsString('Legacy issued transcript', $this->render($legacy));
        $this->assertStringContainsString('LEGACY', $this->render($legacy));
        $this->assertStringNotContainsString('Ada Student', $this->render($legacy));
        $this->assertStringNotContainsString('Home Seminary', $this->render($legacy));
        $this->assertSame($before, $legacy->fresh()->getAttributes());
        $this->assertNull($legacy->fresh()->document_snapshot);
        $this->assertFalse($this->issue($legacy, $this->issueData()));

        Livewire::test(ListOfficialTranscripts::class)
            ->filterTable('issued_without_document_snapshot')->assertCanSeeTableRecords([$legacy]);

        $this->student->update(['first_name' => 'Updated']);
        $component = Livewire::test(EditStudent::class, ['record' => $this->student->id]);
        $component->mountAction('viewTranscriptPreview');
        $this->assertStringContainsString('Updated Student', $component->instance()->getMountedAction()->getModalContent()->render());
        $component->unmountAction()->mountAction('viewGpaPreview');
        $this->assertStringContainsString('Grade points are missing', $component->instance()->getMountedAction()->getModalContent()->render());
    }

    private function viewData(OfficialTranscript $transcript): array
    {
        return (new ReflectionMethod(OfficialTranscriptView::class, 'getViewData'))->invoke(null, $transcript->fresh());
    }

    private function render(OfficialTranscript $transcript): string
    {
        return view('filament.official-transcripts.view', $this->viewData($transcript))->render();
    }

    private function program(Institution $institution): Program
    {
        return Program::create([
            'institution_id' => $institution->id, 'code' => 'MDIV', 'title' => 'Original Program',
            'slug' => 'original-program', 'credential_type' => 'M.Div.',
        ]);
    }

    private function term(Institution $institution, string $code, string $date): AcademicTerm
    {
        return AcademicTerm::create([
            'institution_id' => $institution->id, 'name' => 'Shared Label', 'code' => $code, 'academic_year' => '2025-2026',
            'term_type' => 'custom', 'start_date' => $date, 'end_date' => $date,
        ]);
    }

    private function issue(OfficialTranscript $transcript, array $data): bool
    {
        return (new ReflectionMethod(OfficialTranscriptsTable::class, 'issue'))->invoke(null, $transcript->id, $data);
    }

    private function issueData(): array
    {
        return ['transcript_number' => 'FINAL', 'issued_at' => '2026-05-03 10:00:00', 'delivery_method' => 'printed'];
    }

    private function institution(string $slug): Institution
    {
        return Institution::create(['name' => ucfirst($slug).' Seminary', 'slug' => $slug, 'type' => 'seminary', 'status' => 'active']);
    }

    private function student(Institution $institution): Student
    {
        return Student::create([
            'institution_id' => $institution->id, 'first_name' => 'Ada', 'last_name' => 'Student',
            'email' => 'ada@example.test', 'student_number' => 'S1',
        ]);
    }

    private function transcript(?Student $student = null, string $number = 'REQUEST', string $status = 'draft'): OfficialTranscript
    {
        $student ??= $this->student;

        return OfficialTranscript::create([
            'institution_id' => $student->institution_id, 'student_id' => $student->id,
            'transcript_number' => $number, 'status' => $status,
        ]);
    }

    private function academicRecord(string $code, string $schedulingBasis = 'term'): AcademicRecord
    {
        $course = Course::create([
            'institution_id' => $this->institution->id, 'code' => $code, 'title' => $code,
            'slug' => strtolower($code), 'scheduling_basis' => $schedulingBasis,
        ]);

        return AcademicRecord::create([
            'institution_id' => $this->institution->id, 'student_id' => $this->student->id,
            'course_id' => $course->id, 'course_code' => $code, 'course_title' => $code,
            'final_grade' => 'A', 'status' => 'completed', 'completed_at' => '2026-05-01',
        ]);
    }
}

class DenyTranscriptUpdatePolicy
{
    public function update(User $user, OfficialTranscript $transcript): bool
    {
        return false;
    }
}

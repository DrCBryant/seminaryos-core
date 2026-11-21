<?php

namespace Tests\Feature;

use App\Filament\Resources\OfficialTranscripts\Pages\CreateOfficialTranscript;
use App\Filament\Resources\OfficialTranscripts\Pages\EditOfficialTranscript;
use App\Filament\Resources\OfficialTranscripts\Pages\ListOfficialTranscripts;
use App\Filament\Resources\OfficialTranscripts\Tables\OfficialTranscriptsTable;
use App\Models\AcademicRecord;
use App\Models\Course;
use App\Models\Institution;
use App\Models\OfficialTranscript;
use App\Models\OfficialTranscriptLine;
use App\Models\Student;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $first->update(['final_grade' => 'B']);
        $this->assertFalse($this->issue($draft, ['transcript_number' => 'RETRY'] + $this->issueData()));

        $this->assertSame('FINAL', $draft->fresh()->transcript_number);
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

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $sqlite = DB::getDriverName() === 'sqlite';

        Schema::create('mentor_assessment_results', function (Blueprint $table): void {
            $table->foreignId('attempt_id')->primary()->constrained('student_master_assessment_attempts')->restrictOnDelete();
            $table->foreignId('mentor_faculty_id')->constrained('faculty')->restrictOnDelete();
            $table->foreignId('decided_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at');
            $table->string('status', 50);
            $table->text('feedback')->nullable();
        });

        foreach (['UPDATE', 'DELETE'] as $event) {
            $name = 'retain_mentor_assessment_result_'.strtolower($event);
            $message = 'A finalized mentor assessment result is permanent';
            $body = $sqlite
                ? "BEGIN SELECT RAISE(ABORT, '{$message}'); END"
                : "FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'";
            DB::unprepared("CREATE TRIGGER {$name} BEFORE {$event} ON mentor_assessment_results {$body}");
        }

        $fields = ['status', 'institution_id', 'student_id', 'master_assessment_id', 'course_offering_id'];
        $evidence = ['assessed_at', 'assessor_user_id', 'assessor_notes', 'submitted_at', 'notes'];
        $changed = fn (array $columns): string => implode(' OR ', array_map(fn (string $column): string => $sqlite
            ? "NEW.{$column} IS NOT OLD.{$column}"
            : "NOT (BINARY NEW.{$column} <=> BINARY OLD.{$column})", $columns));
        $hasResult = 'EXISTS (SELECT 1 FROM mentor_assessment_results WHERE attempt_id = OLD.id)';
        $hasMastery = 'EXISTS (SELECT 1 FROM competency_mastery_decisions WHERE attempt_id = OLD.id)';
        $blocked = "(($hasResult OR $hasMastery) AND ({$changed($fields)})) OR ($hasResult AND ({$changed($evidence)}))";
        $message = 'A finalized assessment attempt cannot be changed';
        $body = $sqlite
            ? "WHEN {$blocked} BEGIN SELECT RAISE(ABORT, '{$message}'); END"
            : "FOR EACH ROW BEGIN IF {$blocked} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'; END IF; END";
        DB::unprepared("CREATE TRIGGER retain_finalized_mentor_attempt BEFORE UPDATE ON student_master_assessment_attempts {$body}");
    }

    public function down(): void
    {
        if (DB::table('mentor_assessment_results')->exists()) {
            throw new RuntimeException('Preserve finalized mentor assessment results and use a forward migration.');
        }

        DB::unprepared('DROP TRIGGER IF EXISTS retain_finalized_mentor_attempt');
        Schema::drop('mentor_assessment_results');
    }
};

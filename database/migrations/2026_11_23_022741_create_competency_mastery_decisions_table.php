<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_master_assessment_attempts', fn (Blueprint $table) => $table
            ->unique(['id', 'institution_id', 'student_id', 'master_assessment_id'], 'attempts_mastery_identity'));
        Schema::table('programs', fn (Blueprint $table) => $table->unique(['id', 'institution_id'], 'programs_mastery_identity'));

        Schema::create('competency_mastery_decisions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('program_id');
            $table->unsignedBigInteger('master_assessment_id');
            $table->unsignedBigInteger('attempt_id')->unique();
            $table->unsignedBigInteger('mentor_faculty_id');
            $table->foreignId('decided_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at');
            $table->json('snapshot');
            $table->index(['student_id', 'program_id']);
            $table->foreign(['student_id', 'institution_id'], 'mastery_student_fk')
                ->references(['id', 'institution_id'])->on('students')->restrictOnDelete();
            $table->foreign(['program_id', 'institution_id'], 'mastery_program_fk')
                ->references(['id', 'institution_id'])->on('programs')->restrictOnDelete();
            $table->foreign(['mentor_faculty_id', 'institution_id'], 'mastery_mentor_fk')
                ->references(['id', 'institution_id'])->on('faculty')->restrictOnDelete();
            $table->foreign(['master_assessment_id', 'institution_id'], 'mastery_assessment_fk')
                ->references(['id', 'institution_id'])->on('master_assessments')->restrictOnDelete();
            $table->foreign(['attempt_id', 'institution_id', 'student_id', 'master_assessment_id'], 'mastery_attempt_fk')
                ->references(['id', 'institution_id', 'student_id', 'master_assessment_id'])
                ->on('student_master_assessment_attempts')->restrictOnDelete();
        });

        foreach (['UPDATE', 'DELETE'] as $event) {
            $trigger = 'mastery_retain_'.strtolower($event);
            $message = 'A competency mastery decision is a permanent institutional record';
            $body = DB::getDriverName() === 'sqlite'
                ? "BEGIN SELECT RAISE(ABORT, '{$message}'); END"
                : "FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'";

            DB::unprepared("CREATE TRIGGER {$trigger} BEFORE {$event} ON competency_mastery_decisions {$body}");
        }
    }

    public function down(): void
    {
        if (DB::table('competency_mastery_decisions')->exists()) {
            throw new RuntimeException('Preserve finalized competency mastery decisions and use a forward migration.');
        }

        Schema::drop('competency_mastery_decisions');
        Schema::table('programs', fn (Blueprint $table) => $table->dropUnique('programs_mastery_identity'));
        Schema::table('student_master_assessment_attempts', fn (Blueprint $table) => $table->dropUnique('attempts_mastery_identity'));
    }
};

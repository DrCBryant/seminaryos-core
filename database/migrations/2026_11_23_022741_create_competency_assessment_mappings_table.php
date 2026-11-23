<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();
        if (! in_array($driver, ['sqlite', 'mysql'], true)) {
            throw new RuntimeException('Competency mappings require MySQL or SQLite.');
        }

        Schema::table('program_requirements', fn (Blueprint $table) => $table
            ->unique(['id', 'institution_id', 'requirement_type'], 'requirements_mapping_identity'));
        Schema::table('master_assessments', fn (Blueprint $table) => $table
            ->unique(['id', 'institution_id'], 'assessments_mapping_identity'));

        Schema::create('competency_assessment_mappings', function (Blueprint $table): void {
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('program_requirement_id');
            $table->unsignedBigInteger('master_assessment_id');
            $table->string('requirement_type', 50)->default('competency');
            $table->primary(['program_requirement_id', 'master_assessment_id']);
            $table->index(['master_assessment_id', 'institution_id'], 'mapping_assessment_lookup');
            $table->foreign(['program_requirement_id', 'institution_id', 'requirement_type'], 'mapping_competency_fk')
                ->references(['id', 'institution_id', 'requirement_type'])->on('program_requirements')->cascadeOnDelete();
            $table->foreign(['master_assessment_id', 'institution_id'], 'mapping_assessment_fk')
                ->references(['id', 'institution_id'])->on('master_assessments')->cascadeOnDelete();
        });

        foreach (['INSERT', 'UPDATE'] as $event) {
            $trigger = 'mapping_competency_'.strtolower($event);
            $message = 'Assessment mappings require a competency';
            $body = $driver === 'sqlite'
                ? "WHEN NEW.requirement_type <> 'competency' BEGIN SELECT RAISE(ABORT, '{$message}'); END"
                : "FOR EACH ROW BEGIN IF BINARY NEW.requirement_type <> 'competency' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'; END IF; END";

            DB::unprepared("CREATE TRIGGER {$trigger} BEFORE {$event} ON competency_assessment_mappings {$body}");
        }
    }

    public function down(): void
    {
        Schema::drop('competency_assessment_mappings');
        Schema::table('master_assessments', fn (Blueprint $table) => $table->dropUnique('assessments_mapping_identity'));
        Schema::table('program_requirements', fn (Blueprint $table) => $table->dropUnique('requirements_mapping_identity'));
    }
};

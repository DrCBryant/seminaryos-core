<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TRIGGERS = [
        'official_transcripts_freeze_document',
        'official_transcript_lines_freeze_document',
        'official_transcript_lines_block_late_insert',
    ];

    public function up(): void
    {
        $driver = DB::getDriverName();

        if (! in_array($driver, ['mysql', 'sqlite'], true)
            || ($driver === 'sqlite' && version_compare(DB::getServerVersion(), '3.35', '<'))) {
            throw new RuntimeException('Transcript document snapshots require MySQL or SQLite 3.35 or later.');
        }

        Schema::table('official_transcripts', function (Blueprint $table): void {
            $table->json('document_snapshot')->nullable();
        });

        Schema::table('official_transcript_lines', function (Blueprint $table): void {
            $table->unsignedBigInteger('term_snapshot_id')->nullable();
            $table->boolean('affects_gpa')->nullable();
        });

        $this->protectSnapshot(self::TRIGGERS[0], 'official_transcripts', 'UPDATE',
            "(OLD.document_snapshot IS NULL AND NEW.document_snapshot IS NOT NULL
                AND (OLD.status NOT IN ('draft', 'requested', 'under_review') OR NEW.status <> 'issued'))
                OR (OLD.document_snapshot IS NOT NULL AND (".$this->changed([
                'id', 'uuid', 'institution_id', 'student_id', 'transcript_number', 'status', 'issued_at',
                'purpose', 'recipient_name', 'recipient_email', 'delivery_method', 'document_snapshot',
            ]).'))');

        $currentRead = $driver === 'mysql' ? ' FOR SHARE' : '';

        $this->protectSnapshot(self::TRIGGERS[1], 'official_transcript_lines', 'UPDATE',
            'EXISTS (SELECT 1 FROM official_transcripts WHERE id IN (OLD.official_transcript_id, NEW.official_transcript_id)
                AND document_snapshot IS NOT NULL'.$currentRead.') AND ('.$this->changed([
                'id', 'uuid', 'institution_id', 'student_id', 'official_transcript_id', 'term_snapshot_id',
                'term_label', 'course_code', 'course_title', 'credits_attempted', 'credits_earned',
                'final_grade', 'grade_label', 'grade_points', 'affects_gpa', 'status', 'completed_at',
                'sort_order', 'notes', 'deleted_at',
            ]).')');

        $this->protectSnapshot(self::TRIGGERS[2], 'official_transcript_lines', 'INSERT',
            'EXISTS (SELECT 1 FROM official_transcripts WHERE id = NEW.official_transcript_id
                AND document_snapshot IS NOT NULL'.$currentRead.')');
    }

    private function changed(array $columns): string
    {
        $sqlite = DB::getDriverName() === 'sqlite';

        return implode(' OR ', array_map(
            fn (string $column): string => $sqlite
                ? "OLD.{$column} IS NOT NEW.{$column}"
                : "NOT (CAST(OLD.{$column} AS BINARY) <=> CAST(NEW.{$column} AS BINARY))",
            $columns,
        ));
    }

    private function protectSnapshot(string $name, string $table, string $event, string $condition): void
    {
        $message = 'An issued transcript document snapshot is immutable';
        $body = DB::getDriverName() === 'sqlite'
            ? "WHEN ({$condition}) BEGIN SELECT RAISE(ABORT, '{$message}'); END"
            : "FOR EACH ROW BEGIN IF ({$condition}) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'; END IF; END";

        DB::unprepared("CREATE TRIGGER {$name} BEFORE {$event} ON {$table} {$body}");
    }

    public function down(): void
    {
        if (DB::table('official_transcripts')->whereNotNull('document_snapshot')->exists()) {
            throw new RuntimeException('Issued document snapshots exist. Preserve them and use a forward migration.');
        }

        foreach (self::TRIGGERS as $name) {
            DB::unprepared("DROP TRIGGER {$name}");
        }

        Schema::table('official_transcript_lines', function (Blueprint $table): void {
            $table->dropColumn(['term_snapshot_id', 'affects_gpa']);
        });

        Schema::table('official_transcripts', function (Blueprint $table): void {
            $table->dropColumn('document_snapshot');
        });
    }
};

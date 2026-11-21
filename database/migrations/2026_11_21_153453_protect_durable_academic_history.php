<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    private const FOREIGN_KEYS = [
        'academic_records' => [
            'institution_id' => ['institutions', 'cascade'],
            'student_id' => ['students', 'cascade'],
            'course_id' => ['courses', 'cascade'],
            'course_enrollment_id' => ['course_enrollments', 'set null'],
        ],
        'official_transcripts' => [
            'institution_id' => ['institutions', 'cascade'],
            'student_id' => ['students', 'cascade'],
        ],
        'official_transcript_lines' => [
            'institution_id' => ['institutions', 'cascade'],
            'student_id' => ['students', 'cascade'],
            'official_transcript_id' => ['official_transcripts', 'cascade'],
        ],
    ];

    public function up(): void
    {
        $this->changeRetention(enforce: true);
    }

    public function down(): void
    {
        $this->changeRetention(enforce: false);
    }

    private function changeRetention(bool $enforce): void
    {
        $driver = DB::getDriverName();
        $sqlite = $driver === 'sqlite';

        if (! $sqlite && $driver !== 'mysql') {
            throw new RuntimeException('Academic history retention requires MySQL or SQLite.');
        }

        $this->verifyForeignKeys($enforce, $sqlite);

        if ($sqlite) {
            $triggers = DB::table('sqlite_master')->where('type', 'trigger')
                ->whereIn('tbl_name', array_keys(self::FOREIGN_KEYS))->pluck('name');
            $expected = $enforce ? [] : collect(array_keys(self::FOREIGN_KEYS))
                ->flatMap(fn (string $table): array => [$table.'_retain_delete', $table.'_retain_update'])->all();

            if ($triggers->sort()->values()->all() !== collect($expected)->sort()->values()->all()) {
                throw new RuntimeException('Unexpected SQLite triggers on academic history tables. Review them before rebuilding the tables.');
            }

            // SQLite cannot change foreign-key enforcement inside a transaction.
            Schema::withoutForeignKeyConstraints(fn () => DB::transaction(function () use ($enforce): void {
                $this->applyRetention($enforce, sqlite: true);

                if (DB::select('PRAGMA foreign_key_check') !== []) {
                    throw new RuntimeException('Academic history retention would leave foreign-key violations.');
                }
            }));

            return;
        }

        $this->applyRetention($enforce, sqlite: false);
    }

    private function verifyForeignKeys(bool $enforce, bool $sqlite): void
    {
        $enabled = $sqlite ? DB::scalar('PRAGMA foreign_keys') : DB::scalar('SELECT @@SESSION.foreign_key_checks');
        if (! $enabled || ($sqlite && DB::select('PRAGMA foreign_key_check') !== [])) {
            throw new RuntimeException('Academic history retention requires enabled foreign keys and a consistent database.');
        }

        foreach (self::FOREIGN_KEYS as $table => $keys) {
            $foreignKeys = collect(Schema::getForeignKeys($table));

            foreach ($keys as $column => [$parent, $previous]) {
                $matches = $foreignKeys->filter(fn (array $key): bool => in_array($column, $key['columns'], true));

                $expectedName = $enforce ? "{$table}_{$column}_foreign" : "{$table}_{$column}_retention";
                $expectedRule = $enforce ? $previous : 'restrict';
                $key = $matches->first();

                if ($matches->count() !== 1 || $key['columns'] !== [$column] || $key['foreign_table'] !== $parent
                    || $key['foreign_columns'] !== ['id'] || $key['on_delete'] !== $expectedRule
                    || ! in_array($key['on_update'], ['no action', 'restrict'], true)
                    || (! $sqlite && $key['name'] !== $expectedName)) {
                    throw new RuntimeException("Unexpected foreign key definition for {$table}.{$column}. Inspect the schema before continuing.");
                }

                if (DB::table("{$table} as child")->leftJoin("{$parent} as parent", "child.{$column}", '=', 'parent.id')
                    ->whereNotNull("child.{$column}")->whereNull('parent.id')->exists()) {
                    throw new RuntimeException("Orphaned references found in {$table}.{$column}. Review the data before continuing.");
                }
            }
        }
    }

    private function applyRetention(bool $enforce, bool $sqlite): void
    {
        if (! $enforce) {
            $this->changeTriggers(create: false, sqlite: $sqlite);
        }

        foreach (self::FOREIGN_KEYS as $table => $keys) {
            if ($sqlite) {
                Schema::table($table, function (Blueprint $blueprint) use ($keys, $enforce): void {
                    foreach ($keys as $column => [$parent, $previous]) {
                        $blueprint->dropForeign([$column]);
                        $blueprint->foreign($column)->references('id')->on($parent)->onDelete($enforce ? 'restrict' : $previous);
                    }
                });

                continue;
            }

            $additions = [];
            $removals = [];

            foreach ($keys as $column => [$parent, $previous]) {
                $original = "{$table}_{$column}_foreign";
                $retention = "{$table}_{$column}_retention";
                $add = $enforce ? $retention : $original;
                $drop = $enforce ? $original : $retention;
                $rule = $enforce ? 'RESTRICT' : strtoupper($previous);
                $additions[] = "ADD CONSTRAINT `{$add}` FOREIGN KEY (`{$column}`) REFERENCES `{$parent}` (`id`) ON DELETE {$rule}";
                $removals[] = "DROP FOREIGN KEY `{$drop}`";
            }

            // Add validated replacements before dropping the previous keys.
            DB::statement("ALTER TABLE `{$table}` ".implode(', ', $additions));
            DB::statement("ALTER TABLE `{$table}` ".implode(', ', $removals));
        }

        if ($enforce) {
            $this->changeTriggers(create: true, sqlite: $sqlite);
        }
    }

    private function changeTriggers(bool $create, bool $sqlite): void
    {
        $issued = "OLD.issued_at IS NOT NULL OR OLD.status IN ('issued', 'voided', 'archived')";
        $issuedParent = "EXISTS (SELECT 1 FROM official_transcripts WHERE id = OLD.official_transcript_id
            AND (issued_at IS NOT NULL OR status IN ('issued', 'voided', 'archived')))";
        $legacyOutcome = "(enrollment.status = 'completed' OR enrollment.completed_at IS NOT NULL)
            AND NOT EXISTS (SELECT 1 FROM academic_records WHERE course_enrollment_id = enrollment.id)";
        $enrollmentOutcome = "(OLD.status = 'completed' OR OLD.completed_at IS NOT NULL)";
        $enrollmentRecord = 'EXISTS (SELECT 1 FROM academic_records WHERE course_enrollment_id = OLD.id)';
        $tables = [
            'academic_records' => ['DELETE' => '1 = 1', 'UPDATE' => '1 = 1'],
            'official_transcripts' => ['DELETE' => $issued, 'UPDATE' => $issued],
            'official_transcript_lines' => ['DELETE' => '1 = 1', 'UPDATE' => $issuedParent],
            'course_enrollments' => [
                'DELETE' => $enrollmentOutcome.' AND NOT '.$enrollmentRecord,
                'UPDATE' => "{$enrollmentOutcome} OR {$enrollmentRecord}",
            ],
            'students' => [
                'DELETE' => "EXISTS (SELECT 1 FROM course_enrollments AS enrollment WHERE enrollment.student_id = OLD.id AND {$legacyOutcome})",
            ],
            'courses' => [
                'DELETE' => "EXISTS (SELECT 1 FROM course_enrollments AS enrollment WHERE enrollment.course_id = OLD.id AND {$legacyOutcome})",
            ],
            'institutions' => [
                'DELETE' => "EXISTS (SELECT 1 FROM course_enrollments AS enrollment WHERE {$legacyOutcome} AND (
                    enrollment.institution_id = OLD.id
                    OR enrollment.student_id IN (SELECT id FROM students WHERE institution_id = OLD.id)
                    OR enrollment.course_id IN (SELECT id FROM courses WHERE institution_id = OLD.id)
                ))",
            ],
        ];

        foreach ($tables as $table => $events) {
            foreach ($events as $event => $condition) {
                if ($event === 'UPDATE') {
                    $condition = "OLD.deleted_at IS NULL AND NEW.deleted_at IS NOT NULL AND ({$condition})";
                }

                $name = $table.'_retain_'.strtolower($event);

                if (! $create) {
                    DB::unprepared("DROP TRIGGER {$name}");

                    continue;
                }

                $message = "Academic history retention protects {$table}";
                $body = $sqlite
                    ? "WHEN ({$condition}) BEGIN SELECT RAISE(ABORT, '{$message}'); END"
                    : "FOR EACH ROW BEGIN IF ({$condition}) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'; END IF; END";

                DB::unprepared("CREATE TRIGGER {$name} BEFORE {$event} ON {$table} {$body}");
            }
        }
    }
};

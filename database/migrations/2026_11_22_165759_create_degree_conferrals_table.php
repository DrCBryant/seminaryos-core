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

        if (! in_array($driver, ['mysql', 'sqlite'], true)) {
            throw new RuntimeException('Degree conferral retention requires MySQL or SQLite.');
        }

        Schema::create('degree_conferrals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->foreignId('conferred_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('program_code');
            $table->string('program_title');
            $table->string('credential_type', 100);
            $table->date('conferred_on');
            $table->timestamp('decided_at');
            $table->json('eligibility_snapshot');
            $table->text('registrar_remarks')->nullable();
            $table->unique(['student_id', 'program_id']);
        });

        foreach (['UPDATE', 'DELETE'] as $event) {
            $name = 'degree_conferrals_retain_'.strtolower($event);
            $message = 'A degree conferral is a permanent institutional record';
            $body = $driver === 'sqlite'
                ? "BEGIN SELECT RAISE(ABORT, '{$message}'); END"
                : "FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'";

            DB::unprepared("CREATE TRIGGER {$name} BEFORE {$event} ON degree_conferrals {$body}");
        }
    }

    public function down(): void
    {
        if (DB::table('degree_conferrals')->exists()) {
            throw new RuntimeException('Degree conferrals exist. Preserve awarded credentials and use a forward migration.');
        }

        Schema::drop('degree_conferrals');
    }
};

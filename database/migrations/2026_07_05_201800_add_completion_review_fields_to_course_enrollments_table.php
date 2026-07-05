<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->string('completion_progress_basis')->nullable()->after('final_grade');
            $table->string('completion_progress_status')->nullable()->after('completion_progress_basis');
            $table->longText('completion_evidence_summary')->nullable()->after('completion_progress_status');
            $table->longText('completion_override_reason')->nullable()->after('completion_evidence_summary');
            $table->dateTime('completion_reviewed_at')->nullable()->after('completion_override_reason');
            $table->foreignId('completion_reviewed_by_user_id')->nullable()->after('completion_reviewed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('completion_reviewed_by_user_id');
            $table->dropColumn([
                'completion_progress_basis',
                'completion_progress_status',
                'completion_evidence_summary',
                'completion_override_reason',
                'completion_reviewed_at',
            ]);
        });
    }
};

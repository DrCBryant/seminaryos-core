<?php

use App\Models\StudentSectionManualCompletion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_section_manual_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('course_offering_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_enrollment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('status', 50)->default(StudentSectionManualCompletion::STATUS_PENDING);
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->longText('completion_summary')->nullable();
            $table->string('evidence_reference')->nullable();
            $table->longText('approver_notes')->nullable();
            $table->longText('notes')->nullable();
            $table->timestamps();

            $table->index('institution_id');
            $table->index('course_offering_id');
            $table->index('course_enrollment_id');
            $table->index('student_id');
            $table->index('status');
            $table->index('approved_at');
            $table->index('approver_user_id');
            $table->unique(['course_offering_id', 'student_id'], 'student_section_manual_completion_unique_student');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_section_manual_completions');
    }
};

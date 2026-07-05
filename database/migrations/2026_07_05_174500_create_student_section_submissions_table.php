<?php

use App\Models\StudentSectionSubmission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_section_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('course_offering_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_enrollment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('status', 50)->default(StudentSectionSubmission::STATUS_NOT_STARTED);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->longText('submission_text')->nullable();
            $table->string('submission_reference')->nullable();
            $table->decimal('score', 8, 2)->nullable();
            $table->boolean('passed')->nullable();
            $table->longText('reviewer_notes')->nullable();
            $table->longText('notes')->nullable();
            $table->timestamps();

            $table->index('institution_id');
            $table->index('course_offering_id');
            $table->index('section_assignment_id');
            $table->index('course_enrollment_id');
            $table->index('student_id');
            $table->index('status');
            $table->index('submitted_at');
            $table->index('reviewed_at');
            $table->index('reviewer_user_id');
            $table->unique(['section_assignment_id', 'student_id'], 'student_section_submission_unique_student_assignment');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_section_submissions');
    }
};

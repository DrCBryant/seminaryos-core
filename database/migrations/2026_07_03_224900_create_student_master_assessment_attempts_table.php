<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_master_assessment_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('master_assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_offering_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_enrollment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('status', 50);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('assessed_at')->nullable();
            $table->foreignId('assessor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->longText('assessor_notes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('institution_id');
            $table->index('course_offering_id');
            $table->index('master_assessment_id');
            $table->index('course_enrollment_id');
            $table->index('student_id');
            $table->index('status');
            $table->index('submitted_at');
            $table->index('assessed_at');
            $table->index('assessor_user_id');
            $table->unique(['master_assessment_id', 'student_id'], 'student_master_assessment_unique_student');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_master_assessment_attempts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_term_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('course_enrollment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('course_code');
            $table->string('course_title');
            $table->decimal('credits_attempted', 8, 2)->nullable();
            $table->decimal('credits_earned', 8, 2)->nullable();
            $table->string('final_grade', 20)->nullable();
            $table->decimal('grade_points', 8, 2)->nullable();
            $table->string('status', 50)->default('in_progress');
            $table->date('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('institution_id');
            $table->index('student_id');
            $table->index('course_id');
            $table->index('academic_term_id');
            $table->index('course_enrollment_id');
            $table->index('status');
            $table->index('final_grade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_records');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('faculty_id')->constrained('faculty')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained()->cascadeOnDelete();
            $table->string('role', 50);
            $table->string('status', 50)->default('assigned');
            $table->date('assigned_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('institution_id');
            $table->index('faculty_id');
            $table->index('course_id');
            $table->index('academic_term_id');
            $table->index('role');
            $table->index('status');
            $table->unique(['faculty_id', 'course_id', 'academic_term_id', 'role'], 'teaching_assignments_unique_assignment');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_assignments');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_requirement_substitutions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_requirement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('substitute_course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->foreignId('academic_record_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 50);
            $table->longText('reason')->nullable();
            $table->date('approved_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('institution_id');
            $table->index('student_id');
            $table->index('program_id');
            $table->index('program_requirement_id');
            $table->index('substitute_course_id');
            $table->index('academic_record_id');
            $table->index('status');
            $table->index('approved_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_requirement_substitutions');
    }
};

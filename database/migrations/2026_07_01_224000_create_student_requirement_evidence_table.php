<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_requirement_evidence', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_requirement_id')->constrained()->cascadeOnDelete();
            $table->string('status', 50);
            $table->string('evidence_title')->nullable();
            $table->longText('evidence_description')->nullable();
            $table->date('completed_at')->nullable();
            $table->date('approved_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('institution_id');
            $table->index('student_id');
            $table->index('program_id');
            $table->index('program_requirement_id');
            $table->index('status');
            $table->index('completed_at');
            $table->index('approved_at');
            $table->unique(['student_id', 'program_requirement_id'], 'student_requirement_evidence_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_requirement_evidence');
    }
};

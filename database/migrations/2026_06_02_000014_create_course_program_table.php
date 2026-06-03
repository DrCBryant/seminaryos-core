<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_program', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('requirement_type', 50)->nullable();
            $table->unsignedInteger('sequence_order')->nullable();
            $table->decimal('credits_applied', 8, 2)->nullable();
            $table->timestamps();

            $table->unique(['institution_id', 'program_id', 'course_id']);
            $table->index(['institution_id', 'requirement_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_program');
    }
};

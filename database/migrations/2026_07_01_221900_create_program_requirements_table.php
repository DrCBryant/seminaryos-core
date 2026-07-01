<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_requirements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_requirement_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->string('requirement_type', 50);
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('required_credits', 8, 2)->nullable();
            $table->string('minimum_grade', 50)->nullable();
            $table->decimal('minimum_grade_points', 5, 2)->nullable();
            $table->boolean('allow_substitution')->default(false);
            $table->boolean('is_required')->default(true);
            $table->integer('sort_order')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('institution_id');
            $table->index('program_id');
            $table->index('program_requirement_group_id');
            $table->index('course_id');
            $table->index('requirement_type');
            $table->index('is_active');
            $table->index('sort_order');
            $table->index(['program_requirement_group_id', 'requirement_type'], 'prg_req_group_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_requirements');
    }
};

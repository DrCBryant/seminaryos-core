<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('section_assignments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_offering_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->string('assignment_type', 50);
            $table->string('requirement_basis', 50)->nullable();
            $table->longText('instructions')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->dateTime('available_from')->nullable();
            $table->dateTime('available_until')->nullable();
            $table->decimal('points_possible', 8, 2)->nullable();
            $table->string('passing_threshold')->nullable();
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 50)->default('draft');
            $table->longText('notes')->nullable();
            $table->timestamps();

            $table->index(['course_offering_id', 'status', 'is_required'], 'section_assignments_progress_lookup_index');
            $table->index(['course_offering_id', 'sort_order'], 'section_assignments_sort_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('section_assignments');
    }
};

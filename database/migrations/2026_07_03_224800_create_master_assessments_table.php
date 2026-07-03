<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_assessments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_offering_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->longText('competency_outcomes')->nullable();
            $table->longText('rubric')->nullable();
            $table->string('passing_threshold')->nullable();
            $table->string('status', 50);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('institution_id');
            $table->index('course_offering_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_assessments');
    }
};

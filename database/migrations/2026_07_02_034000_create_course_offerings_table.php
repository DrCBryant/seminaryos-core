<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_offerings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained()->cascadeOnDelete();
            $table->string('section_code')->nullable();
            $table->string('title')->nullable();
            $table->string('delivery_mode', 50);
            $table->string('location')->nullable();
            $table->string('meeting_pattern')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('capacity')->nullable();
            $table->string('status', 50);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('institution_id');
            $table->index('course_id');
            $table->index('academic_term_id');
            $table->index('delivery_mode');
            $table->index('status');
            $table->index('section_code');
            $table->unique([
                'institution_id',
                'course_id',
                'academic_term_id',
                'section_code',
            ], 'course_offerings_unique_section');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_offerings');
    }
};

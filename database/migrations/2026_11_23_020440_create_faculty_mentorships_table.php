<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faculty_mentorships', function (Blueprint $table): void {
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('faculty_id');
            $table->unsignedBigInteger('student_id');
            $table->primary(['faculty_id', 'student_id']);
            $table->foreign(['faculty_id', 'institution_id'])->references(['id', 'institution_id'])
                ->on('faculty')->cascadeOnDelete();
            $table->foreign(['student_id', 'institution_id'])->references(['id', 'institution_id'])
                ->on('students')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::drop('faculty_mentorships');
    }
};

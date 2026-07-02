<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teaching_assignments', function (Blueprint $table) {
            $table->foreignId('course_offering_id')
                ->nullable()
                ->after('academic_term_id')
                ->constrained('course_offerings')
                ->nullOnDelete();

            $table->index('course_offering_id');
            $table->index(['institution_id', 'course_offering_id'], 'teaching_assignments_institution_offering_index');
        });
    }

    public function down(): void
    {
        Schema::table('teaching_assignments', function (Blueprint $table) {
            $table->dropIndex('teaching_assignments_institution_offering_index');
            $table->dropIndex(['course_offering_id']);
            $table->dropConstrainedForeignId('course_offering_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('applicant_id')
                ->nullable()
                ->after('program_id')
                ->constrained('applicants')
                ->nullOnDelete();

            $table->unique('applicant_id');
        });

        Schema::table('applicants', function (Blueprint $table) {
            $table->timestamp('converted_at')
                ->nullable()
                ->after('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('applicant_id');
        });

        Schema::table('applicants', function (Blueprint $table) {
            $table->dropColumn('converted_at');
        });
    }
};

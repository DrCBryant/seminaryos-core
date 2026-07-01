<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('official_transcript_lines', function (Blueprint $table): void {
            $table->string('grade_label')->nullable()->after('final_grade');
        });
    }

    public function down(): void
    {
        Schema::table('official_transcript_lines', function (Blueprint $table): void {
            $table->dropColumn('grade_label');
        });
    }
};

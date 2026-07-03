<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_offerings', function (Blueprint $table) {
            $table->string('progress_basis', 50)
                ->default('attendance')
                ->after('status');
            $table->longText('progress_notes')
                ->nullable()
                ->after('progress_basis');

            $table->index('progress_basis');
        });
    }

    public function down(): void
    {
        Schema::table('course_offerings', function (Blueprint $table) {
            $table->dropIndex(['progress_basis']);
            $table->dropColumn(['progress_basis', 'progress_notes']);
        });
    }
};

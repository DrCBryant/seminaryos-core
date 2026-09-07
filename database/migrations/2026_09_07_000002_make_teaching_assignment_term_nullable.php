<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teaching_assignments', function (Blueprint $table): void {
            $table->dropForeign(['academic_term_id']);
            $table->foreignId('academic_term_id')->nullable()->change();
            $table->foreign('academic_term_id')->references('id')->on('academic_terms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teaching_assignments', function (Blueprint $table): void {
            $table->dropForeign(['academic_term_id']);
            $table->foreignId('academic_term_id')->nullable(false)->change();
            $table->foreign('academic_term_id')->references('id')->on('academic_terms')->cascadeOnDelete();
        });
    }
};

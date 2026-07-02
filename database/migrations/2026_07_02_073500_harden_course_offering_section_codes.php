<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('course_offerings')
            ->whereNull('section_code')
            ->orWhereRaw("TRIM(section_code) = ''")
            ->update(['section_code' => 'MAIN']);

        DB::statement('UPDATE course_offerings SET section_code = UPPER(TRIM(section_code)) WHERE section_code IS NOT NULL');

        Schema::table('course_offerings', function (Blueprint $table) {
            $table->string('section_code')->default('MAIN')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('course_offerings', function (Blueprint $table) {
            $table->string('section_code')->nullable()->default(null)->change();
        });
    }
};

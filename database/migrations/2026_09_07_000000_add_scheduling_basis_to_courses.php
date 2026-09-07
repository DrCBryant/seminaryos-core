<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->string('scheduling_basis', 50)->default('term')->after('delivery_method');
            $table->index(['institution_id', 'scheduling_basis']);
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropIndex(['institution_id', 'scheduling_basis']);
            $table->dropColumn('scheduling_basis');
        });
    }
};

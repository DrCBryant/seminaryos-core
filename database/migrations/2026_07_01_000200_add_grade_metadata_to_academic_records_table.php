<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_records', function (Blueprint $table): void {
            $table->foreignId('grade_scale_id')
                ->nullable()
                ->after('grade_points')
                ->constrained('grade_scales')
                ->nullOnDelete();
            $table->foreignId('grade_value_id')
                ->nullable()
                ->after('grade_scale_id')
                ->constrained('grade_values')
                ->nullOnDelete();
            $table->string('grade_label')->nullable()->after('grade_value_id');
            $table->boolean('earns_credit')->nullable()->after('grade_label');
            $table->boolean('affects_gpa')->nullable()->after('earns_credit');
            $table->boolean('is_passing')->nullable()->after('affects_gpa');

            $table->index('grade_scale_id');
            $table->index('grade_value_id');
            $table->index('affects_gpa');
            $table->index('earns_credit');
            $table->index('is_passing');
        });
    }

    public function down(): void
    {
        Schema::table('academic_records', function (Blueprint $table): void {
            $table->dropIndex(['grade_scale_id']);
            $table->dropIndex(['grade_value_id']);
            $table->dropIndex(['affects_gpa']);
            $table->dropIndex(['earns_credit']);
            $table->dropIndex(['is_passing']);

            $table->dropConstrainedForeignId('grade_value_id');
            $table->dropConstrainedForeignId('grade_scale_id');
            $table->dropColumn([
                'grade_label',
                'earns_credit',
                'affects_gpa',
                'is_passing',
            ]);
        });
    }
};

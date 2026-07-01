<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('grade_scale_id')->constrained()->cascadeOnDelete();
            $table->string('grade');
            $table->string('label')->nullable();
            $table->decimal('grade_points', 5, 2)->nullable();
            $table->decimal('min_percentage', 5, 2)->nullable();
            $table->decimal('max_percentage', 5, 2)->nullable();
            $table->boolean('earns_credit')->default(true);
            $table->boolean('affects_gpa')->default(true);
            $table->boolean('is_passing')->default(true);
            $table->integer('sort_order')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('institution_id');
            $table->index('grade_scale_id');
            $table->index('grade');
            $table->index('sort_order');
            $table->unique(['grade_scale_id', 'grade']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_values');
    }
};

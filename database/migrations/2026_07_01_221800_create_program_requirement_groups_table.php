<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_requirement_groups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('group_type', 50);
            $table->decimal('required_credits', 8, 2)->nullable();
            $table->decimal('minimum_gpa', 4, 3)->nullable();
            $table->integer('sort_order')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('institution_id');
            $table->index('program_id');
            $table->index('group_type');
            $table->index('is_active');
            $table->index('sort_order');
            $table->index(['program_id', 'group_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_requirement_groups');
    }
};

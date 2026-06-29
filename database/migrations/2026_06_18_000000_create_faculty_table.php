<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faculty', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone', 50)->nullable();
            $table->string('title')->nullable();
            $table->longText('bio')->nullable();
            $table->string('status', 50)->default('active');
            $table->boolean('is_public')->default(false);
            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['institution_id', 'email']);
            $table->index(['institution_id', 'status']);
            $table->index(['institution_id', 'is_public']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faculty');
    }
};

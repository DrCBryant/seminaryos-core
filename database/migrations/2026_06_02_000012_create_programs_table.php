<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('title');
            $table->string('slug');
            $table->string('credential_type', 100)->nullable();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->decimal('credit_hours', 8, 2)->nullable();
            $table->string('duration_text')->nullable();
            $table->string('delivery_method', 100)->nullable();
            $table->string('tuition_text')->nullable();
            $table->longText('admissions_requirements')->nullable();
            $table->longText('learning_outcomes')->nullable();
            $table->string('status', 50)->default('draft');
            $table->boolean('is_public')->default(false);
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['institution_id', 'code']);
            $table->unique(['institution_id', 'slug']);
            $table->index(['institution_id', 'status']);
            $table->index(['institution_id', 'is_public']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};

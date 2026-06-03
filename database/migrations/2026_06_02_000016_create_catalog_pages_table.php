<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_pages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('title');
            $table->string('slug');
            $table->string('page_type', 100);
            $table->longText('rendered_content')->nullable();
            $table->string('status', 50)->default('draft');
            $table->boolean('is_public')->default(false);
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamp('last_generated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['catalog_id', 'slug']);
            $table->index(['institution_id', 'source_type', 'source_id']);
            $table->index(['institution_id', 'status']);
            $table->index(['institution_id', 'is_public']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_pages');
    }
};

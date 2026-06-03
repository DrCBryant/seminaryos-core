<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_pages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->string('page_type', 100);
            $table->json('content')->nullable();
            $table->string('status', 50)->default('draft');
            $table->boolean('is_public')->default(false);
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['website_id', 'slug']);
            $table->index(['institution_id', 'status']);
            $table->index(['institution_id', 'is_public']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_pages');
    }
};

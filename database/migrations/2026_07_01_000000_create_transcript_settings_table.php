<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcript_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('transcript_title');
            $table->string('registrar_name')->nullable();
            $table->string('registrar_title')->nullable();
            $table->longText('certification_statement')->nullable();
            $table->longText('footer_statement')->nullable();
            $table->longText('grading_scale_note')->nullable();
            $table->longText('accreditation_note')->nullable();
            $table->longText('transcript_disclaimer')->nullable();
            $table->boolean('show_recipient_info')->default(true);
            $table->boolean('show_delivery_method')->default(true);
            $table->boolean('show_purpose')->default(true);
            $table->boolean('show_grade_points')->default(false);
            $table->boolean('show_status')->default(true);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('institution_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcript_settings');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('official_transcripts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('transcript_number');
            $table->string('status', 50)->default('draft');
            $table->string('purpose')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_email')->nullable();
            $table->string('delivery_method', 50)->nullable();
            $table->text('registrar_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('institution_id');
            $table->index('student_id');
            $table->index('transcript_number');
            $table->index('status');
            $table->index('requested_at');
            $table->index('issued_at');
            $table->unique(['institution_id', 'transcript_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('official_transcripts');
    }
};

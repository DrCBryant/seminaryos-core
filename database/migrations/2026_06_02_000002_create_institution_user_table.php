<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('role', ['super_admin', 'admin', 'staff', 'faculty', 'student']);
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->timestamps();

            $table->unique(['institution_id', 'user_id']);
            $table->index('institution_id');
            $table->index('user_id');
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_user');
    }
};

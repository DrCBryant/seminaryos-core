<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->string('phone', 50)->nullable()->after('password');
            $table->string('avatar_path')->nullable()->after('phone');
            $table->foreignId('current_institution_id')
                ->nullable()
                ->after('avatar_path')
                ->constrained('institutions')
                ->nullOnDelete();
            $table->string('status', 50)->default('active')->after('current_institution_id');
            $table->string('timezone', 50)->default('UTC')->after('status');
            $table->string('locale', 10)->default('en')->after('timezone');
            $table->softDeletes()->after('updated_at');

            $table->index('status');
            $table->index('current_institution_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['current_institution_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['current_institution_id']);
            $table->dropColumn([
                'uuid',
                'phone',
                'avatar_path',
                'current_institution_id',
                'status',
                'timezone',
                'locale',
                'deleted_at',
            ]);
        });
    }
};

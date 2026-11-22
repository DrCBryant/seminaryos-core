<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const IDENTITIES = [
        'student_identity_links' => 'students',
        'faculty_identity_links' => 'faculty',
    ];

    public function up(): void
    {
        foreach (['institution_user', ...array_values(self::IDENTITIES)] as $parent) {
            Schema::table($parent, fn (Blueprint $table) => $table->unique(['id', 'institution_id']));
        }

        foreach (self::IDENTITIES as $link => $parent) {
            Schema::create($link, function (Blueprint $table) use ($parent): void {
                $table->unsignedBigInteger('identity_id')->primary();
                $table->unsignedBigInteger('membership_id')->unique();
                $table->unsignedBigInteger('institution_id');
                $table->foreign(['identity_id', 'institution_id'])->references(['id', 'institution_id'])
                    ->on($parent)->cascadeOnDelete();
                $table->foreign(['membership_id', 'institution_id'])->references(['id', 'institution_id'])
                    ->on('institution_user')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(array_keys(self::IDENTITIES)) as $link) {
            Schema::drop($link);
        }

        foreach (['institution_user', ...array_values(self::IDENTITIES)] as $parent) {
            Schema::table($parent, fn (Blueprint $table) => $table->dropUnique(['id', 'institution_id']));
        }
    }
};

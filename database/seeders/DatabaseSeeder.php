<?php

namespace Database\Seeders;

use App\Models\Catalog;
use App\Models\Course;
use App\Models\Institution;
use App\Models\Program;
use App\Models\Website;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $institution = Institution::query()->updateOrCreate(
            ['slug' => 'kerygma-university'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Kerygma University',
                'type' => 'university',
                'status' => 'active',
                'email' => 'admissions@kerygma.edu',
                'phone' => '1-800-KERYGMA',
                'website' => 'https://kerygma.edu',
                'address_line1' => '1 Kerygma Way',
                'city' => 'Orlando',
                'state' => 'Florida',
                'postal_code' => '32801',
                'country' => 'USA',
                'settings' => [
                    'mission' => 'Forming faithful leaders through theological education.',
                    'catalog_cycle' => 'annual',
                ],
                'primary_color' => '#1D3557',
                'secondary_color' => '#C1121F',
                'max_users' => 500,
                'max_students' => 5000,
                'max_storage_mb' => 20480,
            ],
        );

        $website = Website::withoutGlobalScopes()->updateOrCreate(
            [
                'institution_id' => $institution->id,
                'domain' => 'kerygma.edu',
            ],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Kerygma University Main Website',
                'status' => 'active',
                'primary_color' => '#1D3557',
                'secondary_color' => '#F1FAEE',
                'accent_color' => '#C1121F',
                'settings' => [
                    'is_primary' => true,
                    'audience' => ['prospective-students', 'current-students', 'faculty'],
                ],
            ],
        );

        $programs = collect([
            [
                'code' => 'MDIV',
                'title' => 'Master of Divinity',
                'slug' => 'master-of-divinity',
                'credential_type' => 'Masters',
                'short_description' => 'A professional ministry degree rooted in biblical, theological, and pastoral formation.',
                'description' => 'The Master of Divinity prepares students for pastoral leadership, preaching, discipleship, and mission in church and community settings.',
                'credit_hours' => 72,
                'duration_text' => '3 years',
                'delivery_method' => 'Residential',
                'tuition_text' => '$450 per credit hour',
                'admissions_requirements' => 'Bachelor\'s degree, pastoral recommendation, and personal statement.',
                'learning_outcomes' => 'Interpret Scripture faithfully; lead congregations wisely; practice pastoral care with theological depth.',
                'status' => 'published',
                'is_public' => true,
                'seo_title' => 'Master of Divinity | Kerygma University',
                'seo_description' => 'Prepare for pastoral ministry through rigorous theological education.',
                'published_at' => now(),
            ],
            [
                'code' => 'MACS',
                'title' => 'Master of Arts in Christian Studies',
                'slug' => 'master-of-arts-in-christian-studies',
                'credential_type' => 'Masters',
                'short_description' => 'An academic graduate program for biblical, theological, and ministry study.',
                'description' => 'The Master of Arts in Christian Studies equips students for teaching, church service, and advanced theological reflection in diverse ministry contexts.',
                'credit_hours' => 36,
                'duration_text' => '2 years',
                'delivery_method' => 'Online',
                'tuition_text' => '$400 per credit hour',
                'admissions_requirements' => 'Bachelor\'s degree and statement of calling.',
                'learning_outcomes' => 'Articulate Christian doctrine clearly; apply biblical interpretation responsibly; engage ministry issues thoughtfully.',
                'status' => 'published',
                'is_public' => true,
                'seo_title' => 'Master of Arts in Christian Studies | Kerygma University',
                'seo_description' => 'Advance your theological grounding for ministry and scholarship.',
                'published_at' => now(),
            ],
        ])->mapWithKeys(function (array $attributes) use ($institution) {
            $program = Program::withoutGlobalScopes()->updateOrCreate(
                [
                    'institution_id' => $institution->id,
                    'code' => $attributes['code'],
                ],
                array_merge($attributes, [
                    'uuid' => (string) Str::uuid(),
                    'institution_id' => $institution->id,
                ]),
            );

            return [$attributes['code'] => $program];
        });

        $courses = collect([
            [
                'code' => 'BIB501',
                'title' => 'Biblical Hermeneutics',
                'slug' => 'biblical-hermeneutics',
                'description' => 'Study principles and practices for interpreting Scripture in church and academy.',
                'credit_hours' => 3,
                'delivery_method' => 'Residential',
                'status' => 'published',
                'is_public' => true,
                'seo_title' => 'Biblical Hermeneutics | Kerygma University',
                'seo_description' => 'Interpret Scripture with theological and pastoral wisdom.',
                'published_at' => now(),
            ],
            [
                'code' => 'THE510',
                'title' => 'Systematic Theology I',
                'slug' => 'systematic-theology-i',
                'description' => 'Explore core doctrines of revelation, God, creation, and humanity.',
                'credit_hours' => 3,
                'delivery_method' => 'Residential',
                'status' => 'published',
                'is_public' => true,
                'seo_title' => 'Systematic Theology I | Kerygma University',
                'seo_description' => 'Build a robust theological framework for ministry and scholarship.',
                'published_at' => now(),
            ],
            [
                'code' => 'MIN520',
                'title' => 'Pastoral Care and Counseling',
                'slug' => 'pastoral-care-and-counseling',
                'description' => 'Develop practical and theological competencies for congregational care.',
                'credit_hours' => 3,
                'delivery_method' => 'Hybrid',
                'status' => 'published',
                'is_public' => true,
                'seo_title' => 'Pastoral Care and Counseling | Kerygma University',
                'seo_description' => 'Serve people wisely through biblically grounded pastoral care.',
                'published_at' => now(),
            ],
            [
                'code' => 'MIS530',
                'title' => 'Mission and Evangelism',
                'slug' => 'mission-and-evangelism',
                'description' => 'Examine biblical and historical foundations for mission in local and global settings.',
                'credit_hours' => 3,
                'delivery_method' => 'Online',
                'status' => 'published',
                'is_public' => true,
                'seo_title' => 'Mission and Evangelism | Kerygma University',
                'seo_description' => 'Understand and practice faithful Christian witness in contemporary contexts.',
                'published_at' => now(),
            ],
        ])->mapWithKeys(function (array $attributes) use ($institution) {
            $course = Course::withoutGlobalScopes()->updateOrCreate(
                [
                    'institution_id' => $institution->id,
                    'code' => $attributes['code'],
                ],
                array_merge($attributes, [
                    'uuid' => (string) Str::uuid(),
                    'institution_id' => $institution->id,
                ]),
            );

            return [$attributes['code'] => $course];
        });

        $programs['MDIV']->courses()->sync([
            $courses['BIB501']->id => [
                'institution_id' => $institution->id,
                'requirement_type' => 'core',
                'sequence_order' => 1,
                'credits_applied' => 3,
            ],
            $courses['THE510']->id => [
                'institution_id' => $institution->id,
                'requirement_type' => 'core',
                'sequence_order' => 2,
                'credits_applied' => 3,
            ],
            $courses['MIN520']->id => [
                'institution_id' => $institution->id,
                'requirement_type' => 'core',
                'sequence_order' => 3,
                'credits_applied' => 3,
            ],
            $courses['MIS530']->id => [
                'institution_id' => $institution->id,
                'requirement_type' => 'core',
                'sequence_order' => 4,
                'credits_applied' => 3,
            ],
        ]);

        $programs['MACS']->courses()->sync([
            $courses['BIB501']->id => [
                'institution_id' => $institution->id,
                'requirement_type' => 'core',
                'sequence_order' => 1,
                'credits_applied' => 3,
            ],
            $courses['THE510']->id => [
                'institution_id' => $institution->id,
                'requirement_type' => 'core',
                'sequence_order' => 2,
                'credits_applied' => 3,
            ],
            $courses['MIS530']->id => [
                'institution_id' => $institution->id,
                'requirement_type' => 'elective',
                'sequence_order' => 3,
                'credits_applied' => 3,
            ],
        ]);

        Catalog::withoutGlobalScopes()->updateOrCreate(
            [
                'institution_id' => $institution->id,
                'slug' => '2026-2027-academic-catalog',
            ],
            [
                'uuid' => (string) Str::uuid(),
                'title' => '2026-2027 Academic Catalog',
                'academic_year' => '2026-2027',
                'status' => 'published',
                'effective_start_date' => '2026-08-01',
                'effective_end_date' => '2027-07-31',
                'is_active' => true,
                'description' => 'Official academic catalog for Kerygma University programs, courses, and policies.',
                'seo_title' => '2026-2027 Academic Catalog | Kerygma University',
                'seo_description' => 'Browse the active academic catalog for Kerygma University.',
                'published_at' => now(),
            ],
        );

        $website->refresh();
        $institution->refresh();
        $institution->load([
            'websites',
            'programs',
            'courses',
            'coursePrograms',
            'catalogs',
        ]);
    }
}

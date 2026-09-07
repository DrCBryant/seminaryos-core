<?php

namespace Tests\Unit;

use App\Models\Course;
use PHPUnit\Framework\TestCase;

class CourseSchedulingBasisTest extends TestCase
{
    public function test_it_exposes_the_canonical_scheduling_basis_options(): void
    {
        $this->assertSame([
            'term' => 'Term-bound',
            'completion_date' => 'Completion-date grouped',
        ], Course::schedulingBasisOptions());
    }

    public function test_existing_courses_default_to_term_behavior_when_basis_is_missing(): void
    {
        $course = new Course;

        $this->assertFalse($course->isCompletionDateGrouped());
        $this->assertSame(Course::SCHEDULING_BASIS_TERM, $course->scheduling_basis);
    }

    public function test_completion_date_courses_are_identified_without_a_kairos_specific_flag(): void
    {
        $course = new Course(['scheduling_basis' => Course::SCHEDULING_BASIS_COMPLETION_DATE]);

        $this->assertTrue($course->isCompletionDateGrouped());
    }
}

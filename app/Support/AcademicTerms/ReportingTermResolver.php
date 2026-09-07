<?php

namespace App\Support\AcademicTerms;

use App\Models\AcademicRecord;
use App\Models\AcademicTerm;
use App\Models\Course;
use Carbon\CarbonInterface;

class ReportingTermResolver
{
    public const RESOLVED = 'resolved';

    public const NO_MATCH = 'no_match';

    public const AMBIGUOUS = 'ambiguous';

    /** @var list<string> */
    public const STANDARD_TERM_TYPES = ['fall', 'spring', 'summer', 'winter'];

    /**
     * @return array{status: string, term: ?AcademicTerm, message: ?string, candidates: list<AcademicTerm>}
     */
    public function resolve(Course $course, int $institutionId, CarbonInterface $completedAt): array
    {
        if (! $course->isCompletionDateGrouped()) {
            return [
                'status' => self::NO_MATCH,
                'term' => null,
                'message' => 'This course uses direct AcademicTerm context.',
                'candidates' => [],
            ];
        }

        $candidates = AcademicTerm::query()
            ->where('institution_id', $institutionId)
            ->whereIn('term_type', self::STANDARD_TERM_TYPES)
            ->whereDate('start_date', '<=', $completedAt->toDateString())
            ->whereDate('end_date', '>=', $completedAt->toDateString())
            ->orderBy('start_date')
            ->get()
            ->all();

        if (count($candidates) === 1) {
            return ['status' => self::RESOLVED, 'term' => $candidates[0], 'message' => null, 'candidates' => $candidates];
        }

        if (count($candidates) > 1) {
            return ['status' => self::AMBIGUOUS, 'term' => null, 'message' => 'More than one standard reporting term contains this completion date.', 'candidates' => $candidates];
        }

        return ['status' => self::NO_MATCH, 'term' => null, 'message' => 'No standard reporting term contains this completion date.', 'candidates' => []];
    }

    /**
     * @return array{status: string, term: ?AcademicTerm, message: ?string, candidates: list<AcademicTerm>}
     */
    public function resolveAcademicRecord(AcademicRecord $record): array
    {
        $record->loadMissing('course', 'academicTerm');

        if (! $record->course?->isCompletionDateGrouped()) {
            return [
                'status' => self::RESOLVED,
                'term' => $record->academicTerm,
                'message' => null,
                'candidates' => $record->academicTerm ? [$record->academicTerm] : [],
            ];
        }

        return $record->completed_at
            ? $this->resolve($record->course, $record->institution_id, $record->completed_at->startOfDay())
            : ['status' => self::NO_MATCH, 'term' => null, 'message' => 'A completion date is required to resolve a reporting term.', 'candidates' => []];
    }
}

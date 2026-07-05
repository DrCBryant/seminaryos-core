<?php

namespace App\Filament\Resources\CourseOfferings\Support;

use App\Models\CourseEnrollment;
use App\Models\CourseOffering;
use App\Support\SectionProgress\SectionProgressEvaluator;
use Filament\Actions\Action;

class SectionProgressPreview
{
    public static function make(): Action
    {
        return Action::make('viewSectionProgress')
            ->label('View Section Progress')
            ->icon('heroicon-o-chart-bar-square')
            ->color('gray')
            ->modalHeading('Section Progress Preview')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalWidth('7xl')
            ->modalContent(fn (CourseOffering $record) => view('filament.course-offerings.section-progress-preview', self::getViewData($record)));
    }

    /**
     * @return array<string, mixed>
     */
    protected static function getViewData(CourseOffering $courseOffering): array
    {
        $courseOffering->loadMissing([
            'institution',
            'course',
            'academicTerm',
            'courseEnrollments.student' => fn ($query) => $query->withTrashed(),
            ...SectionProgressEvaluator::courseOfferingRelations(),
        ]);

        $studentRows = $courseOffering->courseEnrollments
            ->sortBy([
                fn (CourseEnrollment $enrollment): string => strtolower((string) $enrollment->student?->last_name),
                fn (CourseEnrollment $enrollment): string => strtolower((string) $enrollment->student?->first_name),
                fn (CourseEnrollment $enrollment): string => strtolower((string) ($enrollment->student?->student_number ?? 'zzzzzzzz')),
                fn (CourseEnrollment $enrollment): int => $enrollment->id,
            ])
            ->values()
            ->map(function (CourseEnrollment $enrollment): array {
                $progress = SectionProgressEvaluator::evaluateEnrollment($enrollment);

                return [
                    'student_name' => $enrollment->student?->full_name ?? 'Unknown Student',
                    'student_number' => $enrollment->student?->student_number ?? '—',
                    'enrollment_status' => $enrollment->status ?? '—',
                    'enrollment_status_label' => SectionProgressEvaluator::formatGenericStatusLabel($enrollment->status),
                    'progress_status' => $progress['progress_status'],
                    'progress_status_label' => SectionProgressEvaluator::formatProgressStatusLabel($progress['progress_status']),
                    'progress_status_badge_classes' => SectionProgressEvaluator::progressStatusBadgeClasses($progress['progress_status']),
                    'progress_basis_used' => $progress['progress_basis_used'],
                    'evidence_summary' => $progress['evidence_summary'],
                    'last_activity_date' => $progress['last_activity_date'] ?? null,
                ];
            })
            ->values();

        return [
            'courseOffering' => $courseOffering,
            'summary' => [
                'institution_name' => $courseOffering->institution?->name ?? '—',
                'course_label' => trim(($courseOffering->course?->code ?? '—').' — '.($courseOffering->course?->title ?? '—')),
                'academic_term' => $courseOffering->academicTerm
                    ? "{$courseOffering->academicTerm->name} ({$courseOffering->academicTerm->academic_year})"
                    : '—',
                'section_code' => $courseOffering->section_code ?: '—',
                'progress_basis' => SectionProgressEvaluator::formatProgressBasisLabel($courseOffering->progress_basis),
                'delivery_mode' => SectionProgressEvaluator::formatDeliveryModeLabel($courseOffering->delivery_mode),
                'capacity' => $courseOffering->capacity === null ? 'Unlimited' : (string) $courseOffering->capacity,
                'enrolled_count' => $courseOffering->enrolledCount(),
            ],
            'studentRows' => $studentRows,
            'progressStatusLabels' => [
                'not_started' => SectionProgressEvaluator::formatProgressStatusLabel('not_started'),
                'in_progress' => SectionProgressEvaluator::formatProgressStatusLabel('in_progress'),
                'satisfied' => SectionProgressEvaluator::formatProgressStatusLabel('satisfied'),
                'needs_attention' => SectionProgressEvaluator::formatProgressStatusLabel('needs_attention'),
                'not_evaluable' => SectionProgressEvaluator::formatProgressStatusLabel('not_evaluable'),
            ],
            'progressStatusBadgeClasses' => [
                'not_started' => SectionProgressEvaluator::progressStatusBadgeClasses('not_started'),
                'in_progress' => SectionProgressEvaluator::progressStatusBadgeClasses('in_progress'),
                'satisfied' => SectionProgressEvaluator::progressStatusBadgeClasses('satisfied'),
                'needs_attention' => SectionProgressEvaluator::progressStatusBadgeClasses('needs_attention'),
                'not_evaluable' => SectionProgressEvaluator::progressStatusBadgeClasses('not_evaluable'),
            ],
        ];
    }
}

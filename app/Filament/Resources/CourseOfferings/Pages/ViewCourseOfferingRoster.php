<?php

namespace App\Filament\Resources\CourseOfferings\Pages;

use App\Filament\Resources\CourseOfferings\CourseOfferingResource;
use App\Filament\Resources\TeachingAssignments\Schemas\TeachingAssignmentForm;
use App\Models\CourseEnrollment;
use App\Models\CourseOffering;
use App\Models\TeachingAssignment;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ViewCourseOfferingRoster extends Page
{
    use InteractsWithRecord {
        getRecord as getBaseRecord;
    }

    protected static string $resource = CourseOfferingResource::class;

    protected static ?string $breadcrumb = 'Roster';

    protected static ?string $navigationLabel = 'Roster';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.resources.course-offerings.pages.view-course-offering-roster';

    public CourseOffering $courseOffering;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->authorizeAccess();

        /** @var CourseOffering $courseOffering */
        $courseOffering = $this->getRecord()->load([
            'institution',
            'course',
            'academicTerm',
            'teachingAssignments.faculty' => fn ($query) => $query->withTrashed(),
            'courseEnrollments.student' => fn ($query) => $query->withTrashed(),
        ]);

        $this->courseOffering = $courseOffering;
    }

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canEdit($this->getRecord()), 403);
    }

    public function getTitle(): string|Htmlable
    {
        $course = $this->courseOffering->course;

        return trim("Roster · {$course?->code} · {$this->courseOffering->section_code}");
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Print')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->extraAttributes([
                    'onclick' => 'window.print()',
                ]),
            Action::make('edit')
                ->label('Edit Offering')
                ->icon('heroicon-o-pencil-square')
                ->color('primary')
                ->url($this->getResourceUrl('edit')),
        ];
    }

    public function getFacultyAssignments(): Collection
    {
        return $this->courseOffering->teachingAssignments
            ->sortBy([
                fn (TeachingAssignment $assignment): string => strtolower((string) $assignment->role),
                fn (TeachingAssignment $assignment): string => strtolower((string) $assignment->faculty?->last_name),
                fn (TeachingAssignment $assignment): string => strtolower((string) $assignment->faculty?->first_name),
                fn (TeachingAssignment $assignment): string => strtolower((string) $assignment->faculty?->email),
            ])
            ->values();
    }

    public function getStudentEnrollments(): Collection
    {
        return $this->courseOffering->courseEnrollments
            ->sortBy([
                fn (CourseEnrollment $enrollment): string => strtolower((string) $enrollment->student?->last_name),
                fn (CourseEnrollment $enrollment): string => strtolower((string) $enrollment->student?->first_name),
                fn (CourseEnrollment $enrollment): string => strtolower((string) ($enrollment->student?->student_number ?? 'zzzzzzzz')),
            ])
            ->values();
    }

    public function getCapacitySummary(): array
    {
        $capacity = $this->courseOffering->capacity;
        $enrolledCount = $this->courseOffering->enrolledCount();

        return [
            'capacity' => $capacity === null ? 'Unlimited' : (string) $capacity,
            'enrolled_count' => $enrolledCount,
            'available_seats' => $capacity === null ? 'Unlimited' : (string) max($capacity - $enrolledCount, 0),
            'capacity_status' => $this->courseOffering->capacityStatus(),
        ];
    }

    public function formatDeliveryMode(?string $value): string
    {
        return CourseOffering::DELIVERY_MODE_OPTIONS[$value] ?? ($value ?: '—');
    }

    public function formatAssignmentRole(?string $value): string
    {
        return TeachingAssignmentForm::ROLE_OPTIONS[$value] ?? ($value ? str($value)->replace('_', ' ')->title()->toString() : '—');
    }

    public function formatDate(mixed $value, string $format = 'M j, Y'): string
    {
        return $value?->format($format) ?? '—';
    }

    public function getRecord(): Model
    {
        return $this->courseOffering ?? $this->getBaseRecord();
    }
}

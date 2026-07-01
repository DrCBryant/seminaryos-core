@php
    $formatCredits = static fn ($value) => $value === null ? '—' : number_format((float) $value, 2);
    $formatDate = static fn ($value) => $value?->format('M j, Y') ?? '—';
    $formatGpa = static fn ($value) => $value === null ? 'N/A' : number_format((float) $value, 3);
    $statusClasses = static fn (string $status) => match ($status) {
        'complete' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200',
        'in_progress' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200',
        'incomplete' => 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-200',
        default => 'bg-gray-100 text-gray-800 dark:bg-white/10 dark:text-gray-200',
    };
    $statusLabel = static fn (string $status) => str_replace('_', ' ', ucfirst($status));
@endphp

<div class="space-y-6">
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Student</p>
                <p class="text-base font-semibold text-gray-950 dark:text-white">{{ $student->full_name }}</p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Student Number</p>
                <p class="text-base text-gray-950 dark:text-white">{{ $student->student_number }}</p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Institution</p>
                <p class="text-base text-gray-950 dark:text-white">{{ $student->institution?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Program</p>
                <p class="text-base text-gray-950 dark:text-white">{{ $student->program?->title ?? '—' }}</p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Enrollment Date</p>
                <p class="text-base text-gray-950 dark:text-white">{{ $formatDate($student->enrollment_date) }}</p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">GPA Preview</p>
                <p class="text-base text-gray-950 dark:text-white">{{ $formatGpa($overallGpa) }}</p>
            </div>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Overall Earned Credits</p>
            <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($overallEarnedCredits, 2) }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Overall Attempted Credits</p>
            <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($overallAttemptedCredits, 2) }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Requirement Groups</p>
            <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $groupResults->count() }}</p>
        </div>
    </div>

    @if (! $hasProgramRequirements)
        <div class="rounded-xl border border-dashed border-gray-300 p-6 text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
            No active program requirement groups are configured for this student's program. Degree audit preview cannot evaluate completion until requirements are defined.
        </div>
    @endif

    @if ($groupResults->isNotEmpty())
        <div class="space-y-3">
            <h3 class="text-lg font-semibold text-gray-950 dark:text-white">Requirement Groups</h3>

            @foreach ($groupResults as $groupResult)
                @php($group = $groupResult['group'])
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h4 class="text-base font-semibold text-gray-950 dark:text-white">{{ $group->name }}</h4>
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $statusClasses($groupResult['status']) }}">{{ $statusLabel($groupResult['status']) }}</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ ucfirst($group->group_type) }} group</p>
                            @if ($group->description)
                                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ $group->description }}</p>
                            @endif
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Required Credits</p>
                                <p class="text-sm text-gray-950 dark:text-white">{{ $formatCredits($groupResult['requiredCredits']) }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Progress Credits</p>
                                <p class="text-sm text-gray-950 dark:text-white">{{ $formatCredits($groupResult['earnedCredits']) }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Minimum GPA</p>
                                <p class="text-sm text-gray-950 dark:text-white">{{ $formatGpa($groupResult['minimumGpa']) }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Calculated GPA</p>
                                <p class="text-sm text-gray-950 dark:text-white">{{ $formatGpa($groupResult['calculatedGpa']) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @php
        $sections = [
            'Completed Requirements' => $completedRequirements,
            'In Progress / Partially Complete Requirements' => $inProgressRequirements,
            'Incomplete Requirements' => $incompleteRequirements,
            'Not Evaluated Requirements' => $notEvaluatedRequirements,
        ];
    @endphp

    @foreach ($sections as $sectionTitle => $items)
        <div class="space-y-3">
            <h3 class="text-lg font-semibold text-gray-950 dark:text-white">{{ $sectionTitle }}</h3>

            @if ($items->isEmpty())
                <div class="rounded-xl border border-dashed border-gray-300 p-6 text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                    No items in this section.
                </div>
            @else
                <div class="space-y-4">
                    @foreach ($items as $result)
                        @php($requirement = $result['requirement'])
                        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h4 class="text-base font-semibold text-gray-950 dark:text-white">{{ $requirement->name }}</h4>
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $statusClasses($result['status']) }}">{{ $statusLabel($result['status']) }}</span>
                                    </div>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ str_replace('_', ' ', ucwords($requirement->requirement_type, '_')) }}</p>
                                    @if ($requirement->description)
                                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ $requirement->description }}</p>
                                    @endif
                                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ $result['message'] }}</p>
                                </div>

                                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Required Credits</p>
                                        <p class="text-sm text-gray-950 dark:text-white">{{ $formatCredits($requirement->required_credits) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Earned Credits</p>
                                        <p class="text-sm text-gray-950 dark:text-white">{{ $formatCredits($result['earnedCredits']) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Minimum Grade</p>
                                        <p class="text-sm text-gray-950 dark:text-white">{{ $requirement->minimum_grade ?? '—' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Minimum Grade Points</p>
                                        <p class="text-sm text-gray-950 dark:text-white">{{ $formatCredits($requirement->minimum_grade_points) }}</p>
                                    </div>
                                </div>
                            </div>

                            @if ($result['matchedRecords']->isNotEmpty())
                                <div class="mt-4 overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                                            <thead class="bg-gray-50 dark:bg-white/5">
                                                <tr>
                                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Course Code</th>
                                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Course Title</th>
                                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Final Grade</th>
                                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Grade Label</th>
                                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Credits Earned</th>
                                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Applied Credits</th>
                                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Completed</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                                                @foreach ($result['matchedRecords'] as $entry)
                                                    @php
                                                        $record = $entry['record'] ?? $entry;
                                                        $appliedCredits = is_array($entry) ? ($entry['appliedCredits'] ?? $record->credits_earned) : $record->credits_earned;
                                                    @endphp
                                                    <tr>
                                                        <td class="px-4 py-3 text-sm text-gray-950 dark:text-white">{{ $record->course_code }}</td>
                                                        <td class="px-4 py-3 text-sm text-gray-950 dark:text-white">{{ $record->course_title }}</td>
                                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $record->final_grade ?? '—' }}</td>
                                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $record->grade_label ?? '—' }}</td>
                                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $formatCredits($record->credits_earned) }}</td>
                                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $formatCredits($appliedCredits) }}</td>
                                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $formatDate($record->completed_at) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach

    <div class="space-y-3">
        <h3 class="text-lg font-semibold text-gray-950 dark:text-white">Unused Academic Records</h3>

        @if ($unusedAcademicRecords->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 p-6 text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                No unused academic records remain after audit matching.
            </div>
        @else
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Course Code</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Course Title</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Credits Earned</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Completed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @foreach ($unusedAcademicRecords as $record)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-950 dark:text-white">{{ $record->course_code }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-950 dark:text-white">{{ $record->course_title }}</td>
                                    <td class="px-4 py-3 text-sm capitalize text-gray-700 dark:text-gray-300">{{ str_replace('_', ' ', $record->status ?? '—') }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $formatCredits($record->credits_earned) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $formatDate($record->completed_at) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>

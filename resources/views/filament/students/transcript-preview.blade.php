@php
    $formatCredits = static fn ($value) => $value === null ? '—' : number_format((float) $value, 2);
    $formatDate = static fn ($value) => $value?->format('M j, Y') ?? '—';
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
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Program</p>
                <p class="text-base text-gray-950 dark:text-white">{{ $student->program?->title ?? '—' }}</p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Institution</p>
                <p class="text-base text-gray-950 dark:text-white">{{ $student->institution?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Enrollment Date</p>
                <p class="text-base text-gray-950 dark:text-white">{{ $formatDate($student->enrollment_date) }}</p>
            </div>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Credits Attempted</p>
            <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($totalCreditsAttempted, 2) }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Credits Earned</p>
            <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($totalCreditsEarned, 2) }}</p>
        </div>
    </div>

    @forelse ($termGroups as $group)
        <div class="space-y-3">
            <h3 class="text-lg font-semibold text-gray-950 dark:text-white">{{ $group['label'] }}</h3>
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Course Code</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Course Title</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Credits Attempted</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Credits Earned</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Final Grade</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Completed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @foreach ($group['records'] as $record)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-950 dark:text-white">{{ $record->course_code }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-950 dark:text-white">{{ $record->course_title }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $formatCredits($record->credits_attempted) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $formatCredits($record->credits_earned) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $record->final_grade ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700 capitalize dark:text-gray-300">{{ str_replace('_', ' ', $record->status) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $formatDate($record->completed_at) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @empty
        <div class="rounded-xl border border-dashed border-gray-300 p-6 text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
            No academic records with academic terms are available for this student.
        </div>
    @endforelse

    @foreach ($otherGroups as $group)
        <div class="space-y-3">
            <h3 class="text-lg font-semibold text-gray-950 dark:text-white">{{ $group['label'] }}</h3>
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Course Code</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Course Title</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Credits Attempted</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Credits Earned</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Final Grade</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Completed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @foreach ($group['records'] as $record)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-950 dark:text-white">{{ $record->course_code }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-950 dark:text-white">{{ $record->course_title }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $formatCredits($record->credits_attempted) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $formatCredits($record->credits_earned) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $record->final_grade ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700 capitalize dark:text-gray-300">{{ str_replace('_', ' ', $record->status) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $formatDate($record->completed_at) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endforeach
</div>

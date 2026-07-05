@php
    $formatDate = static fn ($value) => $value?->format('M j, Y g:i A') ?? '—';
@endphp

<div class="space-y-6">
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">
        <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-950 dark:text-white">Section Summary</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Read-only preview only. No section progress is persisted or written back to enrollment, academic records, attendance, or master assessment data.
                </p>
            </div>
            <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-700 dark:bg-white/10 dark:text-gray-200">
                {{ $summary['progress_basis'] }}
            </span>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Institution</p>
                <p class="text-base font-semibold text-gray-950 dark:text-white">{{ $summary['institution_name'] }}</p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Course</p>
                <p class="text-base font-semibold text-gray-950 dark:text-white">{{ $summary['course_label'] }}</p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Academic Term</p>
                <p class="text-base text-gray-950 dark:text-white">{{ $summary['academic_term'] }}</p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Section Code</p>
                <p class="text-base text-gray-950 dark:text-white">{{ $summary['section_code'] }}</p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Progress Basis</p>
                <p class="text-base text-gray-950 dark:text-white">{{ $summary['progress_basis'] }}</p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Delivery Mode</p>
                <p class="text-base text-gray-950 dark:text-white">{{ $summary['delivery_mode'] }}</p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Capacity</p>
                <p class="text-base text-gray-950 dark:text-white">{{ $summary['capacity'] }}</p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Enrolled Count</p>
                <p class="text-base text-gray-950 dark:text-white">{{ $summary['enrolled_count'] }}</p>
            </div>
        </div>
    </div>

    <div class="space-y-3">
        <div class="flex items-center justify-between gap-3">
            <h3 class="text-lg font-semibold text-gray-950 dark:text-white">Student Progress Preview</h3>
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $studentRows->count() }} student record{{ $studentRows->count() === 1 ? '' : 's' }}</span>
        </div>

        @if ($studentRows->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 p-6 text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                No course enrollments are attached to this section.
            </div>
        @else
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Student Name</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Student Number</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Enrollment Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Progress Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Progress Basis Used</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Evidence Summary</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Last Activity Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @foreach ($studentRows as $row)
                                <tr class="align-top">
                                    <td class="px-4 py-3 text-sm text-gray-950 dark:text-white">{{ $row['student_name'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row['student_number'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row['enrollment_status_label'] }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $row['progress_status_badge_classes'] }}">
                                            {{ $row['progress_status_label'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row['progress_basis_used'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row['evidence_summary'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $formatDate($row['last_activity_date']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>

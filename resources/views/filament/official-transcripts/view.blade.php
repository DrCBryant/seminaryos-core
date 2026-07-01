@php
    $formatCredits = static fn ($value) => $value === null ? '—' : number_format((float) $value, 2);
    $formatDate = static fn ($value) => $value?->format('M j, Y') ?? '—';
    $showRecipientInfo = (bool) ($transcriptSettings['show_recipient_info'] ?? true);
    $showDeliveryMethod = (bool) ($transcriptSettings['show_delivery_method'] ?? true);
    $showPurpose = (bool) ($transcriptSettings['show_purpose'] ?? true);
    $showGradePoints = (bool) ($transcriptSettings['show_grade_points'] ?? false);
    $showStatus = (bool) ($transcriptSettings['show_status'] ?? true);
@endphp

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-yz3leL7XY8FkgxPjW6c8x2kgd2S4mM6zJpG1L0xJw8O1N8MPmMXxMvmP0xSg6u40qCMgfHdCqkfNNPJBbL4U1g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<div class="space-y-6" x-data="{
    exportPdf() {
        const element = document.getElementById('official-transcript-render');

        if (!element || typeof html2pdf === 'undefined') {
            return;
        }

        html2pdf()
            .set({
                margin: [0.5, 0.5, 0.5, 0.5],
                filename: @js($pdfFilename),
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' },
                pagebreak: { mode: ['css', 'legacy'] },
            })
            .from(element)
            .save();
    }
}">
    <div class="flex justify-end print:hidden">
        <button
            type="button"
            x-on:click="exportPdf()"
            class="inline-flex items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500"
        >
            Download PDF
        </button>
    </div>

    <div id="official-transcript-render" class="space-y-6 bg-white text-gray-950 dark:bg-gray-950 dark:text-white">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="space-y-3 text-center">
                <p class="text-sm font-medium uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400">{{ $transcript->institution?->name ?? 'Institution' }}</p>
                <h1 class="text-3xl font-bold text-gray-950 dark:text-white">{{ $transcriptSettings['transcript_title'] ?? 'Official Transcript' }}</h1>
                @if (filled($transcriptSettings['certification_statement'] ?? null))
                    <p class="mx-auto max-w-4xl text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $transcriptSettings['certification_statement'] }}</p>
                @endif
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Institution</p>
                <p class="text-base font-semibold text-gray-950 dark:text-white">{{ $transcript->institution?->name ?? '—' }}</p>
            </div>
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
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Enrollment Date</p>
                <p class="text-base text-gray-950 dark:text-white">{{ $formatDate($student->enrollment_date) }}</p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Transcript Number</p>
                <p class="text-base text-gray-950 dark:text-white">{{ $transcript->transcript_number }}</p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Issued Date</p>
                <p class="text-base text-gray-950 dark:text-white">{{ $formatDate($transcript->issued_at) }}</p>
            </div>
            @if ($showPurpose)
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Purpose</p>
                    <p class="text-base text-gray-950 dark:text-white">{{ $transcript->purpose ?? '—' }}</p>
                </div>
            @endif
            @if ($showDeliveryMethod)
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Delivery Method</p>
                    <p class="text-base text-gray-950 dark:text-white">{{ $transcript->delivery_method ? \App\Filament\Resources\OfficialTranscripts\Schemas\OfficialTranscriptForm::DELIVERY_METHOD_OPTIONS[$transcript->delivery_method] ?? $transcript->delivery_method : '—' }}</p>
                </div>
            @endif
            @if ($showRecipientInfo)
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Recipient Name</p>
                    <p class="text-base text-gray-950 dark:text-white">{{ $transcript->recipient_name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Recipient Email</p>
                    <p class="text-base text-gray-950 dark:text-white">{{ $transcript->recipient_email ?? '—' }}</p>
                </div>
            @endif
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
                                    @if ($showGradePoints)
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Grade Points</th>
                                    @endif
                                    @if ($showStatus)
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                                    @endif
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Completed</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                                @foreach ($group['lines'] as $line)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-gray-950 dark:text-white">{{ $line->course_code }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-950 dark:text-white">{{ $line->course_title }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $formatCredits($line->credits_attempted) }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $formatCredits($line->credits_earned) }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $line->final_grade ?? '—' }}</td>
                                        @if ($showGradePoints)
                                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $formatCredits($line->grade_points) }}</td>
                                        @endif
                                        @if ($showStatus)
                                            <td class="px-4 py-3 text-sm text-gray-700 capitalize dark:text-gray-300">{{ str_replace('_', ' ', $line->status) }}</td>
                                        @endif
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $formatDate($line->completed_at) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-gray-300 p-6 text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                No transcript lines with academic term labels are available.
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
                                    @if ($showGradePoints)
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Grade Points</th>
                                    @endif
                                    @if ($showStatus)
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                                    @endif
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Completed</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                                @foreach ($group['lines'] as $line)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-gray-950 dark:text-white">{{ $line->course_code }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-950 dark:text-white">{{ $line->course_title }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $formatCredits($line->credits_attempted) }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $formatCredits($line->credits_earned) }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $line->final_grade ?? '—' }}</td>
                                        @if ($showGradePoints)
                                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $formatCredits($line->grade_points) }}</td>
                                        @endif
                                        @if ($showStatus)
                                            <td class="px-4 py-3 text-sm text-gray-700 capitalize dark:text-gray-300">{{ str_replace('_', ' ', $line->status) }}</td>
                                        @endif
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $formatDate($line->completed_at) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach

        <div class="grid gap-4 lg:grid-cols-2">
            @if (filled($transcriptSettings['grading_scale_note'] ?? null))
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Grading Scale Note</h3>
                    <p class="mt-2 text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $transcriptSettings['grading_scale_note'] }}</p>
                </div>
            @endif

            @if (filled($transcriptSettings['accreditation_note'] ?? null))
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Accreditation Note</h3>
                    <p class="mt-2 text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $transcriptSettings['accreditation_note'] }}</p>
                </div>
            @endif
        </div>

        @if (filled($transcriptSettings['transcript_disclaimer'] ?? null) || filled($transcriptSettings['footer_statement'] ?? null) || filled($transcriptSettings['registrar_name'] ?? null) || filled($transcriptSettings['registrar_title'] ?? null))
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">
                @if (filled($transcriptSettings['transcript_disclaimer'] ?? null))
                    <div>
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Disclaimer</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $transcriptSettings['transcript_disclaimer'] }}</p>
                    </div>
                @endif

                <div class="mt-6 flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
                    <div>
                        @if (filled($transcriptSettings['footer_statement'] ?? null))
                            <p class="text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $transcriptSettings['footer_statement'] }}</p>
                        @endif
                    </div>
                    <div class="min-w-56 border-t border-gray-300 pt-3 text-sm dark:border-white/20">
                        <p class="font-semibold text-gray-950 dark:text-white">{{ $transcriptSettings['registrar_name'] ?? 'Registrar' }}</p>
                        <p class="text-gray-600 dark:text-gray-400">{{ $transcriptSettings['registrar_title'] ?? 'Office of the Registrar' }}</p>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

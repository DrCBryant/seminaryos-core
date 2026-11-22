@php
    $details = [
        'Student' => $document['student_name'] ?? null,
        'Student Number' => $document['student_number'] ?? null,
        'Program' => $document['program']['title'] ?? null,
        'Enrollment Date' => isset($document['enrollment_date'])
            ? \Illuminate\Support\Carbon::parse($document['enrollment_date'])->format('M j, Y')
            : null,
        'Transcript Number' => $transcript->transcript_number,
        'Issued Date' => $transcript->issued_at?->format('M j, Y'),
    ];
    $showGradePoints = $transcriptSettings['show_grade_points'] ?? false;
    $showStatus = $transcriptSettings['show_status'] ?? false;
    $groups = $termGroups->concat($otherGroups);
    $fixedColumns = 6 + (int) $showGradePoints + (int) $showStatus;
    $formatCredits = fn ($credits) => $credits === null ? '—' : number_format((float) $credits, 2);

    if (filled($document['program']['code'] ?? null)) {
        $details['Program'] .= ' ('.$document['program']['code'].')';
    }

    if ($transcriptSettings['show_purpose'] ?? false) {
        $details['Purpose'] = $transcript->purpose;
    }

    if ($transcriptSettings['show_delivery_method'] ?? false) {
        $details['Delivery Method'] = $document['delivery_label'] ?? null;
    }

    if ($transcriptSettings['show_recipient_info'] ?? false) {
        $details['Recipient'] = $transcript->recipient_name;
        $details['Recipient Email'] = $transcript->recipient_email;
    }
@endphp

@if (! ($printPage ?? false))
    <link rel="stylesheet" href="{{ asset('css/transcript-document.css') }}">
    <div class="transcript-controls">
        <a href="{{ route('official-transcripts.print', $transcript->uuid) }}" target="_blank" rel="noopener">Print / Save PDF</a>
    </div>
@endif

<article class="transcript-document">
    @if ($legacy)
        <p class="transcript-legacy">Legacy issued transcript. Issuance-time identity, settings, and GPA were not captured. This view shows the surviving stored document fields and academic lines.</p>
    @endif

    <header class="transcript-heading">
        @if (filled($document['institution_name'] ?? null))
            <p class="transcript-institution">{{ $document['institution_name'] }}</p>
        @endif
        <h1>{{ $transcriptSettings['transcript_title'] ?? 'Official Transcript' }}</h1>
    </header>

    <dl class="transcript-details">
        @foreach ($details as $label => $value)
            @if (filled($value))
                <div>
                    <dt>{{ $label }}</dt>
                    <dd>{{ $value }}</dd>
                </div>
            @endif
        @endforeach
    </dl>

    @forelse ($groups as $group)
        <table class="transcript-coursework">
            <colgroup>
                <col class="transcript-col-code">
                <col>
                <col class="transcript-col-attempted">
                <col class="transcript-col-credits">
                <col class="transcript-col-grade">
                @if ($showGradePoints)
                    <col class="transcript-col-credits">
                @endif
                @if ($showStatus)
                    <col class="transcript-col-status">
                @endif
                <col class="transcript-col-date">
            </colgroup>
            <thead>
                <tr>
                    <th colspan="{{ $fixedColumns }}" class="transcript-term" scope="colgroup">{{ $group['label'] }}</th>
                </tr>
                <tr>
                    <th scope="col">Code</th>
                    <th scope="col">Course</th>
                    <th scope="col" class="transcript-number">Attempted</th>
                    <th scope="col" class="transcript-number">Earned</th>
                    <th scope="col" class="transcript-number">Grade</th>
                    @if ($showGradePoints)
                        <th scope="col" class="transcript-number">Points</th>
                    @endif
                    @if ($showStatus)
                        <th scope="col">Status</th>
                    @endif
                    <th scope="col">Completed</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($group['lines'] as $line)
                    <tr>
                        <td>{{ $line->course_code }}</td>
                        <td>{{ $line->course_title }}</td>
                        <td class="transcript-number">{{ $formatCredits($line->credits_attempted) }}</td>
                        <td class="transcript-number">{{ $formatCredits($line->credits_earned) }}</td>
                        <td class="transcript-number">{{ $line->final_grade ?? '—' }}</td>
                        @if ($showGradePoints)
                            <td class="transcript-number">{{ $formatCredits($line->grade_points) }}</td>
                        @endif
                        @if ($showStatus)
                            <td>{{ ucfirst(str_replace('_', ' ', $line->status)) }}</td>
                        @endif
                        <td>{{ $line->completed_at?->format('m/d/Y') ?? '—' }}</td>
                    </tr>
                @endforeach
                @if ($group['lines']->whereNotNull('credits_attempted')->isNotEmpty() || $group['lines']->whereNotNull('credits_earned')->isNotEmpty())
                    <tr class="transcript-term-total">
                        <th colspan="2" scope="row">Credit subtotal</th>
                        <td class="transcript-number">{{ $formatCredits($group['lines']->whereNotNull('credits_attempted')->isEmpty() ? null : $group['lines']->sum('credits_attempted')) }}</td>
                        <td class="transcript-number">{{ $formatCredits($group['lines']->whereNotNull('credits_earned')->isEmpty() ? null : $group['lines']->sum('credits_earned')) }}</td>
                        <td colspan="{{ $fixedColumns - 4 }}"></td>
                    </tr>
                @endif
            </tbody>
        </table>
    @empty
        <p>No academic snapshot lines are available.</p>
    @endforelse

    @if ($groups->isNotEmpty())
        <p class="transcript-total"><strong>Total credits</strong> · {{ $formatCredits($totalCreditsAttempted) }} attempted · {{ $formatCredits($totalCreditsEarned) }} earned</p>
    @endif

    @if (! empty($document['awards']))
        <section class="transcript-statement">
            <h2>Awarded Credentials</h2>
            @foreach ($document['awards'] as $award)
                <p>{{ $award['program_title'] }} ({{ $award['program_code'] }}) · {{ $award['credential_type'] }} · {{ $award['conferred_on'] }}</p>
            @endforeach
        </section>
    @endif

    <footer class="transcript-footer">
        @foreach ([
            'grading_scale_note' => 'Grading Scale',
            'accreditation_note' => 'Accreditation',
            'certification_statement' => 'Registrar Certification',
            'transcript_disclaimer' => null,
            'footer_statement' => null,
        ] as $key => $label)
            @if (filled($transcriptSettings[$key] ?? null))
                <section class="transcript-statement">
                    @if ($label)
                        <h2>{{ $label }}</h2>
                    @endif
                    <p>{{ $transcriptSettings[$key] }}</p>
                </section>
            @endif
        @endforeach
        @if ($transcriptSettings['show_registrar_footer'] ?? false)
            <p class="transcript-registrar">
                <strong>{{ $transcriptSettings['registrar_name'] }}</strong><br>
                {{ $transcriptSettings['registrar_title'] }}
            </p>
        @endif
    </footer>
</article>

@php
    $courseOffering = $this->courseOffering;
    $course = $courseOffering->course;
    $term = $courseOffering->academicTerm;
    $institution = $courseOffering->institution;
    $capacitySummary = $this->getCapacitySummary();
    $facultyAssignments = $this->getFacultyAssignments();
    $studentEnrollments = $this->getStudentEnrollments();
    $statusColors = [
        \App\Models\CourseOffering::CAPACITY_STATUS_AVAILABLE => '#166534',
        \App\Models\CourseOffering::CAPACITY_STATUS_NEARLY_FULL => '#9a3412',
        \App\Models\CourseOffering::CAPACITY_STATUS_FULL => '#991b1b',
    ];
    $statusBackgrounds = [
        \App\Models\CourseOffering::CAPACITY_STATUS_AVAILABLE => '#dcfce7',
        \App\Models\CourseOffering::CAPACITY_STATUS_NEARLY_FULL => '#ffedd5',
        \App\Models\CourseOffering::CAPACITY_STATUS_FULL => '#fee2e2',
    ];
@endphp

<div class="roster-page">
    <style>
        .roster-page {
            --roster-ink: #111827;
            --roster-muted: #4b5563;
            --roster-line: #d1d5db;
            --roster-panel: #ffffff;
            --roster-accent: #0f172a;
            --roster-accent-soft: #e2e8f0;
            color: var(--roster-ink);
        }

        .roster-shell {
            max-width: 1100px;
            margin: 0 auto;
            padding: 1.5rem;
            background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
        }

        .roster-card {
            background: var(--roster-panel);
            border: 1px solid var(--roster-line);
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
        }

        .roster-header {
            padding: 2rem;
            display: grid;
            gap: 1.5rem;
            border-bottom: 4px solid var(--roster-accent);
        }

        .roster-eyebrow {
            margin: 0;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: var(--roster-muted);
        }

        .roster-title {
            margin: 0.25rem 0 0;
            font-size: clamp(1.8rem, 3vw, 2.8rem);
            line-height: 1.05;
            font-weight: 800;
            color: var(--roster-accent);
        }

        .roster-subtitle {
            margin: 0.5rem 0 0;
            font-size: 1rem;
            color: var(--roster-muted);
        }

        .roster-meta-grid,
        .roster-stats-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }

        .roster-meta-item,
        .roster-stat {
            padding: 1rem 1.1rem;
            border: 1px solid var(--roster-line);
            border-radius: 0.85rem;
            background: #fff;
        }

        .roster-label {
            display: block;
            margin-bottom: 0.35rem;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--roster-muted);
        }

        .roster-value {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: var(--roster-ink);
        }

        .roster-stat-value {
            margin: 0;
            font-size: 2rem;
            font-weight: 800;
            line-height: 1;
            color: var(--roster-accent);
        }

        .roster-status {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.45rem 0.8rem;
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.03em;
        }

        .roster-section {
            margin-top: 1.5rem;
            padding: 1.5rem;
        }

        .roster-section-title {
            margin: 0 0 0.35rem;
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--roster-accent);
        }

        .roster-section-copy {
            margin: 0 0 1rem;
            color: var(--roster-muted);
            font-size: 0.95rem;
        }

        .roster-table-wrap {
            overflow-x: auto;
            border: 1px solid var(--roster-line);
            border-radius: 0.85rem;
        }

        .roster-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }

        .roster-table th,
        .roster-table td {
            padding: 0.9rem 1rem;
            text-align: left;
            vertical-align: top;
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.92rem;
        }

        .roster-table th {
            background: #f8fafc;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--roster-muted);
        }

        .roster-table tbody tr:last-child td {
            border-bottom: none;
        }

        .roster-name {
            font-weight: 700;
            color: var(--roster-ink);
        }

        .roster-secondary {
            color: var(--roster-muted);
            font-size: 0.86rem;
        }

        .roster-empty {
            padding: 1rem 1.1rem;
            border: 1px dashed var(--roster-line);
            border-radius: 0.85rem;
            color: var(--roster-muted);
            background: #fcfcfd;
        }

        .roster-toolbar {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 1rem;
        }

        .roster-print-button {
            border: none;
            border-radius: 999px;
            background: var(--roster-accent);
            color: #fff;
            padding: 0.8rem 1.2rem;
            font-size: 0.92rem;
            font-weight: 700;
            cursor: pointer;
        }

        @media print {
            .fi-page-header,
            .fi-sidebar,
            .fi-topbar,
            .roster-toolbar {
                display: none !important;
            }

            .fi-main,
            .fi-main-ctn,
            .fi-page,
            .roster-shell {
                padding: 0 !important;
                margin: 0 !important;
                max-width: none !important;
                background: #fff !important;
            }

            .roster-card,
            .roster-meta-item,
            .roster-stat,
            .roster-table-wrap,
            .roster-empty {
                box-shadow: none !important;
                break-inside: avoid;
            }

            .roster-section {
                page-break-inside: avoid;
            }
        }
    </style>

    <div class="roster-shell">
        <div class="roster-toolbar print:hidden">
            <button type="button" class="roster-print-button" onclick="window.print()">Print Roster</button>
        </div>

        <article class="roster-card">
            <header class="roster-header">
                <div>
                    <p class="roster-eyebrow">{{ $institution?->name ?? 'Institution' }}</p>
                    <h1 class="roster-title">{{ $course?->code ?? 'Course' }} — {{ $course?->title ?? $courseOffering->title ?? 'Untitled Course Offering' }}</h1>
                    <p class="roster-subtitle">Printable course offering roster for {{ $term?->name ?? 'Academic Term' }} · Section {{ $courseOffering->section_code }}</p>
                </div>

                <div class="roster-meta-grid">
                    <div class="roster-meta-item"><span class="roster-label">Academic Term</span><p class="roster-value">{{ $term?->name ?? '—' }}{{ $term?->academic_year ? ' ('.$term->academic_year.')' : '' }}</p></div>
                    <div class="roster-meta-item"><span class="roster-label">Section Code</span><p class="roster-value">{{ $courseOffering->section_code }}</p></div>
                    <div class="roster-meta-item"><span class="roster-label">Delivery Mode</span><p class="roster-value">{{ $this->formatDeliveryMode($courseOffering->delivery_mode) }}</p></div>
                    <div class="roster-meta-item"><span class="roster-label">Location</span><p class="roster-value">{{ $courseOffering->location ?: '—' }}</p></div>
                    <div class="roster-meta-item"><span class="roster-label">Meeting Pattern</span><p class="roster-value">{{ $courseOffering->meeting_pattern ?: '—' }}</p></div>
                    <div class="roster-meta-item"><span class="roster-label">Start Date</span><p class="roster-value">{{ $this->formatDate($courseOffering->start_date) }}</p></div>
                    <div class="roster-meta-item"><span class="roster-label">End Date</span><p class="roster-value">{{ $this->formatDate($courseOffering->end_date) }}</p></div>
                </div>

                <div class="roster-stats-grid">
                    <div class="roster-stat"><span class="roster-label">Capacity</span><p class="roster-stat-value">{{ $capacitySummary['capacity'] }}</p></div>
                    <div class="roster-stat"><span class="roster-label">Enrolled Count</span><p class="roster-stat-value">{{ $capacitySummary['enrolled_count'] }}</p></div>
                    <div class="roster-stat"><span class="roster-label">Available Seats</span><p class="roster-stat-value">{{ $capacitySummary['available_seats'] }}</p></div>
                    <div class="roster-stat">
                        <span class="roster-label">Capacity Status</span>
                        <p class="roster-value">
                            <span class="roster-status" style="color: {{ $statusColors[$capacitySummary['capacity_status']] ?? '#1f2937' }}; background: {{ $statusBackgrounds[$capacitySummary['capacity_status']] ?? '#e5e7eb' }};">
                                {{ $capacitySummary['capacity_status'] }}
                            </span>
                        </p>
                    </div>
                </div>
            </header>

            <section class="roster-section">
                <h2 class="roster-section-title">Assigned Faculty</h2>
                <p class="roster-section-copy">Faculty assignments are loaded from teaching assignments on this course offering and sorted by role, then faculty name.</p>

                @if ($facultyAssignments->isEmpty())
                    <div class="roster-empty">No faculty assignments are attached to this course offering.</div>
                @else
                    <div class="roster-table-wrap">
                        <table class="roster-table">
                            <thead>
                                <tr>
                                    <th>Faculty</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Assigned At</th>
                                    <th>Ended At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($facultyAssignments as $assignment)
                                    <tr>
                                        <td>
                                            <div class="roster-name">{{ $assignment->faculty?->full_name ?? '—' }}</div>
                                        </td>
                                        <td>{{ $assignment->faculty?->email ?? '—' }}</td>
                                        <td>{{ $this->formatAssignmentRole($assignment->role) }}</td>
                                        <td>{{ $assignment->status ? str($assignment->status)->replace('_', ' ')->title()->toString() : '—' }}</td>
                                        <td>{{ $this->formatDate($assignment->assigned_at) }}</td>
                                        <td>{{ $this->formatDate($assignment->ended_at) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="roster-section">
                <h2 class="roster-section-title">Enrolled Students</h2>
                <p class="roster-section-copy">Student rows are loaded from course enrollments on this course offering and sorted by last name, first name, then student number.</p>

                @if ($studentEnrollments->isEmpty())
                    <div class="roster-empty">No enrollments are attached to this course offering.</div>
                @else
                    <div class="roster-table-wrap">
                        <table class="roster-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Student Number</th>
                                    <th>Email</th>
                                    <th>Enrollment Status</th>
                                    <th>Enrolled At</th>
                                    <th>Completed At</th>
                                    <th>Final Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($studentEnrollments as $enrollment)
                                    <tr>
                                        <td>
                                            <div class="roster-name">{{ $enrollment->student?->full_name ?? '—' }}</div>
                                        </td>
                                        <td>{{ $enrollment->student?->student_number ?? '—' }}</td>
                                        <td>{{ $enrollment->student?->email ?? '—' }}</td>
                                        <td>{{ $enrollment->status ? str($enrollment->status)->replace('_', ' ')->title()->toString() : '—' }}</td>
                                        <td>{{ $this->formatDate($enrollment->enrolled_at, 'M j, Y g:i A') }}</td>
                                        <td>{{ $this->formatDate($enrollment->completed_at, 'M j, Y g:i A') }}</td>
                                        <td>{{ $enrollment->final_grade ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </article>
    </div>
</div>

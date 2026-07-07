@php
    $courseOffering = $this->courseOffering;
    $course = $courseOffering->course;
    $term = $courseOffering->academicTerm;
    $institution = $courseOffering->institution;
    $summary = $this->getSummary();
    $enrollmentReviews = $this->getEnrollmentReviews();
@endphp

<div class="completion-review-page">
    <style>
        .completion-review-page {
            --review-ink: #111827;
            --review-muted: #4b5563;
            --review-line: #d1d5db;
            --review-panel: #ffffff;
            --review-accent: #0f172a;
            --review-accent-soft: #e2e8f0;
            --review-info-bg: #eff6ff;
            --review-info-border: #bfdbfe;
            color: var(--review-ink);
        }

        .completion-review-shell {
            max-width: 1280px;
            margin: 0 auto;
            padding: 1.5rem;
            background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
        }

        .completion-review-card,
        .completion-review-section {
            background: var(--review-panel);
            border: 1px solid var(--review-line);
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
        }

        .completion-review-header {
            padding: 2rem;
            display: grid;
            gap: 1.5rem;
            border-bottom: 4px solid var(--review-accent);
        }

        .completion-review-eyebrow {
            margin: 0;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: var(--review-muted);
        }

        .completion-review-title {
            margin: 0.25rem 0 0;
            font-size: clamp(1.8rem, 3vw, 2.8rem);
            line-height: 1.05;
            font-weight: 800;
            color: var(--review-accent);
        }

        .completion-review-subtitle {
            margin: 0.5rem 0 0;
            font-size: 1rem;
            color: var(--review-muted);
        }

        .completion-review-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }

        .completion-review-grid--metrics {
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        }

        .completion-review-item,
        .completion-review-stat {
            padding: 1rem 1.1rem;
            border: 1px solid var(--review-line);
            border-radius: 0.85rem;
            background: #fff;
        }

        .completion-review-stat {
            position: relative;
            overflow: hidden;
        }

        .completion-review-stat::before {
            content: '';
            position: absolute;
            inset: 0 auto 0 0;
            width: 4px;
            background: var(--review-stat-accent, var(--review-accent));
        }

        .completion-review-label {
            display: block;
            margin-bottom: 0.35rem;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--review-muted);
        }

        .completion-review-value {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: var(--review-ink);
        }

        .completion-review-stat-value {
            margin: 0;
            font-size: 2rem;
            font-weight: 800;
            line-height: 1;
            color: var(--review-accent);
        }

        .completion-review-section {
            margin-top: 1.5rem;
            padding: 1.5rem;
        }

        .completion-review-section-title {
            margin: 0 0 0.35rem;
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--review-accent);
        }

        .completion-review-section-copy {
            margin: 0;
            color: var(--review-muted);
            font-size: 0.95rem;
        }

        .completion-review-callout {
            margin-top: 1rem;
            padding: 1rem 1.1rem;
            border: 1px solid var(--review-info-border);
            border-radius: 0.85rem;
            background: var(--review-info-bg);
            color: #1e3a8a;
            font-size: 0.94rem;
            line-height: 1.55;
        }

        .completion-review-table-wrap {
            overflow-x: auto;
            margin-top: 1rem;
            border: 1px solid var(--review-line);
            border-radius: 0.85rem;
        }

        .completion-review-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }

        .completion-review-table th,
        .completion-review-table td {
            padding: 0.9rem 1rem;
            text-align: left;
            vertical-align: top;
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.92rem;
        }

        .completion-review-table th {
            background: #f8fafc;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--review-muted);
        }

        .completion-review-table tbody tr:last-child td {
            border-bottom: none;
        }

        .completion-review-name {
            font-weight: 700;
            color: var(--review-ink);
        }

        .completion-review-secondary {
            color: var(--review-muted);
            font-size: 0.86rem;
        }

        .completion-review-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            border-radius: 999px;
            padding: 0.35rem 0.75rem;
            font-size: 0.8rem;
            font-weight: 700;
            line-height: 1.2;
            white-space: nowrap;
            border: 1px solid transparent;
        }

        .completion-review-badge::before {
            content: '';
            width: 0.45rem;
            height: 0.45rem;
            border-radius: 999px;
            background: currentColor;
            opacity: 0.75;
        }

        .completion-review-guidance-list {
            margin: 0;
            padding-left: 1.15rem;
            color: #1e3a8a;
        }

        .completion-review-guidance-list li + li {
            margin-top: 0.4rem;
        }

        .completion-review-next-step {
            margin-top: 0.55rem;
            padding: 0.7rem 0.8rem;
            border-radius: 0.75rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .completion-review-next-step-label {
            display: block;
            margin-bottom: 0.25rem;
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--review-muted);
        }

        .completion-review-next-step-copy {
            margin: 0;
            color: var(--review-ink);
            font-size: 0.86rem;
            line-height: 1.45;
        }

        .completion-review-enrollment-cell {
            display: grid;
            gap: 0.45rem;
        }

        .completion-review-link-note {
            color: var(--review-muted);
            font-size: 0.82rem;
            line-height: 1.4;
        }

        .completion-review-link {
            color: #1d4ed8;
            font-weight: 700;
            text-decoration: none;
        }

        .completion-review-link:hover {
            text-decoration: underline;
        }

        .completion-review-empty {
            margin-top: 1rem;
            padding: 1rem 1.1rem;
            border: 1px dashed var(--review-line);
            border-radius: 0.85rem;
            color: var(--review-muted);
            background: #fcfcfd;
        }
    </style>

    <div class="completion-review-shell">
        <article class="completion-review-card">
            <header class="completion-review-header">
                <div>
                    <p class="completion-review-eyebrow">{{ $institution?->name ?? 'Institution' }}</p>
                    <h1 class="completion-review-title">{{ $course?->code ?? 'Course' }} — {{ $course?->title ?? $courseOffering->title ?? 'Untitled Course Offering' }}</h1>
                    <p class="completion-review-subtitle">Completion review for {{ $term?->name ?? 'Academic Term' }} · Section {{ $courseOffering->section_code }}</p>
                </div>

                <div class="completion-review-grid">
                    <div class="completion-review-item">
                        <span class="completion-review-label">Institution</span>
                        <p class="completion-review-value">{{ $summary['institution_name'] }}</p>
                    </div>
                    <div class="completion-review-item">
                        <span class="completion-review-label">Course</span>
                        <p class="completion-review-value">{{ $summary['course_code_title'] }}</p>
                    </div>
                    <div class="completion-review-item">
                        <span class="completion-review-label">Academic Term</span>
                        <p class="completion-review-value">{{ $summary['academic_term'] }}</p>
                    </div>
                    <div class="completion-review-item">
                        <span class="completion-review-label">Section Code</span>
                        <p class="completion-review-value">{{ $summary['section_code'] }}</p>
                    </div>
                    <div class="completion-review-item">
                        <span class="completion-review-label">Progress Basis</span>
                        <p class="completion-review-value">{{ $summary['progress_basis'] }}</p>
                    </div>
                    <div class="completion-review-item">
                        <span class="completion-review-label">Delivery Mode</span>
                        <p class="completion-review-value">{{ $summary['delivery_mode'] }}</p>
                    </div>
                </div>

                <div class="completion-review-grid">
                    <div class="completion-review-stat">
                        <span class="completion-review-label">Capacity</span>
                        <p class="completion-review-stat-value">{{ $summary['capacity'] }}</p>
                    </div>
                    <div class="completion-review-stat">
                        <span class="completion-review-label">Enrolled Count</span>
                        <p class="completion-review-stat-value">{{ $summary['enrolled_count'] }}</p>
                    </div>
                    <div class="completion-review-stat">
                        <span class="completion-review-label">Completed Enrollment Count</span>
                        <p class="completion-review-stat-value">{{ $summary['completed_enrollment_count'] }}</p>
                    </div>
                    <div class="completion-review-stat">
                        <span class="completion-review-label">Ready to Complete Count</span>
                        <p class="completion-review-stat-value">{{ $summary['ready_to_complete_count'] }}</p>
                    </div>
                    <div class="completion-review-stat">
                        <span class="completion-review-label">Needs Override Count</span>
                        <p class="completion-review-stat-value">{{ $summary['needs_override_count'] }}</p>
                    </div>
                    <div class="completion-review-stat">
                        <span class="completion-review-label">Not Evaluable Count</span>
                        <p class="completion-review-stat-value">{{ $summary['not_evaluable_count'] }}</p>
                    </div>
                </div>

                <div class="completion-review-grid completion-review-grid--metrics">
                    @foreach (['already_completed', 'ready_to_complete', 'override_required', 'not_evaluable', 'not_started', 'in_progress'] as $readinessKey)
                        <div class="completion-review-stat" style="--review-stat-accent: {{ str($this->readinessBadgeClasses($readinessKey))->after('color: ')->before(';')->toString() }};">
                            <span class="completion-review-label">{{ $this->readinessCountLabel($readinessKey) }}</span>
                            <p class="completion-review-stat-value">{{ $summary[$readinessKey . '_count'] ?? 0 }}</p>
                        </div>
                    @endforeach
                </div>
            </header>
        </article>

        <section class="completion-review-section">
            <h2 class="completion-review-section-title">Completion review guidance</h2>
            <p class="completion-review-section-copy">This page helps administrators review whether each student appears ready for official enrollment completion.</p>
            <div class="completion-review-callout">
                <ul class="completion-review-guidance-list">
                    <li>Only enrollments evaluated as ready to complete are candidates for guarded bulk completion.</li>
                    <li>It reviews whether each student appears ready for official enrollment completion.</li>
                    <li>Official completion still happens through the existing Complete Enrollment action.</li>
                    <li>Non-satisfied or not-evaluable progress requires an override reason during completion.</li>
                    <li>No bulk override completion is available.</li>
                </ul>
            </div>
        </section>

        <section class="completion-review-section">
            <h2 class="completion-review-section-title">Enrollment completion review</h2>
            <p class="completion-review-section-copy">Each enrollment is evaluated read-only through the section progress evaluator for this course offering.</p>

            @if ($enrollmentReviews->isEmpty())
                <div class="completion-review-empty">No enrollments are linked to this course offering.</div>
            @else
                <div class="completion-review-table-wrap">
                    <table class="completion-review-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Enrollment Status</th>
                                <th>Completed At</th>
                                <th>Progress Basis</th>
                                <th>Progress Status</th>
                                <th>Evidence Summary</th>
                                <th>Last Activity</th>
                                <th>Completion Readiness</th>
                                <th>Recommended Action</th>
                                <th>Enrollment</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($enrollmentReviews as $review)
                                <tr>
                                    <td>
                                        <div class="completion-review-name">{{ $review['student_name'] }}</div>
                                        <div class="completion-review-secondary">Student #{{ $review['student_number'] }}</div>
                                    </td>
                                    <td>{{ $this->formatEnrollmentStatus($review['enrollment_status']) }}</td>
                                    <td>{{ $this->formatDateTime($review['completed_at']) }}</td>
                                    <td>{{ $review['progress_basis'] }}</td>
                                    <td>
                                        <span class="completion-review-badge" style="{{ $review['progress_badge_classes'] }}">
                                            {{ $review['progress_status_label'] }}
                                        </span>
                                    </td>
                                    <td>{{ $review['evidence_summary'] }}</td>
                                    <td>{{ $this->formatDate($review['last_activity_date']) }}</td>
                                    <td>
                                        <span class="completion-review-badge" style="{{ $this->readinessBadgeClasses($review['readiness']) }}">
                                            {{ $this->formatReadinessLabel($review['readiness']) }}
                                        </span>
                                        @if ($review['requires_override'])
                                            <div class="completion-review-secondary" style="margin-top: 0.45rem;">Override reason will be required during completion.</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="completion-review-next-step">
                                            <span class="completion-review-next-step-label">Next Step</span>
                                            <p class="completion-review-next-step-copy">{{ $this->nextStepLabel($review['readiness']) }}</p>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="completion-review-enrollment-cell">
                                            <a href="{{ $review['edit_url'] }}" class="completion-review-link">Open Enrollment</a>
                                            <div class="completion-review-link-note">Use the enrollment record to access the existing Complete Enrollment workflow.</div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</div>

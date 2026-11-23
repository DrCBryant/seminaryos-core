<x-filament-panels::page>
    <link rel="stylesheet" href="{{ asset('css/student-portal.css') }}">

    <div class="student-portal faculty-portal">
        <div class="student-portal-context">
            <p>{{ $institutionName }} <span aria-hidden="true">/</span> {{ $name }}</p>
            <div>
                @if ($studentUrl)
                    <a href="{{ $studentUrl }}">Student Portal</a>
                @endif
                @if ($registrarUrl)
                    <a class="ms-3" href="{{ $registrarUrl }}">Registrar workspace</a>
                @endif
            </div>
        </div>

        <nav class="student-portal-nav" aria-label="Faculty portal">
            @foreach ($navigation as $key => $label)
                <a href="{{ route('filament.faculty.pages.overview', $key === 'overview' ? [] : ['section' => $key]) }}"
                    @if ($key === $section) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        @if ($section === 'overview')
            <div class="student-portal-stats">
                <a href="{{ route('filament.faculty.pages.overview', ['section' => 'mentees']) }}">
                    <strong>{{ $mentees->count() }}</strong><span>Assigned mentees</span>
                </a>
                <a href="{{ route('filament.faculty.pages.overview', ['section' => 'courses']) }}">
                    <strong>{{ $assignments->count() }}</strong><span>Teaching assignments</span>
                </a>
                <a href="{{ route('filament.faculty.pages.overview', ['section' => 'mentees']) }}">
                    <strong>{{ $pendingAssessments }}</strong><span>Mentee assessments submitted</span>
                </a>
                <a href="{{ route('filament.faculty.pages.overview', ['section' => 'courses']) }}">
                    <strong>{{ $pendingSubmissions }}</strong><span>Section submissions awaiting review</span>
                </a>
            </div>
        @endif

        @if ($section !== 'courses')
            <section class="student-portal-group">
                <h2>Assigned mentees</h2>
                <ul>
                    @forelse ($mentees as $item)
                        <li>
                            <a href="{{ route('filament.faculty.pages.overview', ['section' => 'mentees', 'selection' => $item['id']]) }}"
                                @if ($mentee && $selection === $item['id']) aria-current="true" @endif>{{ $item['name'] }}</a>
                            <span>{{ $item['program'] ?? 'No program assigned' }}</span>
                        </li>
                    @empty
                        <li>No students are assigned to you.</li>
                    @endforelse
                </ul>
            </section>
        @endif

        @if ($section !== 'mentees')
            <section class="student-portal-group">
                <h2>Teaching assignments</h2>
                <ul>
                    @forelse ($assignments as $item)
                        <li>
                            <div>
                                <a href="{{ route('filament.faculty.pages.overview', ['section' => 'courses', 'selection' => $item['id']]) }}"
                                    @if ($course && $selection === $item['id']) aria-current="true" @endif>{{ $item['title'] }} · {{ $item['section'] ?? 'Direct assignment' }}</a>
                                <small>{{ $item['term'] ?? 'No academic term assigned' }}</small>
                            </div>
                            <span class="student-portal-status">{{ $item['role'] }}</span>
                        </li>
                    @empty
                        <li>No current teaching assignments.</li>
                    @endforelse
                </ul>
            </section>
        @endif

        @if ($mentee)
            <h2>{{ $mentee['name'] }} · {{ $mentee['program'] ?? 'No program assigned' }}</h2>
            @include('filament.student-portal.table', [
                'label' => 'Program competencies',
                'columns' => ['title' => 'Competency', 'status' => 'Attainment', 'course' => 'Supporting coursework', 'evidence' => 'Recorded evidence', 'assessments' => 'Mapped assessments', 'decision' => 'Verified mastery'],
                'rows' => $mentee['competencies'],
                'emptyMessage' => 'No competency requirements are configured for this program.',
            ])
            <p class="student-portal-note">A passed attempt supports section progress. An assigned mentor can finalize it as mastery for the mapped competencies in this program.</p>
            <h2>Section Master Assessment history</h2>
            @include('filament.student-portal.table', [
                'label' => 'Section Master Assessment history',
                'columns' => ['title' => 'Assessment', 'status' => 'Attempt status', 'submitted' => 'Submitted', 'assessed' => 'Assessed', 'resultFinalized' => 'Result finalized', 'finalized' => 'Mastery finalized'],
                'rows' => $mentee['attempts'],
                'emptyMessage' => 'No Master Assessment attempts are on record.',
            ])
            @foreach ($mentee['attempts']->where('canReview', true) as $attempt)
                <div class="student-portal-context">
                    <span>{{ $attempt['title'] }}</span>
                    <x-filament::button wire:click="mountAction('reviewAssessment', { attempt: {{ $attempt['id'] }} })">
                        Review assessment
                    </x-filament::button>
                </div>
            @endforeach
            @foreach ($mentee['attempts']->where('canFinalize', true) as $attempt)
                <div class="student-portal-context">
                    <span>{{ $attempt['title'] }}</span>
                    <x-filament::button wire:click="mountAction('finalizeMastery', { attempt: {{ $attempt['id'] }} })">
                        Finalize mastery
                    </x-filament::button>
                </div>
            @endforeach
        @endif

        @if ($course)
            <h2>{{ $course['title'] }} · {{ $course['section'] ?? 'Direct assignment' }}</h2>
            @include('filament.student-portal.table', [
                'label' => 'Enrollment and course progress',
                'columns' => ['title' => 'Student', 'status' => 'Enrollment', 'progress' => 'Section progress', 'attendance' => 'Attendance', 'submissions' => 'Submissions', 'outcome' => 'Finalized outcome', 'completed' => 'Completed'],
                'rows' => $course['enrollments'],
                'emptyMessage' => 'No enrollments match this teaching assignment.',
            ])
        @endif
    </div>
</x-filament-panels::page>

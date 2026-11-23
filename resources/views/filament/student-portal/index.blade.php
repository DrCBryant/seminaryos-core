<x-filament-panels::page>
    <link rel="stylesheet" href="{{ asset('css/student-portal.css') }}">

    <div class="student-portal">
        <div class="student-portal-context">
            <p>{{ $institutionName }} <span aria-hidden="true">/</span> {{ $name }}</p>
            <div>
                @if ($facultyUrl)
                    <a href="{{ $facultyUrl }}">Faculty Portal</a>
                @endif
                @if ($registrarUrl)
                    <a class="ms-3" href="{{ $registrarUrl }}">Registrar workspace</a>
                @endif
            </div>
        </div>

        <nav class="student-portal-nav" aria-label="Student portal">
            @foreach ($navigation as $key => $label)
                <a href="{{ route('filament.student.pages.overview', $key === 'overview' ? [] : ['section' => $key]) }}"
                    @if ($key === $section) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        @switch($section)
            @case('overview')
                <section class="student-portal-summary" aria-label="Student information">
                    <dl>
                        <div><dt>Student number</dt><dd>{{ $studentNumber }}</dd></div>
                        <div><dt>Assigned program</dt><dd>{{ $programTitle ?? 'No program assigned' }}</dd></div>
                        <div><dt>Administrative student status</dt><dd>{{ $administrativeStatus }}</dd></div>
                    </dl>
                </section>

                <div class="student-portal-stats">
                    <a href="{{ route('filament.student.pages.overview', ['section' => 'courses']) }}">
                        <strong>{{ $currentCourses }}</strong><span>Current course enrollments</span>
                    </a>
                    <a href="{{ route('filament.student.pages.overview', ['section' => 'academic-record']) }}">
                        <strong>{{ $completedCourses }}</strong><span>Completed courses on record</span>
                    </a>
                </div>

                <h2>Awarded credentials</h2>
                @include('filament.student-portal.awards', ['awards' => $awards])
                @break

            @case('courses')
                @include('filament.student-portal.table', [
                    'label' => 'Course enrollments',
                    'columns' => ['code' => 'Code', 'title' => 'Course', 'section' => 'Section', 'term' => 'Academic term', 'status' => 'Status', 'enrolled' => 'Enrolled', 'completed' => 'Completed', 'outcome' => 'Finalized outcome'],
                    'rows' => $courses,
                    'emptyMessage' => 'No course enrollments are on record.',
                ])
                @break

            @case('academic-record')
                @include('filament.student-portal.table', [
                    'label' => 'Finalized academic record',
                    'columns' => ['code' => 'Code', 'title' => 'Course', 'term' => 'Reporting term', 'outcome' => 'Grade or outcome', 'attempted' => 'Attempted credits', 'earned' => 'Earned credits', 'completed' => 'Completed', 'status' => 'Status'],
                    'rows' => $records,
                    'emptyMessage' => 'No finalized academic outcomes are on record.',
                ])
                @break

            @case('degree-progress')
                <p class="student-portal-note">This advisory view reflects recorded work against {{ $programTitle ?? 'your assigned program' }}. The registrar makes official conferral decisions.</p>
                @forelse ($groups as $group)
                    <section class="student-portal-group">
                        <header>
                            <h2>{{ $group['name'] }}</h2>
                            <span class="student-portal-status">{{ $group['status'] }}</span>
                        </header>
                        @if ($group['required'] !== null)
                            <p>{{ number_format($group['earned'], 2) }} of {{ number_format($group['required'], 2) }} credits applied</p>
                        @endif
                        <ul>
                            @foreach ($group['requirements'] as $requirement)
                                <li>
                                    <div>
                                        <strong>{{ $requirement['name'] }}</strong>
                                        @if ($requirement['basis'])
                                            <small>{{ $requirement['basis'] }}</small>
                                        @endif
                                    </div>
                                    <span class="student-portal-status">{{ $requirement['status'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @empty
                    <p class="student-portal-note">No degree requirements are available for your assigned program.</p>
                @endforelse
                @break

            @case('awards')
                @include('filament.student-portal.awards', ['awards' => $awards])
                @break
        @endswitch
    </div>
</x-filament-panels::page>

<div class="student-portal">
    @isset($error)
        <p>{{ $error }}</p>
    @else
        <h2>{{ $review['assessment']['title'] }}</h2>
        <p>Mapped competencies: {{ $review['competencies']->join(', ') }}</p>
        @if ($review['submittedAt'])
            <p>Submitted {{ $review['submittedAt']->toFormattedDateString() }}</p>
        @endif
        @foreach ([
            'Assessment instructions' => $review['assessment']['description'],
            'Rubric' => $review['assessment']['rubric'],
            'Passing threshold on record' => $review['assessment']['passing_threshold'],
            'Submitted evidence' => $review['evidence'],
            'Previous assessor notes' => $review['feedback'],
        ] as $label => $value)
            @if (filled($value))
                <section>
                    <h3>{{ $label }}</h3>
                    <p style="white-space: pre-wrap; overflow-wrap: anywhere">{{ $value }}</p>
                </section>
            @endif
        @endforeach
        <p>Finalizing records your result permanently. A passed result verifies mastery for the mapped competencies.</p>
    @endisset
</div>

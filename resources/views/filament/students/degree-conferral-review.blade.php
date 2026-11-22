<div class="space-y-4">
    @if ($blockers)
        <div role="status" class="rounded-md border border-amber-300 p-3">
            <p class="font-semibold">Eligibility cannot be verified</p>
            @foreach ($blockers as $blocker)
                <p>{{ $blocker }}</p>
            @endforeach
        </div>
    @else
        <p>All evaluated requirements are complete. Confirming records the institution’s award of
            {{ $audit['student']->program->title }} ({{ $audit['student']->program->credential_type }}).</p>
    @endif

    @include('filament.students.degree-audit-preview', $audit)
</div>

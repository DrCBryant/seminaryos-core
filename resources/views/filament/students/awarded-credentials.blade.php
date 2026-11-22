<div class="space-y-4">
    @forelse ($awards as $award)
        <article class="space-y-1 rounded-md border border-gray-300 p-3 dark:border-gray-700">
            <h3 class="font-semibold">{{ $award->program_title }} ({{ $award->program_code }})</h3>
            <p>{{ $award->credential_type }} · {{ $award->conferred_on->toFormattedDateString() }}</p>
            <p>Recorded by {{ $award->conferredBy->name }} on {{ $award->decided_at->toDateTimeString() }}</p>
            @if (filled($award->registrar_remarks))
                <p class="whitespace-pre-wrap">{{ $award->registrar_remarks }}</p>
            @endif
        </article>
    @empty
        <p>No credentials have been awarded.</p>
    @endforelse
</div>

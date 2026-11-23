@forelse ($awards as $award)
    <article class="student-portal-award">
        <strong>{{ $award['credential'] }}</strong>
        <p>{{ $award['program'] }} ({{ $award['code'] }})</p>
        <p>Conferred {{ $award['date'] }}</p>
    </article>
@empty
    <p class="student-portal-note">No credentials have been conferred.</p>
@endforelse

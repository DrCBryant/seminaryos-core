@if ($rows->isEmpty())
    <p class="student-portal-note">{{ $emptyMessage }}</p>
@else
    <div class="student-portal-table" role="region" aria-label="{{ $label }}" tabindex="0">
        <table>
            <caption class="sr-only">{{ $label }}</caption>
            <thead>
                <tr>
                    @foreach ($columns as $heading)
                        <th scope="col">{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($columns as $key => $heading)
                            @if ($key === 'title')
                                <th scope="row">{{ $row[$key] ?? '—' }}</th>
                            @else
                                <td>{{ $row[$key] ?? '—' }}</td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@extends('layouts.app', ['title' => 'HR list', 'intro' => 'Every casual in this school. The light is computed. Nobody overrides red.'])

@section('content')
    <p><a class="button" href="{{ route('hr.people.create') }}">Add a person</a></p>
    <div class="filters">
        <a href="{{ route('hr.index') }}" @class(['on' => $filter === ''])>All</a>
        <a href="{{ route('hr.index', ['filter' => 'clear']) }}" @class(['on' => $filter === 'clear'])>Clear today ({{ $counts['clear'] }})</a>
        <a href="{{ route('hr.index', ['filter' => 'expiring']) }}" @class(['on' => $filter === 'expiring'])>Expiring in 30 days ({{ $counts['expiring'] }})</a>
        <a href="{{ route('hr.index', ['filter' => 'provisional']) }}" @class(['on' => $filter === 'provisional'])>Provisional ({{ $counts['provisional'] }})</a>
        <a href="{{ route('hr.index', ['filter' => 'waiting_on_conductor']) }}" @class(['on' => $filter === 'waiting_on_conductor'])>Waiting on Conductor ({{ $counts['waiting_on_conductor'] }})</a>
        <a href="{{ route('hr.index', ['filter' => 'induction_unfinished']) }}" @class(['on' => $filter === 'induction_unfinished'])>Induction unfinished ({{ $counts['induction_unfinished'] }})</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Neighbourhood</th>
                    <th>Stages</th>
                    <th>Light</th>
                    <th>WWCC expiry</th>
                    <th>Level</th>
                    <th>Maintenance end</th>
                    <th>Last day</th>
                    <th>Mentor</th>
                    <th>Director</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php($person = $row['person'])
                    <tr>
                        <td><a href="{{ route('hr.people.show', $person) }}">{{ $person->preferred_name }}</a><div class="meta">{{ $person->employment_status }}</div></td>
                        <td>{{ $person->neighbourhood?->name ?? '—' }}</td>
                        <td>{{ implode(', ', $person->stages ?? []) }}</td>
                        <td><span class="light {{ $row['light']->colour }}">{{ $row['light']->word() }}</span></td>
                        <td>{{ $person->wwcc?->expires_on?->toDateString() ?? '—' }}</td>
                        <td>{{ $person->accreditation->level ?? '—' }}</td>
                        <td>{{ $person->accreditation?->maintenance_end?->toDateString() ?? '—' }}</td>
                        <td>{{ $row['last_day'] ?? '—' }}</td>
                        <td>{{ $person->mentorship?->mentor?->preferred_name ?? '—' }}</td>
                        <td>{{ $person->neighbourhood?->director?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10">No one matches this filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

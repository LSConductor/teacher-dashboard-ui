@extends('layouts.app', ['title' => 'Audit', 'intro' => 'Australia/Sydney. WWCC numbers are not written here.'])

@section('content')
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>When</th>
                    <th>Who</th>
                    <th>Action</th>
                    <th>Person</th>
                    <th>Detail</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($audits as $audit)
                    <tr>
                        <td>{{ $audit->occurred_at->timezone('Australia/Sydney')->format('j M Y, H:i T') }}</td>
                        <td>{{ $audit->actor_name ?? 'Invite' }}</td>
                        <td>{{ $audit->action }}</td>
                        <td>{{ $audit->person_id ?? '—' }}</td>
                        <td><code>{{ json_encode($audit->metadata) }}</code></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection

@extends('layouts.app')
@section('content')
<h1>Parents as partners</h1>
@if($manages)
    <p class="lede">{{ $waiting }} {{ $waiting === 1 ? 'person is' : 'people are' }} finished and waiting on the volunteer check. The parents hub links to <a class="link" href="{{ route('partners.join') }}">the course</a>.</p>
@else
    <p class="lede">Helpers HR has cleared{{ $locked ? ' in '.$locked : '' }}. The clearance number stays with HR.</p>
    @if($locked)
        <p>Showing {{ $locked }} only.</p>
    @endif
@endif
<form class="toolbar" method="get" action="{{ route('partners.index') }}">
    @if($manages)
        <div>
            <label for="neighbourhood">Learning neighbourhood</label>
            <select id="neighbourhood" name="neighbourhood">
                <option value="">All</option>
                @foreach($neighbourhoods as $name)
                    <option value="{{ $name }}" @selected(request('neighbourhood') === $name)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div>
        <label for="interest">Help offered</label>
        <select id="interest" name="interest">
            <option value="">Any</option>
            @foreach($interests as $key => $label)
                <option value="{{ $key }}" @selected(request('interest') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit">Show</button>
</form>
@forelse($partners as $partner)
    <article class="person {{ $partner->course_status === 'ready' ? 'green' : ($partner->course_status === 'waiting' ? 'amber' : '') }}">
        <div><span class="status {{ $partner->course_status === 'ready' ? 'green' : ($partner->course_status === 'waiting' ? 'amber' : '') }}">{{ \App\Hr\PartnerCourse::label($partner->course_status) }}</span></div>
        <div>
            <h2><a href="{{ route('partners.show', $partner) }}">{{ $partner->preferred_name }}</a></h2>
            <p class="meta">{{ $partner->relationship === 'grandparent' ? 'Grandparent' : 'Parent' }} of {{ $partner->child_name }} · {{ $partner->neighbourhood }}</p>
            @if($partner->mobile)
                <p class="meta">{{ $partner->mobile }}</p>
            @endif
            <p>
                @foreach($partner->interests ?? [] as $key)
                    {{ $interests[$key] ?? $key }}@if(! $loop->last), @endif
                @endforeach
            </p>
            @if($partner->availability)
                <p class="muted">{{ $partner->availability }}</p>
            @endif
            @if($partner->strengths)
                <p>{{ $partner->strengths }}</p>
            @endif
        </div>
    </article>
@empty
    <p class="card">No one on this list yet.</p>
@endforelse
@endsection

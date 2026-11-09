@php
    $cover = $ask->cover;
    $when = $cover->when();
@endphp
@if($kind === 'ask')
{{ $cover->class_label }} needs a teacher on {{ $when }}.
@if($cover->teacher_name)
With {{ $cover->teacher_name }}.
@endif
@if($cover->start_time && $cover->finish_time)
{{ $cover->start_time }} to {{ $cover->finish_time }}.
@endif
@if($cover->neighbourhood)
{{ $cover->neighbourhood }}.
@endif
@if($cover->plan_url)
Plan: {{ $cover->plan_url }}
@endif

Open this link to accept or decline. It is only for you.
{{ $url }}
@elseif($kind === 'filled')
{{ $cover->class_label }} on {{ $when }} is filled. You do not need to come for that ask.
@elseif($kind === 'passed')
{{ $cover->class_label }} on {{ $when }} has moved on to the next person. You do not need to reply.
@else
{{ $cover->class_label }} on {{ $when }} was closed. You do not need to reply.
@endif

Living School

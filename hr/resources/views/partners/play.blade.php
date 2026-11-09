@extends('layouts.app')
@section('content')
<p class="muted">Parents as partners · {{ $partner->preferred_name }}</p>
@if($step === 'course')
    <h1>Your course</h1>
    @if($partner->quiz_version_passed && $next)
        <p class="lede">The wording changed. Read the new page, then the check if it asks again.</p>
    @else
        <p class="lede">Read each page in order. The check comes after the last page.</p>
    @endif
    @foreach($pages as $page)
        @php $read = (int) (($partner->seen ?? [])[$page->slug] ?? 0) === (int) $page->version; @endphp
        <p>
            @if($read || ($next && $next->id === $page->id))
                <a class="link" href="{{ route('partners.page', [$partner, $page->slug]) }}">{{ $page->title }}</a>
                @if($read) <span class="muted">Read</span> @endif
            @else
                <span class="muted">{{ $page->title }}</span>
            @endif
        </p>
    @endforeach
    @if($next)
        <p><a class="button" href="{{ route('partners.page', [$partner, $next->slug]) }}">Continue</a></p>
    @else
        <p><a class="button" href="{{ route('partners.check', $partner) }}">Open the check</a></p>
    @endif
@elseif($step === 'page')
    <h1>{{ $page->title }}</h1>
    <p class="muted">{{ $pages->search(fn ($row) => $row->id === $page->id) + 1 }} of {{ $pages->count() }}</p>
    <article class="card">
        @foreach(preg_split("/\n\s*\n/", trim($page->body)) as $paragraph)
            <p style="font-size:18px; line-height:1.5;">{{ $paragraph }}</p>
        @endforeach
        @if($page->video_url)
            <p><a class="button" href="{{ $page->video_url }}" target="_blank" rel="noopener">Watch the introduction</a></p>
        @endif
    </article>
    <form method="post" action="{{ route('partners.page.next', [$partner, $page->slug]) }}">
        @csrf
        <button type="submit">Next</button>
    </form>
@elseif($step === 'check')
    <h1>Check</h1>
    <p class="lede">Answer from the pages you just read.</p>
    <form method="post" action="{{ route('partners.check.save', $partner) }}" class="card">
        @csrf
        @foreach($quiz as $index => $question)
            <fieldset style="border:0; padding:0; margin:0 0 16px;">
                <legend style="font-weight:700; margin-bottom:8px;">{{ $question['prompt'] }}</legend>
                @foreach($question['choices'] as $choice => $label)
                    <label style="font-weight:500;">
                        <input style="width:auto" type="radio" name="answers[{{ $index }}]" value="{{ $choice }}" @checked((string) old('answers.'.$index) === (string) $choice)>
                        {{ $label }}
                    </label>
                @endforeach
            </fieldset>
        @endforeach
        @error('answers')<p class="error">{{ $message }}</p>@enderror
        <button type="submit">Finish the check</button>
    </form>
@elseif($step === 'about')
    <h1>Know thyself</h1>
    <p class="lede">What you enjoy, and the time you can keep. A director uses this when they ask for help.</p>
    <form method="post" action="{{ route('partners.about.save', $partner) }}" class="card">
        @csrf
        <label for="mobile">Mobile</label>
        <input id="mobile" name="mobile" value="{{ old('mobile', $partner->mobile) }}" required>
        <p style="font-weight:700; margin-top:14px;">I can help with</p>
        @foreach($interests as $key => $label)
            <label style="font-weight:500;"><input style="width:auto" type="checkbox" name="interests[]" value="{{ $key }}" @checked(in_array($key, old('interests', $partner->interests ?? []), true))> {{ $label }}</label>
        @endforeach
        <label for="strengths">Interests and strengths</label>
        <textarea id="strengths" name="strengths" rows="4" required>{{ old('strengths', $partner->strengths) }}</textarea>
        <label for="work_style">How you like to help</label>
        <select id="work_style" name="work_style">
            <option value="set_path" @selected(old('work_style', $partner->work_style) === 'set_path')>A set path</option>
            <option value="independent" @selected(old('work_style', $partner->work_style) === 'independent')>Working independently</option>
        </select>
        <label for="with_children">Where you are glad to be</label>
        <select id="with_children" name="with_children">
            <option value="room" @selected(old('with_children', $partner->with_children) === 'room')>A room of children</option>
            <option value="home" @selected(old('with_children', $partner->with_children) === 'home')>At home</option>
            <option value="either" @selected(old('with_children', $partner->with_children) === 'either')>Either</option>
        </select>
        <label for="availability">Time you can keep</label>
        <input id="availability" name="availability" value="{{ old('availability', $partner->availability) }}" placeholder="Tuesday mornings" required>
        <p><button type="submit">Send to HR</button></p>
    </form>
@else
    <h1>HR has this</h1>
    <p class="lede">Thank you, {{ $partner->preferred_name }}. A director can ask you once the volunteer check is recorded. Keep this page if you want to come back.</p>
    <div class="card">
        <p>{{ $partner->relationship === 'grandparent' ? 'Grandparent' : 'Parent' }} of {{ $partner->child_name }} · {{ $partner->neighbourhood }}</p>
        <p>
            @foreach($partner->interests ?? [] as $key)
                {{ $interests[$key] ?? $key }}@if(! $loop->last), @endif
            @endforeach
        </p>
        <p>{{ $partner->availability }}</p>
    </div>
@endif
@endsection

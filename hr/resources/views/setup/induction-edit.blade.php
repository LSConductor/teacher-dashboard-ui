@extends('layouts.app')
@section('content')
<p class="muted"><a class="link" href="{{ route('setup.induction') }}">Quests</a></p>
<h1>{{ $module->title }}</h1>
<p class="lede">Version {{ $module->version }}. If someone has already acknowledged this version, saving the new wording starts the next version.</p>
<form method="post" action="{{ route('setup.induction.update', $module) }}" class="card">
    @csrf @method('put')
    <label for="title">Title</label>
    <input id="title" name="title" value="{{ old('title', $module->title) }}" required>
    <label for="body">Page</label>
    <textarea id="body" name="body" rows="14" required>{{ old('body', $module->body) }}</textarea>
    <label for="video_url">Video link</label>
    <input id="video_url" name="video_url" type="url" value="{{ old('video_url', $module->video_url) }}" placeholder="https://">
    <p class="muted">Paste the full link, starting with https://. A YouTube, Vimeo, or share link is enough. Leave it blank until the video exists. The page text stays the record either way.</p>
    <p><button type="submit">Save quest</button></p>
</form>
<form method="post" action="{{ route('setup.induction.check', $module) }}" class="card">
    @csrf @method('put')
    <h2>The check</h2>
    <p class="muted">Mark the answer that matches the page. A blank question is left out. Saving a new check starts the next version when someone has already passed this one.</p>
    @php
        $questions = $module->quiz ?? [];
        $questions[] = ['prompt' => '', 'choices' => ['', ''], 'answer' => 0];
    @endphp
    @foreach($questions as $index => $question)
        <h3>{{ $question['prompt'] === '' ? 'Add a question' : 'Question '.($index + 1) }}</h3>
        <label for="prompt_{{ $index }}">Question</label>
        <input id="prompt_{{ $index }}" name="prompt[{{ $index }}]" value="{{ old('prompt.'.$index, $question['prompt']) }}">
        @php $choices = array_values($question['choices'] ?? []); $choices[] = ''; @endphp
        @foreach($choices as $choice => $label)
            <label for="choice_{{ $index }}_{{ $choice }}">Answer {{ $choice + 1 }}</label>
            <input id="choice_{{ $index }}_{{ $choice }}" name="choice[{{ $index }}][{{ $choice }}]" value="{{ old('choice.'.$index.'.'.$choice, $label) }}">
            <label style="font-weight:500;"><input style="width:auto" type="radio" name="answer[{{ $index }}]" value="{{ $choice }}" @checked((string) old('answer.'.$index, $question['answer'] ?? 0) === (string) $choice)> This answer matches the page</label>
        @endforeach
    @endforeach
    <p><button type="submit">Save the check</button></p>
</form>
@endsection

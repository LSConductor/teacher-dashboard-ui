@extends('layouts.app')
@section('content')
<p class="muted"><a class="link" href="{{ route('setup.partners') }}">Parents as partners</a></p>
<h1>The check</h1>
<p class="lede">Version {{ $check->version }}. Mark the answer that matches the pages.</p>
<form method="post" action="{{ route('setup.partners.check.update') }}" class="card">
    @csrf @method('put')
    @foreach($check->quiz ?? [] as $index => $question)
        <h2>Question {{ $index + 1 }}</h2>
        <label for="prompt_{{ $index }}">Question</label>
        <input id="prompt_{{ $index }}" name="prompt[{{ $index }}]" value="{{ old('prompt.'.$index, $question['prompt']) }}" required>
        @foreach($question['choices'] as $choice => $label)
            <label for="choice_{{ $index }}_{{ $choice }}">Answer {{ $choice + 1 }}</label>
            <input id="choice_{{ $index }}_{{ $choice }}" name="choice[{{ $index }}][{{ $choice }}]" value="{{ old('choice.'.$index.'.'.$choice, $label) }}">
            <label style="font-weight:500;"><input style="width:auto" type="radio" name="answer[{{ $index }}]" value="{{ $choice }}" @checked((string) old('answer.'.$index, $question['answer']) === (string) $choice)> This answer matches the page</label>
        @endforeach
    @endforeach
    <p><button type="submit">Save the check</button></p>
</form>
@endsection

<article class="sheet">
    <div class="sheet-head">
        <span>Living School</span>
        <strong>{{ $kicker ?? 'Casual teachers' }}</strong>
    </div>
    <div class="sheet-body">
        <h2>{{ $title }}</h2>
        @if(!empty($meta))
            <p class="sheet-meta">{{ $meta }}</p>
        @endif
        @foreach(preg_split("/\n\s*\n/", trim((string) $body)) as $paragraph)
            @if(trim($paragraph) !== '')
                <p>{{ $paragraph }}</p>
            @endif
        @endforeach
    </div>
    <p class="sheet-foot">{{ $footer ?? 'Australia/Sydney. A Working with Children Check number and a date of birth are not printed on this page.' }}</p>
</article>

@if(filled($placeName ?? null))
<div class="place">
    @if(filled($placeLogo ?? null))
        <img class="mark" src="{{ $placeLogo }}" alt="">
    @else
        <span class="mark" aria-hidden="true">{{ mb_strtoupper(mb_substr($placeName, 0, 1)) }}</span>
    @endif
    <span class="place-name">
        <strong>{{ $placeName }}</strong>
        <span>Living School</span>
    </span>
</div>
@endif

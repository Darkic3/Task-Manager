@extends('layouts.app')

@section('title', __('Concept map'))

@section('content')
<div class="lv-page" style="max-width:900px;margin:0 auto;">
    <h2 style="font-weight:800;">{{ $card->front }}</h2>
    <p class="text-muted">{{ $card->back }}</p>

    <section style="margin-top:1rem;">
        <h4>{{ __('Related cards') }}</h4>
        @if($relatedCards->isEmpty())
            <p class="text-muted">{{ __('No related cards.') }}</p>
        @else
            <ul>
                @foreach($relatedCards as $rc)
                    <li>
                        <a href="{{ route('vocabulary.cards.concept-map', $rc) }}">{{ $rc->front }}</a>
                        — {{ $rc->back }}
                        <span class="text-muted small">({{ $rc->deck->name ?? '' }})</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section style="margin-top:1rem;">
        <h4>{{ __('Referenced by') }}</h4>
        @if($incomingCards->isEmpty())
            <p class="text-muted">{{ __('No incoming references.') }}</p>
        @else
            <ul>
                @foreach($incomingCards as $ic)
                    <li>
                        <a href="{{ route('vocabulary.cards.concept-map', $ic) }}">{{ $ic->front }}</a>
                        — {{ $ic->back }}
                        <span class="text-muted small">({{ $ic->deck->name ?? '' }})</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <div style="margin-top:1.5rem;">
        <a href="{{ route('vocabulary.cards.index') }}" class="lv-btn" style="background:#f3f4f6;color:#374151;">{{ __('Back to cards') }}</a>
    </div>
</div>
@endsection
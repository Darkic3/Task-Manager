@extends('layouts.app')

@section('title', __('Concept map'))

@section('content')
<div class="lv-page" style="max-width:1000px;margin:0 auto;">
    {{-- Main card --}}
    <div style="background:#fff;border:1px solid #e9ebf1;border-radius:16px;padding:18px;margin-bottom:14px;box-shadow:0 4px 14px -8px rgba(15,23,42,.10);">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px;">
            <h2 style="margin:0;font-weight:800;">{{ $card->front }}</h2>
            @if($card->card_type)
                @php $ct = \App\Enums\CardType::tryFrom($card->card_type); @endphp
                <span style="background:#4f46e5;color:#fff;font-size:.7rem;font-weight:800;padding:2px 8px;border-radius:999px;">{{ __($ct?->label() ?? $card->card_type) }}</span>
            @endif
            @if($card->priority)
                <span style="background:#fef3c7;color:#b95309;font-size:.7rem;font-weight:800;padding:2px 8px;border-radius:999px;">P{{ $card->priority }}</span>
            @endif
        </div>
        <p style="margin:0 0 8px;color:#4b5563;">{{ $card->back }}</p>
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px;">
            @if($card->source_section)<span style="background:#f3f4f6;color:#4b5563;font-size:.68rem;font-weight:700;padding:2px 8px;border-radius:999px;">{{ $card->source_section }}</span>@endif
            @if(is_array($card->tags) && $card->tags)
                @foreach($card->tags as $tag)
                    <span style="background:#eef2ff;color:#4f46e5;font-size:.68rem;font-weight:700;padding:2px 8px;border-radius:999px;">{{ $tag }}</span>
                @endforeach
            @endif
        </div>
        @if(is_array($card->extra) && $card->extra)
            <div style="font-size:.78rem;color:#6b7280;margin-bottom:8px;">
                @foreach(array_slice($card->extra, 0, 4, true) as $k => $v)
                    <div><strong>{{ $k }}:</strong> {{ is_array($v) ? implode(', ', $v) : $v }}</div>
                @endforeach
            </div>
        @endif
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="{{ route('vocabulary.review.index', ['deck_id' => $card->deck_id]) }}" class="lv-btn" style="background:linear-gradient(135deg,#4f46e5,#8b5cf6);color:#fff;font-size:.78rem;padding:6px 12px;">{{ __('Review') }}</a>
            <button type="button" class="lv-btn" style="background:#f3f4f6;color:#374151;font-size:.78rem;padding:6px 12px;" onclick="document.getElementById('edit-{{ $card->id }}')?.scrollIntoView();">{{ __('Edit') }}</button>
            <a href="{{ route('vocabulary.cards.index') }}" class="lv-btn" style="background:#f3f4f6;color:#374151;font-size:.78rem;padding:6px 12px;">{{ __('Back to cards') }}</a>
        </div>
    </div>

    {{-- Filter + Add relation --}}
    <div style="background:#fff;border:1px solid #e9ebf1;border-radius:16px;padding:18px;margin-bottom:14px;">
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:12px;">
            <form method="GET" action="{{ route('vocabulary.cards.concept-map', $card) }}" style="display:flex;gap:8px;align-items:center;">
                <label style="font-size:.78rem;font-weight:700;">{{ __('Filter by type') }}</label>
                <select name="type" class="form-control" style="min-width:140px;" onchange="this.form.submit()">
                    <option value="all" {{ $typeFilter === 'all' ? 'selected' : '' }}>{{ __('All') }}</option>
                    @foreach($types as $t)
                        <option value="{{ $t }}" {{ $typeFilter === $t ? 'selected' : '' }}>{{ $t }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        <form method="POST" action="{{ route('vocabulary.cards.relations.store', $card) }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            @csrf
            <select name="related_card_id" class="form-control" style="min-width:200px;" required>
                <option value="">{{ __('Select card...') }}</option>
                @foreach($allCards as $ac)
                    <option value="{{ $ac->id }}">{{ $ac->front }}</option>
                @endforeach
            </select>
            <input name="relation_type" class="form-control" style="max-width:160px;" placeholder="{{ __('Relation type') }}" value="related">
            <button class="lv-btn lv-btn-primary" type="submit" style="font-size:.78rem;padding:6px 12px;">{{ __('Add relation') }}</button>
        </form>
    </div>

    {{-- Outgoing --}}
    <div style="background:#fff;border:1px solid #e9ebf1;border-radius:16px;padding:18px;margin-bottom:14px;">
        <h3 style="margin:0 0 10px;font-size:.95rem;font-weight:800;">{{ __('Outgoing relations') }} ({{ $outgoing->count() }})</h3>
        @if($outgoing->isEmpty())
            <p style="color:#8b8d98;font-size:.82rem;">{{ __('No outgoing relations.') }}</p>
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px;">
                @foreach($outgoing as $rel)
                    @php $rc = $rel->relatedCard ?? null; @endphp
                    @if($rc)
                        <div style="border:1px solid #e9ebf1;border-radius:12px;padding:10px;background:#f8fafc;">
                            <div style="font-weight:700;font-size:.85rem;">{{ $rc->front }}</div>
                            <div style="font-size:.72rem;color:#6b7280;margin:2px 0;">{{ $rc->back }}</div>
                            <div style="display:flex;gap:6px;align-items:center;margin-top:6px;flex-wrap:wrap;">
                                <span style="background:#eef2ff;color:#4f46e5;font-size:.66rem;font-weight:800;padding:2px 8px;border-radius:999px;">{{ $rel->relation_type }}</span>
                                @if($rc->source_section)<span style="font-size:.66rem;color:#6b7280;">{{ $rc->source_section }}</span>@endif
                            </div>
                            <div style="display:flex;gap:6px;margin-top:8px;">
                                <a href="{{ route('vocabulary.cards.concept-map', $rc) }}" style="font-size:.72rem;color:#4f46e5;text-decoration:none;">{{ __('Concept map') }}</a>
                                @if($rel->id)
                                    <form method="POST" action="{{ route('vocabulary.cards.relations.destroy', [$card, $rel]) }}" style="display:inline;">
                                        @csrf @method('DELETE')
                                        <button type="submit" style="background:none;border:none;color:#b91c1c;font-size:.72rem;cursor:pointer;padding:0;">{{ __('Remove') }}</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </div>

    {{-- Incoming --}}
    <div style="background:#fff;border:1px solid #e9ebf1;border-radius:16px;padding:18px;margin-bottom:14px;">
        <h3 style="margin:0 0 10px;font-size:.95rem;font-weight:800;">{{ __('Incoming relations') }} ({{ $incoming->count() }})</h3>
        @if($incoming->isEmpty())
            <p style="color:#8b8d98;font-size:.82rem;">{{ __('No incoming relations.') }}</p>
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px;">
                @foreach($incoming as $rel)
                    @php $sc = $rel->card ?? null; @endphp
                    @if($sc)
                        <div style="border:1px solid #e9ebf1;border-radius:12px;padding:10px;background:#f8fafc;">
                            <div style="font-weight:700;font-size:.85rem;">{{ $sc->front }}</div>
                            <div style="font-size:.72rem;color:#6b7280;margin:2px 0;">{{ $sc->back }}</div>
                            <div style="display:flex;gap:6px;align-items:center;margin-top:6px;flex-wrap:wrap;">
                                <span style="background:#eef2ff;color:#4f46e5;font-size:.66rem;font-weight:800;padding:2px 8px;border-radius:999px;">{{ $rel->relation_type }}</span>
                                @if($sc->source_section)<span style="font-size:.66rem;color:#6b7280;">{{ $sc->source_section }}</span>@endif
                            </div>
                            <div style="margin-top:8px;">
                                <a href="{{ route('vocabulary.cards.concept-map', $sc) }}" style="font-size:.72rem;color:#4f46e5;text-decoration:none;">{{ __('Concept map') }}</a>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
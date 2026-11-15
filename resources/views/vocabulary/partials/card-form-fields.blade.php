@php
$card = $card ?? null;
$mode = $mode ?? 'create';
$ct = $card?->card_type ?? 'definition';
$extra = is_array($card?->extra) ? $card->extra : [];
$tagsValue = is_array($card?->tags) ? implode(',', $card->tags) : '';
$relatedValue = is_array($card?->related_cards) ? implode(',', $card->related_cards) : '';
@endphp

<div class="lv-form-fields">
    <div class="lv-row" style="margin-bottom:10px;">
        <select name="deck_id" class="form-control" required>
            @foreach($decks as $d)
                <option value="{{ $d->id }}" {{ (int)old('deck_id', $card?->deck_id) === (int)$d->id ? 'selected' : '' }}>{{ $d->name }}</option>
            @endforeach
        </select>
        <input name="front" class="form-control" required maxlength="255" placeholder="{{ __('Word / Front') }}" value="{{ old('front', $card?->front) }}" style="font-weight:600;">
        <input name="back" class="form-control" required maxlength="2000" placeholder="{{ __('Meaning / Back') }}" value="{{ old('back', $card?->back) }}">
    </div>

    <details style="margin-top:8px;" {{ $mode === 'edit' ? 'open' : '' }}>
        <summary style="cursor:pointer;font-weight:700;font-size:.8rem;">{{ __('Advanced fields') }}</summary>
        <div style="margin-top:8px;display:flex;flex-direction:column;gap:8px;">
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                @foreach(\App\Enums\CardType::cases() as $t)
                    <button type="button" class="lv-mini" onclick="document.getElementById('card_type_select').value='{{ $t->value }}';syncTypeFields();">{{ __($t->label()) }}</button>
                @endforeach
            </div>
            <div class="lv-row">
                <select name="card_type" class="form-control" id="card_type_select" onchange="syncTypeFields()">
                    @foreach(\App\Enums\CardType::cases() as $c)
                        <option value="{{ $c->value }}" {{ old('card_type', $ct) === $c->value ? 'selected' : '' }}>{{ __($c->label()) }}</option>
                    @endforeach
                </select>
                <select name="priority" class="form-control">
                    @for($p = 1; $p <= 3; $p++)
                        <option value="{{ $p }}" {{ (int)old('priority', $card?->priority ?? 2) === $p ? 'selected' : '' }}>{{ __('P:n', ['n' => $p]) }}</option>
                    @endfor
                </select>
            </div>
            <div class="lv-row">
                <textarea name="example" class="form-control" rows="2" placeholder="{{ __('Example') }}">{{ old('example', $card?->example) }}</textarea>
                <textarea name="note" class="form-control" rows="2" placeholder="{{ __('Note') }}">{{ old('note', $card?->note) }}</textarea>
            </div>
            <div class="lv-row">
                <input name="tags" class="form-control" list="existing-tags" placeholder="{{ __('Tags (comma)') }}" value="{{ old('tags', $tagsValue) }}">
                <input name="source_section" class="form-control" list="existing-sources" placeholder="{{ __('Section') }}" value="{{ old('source_section', $card?->source_section) }}">
                <input name="related_cards" class="form-control" placeholder="{{ __('Related IDs (comma)') }}" value="{{ old('related_cards', $relatedValue) }}">
                <input name="difficulty" type="number" min="1" max="5" class="form-control" placeholder="{{ __('Difficulty 1-5') }}" value="{{ old('difficulty', $card?->difficulty) }}">
            </div>

            <datalist id="existing-tags">
                @foreach(($existingTags ?? []) as $t)<option value="{{ $t }}">@endforeach
            </datalist>
            <datalist id="existing-sources">
                @foreach(($existingSources ?? []) as $s)<option value="{{ $s }}">@endforeach
            </datalist>

            <div data-type-section="definition" class="lv-type-section">
                <input name="extra[key_point]" class="form-control" placeholder="{{ __('Key point') }}" value="{{ old('extra.key_point', $extra['key_point'] ?? '') }}">
            </div>

            <div data-type-section="comparison" class="lv-type-section" style="display:flex;flex-direction:column;gap:8px;" hidden>
                <input name="extra[side_a]" class="form-control" placeholder="{{ __('Side A') }}" value="{{ old('extra.side_a', $extra['side_a'] ?? '') }}">
                <input name="extra[side_b]" class="form-control" placeholder="{{ __('Side B') }}" value="{{ old('extra.side_b', $extra['side_b'] ?? '') }}">
                <textarea name="extra[differences]" class="form-control" rows="3" placeholder="{{ __('Differences') }}">{{ old('extra.differences', $extra['differences'] ?? '') }}</textarea>
            </div>

            <div data-type-section="process" class="lv-type-section" style="display:flex;flex-direction:column;gap:8px;" hidden>
                <textarea name="extra[steps]" class="form-control" rows="4" placeholder="{{ __('Steps (one per line)') }}">{{ old('extra.steps', isset($extra['steps']) && is_array($extra['steps']) ? implode("\n", $extra['steps']) : ($extra['steps'] ?? '')) }}</textarea>
                <textarea name="extra[notes]" class="form-control" rows="2" placeholder="{{ __('Notes') }}">{{ old('extra.notes', $extra['notes'] ?? '') }}</textarea>
            </div>

            <div data-type-section="port" class="lv-type-section" style="display:flex;flex-direction:column;gap:8px;" hidden>
                <input name="extra[port_number]" class="form-control" placeholder="{{ __('Port number') }}" value="{{ old('extra.port_number', $extra['port_number'] ?? '') }}">
                <input name="extra[service]" class="form-control" placeholder="{{ __('Service') }}" value="{{ old('extra.service', $extra['service'] ?? '') }}">
                <input name="extra[protocol]" class="form-control" placeholder="{{ __('Protocol') }}" value="{{ old('extra.protocol', $extra['protocol'] ?? '') }}">
                <input name="extra[risk_level]" class="form-control" placeholder="{{ __('Risk level') }}" value="{{ old('extra.risk_level', $extra['risk_level'] ?? '') }}">
                <textarea name="extra[notes]" class="form-control" rows="2" placeholder="{{ __('Notes') }}">{{ old('extra.notes', $extra['notes'] ?? '') }}</textarea>
            </div>

            <div data-type-section="event_id" class="lv-type-section" style="display:flex;flex-direction:column;gap:8px;" hidden>
                <input name="extra[event_id]" class="form-control" placeholder="{{ __('Event ID') }}" value="{{ old('extra.event_id', $extra['event_id'] ?? '') }}">
                <input name="extra[meaning]" class="form-control" placeholder="{{ __('Meaning') }}" value="{{ old('extra.meaning', $extra['meaning'] ?? '') }}">
                <input name="extra[log_source]" class="form-control" placeholder="{{ __('Log source') }}" value="{{ old('extra.log_source', $extra['log_source'] ?? '') }}">
                <input name="extra[when_appears]" class="form-control" placeholder="{{ __('When appears') }}" value="{{ old('extra.when_appears', $extra['when_appears'] ?? '') }}">
                <textarea name="extra[recommended_action]" class="form-control" rows="2" placeholder="{{ __('Recommended action') }}">{{ old('extra.recommended_action', $extra['recommended_action'] ?? '') }}</textarea>
            </div>

            <div data-type-section="attack" class="lv-type-section" style="display:flex;flex-direction:column;gap:8px;" hidden>
                <input name="extra[name]" class="form-control" placeholder="{{ __('Attack name') }}" value="{{ old('extra.name', $extra['name'] ?? '') }}">
                <textarea name="extra[description]" class="form-control" rows="2" placeholder="{{ __('Description') }}">{{ old('extra.description', $extra['description'] ?? '') }}</textarea>
                <input name="extra[tactic]" class="form-control" placeholder="{{ __('Tactic') }}" value="{{ old('extra.tactic', $extra['tactic'] ?? '') }}">
                <textarea name="extra[detection_methods]" class="form-control" rows="3" placeholder="{{ __('Detection methods (one per line)') }}">{{ old('extra.detection_methods', isset($extra['detection_methods']) && is_array($extra['detection_methods']) ? implode("\n", $extra['detection_methods']) : ($extra['detection_methods'] ?? '')) }}</textarea>
                <input name="extra[related_event_ids]" class="form-control" placeholder="{{ __('Related event IDs (comma)') }}" value="{{ old('extra.related_event_ids', $extra['related_event_ids'] ?? '') }}">
            </div>

            <div data-type-section="scenario" class="lv-type-section" style="display:flex;flex-direction:column;gap:8px;" hidden>
                <textarea name="extra[alert_description]" class="form-control" rows="2" placeholder="{{ __('Alert description') }}">{{ old('extra.alert_description', $extra['alert_description'] ?? '') }}</textarea>
                <textarea name="extra[triage_steps]" class="form-control" rows="4" placeholder="{{ __('Triage steps (one per line)') }}">{{ old('extra.triage_steps', isset($extra['triage_steps']) && is_array($extra['triage_steps']) ? implode("\n", $extra['triage_steps']) : ($extra['triage_steps'] ?? '')) }}</textarea>
                <textarea name="extra[expected_outcome]" class="form-control" rows="2" placeholder="{{ __('Expected outcome') }}">{{ old('extra.expected_outcome', $extra['expected_outcome'] ?? '') }}</textarea>
            </div>

            <div data-type-section="principle" class="lv-type-section" style="display:flex;flex-direction:column;gap:8px;" hidden>
                <input name="extra[name]" class="form-control" placeholder="{{ __('Principle name') }}" value="{{ old('extra.name', $extra['name'] ?? '') }}">
                <textarea name="extra[definition]" class="form-control" rows="2" placeholder="{{ __('Definition') }}">{{ old('extra.definition', $extra['definition'] ?? '') }}</textarea>
                <textarea name="extra[why_important]" class="form-control" rows="2" placeholder="{{ __('Why important in SOC') }}">{{ old('extra.why_important', $extra['why_important'] ?? '') }}</textarea>
                <textarea name="extra[example]" class="form-control" rows="2" placeholder="{{ __('Example') }}">{{ old('extra.example', $extra['example'] ?? '') }}</textarea>
            </div>

            <div data-type-section="ioc" class="lv-type-section" style="display:flex;flex-direction:column;gap:8px;" hidden>
                <input name="extra[value]" class="form-control" placeholder="{{ __('IOC value') }}" value="{{ old('extra.value', $extra['value'] ?? '') }}">
                <select name="extra[type]" class="form-control">
                    @foreach(['ip','domain','hash','url'] as $t)
                        <option value="{{ $t }}" {{ old('extra.type', $extra['type'] ?? '') === $t ? 'selected' : '' }}>{{ $t }}</option>
                    @endforeach
                </select>
                <textarea name="extra[meaning]" class="form-control" rows="2" placeholder="{{ __('Meaning') }}">{{ old('extra.meaning', $extra['meaning'] ?? '') }}</textarea>
                <textarea name="extra[how_to_check]" class="form-control" rows="2" placeholder="{{ __('How to check') }}">{{ old('extra.how_to_check', $extra['how_to_check'] ?? '') }}</textarea>
            </div>

            <div data-type-section="cloze" class="lv-type-section" style="display:flex;flex-direction:column;gap:8px;" hidden>
                <textarea name="extra[text_with_cloze]" class="form-control" rows="3" placeholder="{{ __('Text with cloze marker') }}">{{ old('extra.text_with_cloze', $extra['text_with_cloze'] ?? '') }}</textarea>
                <textarea name="extra[explanation]" class="form-control" rows="2" placeholder="{{ __('Explanation') }}">{{ old('extra.explanation', $extra['explanation'] ?? '') }}</textarea>
            </div>
        </div>
    </details>
</div>

<script>
function syncTypeFields() {
    const select = document.getElementById('card_type_select');
    if (!select) return;
    const type = select.value;
    document.querySelectorAll('[data-type-section]').forEach(el => {
        el.hidden = el.dataset.typeSection !== type;
    });
}
document.addEventListener('DOMContentLoaded', syncTypeFields);
</script>

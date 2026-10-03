{{-- Avoid history modal: single organized place for slips/cravings/notes (Phase 3) --}}
<div class="pl-modal" id="plAvoidHistoryModal" hidden>
    <div class="pl-modal-backdrop" data-history-close></div>
    <div class="pl-modal-dialog pl-history-dialog" role="dialog" aria-modal="true" aria-labelledby="plHistoryTitle">
        <div class="pl-modal-head">
            <div>
                <div class="pl-modal-title" id="plHistoryTitle" data-history-title>{{ __('History') }}</div>
                <div class="pl-modal-sub" data-history-sub></div>
            </div>
            <button type="button" class="pl-modal-x" data-history-close aria-label="{{ __('Close') }}">&times;</button>
        </div>
        <div class="pl-history-filters">
            <div class="pl-history-chips" data-history-types>
                <button type="button" data-history-type="all" class="active">{{ __('All') }}</button>
                <button type="button" data-history-type="slip">{{ __('Slips') }}</button>
                <button type="button" data-history-type="craving">{{ __('Cravings') }}</button>
                <button type="button" data-history-type="note">{{ __('Notes') }}</button>
            </div>
            <div class="pl-history-ranges" data-history-ranges>
                <button type="button" data-history-range="7">7 {{ __('d') }}</button>
                <button type="button" data-history-range="30" class="active">30 {{ __('d') }}</button>
                <button type="button" data-history-range="90">90 {{ __('d') }}</button>
            </div>
        </div>
        <div class="pl-modal-body" data-history-body>
            <div class="pl-history-list" data-history-list></div>
            <div class="pl-history-empty" data-history-empty hidden>{{ __('Nothing logged yet.') }}</div>
            <div class="pl-history-loading" data-history-loading hidden>{{ __('Loading…') }}</div>
            <button type="button" class="pl-history-more" data-history-more hidden>{{ __('Load more') }}</button>
        </div>
    </div>
</div>

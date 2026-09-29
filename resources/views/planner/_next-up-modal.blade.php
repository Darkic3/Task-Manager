{{-- Quick Action Mini-Modal for Next Up & Routine Logging --}}
<div class="pl-qa-modal" id="plNextUpModal" hidden>
    <div class="pl-qa-modal-backdrop" onclick="closeNextUpModal()"></div>
    <div class="pl-qa-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="plNextUpModalTitle">
        
        {{-- Header --}}
        <div class="pl-qa-modal-head">
            <div class="d-flex align-items-center gap-2">
                <div class="pl-qa-modal-icon" id="plNextUpModalIcon">
                    <i class="bi bi-lightning-charge-fill"></i>
                </div>
                <div>
                    <h5 class="pl-qa-modal-title mb-0" id="plNextUpModalTitle">{{ __('Quick Log & Complete') }}</h5>
                    <span class="pl-qa-modal-sub" id="plNextUpModalSub"></span>
                </div>
            </div>
            <button type="button" class="pl-qa-modal-close" onclick="closeNextUpModal()" aria-label="{{ __('Close') }}">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        {{-- Body --}}
        <div class="pl-qa-modal-body">
            
            {{-- Summary Hero Chip --}}
            <div class="pl-qa-hero-chip mb-3" id="plNextUpModalHero">
                <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-check2-circle fs-5 text-primary" id="plNextUpHeroIcon"></i>
                        <span class="fw-bold text-dark fs-6" id="plNextUpHeroTitle"></span>
                    </div>
                    <div class="d-flex align-items-center gap-1" id="plNextUpHeroBadges">
                        {{-- Injected dynamically (e.g. Period badge, Project badge, Streak) --}}
                    </div>
                </div>
                <div class="small text-muted mt-1" id="plNextUpHeroDesc" style="display:none;"></div>
            </div>

            {{-- 1. Time Tracking Panel --}}
            <div class="pl-qa-panel" id="plModalPanelTime" style="display:none;">
                <label class="form-label fw-bold small text-dark d-flex align-items-center justify-content-between mb-2">
                    <span><i class="bi bi-alarm text-primary me-1"></i> <span id="plModalTimeLabel">{{ __('Wake-up Time') }}</span></span>
                    <button type="button" class="btn btn-xs btn-outline-primary rounded-pill px-2 py-0" onclick="plSetTimePreset('now')" style="font-size:11.5px;">
                        <i class="bi bi-clock-history"></i> {{ __('Now') }}
                    </button>
                </label>
                
                <div class="pl-qa-time-box mb-3">
                    <i class="bi bi-clock fs-4 text-primary"></i>
                    <input type="time" id="plModalTimeInput" class="form-control form-control-lg text-center fw-bold pl-time-modal-input" step="60" dir="ltr">
                </div>

                <div class="pl-qa-presets-wrap">
                    <span class="small text-muted d-block mb-1 fw-semibold">{{ __('Quick presets') }}:</span>
                    <div class="d-flex flex-wrap gap-1.5" id="plModalTimePresets">
                        <button type="button" class="pl-preset-chip" onclick="plSetTimePreset('06:00')">06:00</button>
                        <button type="button" class="pl-preset-chip" onclick="plSetTimePreset('06:30')">06:30</button>
                        <button type="button" class="pl-preset-chip" onclick="plSetTimePreset('07:00')">07:00</button>
                        <button type="button" class="pl-preset-chip" onclick="plSetTimePreset('07:30')">07:30</button>
                        <button type="button" class="pl-preset-chip" onclick="plSetTimePreset('08:00')">08:00</button>
                        <button type="button" class="pl-preset-chip" onclick="plAdjustTimeMinutes(-15)">-15m</button>
                        <button type="button" class="pl-preset-chip" onclick="plAdjustTimeMinutes(15)">+15m</button>
                    </div>
                </div>
            </div>

            {{-- 2. Number/Value Tracking Panel --}}
            <div class="pl-qa-panel" id="plModalPanelValue" style="display:none;">
                <label class="form-label fw-bold small text-dark d-flex align-items-center justify-content-between mb-2">
                    <span><i class="bi bi-clipboard-data text-primary me-1"></i> <span id="plModalValueLabel">{{ __('Value') }}</span></span>
                    <span class="badge bg-light text-muted border px-2" id="plModalValueUnit"></span>
                </label>

                <div class="input-group input-group-lg mb-3 pl-stepper-wrap">
                    <button class="btn btn-outline-secondary px-3" type="button" onclick="plAdjustValue(-1)">
                        <i class="bi bi-dash-lg"></i>
                    </button>
                    <input type="number" id="plModalValueInput" class="form-control text-center fw-bold fs-4" min="0" step="any" placeholder="0">
                    <button class="btn btn-outline-secondary px-3" type="button" onclick="plAdjustValue(1)">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </div>

                <div class="pl-qa-presets-wrap">
                    <span class="small text-muted d-block mb-1 fw-semibold">{{ __('Quick presets') }}:</span>
                    <div class="d-flex flex-wrap gap-1.5">
                        <button type="button" class="pl-preset-chip" onclick="plAdjustValue(1)">+1</button>
                        <button type="button" class="pl-preset-chip" onclick="plAdjustValue(2)">+2</button>
                        <button type="button" class="pl-preset-chip" onclick="plAdjustValue(5)">+5</button>
                        <button type="button" class="pl-preset-chip" onclick="plAdjustValue(10)">+10</button>
                    </div>
                </div>
            </div>

            {{-- 3. Sets Tracking Panel --}}
            <div class="pl-qa-panel" id="plModalPanelSets" style="display:none;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <label class="form-label fw-bold small text-dark mb-0">
                        <i class="bi bi-activity text-primary me-1"></i> <span id="plModalSetsStepName">{{ __('Sets') }}</span>
                    </label>
                    <button type="button" class="btn btn-xs btn-link text-decoration-none p-0 small" onclick="plFillAllSetsFromFirst()">
                        <i class="bi bi-copy"></i> {{ __('Fill all sets') }}
                    </button>
                </div>
                <div class="pl-sets-grid d-flex flex-column gap-2 mb-3" id="plModalSetsGrid">
                    {{-- Dynamically generated set inputs --}}
                </div>
            </div>

            {{-- 4. Avoid Habit (Slip) Panel --}}
            <div class="pl-qa-panel" id="plModalPanelAvoid" style="display:none;">
                <div class="alert alert-danger d-flex align-items-center gap-2 p-2 px-3 rounded-3 mb-3">
                    <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>
                    <div class="small">
                        {{ __('Logging a slip records that the avoided habit occurred today.') }}
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small text-muted fw-semibold mb-1">{{ __('Reason / Note (Optional)') }}</label>
                    <input type="text" id="plModalAvoidNote" class="form-control form-control-sm" placeholder="...">
                </div>
            </div>

            {{-- 5. Multi-Step Checklist Overview --}}
            <div class="pl-qa-panel" id="plModalPanelSteps" style="display:none;">
                <label class="form-label fw-bold small text-dark mb-2">
                    <i class="bi bi-list-check text-primary me-1"></i> {{ __('Steps') }}
                </label>
                <div class="pl-modal-steps-list d-flex flex-column gap-1.5" id="plModalStepsList">
                    {{-- Dynamically listed steps --}}
                </div>
            </div>

        </div>

        {{-- Footer --}}
        <div class="pl-qa-modal-foot">
            <button type="button" class="btn btn-light rounded-pill px-3 fw-semibold text-muted" onclick="closeNextUpModal()">
                {{ __('Cancel') }}
            </button>
            <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold d-inline-flex align-items-center gap-2 pl-qa-submit-btn" id="plModalSubmitBtn" onclick="plSubmitNextUpModal()">
                <span class="spinner-border spinner-border-sm d-none" id="plModalSpinner" role="status" aria-hidden="true"></span>
                <i class="bi bi-check-lg" id="plModalBtnIcon"></i>
                <span id="plModalBtnText">{{ __('Save & Complete') }}</span>
            </button>
        </div>

    </div>
</div>

{{-- Live Floating Focus & Time Tracking Bar --}}
<div class="pl-floating-focus-bar" id="plFloatingFocusBar" style="display: none;">
    <div class="pl-ffb-container">
        
        {{-- Left: Live Status & Task Info --}}
        <div class="pl-ffb-info">
            <span class="pl-ffb-pulse" title="{{ __('Active Time Tracking') }}"></span>
            <div class="pl-ffb-text">
                <div class="pl-ffb-title" id="plFfbTaskTitle">{{ __('Active Focus Task') }}</div>
                <div class="pl-ffb-sub" id="plFfbProject">{{ __('My Day') }}</div>
            </div>
        </div>

        {{-- Center: Live Digital Clock --}}
        <div class="pl-ffb-clock-wrap">
            <div class="pl-ffb-clock font-monospace" id="plFfbClock">00:00:00</div>
            <span class="pl-ffb-mode-badge" id="plFfbStatusBadge">{{ __('Focusing') }}</span>
        </div>

        {{-- Right: Quick Controls --}}
        <div class="pl-ffb-actions">
            <button type="button" class="pl-ffb-btn pl-ffb-btn-pause" id="plFfbPauseBtn" onclick="plToggleFloatingPause()" title="{{ __('Pause / Resume') }}">
                <i class="bi bi-pause-fill" id="plFfbPauseIcon"></i>
            </button>
            <button type="button" class="pl-ffb-btn pl-ffb-btn-stop" onclick="plStopFloatingTimer()" title="{{ __('Stop & Save') }}">
                <i class="bi bi-stop-fill"></i>
            </button>
            <button type="button" class="pl-ffb-btn pl-ffb-btn-break" onclick="plStartBreak(5)" title="{{ __('5 min Break (Pomodoro)') }}">
                <i class="bi bi-cup-hot-fill"></i> <span class="d-none d-md-inline ms-1">{{ __('5m Break') }}</span>
            </button>
            <button type="button" class="pl-ffb-btn pl-ffb-btn-fullscreen" onclick="plExpandToFocusWorkstation()" title="{{ __('Focus Mode • Workstation') }}">
                <i class="bi bi-arrows-fullscreen"></i>
            </button>
            <button type="button" class="pl-ffb-btn pl-ffb-btn-close" onclick="plCloseFloatingBar()" title="{{ __('Hide Bar') }}">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

    </div>
</div>

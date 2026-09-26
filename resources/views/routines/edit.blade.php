@extends('layouts.app')

@section('title', 'Edit Routine')

@push('styles')
<style>
    .main-content { padding: 14px 16px; background: #f7f8fa; min-height: 100vh; }

    .cu-header {
        background: transparent; border: none; box-shadow: none; border-radius: 0;
        padding: 0 0 14px; color: #1f2328; overflow: visible; margin-bottom: 14px; position: relative;
    }
    .cu-header::before { display: none; }
    .cu-back {
        display:inline-flex; align-items:center; gap:6px; font-size:13px; font-weight:500;
        color:#8a8f98; text-decoration:none;
    }
    .cu-back:hover { color:#7c3aed; }
    .cu-header-title { font-weight: 700; font-size: 19px; margin: 14px 0 0; position: relative; color:#1f2328; word-break:break-word; }
    .cu-header-sub   { font-size: 12px; opacity: .8; margin: 2px 0 0; position: relative; color:#8a8f98; }

    .cu-layout { display: block; max-width: 720px; margin: 0 auto; }

    .cu-info-panel {
        background: white; border: 1px solid #e3e4e8; border-radius: 8px;
        overflow: hidden;
    }
    @media(min-width:769px) { .cu-info-panel { position: sticky; top: 14px; } }
    .cu-info-panel-header {
        background: #f7f8fa; border-bottom: 1px solid #e3e4e8; padding: 10px 14px;
    }
    .cu-info-panel-header span { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; color: #8a8f98; }
    .cu-info-body { padding: 14px; }
    .cu-avatar {
        width: 48px; height: 48px; border-radius: 10px; background: #7c3aed;
        display: flex; align-items: center; justify-content: center;
        font-size: 22px; color: white; margin: 0 auto 10px;
    }
    .cu-avatar.weekly  { background: #2563eb; }
    .cu-avatar.monthly { background: #d97706; }
    .cu-panel-name { text-align: center; font-size: 13px; font-weight: 700; color: #1a1d23; margin-bottom: 6px; word-break: break-word; }
    .cu-freq-badge {
        display: flex; align-items: center; justify-content: center; gap: 5px;
        padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700;
        text-transform: uppercase; letter-spacing: .6px; margin-bottom: 12px;
    }
    .cu-freq-badge.daily   { background: #ede9fe; color: #6d28d9; }
    .cu-freq-badge.weekly  { background: #dbeafe; color: #1d4ed8; }
    .cu-freq-badge.monthly { background: #fef3c7; color: #b45309; }
    .cu-meta-row {
        display: flex; align-items: flex-start; gap: 8px;
        padding: 7px 0; border-top: 1px solid #f0f1f3;
        font-size: 12px; color: #6b7385;
    }
    .cu-meta-row i { font-size: 13px; color: #adb0b8; flex-shrink: 0; margin-top: 1px; }
    .cu-meta-row strong { color: #1a1d23; font-weight: 600; }

    .cu-sections { display: flex; flex-direction: column; gap: 10px; }
    .cu-section { background: white; border: 1px solid #e3e4e8; border-radius: 8px; overflow: hidden; }
    .cu-section-header {
        display: flex; align-items: center; gap: 8px;
        padding: 10px 16px; background: #fafbfc; border-bottom: 1px solid #e3e4e8;
    }
    .cu-section-icon {
        width: 26px; height: 26px; border-radius: 6px;
        display: flex; align-items: center; justify-content: center; font-size: 13px; flex-shrink: 0;
    }
    .cu-section-icon.purple { background: #ede9fe; color: #7c3aed; }
    .cu-section-icon.blue   { background: #dbeafe; color: #2563eb; }
    .cu-section-icon.green  { background: #dcfce7; color: #16a34a; }
    .cu-section-title { font-size: 13px; font-weight: 700; color: #1a1d23; margin: 0; }
    .cu-section-sub   { font-size: 11px; color: #8a8f98; margin: 0 0 0 auto; }
    .cu-section-body  { padding: 16px; }

    .cu-field { margin-bottom: 14px; }
    .cu-field:last-child { margin-bottom: 0; }
    .cu-field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    @media(max-width:500px) { .cu-field-row { grid-template-columns: 1fr; } }
    .cu-label {
        display: block; font-size: 11px; font-weight: 700;
        text-transform: uppercase; letter-spacing: .7px; color: #8a8f98; margin-bottom: 5px;
    }
    .cu-input {
        width: 100%; height: 34px; padding: 0 10px 0 34px;
        border: 1px solid #d3d5db; border-radius: 6px; background: white;
        font-size: 13px; color: #1a1d23; outline: none;
        transition: border-color .15s, box-shadow .15s; box-sizing: border-box;
    }
    .cu-input:focus { border-color: #7c3aed; box-shadow: 0 0 0 2px rgba(124,58,237,.15); }
    .cu-input.is-invalid { border-color: #dc2626; }
    .cu-textarea {
        width: 100%; padding: 8px 10px; min-height: 90px;
        border: 1px solid #d3d5db; border-radius: 6px; background: white;
        font-size: 13px; color: #1a1d23; outline: none; resize: vertical;
        transition: border-color .15s, box-shadow .15s; box-sizing: border-box;
    }
    .cu-textarea:focus { border-color: #7c3aed; box-shadow: 0 0 0 2px rgba(124,58,237,.15); }
    .cu-input-wrap { position: relative; }
    .cu-input-wrap > i {
        position: absolute; left: 10px; top: 50%;
        transform: translateY(-50%); font-size: 13px; color: #adb0b8; pointer-events: none;
    }
    .invalid-feedback { display: block; margin-top: 4px; font-size: 11px; color: #dc2626; font-weight: 500; }

    .cu-freq-chips { display: flex; gap: 8px; flex-wrap: wrap; }
    .cu-chip-opt   { position: relative; }
    .cu-chip-opt input { position: absolute; opacity: 0; width: 0; height: 0; }
    .cu-chip-label {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 5px 14px; border: 1px solid #d3d5db; border-radius: 20px;
        cursor: pointer; font-size: 12px; font-weight: 600; color: #6b7385;
        background: white; transition: all .15s; user-select: none;
    }
    .cu-chip-label:hover    { border-color: #7c3aed; color: #7c3aed; background: #faf5ff; }
    .chip-daily   input:checked + .cu-chip-label { color: #5b21b6; border-color: #7c3aed; background: #ede9fe; }
    .chip-weekly  input:checked + .cu-chip-label { color: #1d4ed8; border-color: #2563eb; background: #dbeafe; }
    .chip-monthly input:checked + .cu-chip-label { color: #b45309; border-color: #d97706; background: #fef3c7; }
    .chip-everyn  input:checked + .cu-chip-label { color: #0e7490; border-color: #06b6d4; background: #cffafe; }

    .cu-picker { display: none; margin-top: 14px; padding-top: 14px; border-top: 1px solid #e3e4e8; }
    .cu-picker.visible { display: block; }
    .cu-when-panel { display: none; margin-top: 14px; padding-top: 14px; border-top: 1px solid #e3e4e8; }
    .cu-picker-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .7px; color: #8a8f98; margin-bottom: 10px; }
    .cu-check-grid { display: flex; flex-wrap: wrap; gap: 6px; }
    .cu-check-item { position: relative; }
    .cu-check-item input { position: absolute; opacity: 0; width: 0; height: 0; }
    .cu-check-item label {
        display: inline-flex; align-items: center; justify-content: center;
        padding: 4px 10px; min-width: 36px; border: 1px solid #d3d5db; border-radius: 6px;
        cursor: pointer; font-size: 12px; font-weight: 600; color: #6b7385;
        background: white; transition: all .15s; user-select: none; text-align: center;
    }
    .cu-check-item label:hover { border-color: #7c3aed; color: #7c3aed; background: #faf5ff; }
    .cu-check-item input:checked + label { background: #7c3aed; border-color: #7c3aed; color: white; }
    .cu-week-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(40px,1fr)); gap: 5px; }
    .cu-week-grid .cu-check-item label { width: 100%; font-size: 11px; padding: 4px 6px; }

    .cu-action-bar {
        display: flex; align-items: center; justify-content: flex-end; gap: 8px;
        padding: 12px 16px; background: #fafbfc; border-top: 1px solid #e3e4e8;
    }
    .cu-btn-cancel {
        padding: 6px 16px; border: 1px solid #d3d5db; background: white; color: #6b7385;
        border-radius: 6px; font-size: 13px; font-weight: 600; text-decoration: none;
        transition: all .15s; line-height: 1.4;
    }
    .cu-btn-cancel:hover { border-color: #adb0b8; color: #1a1d23; background: #f7f8fa; }
    .cu-btn-save {
        padding: 6px 18px; background: #7c3aed; border: 1px solid #7c3aed;
        color: white; border-radius: 6px; font-size: 13px; font-weight: 600;
        cursor: pointer; transition: all .15s; line-height: 1.4;
    }
    .cu-btn-save:hover { background: #6d28d9; border-color: #6d28d9; box-shadow: 0 2px 6px rgba(109,40,217,.35); }

    .cu-danger-section {
        background: white; border: 1px solid #fca5a5; border-radius: 8px;
        overflow: hidden; margin-top: 10px;
    }
    .cu-danger-header {
        display: flex; align-items: center; gap: 8px;
        padding: 10px 16px; background: #fff5f5; border-bottom: 1px solid #fca5a5;
    }
    .cu-danger-header span { font-size: 13px; font-weight: 700; color: #dc2626; }
    .cu-danger-body { padding: 16px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
    .cu-danger-desc { font-size: 13px; color: #6b7385; }
    .cu-btn-danger {
        padding: 6px 16px; background: #dc2626; border: 1px solid #dc2626;
        color: white; border-radius: 6px; font-size: 13px; font-weight: 600;
        cursor: pointer; transition: all .15s; line-height: 1.4;
    }
    .cu-btn-danger:hover { background: #b91c1c; border-color: #b91c1c; }

    /* ── Responsive ─────────────────────────────────────── */
    @media(max-width:768px) {
        .cu-header { padding: 10px 14px; }
        .cu-header-title { font-size: 15px; }
        .cu-header-sub   { font-size: 11px; }
        .cu-field-row    { grid-template-columns: 1fr; }
        .cu-action-bar   { flex-direction: column-reverse; }
        .cu-btn-cancel,
        .cu-btn-save     { width: 100%; text-align: center; padding: 10px; }
        .cu-danger-body  { flex-direction: column; align-items: flex-start; }
        .cu-btn-danger   { width: 100%; text-align: center; }
    }
</style>
@endpush

@php
    $currentDays      = $routine->decodedDays();
    $currentMonthDays = $routine->decodedMonthDays();
    $freq             = old('frequency', $routine->frequency);
    $periods          = config('routines.periods');
    $whenMode         = old('time_period', $routine->time_period)
        ? 'period'
        : ((old('start_time', $routine->start_time) || old('end_time', $routine->end_time)) ? 'time' : 'none');
@endphp

@section('content')
<div class="main-content">

    {{-- Minimal header --}}
    <div class="cu-header">
        <a href="{{ route('routines.index') }}" class="cu-back">
            <i class="bi bi-arrow-left"></i> Routines
        </a>
        <h1 class="cu-header-title">Edit Routine</h1>
        <p class="cu-header-sub">Update "{{ $routine->title }}"</p>
    </div>

    {{-- Form --}}
    <div class="cu-layout">

        {{-- Form --}}
        <form action="{{ route('routines.update', $routine->id) }}" method="POST" id="routineForm">
            @csrf
            @method('PUT')
            <div class="cu-sections">

                {{-- Basic Info --}}
                <div class="cu-section">
                    <div class="cu-section-header">
                        <span class="cu-section-icon purple"><i class="bi bi-card-text"></i></span>
                        <span class="cu-section-title">Basic Info</span>
                    </div>
                    <div class="cu-section-body">
                        <div class="cu-field">
                            <label for="title" class="cu-label">Title <span style="color:#dc2626;">*</span></label>
                            <div class="cu-input-wrap">
                                <i class="bi bi-card-text"></i>
                                <input type="text" name="title" id="title"
                                       class="cu-input {{ $errors->has('title') ? 'is-invalid' : '' }}"
                                       value="{{ old('title', $routine->title) }}"
                                       placeholder="e.g. Morning workout" required>
                            </div>
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="cu-field">
                            <label for="description" class="cu-label">Description</label>
                            <textarea name="description" id="description"
                                      class="cu-textarea {{ $errors->has('description') ? 'is-invalid' : '' }}"
                                      placeholder="Add details about this routine...">{{ old('description', $routine->description) }}</textarea>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                {{-- Schedule --}}
                <div class="cu-section">
                    <div class="cu-section-header">
                        <span class="cu-section-icon blue"><i class="bi bi-calendar3"></i></span>
                        <span class="cu-section-title">Schedule</span>
                        <span class="cu-section-sub">Frequency &amp; recurrence</span>
                    </div>
                    <div class="cu-section-body">
                        <div class="cu-field">
                            <label class="cu-label">Frequency <span style="color:#dc2626;">*</span></label>
                            <div class="cu-freq-chips">
                                <div class="cu-chip-opt chip-daily">
                                    <input type="radio" name="frequency" id="freq_daily" value="daily"
                                        {{ $freq === 'daily' ? 'checked' : '' }}>
                                    <label for="freq_daily" class="cu-chip-label">
                                        <i class="bi bi-sun"></i> Daily
                                    </label>
                                </div>
                                <div class="cu-chip-opt chip-weekly">
                                    <input type="radio" name="frequency" id="freq_weekly" value="weekly"
                                        {{ $freq === 'weekly' ? 'checked' : '' }}>
                                    <label for="freq_weekly" class="cu-chip-label">
                                        <i class="bi bi-calendar-week"></i> Weekly
                                    </label>
                                </div>
                                <div class="cu-chip-opt chip-monthly">
                                    <input type="radio" name="frequency" id="freq_monthly" value="monthly"
                                        {{ $freq === 'monthly' ? 'checked' : '' }}>
                                    <label for="freq_monthly" class="cu-chip-label">
                                        <i class="bi bi-calendar-month"></i> Monthly
                                    </label>
                                </div>
                                <div class="cu-chip-opt chip-everyn">
                                    <input type="radio" name="frequency" id="freq_everyn" value="every_n_days"
                                        {{ $freq === 'every_n_days' ? 'checked' : '' }}>
                                    <label for="freq_everyn" class="cu-chip-label">
                                        <i class="bi bi-arrow-left-right"></i> Every N days
                                    </label>
                                </div>
                            </div>
                            @error('frequency')<div class="invalid-feedback mt-1">{{ $message }}</div>@enderror
                        </div>

                        {{-- Daily: no selection needed --}}
                        <div class="cu-picker {{ $freq === 'daily' ? 'visible' : '' }}" id="picker-daily">
                            <div class="cu-picker-title">Schedule</div>
                            <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:#5b21b6;font-weight:600;background:#ede9fe;border:1px solid #c4b5fd;border-radius:8px;padding:10px 14px;">
                                <i class="bi bi-sun"></i> This routine runs every day.
                            </div>
                        </div>

                        {{-- Weekly: pick weekdays --}}
                        <div class="cu-picker {{ $freq === 'weekly' ? 'visible' : '' }}" id="picker-weekly">
                            <div class="cu-picker-title">Select days of the week <span style="color:#dc2626;">*</span></div>
                            <div class="cu-check-grid">
                                @foreach(['saturday','sunday','monday','tuesday','wednesday','thursday','friday'] as $day)
                                <div class="cu-check-item">
                                    <input type="checkbox" name="days[]" value="{{ $day }}" id="day_{{ $day }}"
                                        {{ in_array($day, old('days', $currentDays)) ? 'checked' : '' }}>
                                    <label for="day_{{ $day }}">{{ ucfirst(substr($day,0,3)) }}</label>
                                </div>
                                @endforeach
                            </div>
                            @error('days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @error('days.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Monthly: pick days of month 1-31 --}}
                        <div class="cu-picker {{ $freq === 'monthly' ? 'visible' : '' }}" id="picker-monthly">
                            <div class="cu-picker-title">Select days of the month <span style="color:#dc2626;">*</span></div>
                            <div class="cu-week-grid">
                                @for($d = 1; $d <= 31; $d++)
                                <div class="cu-check-item">
                                    <input type="checkbox" name="month_days[]" value="{{ $d }}" id="mday_{{ $d }}"
                                        {{ in_array($d, array_map('intval', old('month_days', $currentMonthDays))) ? 'checked' : '' }}>
                                    <label for="mday_{{ $d }}">{{ $d }}</label>
                                </div>
                                @endfor
                            </div>
                            @error('month_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @error('month_days.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Every N days --}}
                        <div class="cu-picker {{ $freq === 'every_n_days' ? 'visible' : '' }}" id="picker-every_n_days">
                            <div class="cu-picker-title">Run every how many days?</div>
                            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                <input type="number" name="every_n_days" id="every_n_days"
                                       class="cu-input {{ $errors->has('every_n_days') ? 'is-invalid' : '' }}"
                                       style="width:110px;padding-left:10px;"
                                       min="2" max="60" step="1" value="{{ old('every_n_days', $routine->every_n_days ?? 2) }}">
                                <span style="font-size:12px;color:#6b7385;">
                                    <strong>2</strong> = one day on, one day off · <strong>3</strong> = every third day
                                </span>
                            </div>
                            @error('every_n_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                {{-- When --}}
                <div class="cu-section">
                    <div class="cu-section-header">
                        <span class="cu-section-icon green"><i class="bi bi-clock"></i></span>
                        <span class="cu-section-title">When</span>
                        <span class="cu-section-sub">Optional</span>
                    </div>
                    <div class="cu-section-body">
                        <div class="cu-field">
                            <div class="cu-freq-chips">
                                <div class="cu-chip-opt chip-daily">
                                    <input type="radio" name="when_mode" id="when_none" value="none"
                                        {{ $whenMode === 'none' ? 'checked' : '' }}>
                                    <label for="when_none" class="cu-chip-label"><i class="bi bi-dash-circle"></i> Any time</label>
                                </div>
                                <div class="cu-chip-opt chip-weekly">
                                    <input type="radio" name="when_mode" id="when_period" value="period"
                                        {{ $whenMode === 'period' ? 'checked' : '' }}>
                                    <label for="when_period" class="cu-chip-label"><i class="bi bi-sunrise"></i> Time of day</label>
                                </div>
                                <div class="cu-chip-opt chip-monthly">
                                    <input type="radio" name="when_mode" id="when_time" value="time"
                                        {{ $whenMode === 'time' ? 'checked' : '' }}>
                                    <label for="when_time" class="cu-chip-label"><i class="bi bi-clock-history"></i> Exact time</label>
                                </div>
                            </div>
                        </div>

                        {{-- Time of day --}}
                        <div class="cu-when-panel" id="when-panel-period">
                            <div class="cu-picker-title">Choose a time of day</div>
                            <div class="cu-check-grid">
                                @foreach($periods as $key => $period)
                                <div class="cu-check-item">
                                    <input type="radio" name="time_period" value="{{ $key }}" id="period_{{ $key }}"
                                        {{ old('time_period', $routine->time_period) === $key ? 'checked' : '' }}>
                                    <label for="period_{{ $key }}"><i class="bi {{ $period['icon'] }} me-1"></i>{{ $period['label'] }}</label>
                                </div>
                                @endforeach
                            </div>
                            @error('time_period')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Exact time --}}
                        <div class="cu-when-panel" id="when-panel-time">
                            <div class="cu-field-row">
                                <div class="cu-field" style="margin-bottom:0;">
                                    <label for="start_time" class="cu-label">Start Time</label>
                                    <div class="cu-input-wrap">
                                        <i class="bi bi-clock"></i>
                                        <input type="time" name="start_time" id="start_time"
                                               class="cu-input {{ $errors->has('start_time') ? 'is-invalid' : '' }}"
                                               value="{{ old('start_time', $routine->start_time ? \Carbon\Carbon::parse($routine->start_time)->format('H:i') : '') }}">
                                    </div>
                                    @error('start_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="cu-field" style="margin-bottom:0;">
                                    <label for="end_time" class="cu-label">End Time</label>
                                    <div class="cu-input-wrap">
                                        <i class="bi bi-clock-fill"></i>
                                        <input type="time" name="end_time" id="end_time"
                                               class="cu-input {{ $errors->has('end_time') ? 'is-invalid' : '' }}"
                                               value="{{ old('end_time', $routine->end_time ? \Carbon\Carbon::parse($routine->end_time)->format('H:i') : '') }}">
                                    </div>
                                    @error('end_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Tracking (optional measurable routines) --}}
                <div class="cu-section">
                    <div class="cu-section-header">
                        <span class="cu-section-icon purple"><i class="bi bi-graph-up"></i></span>
                        <span class="cu-section-title">Tracking</span>
                        <span class="cu-section-sub">Optional — log a number each day</span>
                    </div>
                    <div class="cu-section-body">
                        @php $tm = old('tracking_mode', $routine->tracking_mode ?? 'none'); @endphp
                        <select name="tracking_mode" id="tracking_mode" class="cu-input">
                            <option value="none" {{ $tm === 'none' ? 'selected' : '' }}>No tracking — just tick</option>
                            <option value="value" {{ $tm === 'value' ? 'selected' : '' }}>One value per day (e.g. weight)</option>
                            <option value="sets" {{ $tm === 'sets' ? 'selected' : '' }}>Sets per step (e.g. workout moves)</option>
                        </select>
                        <div id="tracking-value-fields" style="display:flex;gap:6px;margin-top:8px;">
                            <select name="value_kind" class="cu-input" style="flex:1;" title="Value kind">
                                @foreach(['number' => 'Number', 'weight' => 'Weight', 'time' => 'Time', 'reps' => 'Reps', 'percent' => 'Percent'] as $k => $lbl)
                                    <option value="{{ $k }}" {{ old('value_kind', $routine->value_kind) === $k ? 'selected' : '' }}>{{ $lbl }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="value_unit" class="cu-input" style="width:90px;" placeholder="Unit (kg)" maxlength="20" value="{{ old('value_unit', $routine->value_unit) }}">
                            <input type="text" name="value_label" class="cu-input" style="flex:1;" placeholder="Label (e.g. Weight)" maxlength="100" value="{{ old('value_label', $routine->value_label) }}">
                        </div>
                        <div id="tracking-sets-hint" style="font-size:11px;color:#8a8f98;margin-top:8px;">
                            Set the number of sets per step below — each set is logged with its number every day.
                        </div>
                    </div>
                </div>

                {{-- Steps (optional sub-items) --}}
                <div class="cu-section">
                    <div class="cu-section-header">
                        <span class="cu-section-icon purple"><i class="bi bi-list-check"></i></span>
                        <span class="cu-section-title">Steps</span>
                        <span class="cu-section-sub">Optional — tick each part to finish</span>
                    </div>
                    <div class="cu-section-body">
                        <div id="stepRows"></div>
                        <button type="button" class="cu-chip-label" style="margin-top:4px;" onclick="addStepRow()">
                            <i class="bi bi-plus-lg"></i> Add step
                        </button>
                        <div style="font-size:11px;color:#8a8f98;margin-top:8px;">
                            e.g. "Set 1 (3×15)", "Set 2 (3×15)", "Set 3 (3×15)" — on the Day page the routine auto-completes when all steps are ticked.
                        </div>
                        @error('items.*.name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="cu-action-bar" style="background:white;border:1px solid #e3e4e8;border-radius:8px;">
                    <a href="{{ route('routines.index') }}" class="cu-btn-cancel">Cancel</a>
                    <button type="submit" class="cu-btn-save">
                        <i class="bi bi-check-lg me-1"></i>Save Changes
                    </button>
                </div>

            </div>
        </form>

    </div>{{-- /.cu-layout --}}

    {{-- Danger Zone --}}
    <div class="cu-danger-section">
        <div class="cu-danger-header">
            <i class="bi bi-exclamation-triangle-fill" style="color:#dc2626;font-size:14px;"></i>
            <span>Danger Zone</span>
        </div>
        <div class="cu-danger-body">
            <div class="cu-danger-desc">
                Permanently delete this routine. This action <strong>cannot be undone</strong>.
            </div>
            <form action="{{ route('routines.destroy', $routine->id) }}" method="POST" id="deleteForm">
                @csrf
                @method('DELETE')
                <button type="button" class="cu-btn-danger" onclick="confirmDelete()">
                    <i class="bi bi-trash me-1"></i>Delete Routine
                </button>
            </form>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const radios  = document.querySelectorAll('input[name="frequency"]');
    const pickers = {
        daily:   document.getElementById('picker-daily'),
        weekly:  document.getElementById('picker-weekly'),
        monthly: document.getElementById('picker-monthly'),
        every_n_days: document.getElementById('picker-every_n_days'),
    };

    function updatePickers(val) {
        Object.entries(pickers).forEach(([key, el]) => {
            el.classList.toggle('visible', key === val);
        });
    }

    radios.forEach(r => r.addEventListener('change', () => updatePickers(r.value)));

    // "When" mode: Any time / Time of day / Exact time
    const whenPanels = {
        period: document.getElementById('when-panel-period'),
        time:   document.getElementById('when-panel-time'),
    };
    const periodInputs = whenPanels.period ? whenPanels.period.querySelectorAll('input') : [];
    const timeInputs   = whenPanels.time ? whenPanels.time.querySelectorAll('input') : [];

    function updateWhen(mode) {
        if (whenPanels.period) whenPanels.period.style.display = mode === 'period' ? 'block' : 'none';
        if (whenPanels.time)   whenPanels.time.style.display   = mode === 'time' ? 'block' : 'none';
        periodInputs.forEach(i => i.disabled = mode !== 'period');
        timeInputs.forEach(i => i.disabled = mode !== 'time');
    }

    document.querySelectorAll('input[name="when_mode"]').forEach(r => r.addEventListener('change', () => updateWhen(r.value)));
    const whenChecked = document.querySelector('input[name="when_mode"]:checked') || document.getElementById('when_none');
    if (whenChecked) { whenChecked.checked = true; updateWhen(whenChecked.value); }

    /* ── Steps editor ── */
    let stepIdx = 0;
    window.addStepRow = function (name = '', id = '', sets = 1, unit = '', timePeriod = '', scheduledTime = '') {
        const i = stepIdx++;
        const wrap = document.createElement('div');
        wrap.style.cssText = 'display:flex;gap:6px;align-items:center;margin-bottom:6px;flex-wrap:wrap;';
        wrap.innerHTML = `
            <input type="hidden" name="items[${i}][id]" value="${id}">
            <input type="text" class="cu-input" name="items[${i}][name]" style="padding-left:10px;flex:1;min-width:140px;" placeholder="e.g. Pull-up" maxlength="255">
            <select class="cu-input" name="items[${i}][time_period]" style="width:125px;flex-shrink:0;" title="Time of day">
                <option value="">Any time</option>
                @foreach(config('routines.periods', []) as $key => $period)
                <option value="{{ $key }}">{{ $period['label'] }}</option>
                @endforeach
            </select>
            <input type="time" class="cu-input" name="items[${i}][scheduled_time]" style="width:112px;flex-shrink:0;" title="Exact time">
            <span style="display:flex;align-items:center;gap:3px;color:#8a8f98;font-size:12px;font-weight:700;flex-shrink:0;" title="How many sets are logged per day">×
                <input type="number" class="cu-input" name="items[${i}][target_sets]" style="width:54px;text-align:center;padding-left:6px;padding-right:6px;" min="1" max="20" value="${sets}">
                <span style="font-weight:600;">sets</span>
            </span>
            <input type="text" class="cu-input" name="items[${i}][unit]" style="width:70px;flex-shrink:0;" placeholder="unit" title="Unit shown next to sets (e.g. kg, reps)" maxlength="20" value="${unit}">
            <button type="button" class="cu-chip-label" style="padding:4px 8px;color:#dc2626;border-color:#fecaca;flex-shrink:0;" title="Remove"><i class="bi bi-x-lg"></i></button>`;
        wrap.querySelector('input[type=text]').value = name;
        const periodSelect = wrap.querySelector('select');
        const timeInput = wrap.querySelector('input[type=time]');
        periodSelect.value = timePeriod || '';
        timeInput.value = scheduledTime || '';
        periodSelect.addEventListener('change', () => { if (periodSelect.value) timeInput.value = ''; });
        timeInput.addEventListener('input', () => { if (timeInput.value) periodSelect.value = ''; });
        wrap.querySelector('button').onclick = () => wrap.remove();
        document.getElementById('stepRows').appendChild(wrap);
    };
    @php
        $initialSteps = collect(old('items', $routine->checklistItems->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'target_sets' => $s->target_sets,
            'unit' => $s->unit,
            'time_period' => $s->time_period,
            'scheduled_time' => $s->scheduled_time ? substr((string) $s->scheduled_time, 0, 5) : '',
        ])->all()));
    @endphp
    @foreach($initialSteps as $row)
    addStepRow(@json($row['name'] ?? ''), @json($row['id'] ?? ''), @json($row['target_sets'] ?? 1), @json($row['unit'] ?? ''), @json($row['time_period'] ?? ''), @json($row['scheduled_time'] ?? ''));
    @endforeach

    /* ── Tracking mode toggle ── */
    (function () {
        const mode = document.getElementById('tracking_mode');
        const valFields = document.getElementById('tracking-value-fields');
        const setsHint = document.getElementById('tracking-sets-hint');
        function updateTracking() {
            valFields.style.display = mode.value === 'value' ? '' : 'none';
            setsHint.style.display = mode.value === 'sets' ? '' : 'none';
        }
        mode.addEventListener('change', updateTracking);
        updateTracking();
    })();
});

function confirmDelete() {
    if (confirm('Delete this routine? This cannot be undone.')) {
        document.getElementById('deleteForm').submit();
    }
}
</script>
@endpush

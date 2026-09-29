@php
    $nextTask = $nextUp['task'] ?? null;
    $nextRoutine = $nextUp['routine'] ?? null;
    $priorityColors = ['high' => '#dc2626', 'medium' => '#d97706', 'low' => '#16a34a'];
    $stepsDone = $nextUp['stepsDone'] ?? 0;
    $stepsTotal = $nextUp['stepsTotal'] ?? 0;
    $nextStep = $nextUp['nextStep'] ?? null;
    $hasSteps = $stepsTotal > 0;
    $isAvoid = ! empty($nextUp['is_avoid']);
    /* Routines that log a single value per day get a guided input too. */
    $valueGuide = $nextRoutine
        && $nextRoutine->tracking_mode === 'value'
        && ($nextRoutine->logValues['value'] ?? null) === null;
@endphp
<div class="pl-next-wrap" id="plNextUp" data-next-date="{{ $date->toDateString() }}" @if(! $nextUp) hidden @endif>
    @if($nextUp)
        <div class="pl-next-card"
             data-next-type="{{ $nextUp['type'] }}"
             data-next-id="{{ $nextTask?->id ?? $nextRoutine?->id }}"
             data-next-url="{{ $nextTask ? route('planner.tasks.toggle', $nextTask) : route('planner.routines.toggle', $nextRoutine) }}"
             @if($isAvoid && $nextRoutine) data-slip-url="{{ $nextStep ? route('planner.check-items.slip', $nextStep['id']) : route('planner.routines.slip', $nextRoutine) }}"@endif>
            <span class="pl-next-label"><i class="bi bi-lightning-charge-fill"></i> {{ __('Next up') }}</span>

            <div class="pl-next-body">
                <div class="pl-next-title">
                    <i class="bi {{ $nextTask ? 'bi-check2-square' : 'bi-arrow-repeat' }}"></i>
                    {{ $nextTask ? __($nextTask->title) : __($nextRoutine?->title) }}
                </div>
                <div class="pl-next-meta">
                    @if($nextTask)
                        @php $pc = $priorityColors[$nextTask->priority] ?? '#94a3b8'; @endphp
                        <span class="pl-priority" style="color:{{ $pc }};background:{{ $pc }}1a;">{{ __(ucfirst($nextTask->priority)) }}</span>
                        @if($nextTask->project)
                            <span class="pl-proj"><i class="bi bi-folder"></i> {{ $nextTask->project->name }}</span>
                        @endif
                        @if($nextTask->dueTimeLabel())
                            <span class="pl-due"><i class="bi bi-clock"></i> {{ $nextTask->dueTimeLabel() }}</span>
                        @endif
                        @if($nextTask->estimatedLabel())
                            <span class="pl-due"><i class="bi bi-hourglass-split"></i> {{ $nextTask->estimatedLabel() }}</span>
                        @endif
                    @else
                        @if($nextRoutine->activeStepSchedule)
                            @php $as = $nextRoutine->activeStepSchedule; @endphp
                            <span class="pl-priority" style="color:{{ $as['period_color'] ?: '#64748b' }};background:{{ $as['period_color'] ?: '#64748b' }}1a;text-transform:none;">
                                <i class="bi {{ $as['period_icon'] ?: 'bi-clock' }}"></i> {{ $as['period_label'] ? __($as['period_label']) : $as['time_label'] }}
                            </span>
                        @elseif($nextRoutine->time_period)
                            <span class="pl-priority" style="color:{{ $nextRoutine->periodColor() }};background:{{ $nextRoutine->periodColor() }}1a;text-transform:none;">
                                <i class="bi {{ $nextRoutine->periodIcon() }}"></i> {{ __($nextRoutine->periodLabel()) }}
                            </span>
                        @else
                            <span class="pl-priority" style="color:#7c3aed;background:#7c3aed1a;text-transform:none;">
                                <i class="bi bi-arrow-repeat"></i> {{ __(ucfirst($nextRoutine->frequency)) }}
                            </span>
                        @endif
                    @endif
                    @if($hasSteps)
                        @if($isAvoid)
                            @if($stepsDone > 0)
                                <span class="steps-count bad">{{ $stepsDone }}/{{ $stepsTotal }} {{ __('slips') }}</span>
                            @endif
                        @else
                            <span class="steps-count {{ $stepsDone === $stepsTotal ? 'all' : '' }}">{{ $stepsDone }}/{{ $stepsTotal }}</span>
                        @endif
                    @endif
                </div>
            </div>

            @if($isAvoid && $nextRoutine)
                {{-- Avoid habit: guide the first clean step, slips only — never a check. --}}
                @if($nextStep)
                <div class="pl-next-step" data-next-step
                     data-step-id="{{ $nextStep['id'] }}"
                     data-slip-url="{{ route('planner.check-items.slip', $nextStep['id']) }}">
                    <i class="bi bi-shield"></i>
                    <span class="pl-next-step-name">{{ __($nextStep['name']) }}
                        @if(!empty($nextStep['period_label']) || !empty($nextStep['time_label']))
                            <span class="pl-step-schedule" style="color:{{ $nextStep['period_color'] ?: '#64748b' }};font-size:10.5px;">
                                <i class="bi {{ $nextStep['period_icon'] ?: 'bi-clock' }}"></i>
                                {{ $nextStep['period_label'] ? __($nextStep['period_label']) : $nextStep['time_label'] }}
                            </span>
                        @endif
                    </span>
                </div>
                @endif
            @elseif($hasSteps && $nextStep)
                {{-- Point at the first unfinished step; mini set inputs when that step is tracked --}}
                <div class="pl-next-step" data-next-step
                     data-step-id="{{ $nextStep['id'] }}"
                     data-step-url="{{ $nextTask ? route('planner.task-items.toggle', $nextStep['id']) : route('planner.check-items.toggle', $nextStep['id']) }}"
                     data-logged="{{ !empty($nextStep['sets']) ? 1 : 0 }}"
                     @if($nextRoutine && $nextRoutine->tracking_mode === 'sets') data-logsets="1" data-log-url="{{ route('planner.routines.log', $nextRoutine) }}" @endif>
                    <i class="bi bi-diagram-3"></i>
                    <span class="pl-next-step-name">{{ __($nextStep['name']) }}
                        @if(!empty($nextStep['period_label']) || !empty($nextStep['time_label']))
                            <span class="pl-step-schedule" style="color:{{ $nextStep['period_color'] ?: '#64748b' }};font-size:10.5px;">
                                <i class="bi {{ $nextStep['period_icon'] ?: 'bi-clock' }}"></i>
                                {{ $nextStep['period_label'] ? __($nextStep['period_label']) : $nextStep['time_label'] }}
                            </span>
                        @endif
                    </span>
                    @if($nextRoutine && $nextRoutine->tracking_mode === 'sets')
                        @php
                            $target = max(1, (int) ($nextStep['target_sets'] ?? 1));
                            $logged = $nextStep['sets'] ?? [];
                            $unit = $nextStep['unit'] ?? null;
                        @endphp
                        <span class="pl-next-set-inputs">
                            @for($s = 1; $s <= $target; $s++)
                                <input type="number" min="0" step="any" data-set="{{ $s }}"
                                       value="{{ $logged[$s] ?? '' }}"
                                       placeholder="{{ ($logged[$s] ?? '') !== '' ? '' : ($unit ?: 'S'.$s) }}"
                                       aria-label="{{ __($nextStep['name']) }} {{ __('Set') }} {{ $s }}">
                            @endfor
                        </span>
                    @endif
                </div>
            @elseif($valueGuide)
                {{-- Value-tracked routine: one simple input before it can complete --}}
                <div class="pl-next-step" data-next-step data-logvalue="1" data-kind="{{ $nextRoutine->isTimeValue() ? 'time' : 'number' }}"
                     data-log-url="{{ route('planner.routines.log', $nextRoutine) }}">
                    <i class="bi bi-clipboard-data"></i>
                    <span class="pl-next-step-name">{{ __($nextRoutine->value_label ?: $nextRoutine->trackingLabel()) }}</span>
                    <span class="pl-next-set-inputs">
                        @if($nextRoutine->isTimeValue())
                            <div class="pl-time-picker-custom">
                                <i class="bi bi-alarm-fill pl-time-icon"></i>
                                <input type="time" step="60" data-next-value-input
                                       class="pl-modern-time-input"
                                       aria-label="{{ __($nextRoutine->value_label ?: $nextRoutine->trackingLabel()) }}">
                            </div>
                        @else
                            <input type="number" min="0" step="any" data-next-value-input
                                   placeholder="{{ $nextRoutine->value_unit ?: '0' }}"
                                   aria-label="{{ __($nextRoutine->value_label ?: $nextRoutine->trackingLabel()) }}">
                        @endif
                    </span>
                </div>
            @endif

            <div class="pl-next-actions">
                @if($isAvoid && $nextRoutine)
                    <button type="button" class="pl-next-done" onclick="plSlipNext()" style="background:#b91c1c;color:#fff;border-color:#b91c1c;">
                        <i class="bi bi-exclamation-triangle"></i>
                        {{ __('Log Slip') }}
                    </button>
                @else
                    @if($nextTask)
                        <button type="button" class="btn btn-sm btn-outline-light d-inline-flex align-items-center gap-1 rounded-pill px-3 me-1" onclick="openFocusWorkstation({{ $nextTask->id }}, '{{ addslashes($nextTask->title) }}', '{{ addslashes($nextTask->project->name ?? 'My Day') }}')" title="{{ __('Open in Fullscreen Focus Mode') }}">
                            <i class="bi bi-bullseye"></i> {{ __('Focus') }}
                        </button>
                        <button type="button" class="pl-next-start" onclick="plStartNext()">
                            <i class="bi bi-play-fill"></i> {{ __('Start') }}
                        </button>
                    @endif
                    <button type="button" class="pl-next-done" onclick="plCompleteNext()">
                        <i class="bi bi-check-lg"></i>
                        @if($hasSteps && $nextStep)
                            {{ __('Complete step') }}
                        @elseif($valueGuide)
                            {{ __('Log') }}
                        @else
                            {{ __('Complete') }}
                        @endif
                    </button>
                @endif
            </div>
        </div>
    @endif
</div>

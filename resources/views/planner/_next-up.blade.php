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

    $isSets = $nextRoutine && $nextRoutine->tracking_mode === 'sets';
    $isTime = $nextRoutine && $nextRoutine->isTimeValue();
    $needsModal = $valueGuide || $isSets || $isAvoid || ($hasSteps && $stepsTotal > 1);

    // Prepare steps JSON if needed
    $allSteps = [];
    if ($nextRoutine && !empty($nextRoutine->ringSteps)) {
        $allSteps = collect($nextRoutine->ringSteps)->map(function($s) {
            return [
                'id' => $s['id'] ?? null,
                'name' => $s['name'] ?? '',
                'completed' => !empty($s['completed']),
                'violated' => !empty($s['violated']),
                'target_sets' => $s['target_sets'] ?? 1,
                'unit' => $s['unit'] ?? '',
                'period_label' => $s['period_label'] ?? '',
                'period_icon' => $s['period_icon'] ?? '',
                'period_color' => $s['period_color'] ?? '',
            ];
        })->values()->all();
    } elseif ($nextTask && $nextTask->checklistItems) {
        $allSteps = $nextTask->checklistItems->map(function($s) {
            return [
                'id' => $s->id,
                'name' => $s->name,
                'completed' => (bool)$s->completed,
                'target_sets' => 1,
            ];
        })->values()->all();
    }
@endphp

<div class="pl-next-wrap" id="plNextUp" data-next-date="{{ $date->toDateString() }}" @if(! $nextUp) hidden @endif>
    @if($nextUp)
        <div class="pl-next-card {{ $isAvoid ? 'pl-next-card-avoid' : '' }}"
             role="button"
             tabindex="0"
             onclick="handleNextUpCardClick(event)"
             data-next-type="{{ $nextUp['type'] }}"
             data-next-id="{{ $nextTask?->id ?? $nextRoutine?->id }}"
             data-next-title="{{ $nextTask ? $nextTask->title : $nextRoutine?->title }}"
             data-next-url="{{ $nextTask ? route('planner.tasks.toggle', $nextTask) : route('planner.routines.toggle', $nextRoutine) }}"
             data-has-steps="{{ $hasSteps ? 1 : 0 }}"
             data-steps-done="{{ $stepsDone }}"
             data-steps-total="{{ $stepsTotal }}"
             data-step-id="{{ $nextStep['id'] ?? '' }}"
             data-step-name="{{ $nextStep['name'] ?? '' }}"
             data-step-url="{{ $nextStep ? ($nextTask ? route('planner.task-items.toggle', $nextStep['id']) : route('planner.check-items.toggle', $nextStep['id'])) : '' }}"
             data-slip-url="{{ $isAvoid && $nextRoutine ? ($nextStep ? route('planner.check-items.slip', $nextStep['id']) : route('planner.routines.slip', $nextRoutine)) : '' }}"
             data-is-avoid="{{ $isAvoid ? 1 : 0 }}"
             data-needs-modal="{{ $needsModal ? 1 : 0 }}"
             data-track-mode="{{ $nextRoutine ? $nextRoutine->tracking_mode : 'none' }}"
             data-log-url="{{ $nextRoutine ? route('planner.routines.log', $nextRoutine) : '' }}"
             data-value-kind="{{ $isTime ? 'time' : 'number' }}"
             data-value-unit="{{ $nextRoutine ? ($nextRoutine->value_unit ?? '') : '' }}"
             data-value-label="{{ $nextRoutine ? ($nextRoutine->value_label ?: $nextRoutine->trackingLabel()) : '' }}"
             data-target-sets="{{ $nextStep['target_sets'] ?? ($isSets ? 3 : 1) }}"
             data-logged-sets="{{ json_encode($nextStep['sets'] ?? []) }}"
             data-all-steps="{{ json_encode($allSteps) }}">
            
            {{-- Glowing Accent Top Badge --}}
            <div class="pl-next-top-badge">
                <span class="pl-next-label">
                    <i class="bi bi-lightning-charge-fill"></i> {{ __('Next up') }}
                </span>
                @if($hasSteps)
                    <span class="pl-next-step-indicator {{ $stepsDone === $stepsTotal ? 'all-done' : '' }}">
                        <i class="bi bi-check2-all"></i>
                        {{ $stepsDone }}/{{ $stepsTotal }}
                    </span>
                @endif
            </div>

            {{-- Body Info --}}
            <div class="pl-next-body">
                <div class="pl-next-title-row">
                    <div class="pl-next-icon-wrap {{ $nextTask ? 'is-task' : 'is-routine' }} {{ $isAvoid ? 'is-avoid' : '' }}">
                        <i class="bi {{ $isAvoid ? 'bi-shield-exclamation' : ($nextTask ? 'bi-check2-square' : 'bi-arrow-repeat') }}"></i>
                    </div>
                    <div class="pl-next-title-content">
                        <div class="pl-next-title">
                            {{ $nextTask ? __($nextTask->title) : __($nextRoutine?->title) }}
                        </div>
                        <div class="pl-next-meta">
                            @if($nextTask)
                                @php $pc = $priorityColors[$nextTask->priority] ?? '#94a3b8'; @endphp
                                <span class="pl-priority-pill" style="color:{{ $pc }};background:{{ $pc }}14;border-color:{{ $pc }}33;">
                                    {{ __(ucfirst($nextTask->priority)) }}
                                </span>
                                @if($nextTask->project)
                                    <span class="pl-meta-tag"><i class="bi bi-folder2-open"></i> {{ $nextTask->project->name }}</span>
                                @endif
                                @if($nextTask->dueTimeLabel())
                                    <span class="pl-meta-tag"><i class="bi bi-clock"></i> {{ $nextTask->dueTimeLabel() }}</span>
                                @endif
                                @if($nextTask->estimatedLabel())
                                    <span class="pl-meta-tag"><i class="bi bi-hourglass-split"></i> {{ $nextTask->estimatedLabel() }}</span>
                                @endif
                            @else
                                @if($nextRoutine->activeStepSchedule)
                                    @php $as = $nextRoutine->activeStepSchedule; @endphp
                                    <span class="pl-period-pill" style="color:{{ $as['period_color'] ?: '#64748b' }};background:{{ $as['period_color'] ?: '#64748b' }}14;border-color:{{ $as['period_color'] ?: '#64748b' }}33;">
                                        <i class="bi {{ $as['period_icon'] ?: 'bi-clock' }}"></i> {{ $as['period_label'] ? __($as['period_label']) : $as['time_label'] }}
                                    </span>
                                @elseif($nextRoutine->time_period)
                                    <span class="pl-period-pill" style="color:{{ $nextRoutine->periodColor() }};background:{{ $nextRoutine->periodColor() }}14;border-color:{{ $nextRoutine->periodColor() }}33;">
                                        <i class="bi {{ $nextRoutine->periodIcon() }}"></i> {{ __($nextRoutine->periodLabel()) }}
                                    </span>
                                @else
                                    <span class="pl-period-pill" style="color:#7c3aed;background:#7c3aed14;border-color:#7c3aed33;">
                                        <i class="bi bi-arrow-repeat"></i> {{ __(ucfirst($nextRoutine->frequency)) }}
                                    </span>
                                @endif

                                @if(!empty($nextRoutine->ringStreak) && $nextRoutine->ringStreak > 1)
                                    <span class="pl-streak-tag">
                                        <i class="bi bi-fire"></i> {{ $nextRoutine->ringStreak }}
                                    </span>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Prominent Context Prompt Pill (Step name / Value / Sets / Avoid) --}}
                @if($isAvoid && $nextRoutine && $nextStep)
                    <div class="pl-next-prompt-pill avoid">
                        <i class="bi bi-shield"></i>
                        <span>{{ __($nextStep['name']) }}</span>
                    </div>
                @elseif($hasSteps && $nextStep)
                    <div class="pl-next-prompt-pill step">
                        <i class="bi bi-diagram-3"></i>
                        <span class="fw-bold">{{ __('Next Step') }}:</span>
                        <span>{{ __($nextStep['name']) }}</span>
                        @if(!empty($nextStep['period_label']) || !empty($nextStep['time_label']))
                            <span class="pl-step-schedule-mini" style="color:{{ $nextStep['period_color'] ?: '#7c3aed' }};">
                                <i class="bi {{ $nextStep['period_icon'] ?: 'bi-clock' }}"></i>
                                {{ $nextStep['period_label'] ? __($nextStep['period_label']) : $nextStep['time_label'] }}
                            </span>
                        @endif
                    </div>
                @elseif($valueGuide)
                    <div class="pl-next-prompt-pill value">
                        <i class="bi {{ $isTime ? 'bi-alarm-fill' : 'bi-clipboard-data-fill' }}"></i>
                        <span>{{ __($nextRoutine->value_label ?: ($isTime ? 'Wake-up Time' : 'Value')) }}</span>
                        @if(!$isTime && $nextRoutine->value_unit)
                            <span class="badge bg-light text-secondary border px-1.5 py-0.5">{{ $nextRoutine->value_unit }}</span>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Actions --}}
            <div class="pl-next-actions">
                @if($isAvoid && $nextRoutine)
                    <button type="button" class="pl-action-btn pl-action-btn-slip" onclick="event.stopPropagation(); openNextUpModal();">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        {{ __('Log Slip') }}
                    </button>
                @else
                    @if($nextTask)
                        <button type="button" class="pl-action-btn pl-action-btn-focus" onclick="event.stopPropagation(); openFocusWorkstation({{ $nextTask->id }}, '{{ addslashes($nextTask->title) }}', '{{ addslashes($nextTask->project->name ?? 'My Day') }}')" title="{{ __('Focus Mode • Workstation') }}">
                            <i class="bi bi-bullseye"></i> {{ __('Focus') }}
                        </button>
                        <button type="button" class="pl-action-btn pl-action-btn-start" onclick="event.stopPropagation(); plStartNext();">
                            <i class="bi bi-play-fill"></i> {{ __('Start') }}
                        </button>
                    @endif

                    <button type="button" class="pl-action-btn pl-action-btn-primary" onclick="event.stopPropagation(); handleNextUpClick();">
                        <i class="bi {{ $needsModal ? 'bi-pencil-square' : 'bi-check-lg' }}"></i>
                        @if($needsModal)
                            @if($isTime)
                                {{ __('Log Time') }}
                            @elseif($valueGuide)
                                {{ __('Log Value') }}
                            @elseif($isSets)
                                {{ __('Log Sets') }}
                            @else
                                {{ __('Quick Action') }}
                            @endif
                        @else
                            @if($hasSteps && $nextStep)
                                {{ __('Complete step') }}
                            @else
                                {{ __('Complete') }}
                            @endif
                        @endif
                    </button>
                @endif
            </div>

        </div>
    @endif
</div>

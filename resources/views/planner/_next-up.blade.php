@php
    $nextTask = $nextUp['task'] ?? null;
    $nextRoutine = $nextUp['routine'] ?? null;
    $priorityColors = ['high' => '#dc2626', 'medium' => '#d97706', 'low' => '#16a34a'];
    $stepsDone = $nextUp['stepsDone'] ?? 0;
    $stepsTotal = $nextUp['stepsTotal'] ?? 0;
    $nextStep = $nextUp['nextStep'] ?? null;
    $hasSteps = $stepsTotal > 0;
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
             data-next-url="{{ $nextTask ? route('planner.tasks.toggle', $nextTask) : route('planner.routines.toggle', $nextRoutine) }}">
            <span class="pl-next-label"><i class="bi bi-lightning-charge-fill"></i> Next up</span>

            <div class="pl-next-body">
                <div class="pl-next-title">
                    <i class="bi {{ $nextTask ? 'bi-check2-square' : 'bi-arrow-repeat' }}"></i>
                    {{ $nextTask?->title ?? $nextRoutine?->title }}
                </div>
                <div class="pl-next-meta">
                    @if($nextTask)
                        @php $pc = $priorityColors[$nextTask->priority] ?? '#94a3b8'; @endphp
                        <span class="pl-priority" style="color:{{ $pc }};background:{{ $pc }}1a;">{{ ucfirst($nextTask->priority) }}</span>
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
                        @if($nextRoutine->time_period)
                            <span class="pl-priority" style="color:{{ $nextRoutine->periodColor() }};background:{{ $nextRoutine->periodColor() }}1a;text-transform:none;">
                                <i class="bi {{ $nextRoutine->periodIcon() }}"></i> {{ $nextRoutine->periodLabel() }}
                            </span>
                        @else
                            <span class="pl-priority" style="color:#7c3aed;background:#7c3aed1a;text-transform:none;">
                                <i class="bi bi-arrow-repeat"></i> {{ ucfirst($nextRoutine->frequency) }}
                            </span>
                        @endif
                    @endif
                    @if($hasSteps)
                        <span class="steps-count {{ $stepsDone === $stepsTotal ? 'all' : '' }}">{{ $stepsDone }}/{{ $stepsTotal }}</span>
                    @endif
                </div>
            </div>

            @if($hasSteps && $nextStep)
                {{-- Point at the first unfinished step; mini set inputs when that step is tracked --}}
                <div class="pl-next-step" data-next-step
                     data-step-id="{{ $nextStep['id'] }}"
                     data-step-url="{{ $nextTask ? route('planner.task-items.toggle', $nextStep['id']) : route('planner.check-items.toggle', $nextStep['id']) }}"
                     @if($nextRoutine && $nextRoutine->tracking_mode === 'sets') data-logsets="1" data-log-url="{{ route('planner.routines.log', $nextRoutine) }}" @endif>
                    <i class="bi bi-diagram-3"></i>
                    <span class="pl-next-step-name">{{ $nextStep['name'] }}</span>
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
                                       aria-label="{{ $nextStep['name'] }} set {{ $s }}">
                            @endfor
                        </span>
                    @endif
                </div>
            @elseif($valueGuide)
                {{-- Value-tracked routine: one simple input before it can complete --}}
                <div class="pl-next-step" data-next-step data-logvalue="1" data-kind="{{ $nextRoutine->isTimeValue() ? 'time' : 'number' }}"
                     data-log-url="{{ route('planner.routines.log', $nextRoutine) }}">
                    <i class="bi bi-clipboard-data"></i>
                    <span class="pl-next-step-name">{{ $nextRoutine->trackingLabel() }}</span>
                    <span class="pl-next-set-inputs">
                        @if($nextRoutine->isTimeValue())
                            <input type="time" step="60" data-next-value-input
                                   aria-label="{{ $nextRoutine->trackingLabel() }}">
                        @else
                            <input type="number" min="0" step="any" data-next-value-input
                                   placeholder="{{ $nextRoutine->value_unit ?: '0' }}"
                                   aria-label="{{ $nextRoutine->trackingLabel() }}">
                        @endif
                    </span>
                </div>
            @endif

            <div class="pl-next-actions">
                @if($nextTask)
                    <button type="button" class="pl-next-start" onclick="plStartNext()">
                        <i class="bi bi-play-fill"></i> Start
                    </button>
                @endif
                <button type="button" class="pl-next-done" onclick="plCompleteNext()">
                    <i class="bi bi-check-lg"></i>
                    @if($hasSteps && $nextStep)
                        Complete step
                    @elseif($valueGuide)
                        Log
                    @else
                        Complete
                    @endif
                </button>
            </div>
        </div>
    @endif
</div>

@php
    $routineDate = $routineDate ?? now();
    $toggleable  = $toggleable ?? true;
    $record      = $toggleable ? $routine->completionRecord($routineDate) : null;
    $isDone      = $record !== null && ($record->status ?? 'done') === \App\Models\RoutineCompletion::STATUS_DONE;
    $isSkipped   = $record !== null && ($record->status ?? 'done') === \App\Models\RoutineCompletion::STATUS_SKIPPED;
    $doneAt      = $record?->completed_at;
    $freqColors  = ['daily' => '#7c3aed', 'weekly' => '#2563eb', 'monthly' => '#d97706', 'every_n_days' => '#0e7490'];
    $fc          = $freqColors[$routine->frequency] ?? '#7c3aed';
    $freqIcons   = ['daily' => 'bi-sun', 'weekly' => 'bi-calendar-week', 'monthly' => 'bi-calendar-month', 'every_n_days' => 'bi-arrow-left-right'];
    $fi          = $freqIcons[$routine->frequency] ?? 'bi-arrow-repeat';
    $active      = $routine->activeStepSchedule ?? null;
    $accent      = ($active['period_color'] ?? null) ?: $routine->periodColor() ?: $fc;
    $isAvoid    = ($routine->behavior_type ?? 'build') === 'avoid';
    $avoidQty   = $toggleable ? (int) ($routine->avoidDayQty ?? 0) : 0;
    $avoidBad   = $avoidQty > 0;
    $countMode  = ! empty($routine->count_violations);
    $nowLocal   = now()->format('Y-m-d\TH:i');

    /* Habit Ring (package A): adherence ring + streak flame + last-7 dots */
    $ringRate    = $routine->ringRate ?? null;
    $ringStreak  = $routine->ringStreak ?? null;
    $ringLast7   = $routine->ringLast7 ?? null;
    $ringC       = 2 * M_PI * 15.5;
    $ringOffset  = $ringRate !== null ? $ringC - ($ringC * $ringRate / 100) : 0;

    /* Tracking: value mode shows one input, sets mode shows per-step set inputs */
    $trackMode   = $routine->tracking_mode ?? 'none';
    $logValues   = $toggleable ? ($routine->logValues ?? []) : [];
    $logUrl      = $toggleable && $trackMode !== 'none' ? route('planner.routines.log', $routine) : null;

    /* Details (steps + logging) start collapsed to keep the day view tidy */
    $hasSteps    = $toggleable && ! empty($routine->ringSteps) && count($routine->ringSteps) > 0;
    $hasDetails  = $toggleable && ($hasSteps || $logUrl);

    /* Big or tracked routines are easier to fill in a modal than an accordion. */
    $stepCount   = ! empty($routine->ringSteps) ? count($routine->ringSteps) : 0;
    $useModal    = $hasDetails && ($stepCount > 5 || $trackMode !== 'none');
    $modalSub    = ($toggleable ? ucfirst($routine->frequency) : $routine->recurrenceLabel());
    if ($stepCount > 0) {
        $modalSub .= ' · ' . $stepCount . ' steps';
    }

    /* Day-state derived from steps (build routines only): fully failed and
       partially settled days lock the bulk checkbox — reopening happens
       step by step (unskip / log), never by re-ticking. */
    $stepsDoneN = $toggleable && ! $isAvoid && ! empty($routine->ringSteps)
        ? collect($routine->ringSteps)->where('completed', true)->count() : 0;
    $stepsSkippedN = $toggleable && ! $isAvoid && ! empty($routine->ringSteps)
        ? collect($routine->ringSteps)->where('skipped', true)->count() : 0;
    $stepsOpenN = max(0, $stepCount - $stepsDoneN - $stepsSkippedN);
    $allStepsSkipped = $stepCount > 0 && $stepsSkippedN === $stepCount;
    $failedDay = $isSkipped || $allStepsSkipped;
    $settledPartial = ! $isDone && ! $failedDay && $stepCount > 0 && $stepsOpenN === 0 && $stepsDoneN > 0;
    $checkLocked = $failedDay || $settledPartial;
@endphp
<div class="pl-task pl-routine {{ $isDone ? 'is-done' : '' }} {{ $failedDay ? 'is-skipped' : '' }} {{ $settledPartial ? 'is-settled' : '' }}"
     @if($toggleable)
     data-routine-item
     data-id="{{ $routine->id }}"
     data-date="{{ $routineDate->toDateString() }}"
     data-completed="{{ $isDone ? 1 : 0 }}"
     data-status="{{ $isDone ? 'done' : ($failedDay ? 'skipped' : ($settledPartial ? 'settled' : 'open')) }}"
     data-count="{{ !empty($count) ? 1 : 0 }}"
     @if($useModal)
     data-modal="1"
     data-modal-title="{{ $routine->title }}"
     data-modal-sub="{{ $modalSub }}"
     @endif
     @endif
     style="border-left:3px solid {{ $accent }};">

    @if($toggleable)
        @if($isAvoid)
            {{-- Avoid habits are never checked: shield shows clean vs slipped. --}}
            <span class="pl-avoid-shield {{ $avoidBad ? 'bad' : 'ok' }}"
                  title="{{ $avoidBad ? 'Slip logged today' : 'Clean so far' }}{{ $ringRate !== null ? ' · ' . $ringRate . '% clean (30d)' : '' }}">
                <i class="bi {{ $avoidBad ? 'bi-shield-fill-exclamation' : 'bi-shield-fill-check' }}"></i>
            </span>
        @else
        <label class="pl-habit {{ $checkLocked ? 'is-locked' : '' }}" title="{{ $failedDay ? __('Failed for today — reopen a step to continue') : ($settledPartial ? __('Settled for today') : ($isDone ? 'Mark as not done' : 'Mark as done')) }}{{ $ringRate !== null ? ' · ' . $ringRate . '% adherence (30d)' : '' }}">
            <input type="checkbox"
                   {{ ($isDone || $settledPartial) ? 'checked' : '' }}
                   {{ $checkLocked ? 'disabled' : '' }}
                   data-url="{{ route('planner.routines.toggle', $routine) }}"
                   data-id="{{ $routine->id }}"
                   data-date="{{ $routineDate->toDateString() }}"
                   onchange="toggleRoutine(this)">
            @if($ringRate !== null)
                <span class="habit-ring">
                    <svg viewBox="0 0 36 36" aria-hidden="true">
                        <circle class="ring-bg" cx="18" cy="18" r="15.5"></circle>
                        <circle class="ring-fg"
                                style="stroke-dasharray:{{ number_format($ringC, 1, '.', '') }};stroke-dashoffset:{{ number_format($ringOffset, 1, '.', '') }};"></circle>
                    </svg>
                    <span class="routine-check-box"><i class="bi {{ $failedDay ? 'bi-x-lg' : 'bi-check-lg' }}"></i></span>
                </span>
            @else
                <span class="routine-check-box"><i class="bi {{ $failedDay ? 'bi-x-lg' : 'bi-check-lg' }}"></i></span>
            @endif
        </label>
        @endif
    @else
        <span class="pl-routine-static" title="Scheduled for another day">
            <i class="bi bi-calendar3"></i>
        </span>
    @endif

    <div class="pl-task-body">
        <div class="pl-task-title">
            {{ $routine->title }}
            @if($isAvoid)
                <span class="pl-avoid-tag" title="Forbidden habit — staying clean is the goal">🚫 ترک‌کردنی</span>
            @endif
            @if($isDone && $toggleable && $ringStreak !== null && $ringStreak > 0)
                <span class="flame" title="{{ $ringStreak }} in a row">🔥{{ $ringStreak }}</span>
            @endif
            @if($isAvoid && $toggleable && ($ringStreak ?? 0) > 0)
                <span class="flame" title="{{ $ringStreak }} clean days in a row">🛡️{{ $ringStreak }}</span>
            @endif
            @if($toggleable && ! empty($routine->ringSteps) && $routine->ringSteps->count() > 0)
                @if($isAvoid)
                    @php $slipped = $routine->ringSteps->where('violated', true)->count(); @endphp
                    @if($slipped > 0)
                        <span class="steps-count bad" title="Steps with a slip today">{{ $slipped }}/{{ $routine->ringSteps->count() }} slips</span>
                    @endif
                @else
                    @php
                        $stepsDone = $routine->ringSteps->where('completed', true)->count();
                        $stepsSkipped = $routine->ringSteps->where('skipped', true)->count();
                    @endphp
                    <span class="steps-count {{ $stepsDone === $routine->ringSteps->count() ? 'all' : '' }}">
                        {{ $stepsDone }}/{{ $routine->ringSteps->count() }}
                    </span>
                    @if($stepsSkipped > 0)
                        <span class="steps-skipped" data-skip-count title="{{ $stepsSkipped }} step(s) marked as not done">✗{{ $stepsSkipped }}</span>
                    @endif
                @endif
            @endif
            @if($hasDetails)
                @if($useModal)
                    <button type="button" class="pl-expand pl-expand-modal" onclick="openRoutineModal(this)" title="Open details">
                        <i class="bi bi-arrows-angle-expand"></i>
                    </button>
                @else
                    <button type="button" class="pl-expand" onclick="toggleRoutineDetails(this)" title="Show details">
                        <i class="bi bi-chevron-down"></i>
                    </button>
                @endif
            @endif
            @if($toggleable && !$isAvoid)
                {{-- ✗ records "did not do it": closes the day without a tick. --}}
                <button type="button"
                        class="pl-routine-skip {{ $failedDay ? 'active' : '' }}"
                        data-skip-url="{{ route('planner.routines.skip', $routine) }}"
                        data-id="{{ $routine->id }}"
                        data-date="{{ $routineDate->toDateString() }}"
                        title="{{ $failedDay ? __('Undo skip') : __('Mark as not done') }}"
                        aria-label="{{ __('Mark as not done') }}"
                        aria-pressed="{{ $failedDay ? 'true' : 'false' }}"
                        onclick="skipRoutine(this)">
                    <i class="bi bi-x-lg"></i>
                </button>
            @endif
            @if($settledPartial)
                <span class="pl-settled-tag" data-settled-tag title="{{ __('Every step is resolved') }}">
                    <i class="bi bi-check-all"></i> {{ __('Settled') }}
                </span>
            @endif
        </div>
        <div class="pl-task-meta">
            @if($failedDay)
                <span class="pl-skip-tag">
                    <i class="bi bi-x-circle-fill"></i> {{ __('Not done') }}
                    @if($isSkipped && !empty($record->skip_reason) && isset(\App\Models\RoutineCompletion::SKIP_REASONS[$record->skip_reason]))· {{ __(\App\Models\RoutineCompletion::SKIP_REASONS[$record->skip_reason]) }}@endif
                </span>
            @endif
            @if($isAvoid)
                @if($avoidBad)
                    <span class="pl-priority" style="color:#b91c1c;background:#fee2e2;text-transform:none;">
                        <i class="bi bi-exclamation-triangle"></i> لغزش ثبت شد{{ $countMode ? ' · ×'.$avoidQty : '' }}
                    </span>
                @else
                    <span class="pl-priority" style="color:#15803d;background:#dcfce7;text-transform:none;">
                        <i class="bi bi-shield-check"></i> پاک تا الان
                    </span>
                @endif
            @endif
            @if($active)
                <span class="pl-priority" style="color:{{ $accent }};background:{{ $accent }}1a;text-transform:none;">
                    <i class="bi {{ $active['period_icon'] ?: 'bi-clock' }}"></i> {{ $active['period_label'] ?: $active['time_label'] }}
                </span>
            @elseif($routine->time_period)
                <span class="pl-priority" style="color:{{ $accent }};background:{{ $accent }}1a;text-transform:none;">
                    <i class="bi {{ $routine->periodIcon() }}"></i> {{ $routine->periodLabel() }}
                </span>
            @else
                <span class="pl-priority" style="color:{{ $fc }};background:{{ $fc }}1a;text-transform:none;">
                    <i class="bi {{ $fi }}"></i> {{ $toggleable ? ucfirst($routine->frequency) : $routine->recurrenceLabel() }}
                </span>
                @if($routine->timeLabel())
                    <span class="pl-due"><i class="bi bi-clock"></i> {{ $routine->timeLabel() }}</span>
                @endif
            @endif
            @if($isDone && $doneAt)
                <span class="pl-due" style="color:#16a34a;"><i class="bi bi-check2"></i> {{ $doneAt->format('g:i A') }}</span>
            @endif
            @if($ringLast7 !== null && $toggleable)
                <span class="last7" title="Last 7 days">
                    @foreach($ringLast7 as $sq)
                        <i class="sq sq-{{ $sq['state'] }}"></i>
                    @endforeach
                </span>
            @endif
        </div>

        @if($hasDetails)
        <div class="pl-details" data-details>
        @endif
        @if($toggleable && ! empty($routine->ringSteps) && count($routine->ringSteps) > 0)
            @if($isAvoid)
                {{-- Avoid steps: no check — a violated step turns red, the rest stay open. --}}
                <div class="pl-steps pl-avoid-steps">
                    @foreach($routine->ringSteps as $step)
                        <div class="pl-step avoid {{ !empty($step['violated']) ? 'violated' : '' }}"
                             data-step-item data-id="{{ $step['id'] }}"
                             data-routine="{{ $routine->id }}"
                             data-date="{{ $routineDate->toDateString() }}">
                            <i class="bi {{ !empty($step['violated']) ? 'bi-x-circle-fill' : 'bi-shield' }}"></i>
                            {{ $step['name'] }}
                            @if(!empty($step['period_label']) || !empty($step['time_label']))
                                <span class="pl-step-schedule" style="color:{{ $step['period_color'] ?: '#64748b' }};">
                                    <i class="bi {{ $step['period_icon'] ?: 'bi-clock' }}"></i>
                                    {{ $step['period_label'] ?: $step['time_label'] }}
                                </span>
                            @endif
                            @if(!empty($step['violated']))
                                <span class="pl-step-slipcount" title="Slips in this slot today">×{{ $step['violation_qty'] }}</span>
                            @endif
                            <button type="button" class="pl-step-slipbtn" data-avoid-step-slip
                                    data-slip-url="{{ route('planner.check-items.slip', $step['id']) }}"
                                    data-date="{{ $routineDate->toDateString() }}"
                                    data-count-mode="{{ $countMode ? '1' : '0' }}"
                                    title="Log a slip in this slot">لغزش</button>
                        </div>
                        <div class="pl-avoid-panel" data-step-slip-panel hidden>
                            <form data-step-slip-form
                                  data-slip-url="{{ route('planner.check-items.slip', $step['id']) }}"
                                  data-date="{{ $routineDate->toDateString() }}"
                                  data-routine-id="{{ $routine->id }}"
                                  data-suggest-url="{{ route('planner.routines.suggestions', $routine) }}"
                                  onsubmit="return submitStepSlip(this)">
                                <div class="pl-avoid-grid">
                                    @if($countMode)
                                        <input type="number" name="quantity" min="1" value="1" title="{{ __('Quantity') }}" aria-label="{{ __('Quantity') }}" class="pl-avoid-qty">
                                    @endif
                                    <x-jalali-date name="occurred_at" type="datetime" :value="$nowLocal" title="{{ __('Exact time') }}" aria-label="{{ __('Exact time') }}" />
                                    <button type="button" class="pl-avoid-now" data-avoid-now title="{{ __('Now') }}">{{ __('Now') }}</button>
                                </div>
                                <div class="pl-avoid-grid">
                                    <input type="text" name="trigger" maxlength="100" list="{{ 'trig-list-r' . $routine->id }}" placeholder="{{ __('Trigger (optional)') }}" aria-label="{{ __('Trigger') }}" autocomplete="off">
                                    <input type="text" name="location" maxlength="100" list="{{ 'loc-list-r' . $routine->id }}" placeholder="{{ __('Location (optional)') }}" aria-label="{{ __('Location') }}" autocomplete="off">
                                </div>
                                <div class="pl-avoid-grid">
                                    <select name="mood" aria-label="{{ __('Mood') }}" title="{{ __('Craving intensity 1-10') }}">
                                        <option value="">{{ __('Mood (1-10)') }}</option>
                                        @for($m = 1; $m <= 10; $m++)
                                            <option value="{{ $m }}">{{ $m }}</option>
                                        @endfor
                                    </select>
                                    <input type="text" name="note" maxlength="2000" placeholder="{{ __('Note (optional)') }}" aria-label="{{ __('Note') }}">
                                </div>
                                <button type="submit" class="pl-avoid-submit">ثبت</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @else
            <div class="pl-steps">
                @foreach($routine->ringSteps as $step)
                    @php $stepSkipped = !empty($step['skipped']); @endphp
                    <span class="pl-step-wrap {{ $step['completed'] ? 'done' : '' }} {{ $stepSkipped ? 'is-skipped' : '' }}"
                          data-step-wrap data-id="{{ $step['id'] }}"
                          data-routine="{{ $routine->id }}"
                          data-date="{{ $routineDate->toDateString() }}">
                    <button type="button"
                            class="pl-step {{ $step['completed'] ? 'done' : '' }} {{ $stepSkipped ? 'is-skipped' : '' }}"
                            data-step-item data-id="{{ $step['id'] }}"
                            data-routine="{{ $routine->id }}"
                            data-date="{{ $routineDate->toDateString() }}"
                            data-url="{{ route('planner.check-items.toggle', $step['id']) }}"
                            data-tracked="{{ $trackMode }}"
                            data-logged="{{ !empty($step['sets']) ? 1 : 0 }}"
                            onclick="toggleCheckItem(this)">
                        <i class="bi {{ $stepSkipped ? 'bi-x-circle' : ($step['completed'] ? 'bi-check-circle-fill' : 'bi-circle') }}"></i>
                        {{ $step['name'] }}
                        @if(!empty($step['period_label']) || !empty($step['time_label']))
                            <span class="pl-step-schedule" style="color:{{ $step['period_color'] ?: '#64748b' }};">
                                <i class="bi {{ $step['period_icon'] ?: 'bi-clock' }}"></i>
                                {{ $step['period_label'] ?: $step['time_label'] }}
                            </span>
                        @endif
                    </button>
                    <button type="button"
                            class="pl-step-skip {{ $stepSkipped ? 'active' : '' }}"
                            data-step-skip data-id="{{ $step['id'] }}"
                            data-date="{{ $routineDate->toDateString() }}"
                            data-skip-url="{{ route('planner.check-items.skip', $step['id']) }}"
                            title="{{ $stepSkipped ? __('Undo skip') : __('Mark step as not done') }}"
                            aria-label="{{ __('Mark step as not done') }}"
                            aria-pressed="{{ $stepSkipped ? 'true' : 'false' }}"
                            onclick="event.stopPropagation(); skipStep(this)">
                        <i class="bi bi-x-lg"></i>
                    </button>
                    </span>
                @endforeach
            </div>
            @php
                $openSteps = $routine->ringSteps->where('completed', false)->where('skipped', false)->count();
                $skippedSteps = $routine->ringSteps->where('skipped', true)->count();
            @endphp
            @if($openSteps > 0 && $skippedSteps > 0)
                <button type="button" class="pl-skip-rest" data-skip-rest
                        data-routine="{{ $routine->id }}"
                        data-date="{{ $routineDate->toDateString() }}"
                        title="{{ __('Skip the remaining open steps') }}">
                    {{ __('Skip remaining') }} (<span data-skip-rest-n>{{ $openSteps }}</span>)
                </button>
            @endif
            @endif
        @endif

        @if($isAvoid && $toggleable)
            @php
                $trigListId = 'trig-list-r' . $routine->id;
                $locListId = 'loc-list-r' . $routine->id;
            @endphp
            <div class="pl-avoid-actions">
                <button type="button" class="pl-avoid-btn slip" data-avoid-slip>ثبت لغزش</button>
                <button type="button" class="pl-avoid-btn note" data-avoid-note>وسوسه / یادداشت</button>
            </div>
            <div class="pl-avoid-panel" data-slip-panel hidden>
                <form data-slip-form
                      data-slip-url="{{ route('planner.routines.slip', $routine) }}"
                      data-date="{{ $routineDate->toDateString() }}"
                      data-routine-id="{{ $routine->id }}"
                      data-suggest-url="{{ route('planner.routines.suggestions', $routine) }}"
                      onsubmit="return submitRoutineSlip(this)">
                    <div class="pl-avoid-grid">
                        @if($countMode)
                            <input type="number" name="quantity" min="1" value="1" title="{{ __('Quantity') }}" aria-label="{{ __('Quantity') }}" class="pl-avoid-qty">
                        @endif
                        <x-jalali-date name="occurred_at" type="datetime" :value="$nowLocal" title="{{ __('Exact time') }}" aria-label="{{ __('Exact time') }}" />
                        <button type="button" class="pl-avoid-now" data-avoid-now title="{{ __('Now') }}">{{ __('Now') }}</button>
                    </div>
                    <div class="pl-avoid-grid">
                        <input type="text" name="trigger" maxlength="100" list="{{ $trigListId }}" placeholder="{{ __('Trigger (optional)') }}" aria-label="{{ __('Trigger') }}" autocomplete="off">
                        <input type="text" name="location" maxlength="100" list="{{ $locListId }}" placeholder="{{ __('Location (optional)') }}" aria-label="{{ __('Location') }}" autocomplete="off">
                    </div>
                    <div class="pl-avoid-grid">
                        <select name="mood" aria-label="{{ __('Mood') }}" title="{{ __('Craving intensity 1-10') }}">
                            <option value="">{{ __('Mood (1-10)') }}</option>
                            @for($m = 1; $m <= 10; $m++)
                                <option value="{{ $m }}">{{ $m }}</option>
                            @endfor
                        </select>
                        <input type="text" name="note" maxlength="2000" placeholder="{{ __('Note (optional)') }}" aria-label="{{ __('Note') }}">
                    </div>
                    <datalist id="{{ $trigListId }}"></datalist>
                    <datalist id="{{ $locListId }}"></datalist>
                    <button type="submit" class="pl-avoid-submit">{{ __('Log Slip') }}</button>
                </form>
            </div>
            <div class="pl-avoid-panel" data-note-panel hidden>
                <form data-note-form
                      data-note-url="{{ route('planner.routines.note', $routine) }}"
                      data-date="{{ $routineDate->toDateString() }}"
                      data-routine-id="{{ $routine->id }}"
                      data-suggest-url="{{ route('planner.routines.suggestions', $routine) }}"
                      onsubmit="return submitRoutineNote(this)">
                    <div class="pl-avoid-grid">
                        <select name="kind" aria-label="{{ __('Kind') }}">
                            <option value="craving">{{ __('Craving') }}</option>
                            <option value="note">{{ __('Note') }}</option>
                        </select>
                        <x-jalali-date name="occurred_at" type="datetime" :value="$nowLocal" title="{{ __('Exact time') }}" aria-label="{{ __('Exact time') }}" />
                        <button type="button" class="pl-avoid-now" data-avoid-now title="{{ __('Now') }}">{{ __('Now') }}</button>
                    </div>
                    <div class="pl-avoid-grid">
                        <input type="text" name="trigger" maxlength="100" list="{{ $trigListId }}" placeholder="{{ __('Trigger (optional)') }}" aria-label="{{ __('Trigger') }}" autocomplete="off">
                        <input type="text" name="location" maxlength="100" list="{{ $locListId }}" placeholder="{{ __('Location (optional)') }}" aria-label="{{ __('Location') }}" autocomplete="off">
                    </div>
                    <div class="pl-avoid-grid">
                        <select name="mood" aria-label="{{ __('Mood') }}" title="{{ __('Craving intensity 1-10') }}">
                            <option value="">{{ __('Mood (1-10)') }}</option>
                            @for($m = 1; $m <= 10; $m++)
                                <option value="{{ $m }}">{{ $m }}</option>
                            @endfor
                        </select>
                        <input type="text" name="note" maxlength="2000" placeholder="{{ __('Details…') }}" aria-label="{{ __('Details') }}">
                    </div>
                    <button type="submit" class="pl-avoid-submit">{{ __('Log') }}</button>
                </form>
            </div>
        @endif

        @if($logUrl && $trackMode === 'value')
            @php $isTimeLog = $routine->isTimeValue(); $logVal = $logValues['value'] ?? null; @endphp
            <div class="pl-log {{ $isTimeLog ? 'is-time' : '' }}" data-log-value data-kind="{{ $isTimeLog ? 'time' : 'number' }}" data-routine="{{ $routine->id }}" data-date="{{ $routineDate->toDateString() }}" data-url="{{ $logUrl }}">
                @if($isTimeLog)
                    <x-time-picker :id="'tp-row-' . $routine->id . '-' . $routineDate->toDateString()"
                                   :value="\App\Models\Routine::minutesToTimeValue($logVal ?? $routine->scheduledReferenceMinutes())"
                                   :compact="true" />
                @else
                    <input type="number" step="any" min="0" placeholder="{{ __($routine->value_label ?: $routine->trackingLabel()) }}"
                           value="{{ $logVal }}" aria-label="{{ __($routine->value_label ?: $routine->trackingLabel()) }}">
                @endif
                <button type="button" onclick="logRoutineValue(this)"><i class="bi bi-check2"></i> {{ __('Log') }}</button>
                @if($logVal !== null && $logVal !== '')<span class="pl-log-saved">✓ {{ $isTimeLog ? \App\Models\Routine::minutesToTimeValue($logVal) : $logVal }}</span>@endif
            </div>
        @endif

        @if($logUrl && $trackMode === 'sets' && ! empty($routine->ringSteps) && count($routine->ringSteps) > 0)
            <div class="pl-logsets">
                @foreach($routine->ringSteps as $step)
                    @php $logged = $step['sets'] ?? []; $target = max(1, (int) ($step['target_sets'] ?? 1)); @endphp
                    <div class="pl-logset" data-log-sets data-item="{{ $step['id'] }}" data-routine="{{ $routine->id }}" data-date="{{ $routineDate->toDateString() }}" data-url="{{ $logUrl }}">
                        <span class="pl-logset-name">{{ __($step['name']) }}{{ $step['unit'] ? ' (' . $step['unit'] . ')' : '' }}
                            @if(!empty($step['period_label']) || !empty($step['time_label']))
                                <span class="pl-step-schedule" style="color:{{ $step['period_color'] ?: '#64748b' }};">
                                    <i class="bi {{ $step['period_icon'] ?: 'bi-clock' }}"></i>
                                    {{ $step['period_label'] ? __($step['period_label']) : $step['time_label'] }}
                                </span>
                            @endif
                        </span>
                        @for($s = 1; $s <= $target; $s++)
                            <input type="number" step="any" min="0" data-set="{{ $s }}" placeholder="S{{ $s }}"
                                   value="{{ $logged[$s] ?? '' }}" aria-label="{{ __($step['name']) }} {{ __('Set') }} {{ $s }}">
                        @endfor
                        <button type="button" onclick="logRoutineSets(this)">{{ __('Log') }}</button>
                    </div>
                @endforeach
            </div>
        @endif
        @if($hasDetails)
        </div>
        @endif
    </div>
</div>

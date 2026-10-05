{{-- One week-view day column. Vars: $day, $isFa, $isDayToday --}}
@php
    $dayDate = $day['date'];
    $dayISO = $dayDate->toDateString();
    $hasContent = count($day['groups']) > 0 || count($day['routines']) > 0;
    if ($isFa) {
        $jal = \Morilog\Jalali\Jalalian::fromCarbon($dayDate);
        $wdName = $jal->format('l');
        $dayNum = app_num($jal->format('j'));
        $monthName = $jal->format('F');
    } else {
        $wdName = $dayDate->format('l');
        $dayNum = $dayDate->format('j');
        $monthName = $dayDate->format('M');
    }
@endphp
<section class="pw-day {{ $isDayToday ? 'is-today' : '' }}" data-week-day="{{ $dayISO }}">
    <header class="pw-day-head">
        <div class="pw-day-id">
            <span class="pw-day-num">{{ $dayNum }}</span>
            <span class="pw-day-txt">
                <strong>{{ $wdName }}</strong>
                <small>{{ $monthName }}</small>
            </span>
        </div>
        <div class="pw-day-side">
            @if($isDayToday)
                <span class="pw-today-pill">{{ __('Today') }}</span>
            @endif
            <span class="pw-day-count" data-day-open title="{{ __('Open tasks') }}">{{ app_num($day['openCount']) }}</span>
        </div>
    </header>

    @if(count($day['routines']))
        <div class="pw-routines">
            <div class="pw-routines-head">
                <i class="bi bi-arrow-repeat"></i>
                <span>{{ __('Routines') }}</span>
                <span class="pw-routines-count pf-num">{{ app_num($day['routineDone']) }}/{{ app_num($day['routineTotal']) }}</span>
            </div>
            <div class="pw-routine-list">
                @foreach($day['routines'] as $routine)
                    @include('planner._routine-chip', ['routine' => $routine, 'dayDate' => $dayDate])
                @endforeach
            </div>
        </div>
    @endif

    <div class="pw-groups" data-day-groups>
        @foreach($day['groups'] as $g)
            <div class="pw-group" data-period-group="{{ $g['key'] }}">
                <div class="pw-group-head">
                    <i class="bi {{ $g['icon'] }}" style="color:{{ $g['color'] }};"></i>
                    <span class="pw-group-name">{{ __($g['label']) }}</span>
                    <span class="pw-group-count">{{ app_num(count($g['tasks'])) }}</span>
                    <button type="button" class="pw-group-add" data-week-add
                            data-date="{{ $dayISO }}" data-period="{{ $g['key'] }}"
                            title="{{ __('Add to :section', ['section' => __($g['label'])]) }}">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </div>
                <div class="pw-group-body" data-period-body="{{ $g['key'] }}">
                    @foreach($g['tasks'] as $task)
                        @include('planner._week-task', ['task' => $task, 'currentDate' => $dayISO])
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div class="pw-day-empty" data-day-empty @if($hasContent) hidden @endif>
        <i class="bi bi-cup-hot"></i>
        <span>{{ __('Free day — add something?') }}</span>
    </div>

    <footer class="pw-day-foot">
        <button type="button" class="pw-add" data-week-add data-date="{{ $dayISO }}" data-period="">
            <i class="bi bi-plus-lg"></i> {{ __('Add task') }}
        </button>
    </footer>

    {{-- Add popover: new task + pick from existing inbox --}}
    <div class="pw-pop" data-week-pop hidden>
        <div class="pw-pop-sec">
            <div class="pw-pop-chips" data-week-periods>
                <button type="button" data-wp-period="" class="active"><i class="bi bi-inbox"></i> {{ __('Anytime') }}</button>
                @foreach(config('routines.periods', []) as $key => $p)
                    <button type="button" data-wp-period="{{ $key }}">
                        <i class="bi {{ $p['icon'] }}" style="color:{{ $p['color'] }};"></i> {{ __($p['label']) }}
                    </button>
                @endforeach
            </div>
            <div class="pw-pop-new">
                <input type="text" data-week-title maxlength="255" autocomplete="off"
                       placeholder="{{ __('New task for :day…', ['day' => $wdName]) }}" aria-label="{{ __('New task title') }}">
                <button type="button" data-week-create>{{ __('Add') }}</button>
            </div>
        </div>
        <div class="pw-pop-div"><span>{{ __('or pick existing') }}</span></div>
        <div class="pw-pop-sec">
            <input type="text" data-week-search autocomplete="off"
                   placeholder="{{ __('Search inbox…') }}" aria-label="{{ __('Search unscheduled tasks') }}">
            <div class="pw-pop-list" data-week-backlog></div>
        </div>
    </div>
</section>

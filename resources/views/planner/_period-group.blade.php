{{-- Period group inside "Today's Tasks". Vars: $periodKey, $period (label/icon/color), $rows --}}
<div class="pl-period-group" data-period-group="{{ $periodKey }}">
    <div class="pl-period-head">
        <i class="bi {{ $period['icon'] ?? 'bi-inbox' }}" style="color:{{ $period['color'] ?? '#64748b' }};"></i>
        <span>{{ $period['label'] ?? 'Anytime' }}</span>
    </div>
    <div class="pl-period-body" data-period-body="{{ $periodKey }}">
        @foreach($rows as $task)
            @include('planner._task-row', [
                'task' => $task,
                'count' => true,
                'postpone' => 'tomorrow',
                'hideDue' => true,
                'draggable' => true,
            ])
        @endforeach
    </div>
</div>

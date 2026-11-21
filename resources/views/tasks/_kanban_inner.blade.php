@php
    $columns = [
        'to_do'      => ['label' => __('To Do'),        'dot' => '#94a3b8',            'empty' => __('No tasks here'),     'icon' => 'bi-circle',           'collapsed' => false],
        'in_progress'=> ['label' => __('In Progress'),   'dot' => '#7c3aed',            'empty' => __('Nothing active'),    'icon' => 'bi-arrow-clockwise',  'collapsed' => false],
        'on_hold'    => ['label' => __('On Hold'),       'dot' => '#ad6800',            'empty' => __('None on hold'),      'icon' => 'bi-pause-circle',     'collapsed' => true],
        'in_review'  => ['label' => __('In Review'),     'dot' => '#0b6bcb',            'empty' => __('Nothing to review'), 'icon' => 'bi-eye',              'collapsed' => true],
        'completed'  => ['label' => __('Completed'),     'dot' => '#29774b',            'empty' => __('Nothing done yet'),  'icon' => 'bi-check-circle',     'collapsed' => true],
    ];
    $todoCnt     = count($tasks['to_do'] ?? []);
    $progressCnt = count($tasks['in_progress'] ?? []);
    $onHoldCnt   = count($tasks['on_hold'] ?? []);
    $inReviewCnt = count($tasks['in_review'] ?? []);
    $completedCnt= count($tasks['completed'] ?? []);
    $colCounts = [
        'to_do' => $todoCnt, 'in_progress' => $progressCnt, 'on_hold' => $onHoldCnt,
        'in_review' => $inReviewCnt, 'completed' => $completedCnt,
    ];
@endphp

@foreach($columns as $statusKey => $col)
    <div class="cu-col {{ $col['collapsed'] ? 'collapsed' : '' }}" data-collapsed="{{ $col['collapsed'] ? '1' : '0' }}">
        <div class="cu-col-head" data-col-toggle="{{ $statusKey }}">
            <span class="cu-col-dot" style="background:{{ $col['dot'] }};"></span>
            <span class="cu-col-head-left">
                <span>{{ $col['label'] }}</span>
                <span class="cu-col-count" id="cnt-{{ $statusKey }}">{{ $colCounts[$statusKey] }}</span>
            </span>
            <div class="cu-col-actions">
                <button class="cu-col-add" data-bs-toggle="modal" data-bs-target="#createTaskModal" data-status="{{ $statusKey }}" title="{{ __('New task in this column') }}">
                    <i class="bi bi-plus-lg"></i>
                </button>
                <button type="button" class="cu-col-chevron-btn" data-chevron="{{ $statusKey }}" title="{{ __('Collapse / expand') }}">
                    <i class="bi bi-chevron-down"></i>
                </button>
            </div>
        </div>
        <div class="cu-col-body" id="col-{{ $statusKey }}" data-status="{{ $statusKey }}">
            @foreach($tasks[$statusKey] ?? [] as $task)
                @include('tasks._card', ['task' => $task])
            @endforeach
            <div class="cu-col-empty" @if(!empty($tasks[$statusKey])) style="display:none;" @endif>
                <i class="bi {{ $col['icon'] }}" style="font-size:20px;display:block;margin-bottom:6px;"></i>{{ $col['empty'] }}
            </div>
            <form class="cu-quickadd" data-quickadd="{{ $statusKey }}">
                <i class="bi bi-plus-lg"></i>
                <input type="text" placeholder="{{ __('Add task…') }}" data-status="{{ $statusKey }}">
            </form>
        </div>
    </div>
@endforeach
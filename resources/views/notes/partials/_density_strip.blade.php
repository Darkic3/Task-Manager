{{-- GitHub-style daily note density for the last N days. --}}
@php
    $counts = $dailyCounts ?? [];
    $days = 119;
    $cells = [];
    $max = $counts ? max(1, max($counts)) : 1;

    for ($i = $days; $i >= 0; $i--) {
        $d = now()->subDays($i);
        $key = $d->toDateString();
        $c = (int) ($counts[$key] ?? 0);
        $level = $c === 0 ? 0 : ($c <= max(1, (int) ceil($max / 4)) ? 1 : ($c <= (int) ceil($max / 2) ? 2 : ($c <= max(1, (int) ceil($max * .8)) ? 3 : 4)));
        $cells[] = ['date' => $key, 'count' => $c, 'level' => $level, 'dow' => $d->dayOfWeek];
    }
@endphp

<div class="nt-density">
    <div class="nt-density-head">
        <span><i class="bi bi-calendar3"></i> {{ __('Activity') }}</span>
        <span>{{ app_num(array_sum($counts)) }} {{ __('notes / 120 days') }}</span>
    </div>

    <div class="nt-density-grid">
        @php $columns = array_chunk($cells, 7); @endphp
        @foreach ($columns as $week)
            <div class="nt-density-col">
                @foreach ($week as $cell)
                    @php
                        $on = ! empty($filters['from']) && substr($filters['from'], 0, 10) === $cell['date'];
                    @endphp
                    <a class="nt-density-cell lv{{ $cell['level'] }}"
                       href="{{ request()->fullUrlWithQuery(array_filter(['from' => $cell['date'], 'to' => $cell['date']], fn ($v) => $v)) }}"
                       title="{{ app_date($cell['date']) }} — {{ app_num($cell['count']) }} {{ __('notes') }}"
                       style="{{ $on ? 'outline-color:var(--primary-600)' : '' }}"></a>
                @endforeach
            </div>
        @endforeach
    </div>
</div>
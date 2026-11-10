@extends('layouts.app')

@section('title', __('Journal insights'))

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">{{ __('Insights') }}</h1>
        <a href="{{ route('journal.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('Back') }}</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card text-center"><div class="card-body">
                <div class="h4 mb-0">{{ $totalEntries }}</div>
                <div class="text-muted small">{{ __('Entries') }}</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card text-center"><div class="card-body">
                <div class="h4 mb-0">{{ $totalWords }}</div>
                <div class="text-muted small">{{ __('Words') }}</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card text-center"><div class="card-body">
                <div class="h4 mb-0">{{ $currentStreak }}</div>
                <div class="text-muted small">{{ __('Current streak (days)') }}</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card text-center"><div class="card-body">
                <div class="h4 mb-0">{{ $longestStreak }}</div>
                <div class="text-muted small">{{ __('Longest streak (days)') }}</div>
            </div></div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">{{ __('Mood distribution (last 30 days)') }}</div>
        <div class="card-body">
            @php $maxMood = max($moodCounts) ?: 1; @endphp
            <div class="d-flex align-items-end" style="height:120px;gap:4px;">
                @for ($i = 1; $i <= 10; $i++)
                    @php $h = round(($moodCounts[$i] / $maxMood) * 100); @endphp
                    <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;">
                        <div style="width:100%;height:{{ $h }}%;background:#7c3aed;border-radius:4px 4px 0 0;min-height:2px;"></div>
                        <div class="small text-muted mt-1">{{ $i }}</div>
                    </div>
                @endfor
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">{{ __('Top tags') }}</div>
        <div class="card-body">
            @if (empty($tagCorrelation))
                <p class="text-muted mb-0">{{ __('No tags yet.') }}</p>
            @else
                <table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('Tag') }}</th><th>{{ __('Count') }}</th><th>{{ __('Avg mood') }}</th></tr></thead>
                    <tbody>
                        @foreach ($tagCorrelation as $name => $info)
                            <tr>
                                <td>{{ $name }}</td>
                                <td>{{ $info['count'] }}</td>
                                <td>{{ $info['avg_mood'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">{{ __('On this day (previous years)') }}</div>
        <div class="card-body">
            @if ($onThisDay->isEmpty())
                <p class="text-muted mb-0">{{ __('Nothing written on this date in previous years.') }}</p>
            @else
                <ul class="list-group list-group-flush">
                    @foreach ($onThisDay as $e)
                        <li class="list-group-item">
                            <a href="{{ route('journal.show', $e) }}">{{ $e->title ?: __('Untitled entry') }}</a>
                            <span class="text-muted small ms-2">{{ $e->entry_date?->format('Y') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header">{{ __('Export') }}</div>
        <div class="card-body">
            <form method="GET" action="{{ route('journal.export') }}" class="row g-2">
                <div class="col-md-3">
                    <input type="date" name="from" class="form-control" placeholder="{{ __('From') }}">
                </div>
                <div class="col-md-3">
                    <input type="date" name="to" class="form-control" placeholder="{{ __('To') }}">
                </div>
                <div class="col-md-3">
                    <select name="format" class="form-select">
                        <option value="md">Markdown</option>
                        <option value="json">JSON</option>
                        <option value="csv">CSV</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary w-100">{{ __('Download') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

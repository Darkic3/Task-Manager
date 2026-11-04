@extends('layouts.app')

@section('title', __('Import CSV'))

@push('styles')
<style>
.vb-wrap { max-width:860px; margin:0 auto; }
.vb-topbar { display:flex; align-items:center; gap:10px; margin-bottom:12px; flex-wrap:wrap; }
.vb-title { font-size:19px; font-weight:700; color:#1f2328; margin:0; flex:1; }
.vb-btn { display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border-radius:6px; border:1px solid #e5e7eb; background:white; color:#6b6f78; font-size:12px; font-weight:600; text-decoration:none; cursor:pointer; }
.vb-btn:hover { border-color:#6366f1; color:#6366f1; text-decoration:none; }
.vb-btn.primary { background:#6366f1; border-color:#6366f1; color:white; }
.vb-form { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:1rem; margin-bottom:1rem; }
.vb-hint { font-size:.78rem; color:#6b7280; line-height:1.7; }
.vb-hint code { background:#f3f4f6; border-radius:4px; padding:0 5px; font-size:.75rem; }
.vb-table { width:100%; border-collapse:collapse; font-size:.82rem; }
.vb-table th, .vb-table td { border-bottom:1px solid #eef0f3; padding:.45rem .6rem; text-align:start; }
.vb-table th { color:#6b7280; font-size:.72rem; text-transform:uppercase; letter-spacing:.03em; }
.vb-err { color:#b91c1c; font-size:.78rem; }
</style>
@endpush

@section('content')
<div class="vb-wrap">
    <div class="vb-topbar">
        <h1 class="vb-title">{{ __('Import CSV') }}</h1>
        <a href="{{ route('vocabulary.imports.template') }}" class="vb-btn"><i class="bi bi-download"></i>{{ __('Download template') }}</a>
        <a href="{{ route('vocabulary.cards.index') }}" class="vb-btn"><i class="bi bi-arrow-left"></i>{{ __('Cards') }}</a>
    </div>

    <div class="vb-form">
        <form method="POST" action="{{ route('vocabulary.imports.preview') }}" enctype="multipart/form-data" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:end;">
            @csrf
            <div style="flex:1;min-width:160px;">
                <label class="form-label">{{ __('Deck') }}</label>
                <select name="deck_id" class="form-control" required>
                    @foreach($decks as $d)
                    <option value="{{ $d->id }}" {{ (int) old('deck_id', $preview['deck_id'] ?? 0) === $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="flex:2;min-width:200px;">
                <label class="form-label">{{ __('CSV file') }}</label>
                <input type="file" name="file" class="form-control" accept=".csv,.txt" required>
            </div>
            <button class="vb-btn primary" type="submit"><i class="bi bi-eye"></i>{{ __('Preview') }}</button>
        </form>
        <p class="vb-hint" style="margin:.7rem 0 0;">{{ __('Columns: word, meaning, example (optional), note (optional). Header row, commas or semicolons — all auto-detected. Max 1000 rows per file.') }}</p>
        <p class="vb-hint" style="margin:.2rem 0 0;">{{ __('Bulk import skips the daily new-card limit — every imported card starts in Box 1, due today.') }}</p>
    </div>

    @if($preview)
    <div class="vb-form">
        <h6 style="font-weight:700;font-size:.85rem;margin:0 0 .6rem;">{{ __(':valid valid rows', ['valid' => app_num(count($preview['rows']))]) }}@if(!empty($preview['errors'])) <span class="vb-err">· {{ __(':invalid invalid', ['invalid' => app_num(count($preview['errors']))]) }}</span>@endif @if(!empty($preview['truncated'])) <span class="vb-err">· {{ __('truncated to 1000 rows') }}</span>@endif</h6>
        <div style="overflow-x:auto;">
            <table class="vb-table">
                <thead><tr><th>#</th><th>{{ __('Word') }}</th><th>{{ __('Meaning') }}</th><th>{{ __('Ex') }}</th></tr></thead>
                <tbody>
                    @foreach(array_slice($preview['rows'], 0, 50) as $i => $row)
                    <tr><td>{{ app_num($i + 1) }}</td><td style="font-weight:600;">{{ $row['front'] }}</td><td>{{ $row['back'] }}</td><td style="color:#6b7280;">{{ \Illuminate\Support\Str::limit($row['example'] ?? '', 60) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if(count($preview['rows']) > 50)
        <p class="vb-hint">… {{ __(':more more rows not shown', ['more' => app_num(count($preview['rows']) - 50)]) }}</p>
        @endif
        @if(!empty($preview['errors']))
        <div style="margin-top:.6rem;">
            @foreach(array_slice($preview['errors'], 0, 10) as $e)
            <div class="vb-err">{{ __('Line :line', ['line' => app_num($e['line'])]) }}: {{ __($e['message']) }}</div>
            @endforeach
        </div>
        @endif
        <form method="POST" action="{{ route('vocabulary.imports.confirm') }}" style="margin-top:.8rem;">
            @csrf
            <button class="vb-btn primary" type="submit"><i class="bi bi-check-lg"></i>{{ __('Confirm import') }}</button>
        </form>
    </div>
    @endif
</div>
@endsection

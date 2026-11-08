@extends('layouts.app')

@section('title', __('Import CSV'))

@push('styles')
<style>
.lv-page { max-width:860px; margin:0 auto; }
.lv-hero {
    position:relative; overflow:hidden;
    background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 55%,#9333ea 100%);
    border-radius:18px; padding:22px 24px; margin-bottom:16px; color:#fff;
    box-shadow:0 18px 36px -18px rgba(79,70,229,.55);
}
.lv-hero::before {
    content:''; position:absolute; width:180px; height:180px; top:-75px;
    inset-inline-end:-50px; border-radius:50%; background:rgba(255,255,255,.10);
}
.lv-hero-in { position:relative; z-index:1; display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
.lv-hero-ico {
    width:48px; height:48px; flex:none; border-radius:14px;
    display:grid; place-items:center; font-size:1.4rem;
    background:rgba(255,255,255,.18); border:1px solid rgba(255,255,255,.35);
}
.lv-hero-txt { flex:1; min-width:180px; }
.lv-hero-txt h1 { margin:0; font-size:1.25rem; font-weight:800; }
.lv-hero-txt p { margin:2px 0 0; font-size:.78rem; color:rgba(255,255,255,.82); }
.lv-hero-actions { display:flex; gap:8px; flex-wrap:wrap; }
.lv-btn {
    display:inline-flex; align-items:center; gap:6px;
    padding:8px 14px; border-radius:10px; font-size:.8rem; font-weight:700;
    text-decoration:none; cursor:pointer; border:1px solid transparent;
    transition:transform .15s, background .15s; white-space:nowrap;
}
.lv-btn:active { transform:scale(.97); }
.lv-btn-glass { background:rgba(255,255,255,.16); color:#fff; border-color:rgba(255,255,255,.35); }
.lv-btn-glass:hover { background:rgba(255,255,255,.26); color:#fff; }
.lv-btn-primary {
    background:linear-gradient(135deg,#4f46e5,#8b5cf6); color:#fff;
    box-shadow:0 10px 20px -8px rgba(99,102,241,.6);
}
.lv-btn-primary:hover { color:#fff; transform:translateY(-1px); }
.lv-sec {
    background:#fff; border:1px solid #e9ebf1; border-radius:16px;
    padding:18px; margin-bottom:14px;
    box-shadow:0 4px 14px -8px rgba(15,23,42,.10);
}
.lv-sec-hd { display:flex; align-items:center; gap:10px; margin-bottom:14px; }
.lv-sec-ico {
    width:36px; height:36px; flex:none; border-radius:11px;
    display:grid; place-items:center; font-size:1rem; color:#fff;
    background:linear-gradient(135deg,#6366f1,#8b5cf6);
    box-shadow:0 8px 16px -8px rgba(99,102,241,.6);
}
.lv-sec-hd h2 { margin:0; font-size:.92rem; font-weight:800; color:#111827; flex:1; }
.lv-steps { display:flex; gap:8px; margin-bottom:16px; }
.lv-step { flex:1; display:flex; align-items:center; gap:8px; background:#f8fafc;
    border:1px solid #eef0f3; border-radius:12px; padding:9px 12px;
    font-size:.74rem; font-weight:800; color:#6b7280; }
.lv-step .n { width:24px; height:24px; flex:none; border-radius:8px; display:grid;
    place-items:center; background:#e0e7ff; color:#4f46e5; font-size:.72rem; }
.lv-step.active { background:#eef2ff; border-color:#c7d2fe; color:#4338ca; }
.lv-step.active .n { background:#4f46e5; color:#fff; }
.lv-form-grid { display:flex; gap:10px; flex-wrap:wrap; align-items:end; }
.lv-field { display:flex; flex-direction:column; gap:5px; }
.lv-field label { font-size:.74rem; font-weight:800; color:#374151; }
.lv-field .form-control { border-radius:10px !important; }
.lv-hint { font-size:.76rem; color:#6b7280; line-height:1.7; margin:.6rem 0 0; }
.lv-hint code { background:#eef2ff; color:#4f46e5; border-radius:5px; padding:1px 6px;
    font-size:.72rem; font-family:inherit; }
.lv-upload {
    flex:2; min-width:220px; border:1.5px dashed #c7d2fe; border-radius:12px;
    padding:14px; text-align:center; cursor:pointer; transition:all .15s;
    background:#fafbff;
}
.lv-upload:hover { border-color:#6366f1; background:#f5f6ff; }
.lv-upload input { display:none; }
.lv-upload .t { font-size:.82rem; font-weight:800; color:#4338ca; }
.lv-upload .s { font-size:.7rem; color:#6b7280; }
.lv-table { width:100%; border-collapse:collapse; font-size:.8rem; }
.lv-table th, .lv-table td { border-bottom:1px solid #eef0f3; padding:8px 10px; text-align:start; }
.lv-table th { color:#6b7280; font-size:.68rem; text-transform:uppercase; letter-spacing:.04em; }
.lv-table tr:last-child td { border-bottom:none; }
.lv-err { color:#b91c1c; font-size:.76rem; font-weight:600; padding:3px 0;
    display:flex; align-items:center; gap:6px; }
.lv-ok-count { display:inline-flex; align-items:center; gap:6px; font-size:.8rem; font-weight:800;
    color:#15803d; background:#dcfce7; border-radius:999px; padding:4px 13px; }
.lv-bad-count { display:inline-flex; align-items:center; gap:6px; font-size:.8rem; font-weight:800;
    color:#b45309; background:#fef3c7; border-radius:999px; padding:4px 13px; }
</style>
@endpush

@section('content')
<div class="lv-page">
    <header class="lv-hero">
        <div class="lv-hero-in">
            <div class="lv-hero-ico"><i class="bi bi-upload"></i></div>
            <div class="lv-hero-txt">
                <h1>{{ __('Import CSV') }}</h1>
                <p>{{ __('Bulk-add words from a spreadsheet in seconds.') }}</p>
            </div>
            <div class="lv-hero-actions">
                <a href="{{ route('vocabulary.imports.template') }}" class="lv-btn lv-btn-glass"><i class="bi bi-download"></i>{{ __('Download template') }}</a>
                <a href="{{ route('vocabulary.cards.index') }}" class="lv-btn lv-btn-glass"><i class="bi bi-grid"></i>{{ __('Cards') }}</a>
            </div>
        </div>
    </header>

    <div class="lv-steps">
        <div class="lv-step {{ $preview ? '' : 'active' }}"><span class="n">1</span>{{ __('Upload') }}</div>
        <div class="lv-step {{ $preview ? 'active' : '' }}"><span class="n">2</span>{{ __('Preview') }}</div>
        <div class="lv-step"><span class="n">3</span>{{ __('Confirm import') }}</div>
    </div>

    <section class="lv-sec">
        <div class="lv-sec-hd">
            <div class="lv-sec-ico"><i class="bi bi-file-earmark-spreadsheet"></i></div>
            <h2>{{ __('Upload') }}</h2>
        </div>
        <form method="POST" action="{{ route('vocabulary.imports.preview') }}" enctype="multipart/form-data">
            @csrf
            <div class="lv-form-grid">
                <div class="lv-field" style="flex:1;min-width:160px;">
                    <label>{{ __('Deck') }}</label>
                    <select name="deck_id" class="form-control" required>
                        @foreach($decks as $d)
                        <option value="{{ $d->id }}" {{ (int) old('deck_id', $preview['deck_id'] ?? 0) === $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>
                <label class="lv-upload">
                    <div class="t"><i class="bi bi-cloud-arrow-up"></i> <span id="lvFileName">{{ __('Choose a CSV file') }}</span></div>
                    <div class="s">CSV · TXT · {{ __('max 2 MB') }}</div>
                    <input type="file" id="lvFile" name="file" accept=".csv,.txt" required>
                </label>
                <button class="lv-btn lv-btn-primary" type="submit" style="padding:11px 18px;"><i class="bi bi-eye"></i>{{ __('Preview') }}</button>
            </div>
        </form>
        <p class="lv-hint">{{ __('Columns: word, meaning, example (optional), note (optional). Header row, commas or semicolons — all auto-detected. Max 1000 rows per file.') }}</p>
        <p class="lv-hint">{{ __('Bulk import skips the daily new-card limit — every imported card starts in Box 1, due today.') }} {{ __('Duplicate words already in the deck are skipped automatically.') }}</p>
    </section>

    @if($preview)
    <section class="lv-sec">
        <div class="lv-sec-hd">
            <div class="lv-sec-ico"><i class="bi bi-eye"></i></div>
            <h2>{{ __('Preview') }}</h2>
            <span class="lv-ok-count"><i class="bi bi-check-circle-fill"></i>{{ __(':valid valid rows', ['valid' => app_num(count($preview['rows']))]) }}</span>
            @if(!empty($preview['errors']))<span class="lv-bad-count"><i class="bi bi-exclamation-triangle-fill"></i>{{ __(':invalid invalid', ['invalid' => app_num(count($preview['errors']))]) }}</span>@endif
            @if(!empty($preview['truncated']))<span class="lv-bad-count">{{ __('truncated to 1000 rows') }}</span>@endif
            @if(!empty($preview['duplicates']))<span class="lv-bad-count"><i class="bi bi-files"></i>{{ __(':count duplicates skipped', ['count' => app_num($preview['duplicates'])]) }}</span>@endif
        </div>
        <div style="overflow-x:auto;">
            <table class="lv-table">
                <thead><tr><th>#</th><th>{{ __('Word') }}</th><th>{{ __('Meaning') }}</th><th>{{ __('Ex') }}</th></tr></thead>
                <tbody>
                    @foreach(array_slice($preview['rows'], 0, 50) as $i => $row)
                    <tr><td style="color:#9ca3af;">{{ app_num($i + 1) }}</td><td style="font-weight:700;color:#111827;">{{ $row['front'] }}</td><td>{{ $row['back'] }}</td><td style="color:#6b7280;">{{ \Illuminate\Support\Str::limit($row['example'] ?? '', 60) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if(count($preview['rows']) > 50)
        <p class="lv-hint">… {{ __(':more more rows not shown', ['more' => app_num(count($preview['rows']) - 50)]) }}</p>
        @endif
        @if(!empty($preview['errors']))
        <div style="margin-top:.6rem;background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:10px 14px;">
            @foreach(array_slice($preview['errors'], 0, 10) as $e)
            <div class="lv-err"><i class="bi bi-x-circle"></i>{{ __('Line :line', ['line' => app_num($e['line'])]) }}: {{ __($e['message']) }}</div>
            @endforeach
        </div>
        @endif
        <form method="POST" action="{{ route('vocabulary.imports.confirm') }}" style="margin-top:1rem;">
            @csrf
            <button class="lv-btn lv-btn-primary" type="submit" style="padding:11px 22px;"><i class="bi bi-check-lg"></i>{{ __('Confirm import') }}</button>
        </form>
    </section>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.getElementById('lvFile')?.addEventListener('change', function () {
    if (this.files.length) document.getElementById('lvFileName').textContent = this.files[0].name;
});
</script>
@endpush

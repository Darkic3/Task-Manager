@extends('layouts.app')

@section('title', 'Exercise Library')

@push('styles')
<style>
    .wk-shell{padding:22px 24px 48px;background:#f7f9fc;min-height:100vh}.wk-wrap{max-width:1100px;margin:auto}
    .wk-head{display:flex;align-items:center;gap:12px;margin-bottom:18px}.wk-head h1{margin:0;font-size:22px;font-weight:800;color:#0f172a}.wk-head p{margin:4px 0 0;color:#64748b;font-size:12px}.wk-head-main{flex:1}
    .wk-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;box-shadow:0 5px 18px rgba(15,23,42,.04)}.wk-toolbar{padding:12px;display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}.wk-input{border:1px solid #dbe3ee;border-radius:9px;padding:8px 11px;font-size:13px;outline:0;background:#fff}.wk-input:focus{border-color:#6366f1;box-shadow:0 0 0 3px #eef2ff}.wk-search{flex:1;min-width:200px}.wk-btn{display:inline-flex;align-items:center;gap:6px;border:1px solid #dbe3ee;border-radius:9px;padding:8px 12px;font-size:12px;font-weight:700;text-decoration:none;color:#475569;background:#fff}.wk-btn:hover{color:#4338ca;border-color:#a5b4fc;background:#f8faff}.wk-btn.primary{background:#4f46e5;border-color:#4f46e5;color:#fff}.wk-btn.primary:hover{background:#4338ca;color:#fff}.wk-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;padding:14px}.wk-ex{padding:14px;min-height:160px;display:flex;flex-direction:column}.wk-ex-title{font-weight:800;font-size:14px;color:#1e293b}.wk-ex-meta{display:flex;gap:5px;flex-wrap:wrap;margin:9px 0}.wk-pill{background:#eef2ff;color:#4338ca;border-radius:20px;padding:3px 8px;font-size:10px;font-weight:700}.wk-pill.gray{background:#f1f5f9;color:#64748b}.wk-ex-note{font-size:12px;color:#64748b;line-height:1.5;flex:1}.wk-ex-foot{border-top:1px solid #eef2f7;margin-top:12px;padding-top:10px;display:flex;justify-content:space-between;align-items:center;font-size:11px;color:#94a3b8}.wk-actions{display:flex;gap:5px}.wk-icon{color:#64748b;text-decoration:none;padding:3px 6px;border-radius:6px}.wk-icon:hover{background:#eef2ff;color:#4338ca}.wk-empty{text-align:center;padding:60px 20px;color:#64748b}.wk-empty i{font-size:34px;color:#a5b4fc;display:block;margin-bottom:10px}.pagination{margin:0;padding:0 14px 14px;justify-content:center}@media(max-width:800px){.wk-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:560px){.wk-shell{padding:14px 12px 40px}.wk-head{align-items:flex-start}.wk-head .wk-btn{padding:8px}.wk-head .wk-btn span{display:none}.wk-grid{grid-template-columns:1fr;padding:10px}.wk-toolbar{margin-bottom:10px}.wk-input{width:100%}.wk-toolbar .wk-btn{justify-content:center;flex:1}}
</style>
@endpush

@section('content')
<div class="wk-shell"><div class="wk-wrap">
    <div class="wk-head">
        <div class="wk-head-main"><h1>Exercise Library</h1><p>Define each movement once, then reuse it across every workout plan.</p></div>
        <a href="{{ route('workouts.plans.index') }}" class="wk-btn"><i class="bi bi-calendar3"></i><span>Plans</span></a>
        <a href="{{ route('workouts.exercises.create') }}" class="wk-btn primary"><i class="bi bi-plus-lg"></i><span>New exercise</span></a>
    </div>
    @if(session('success'))<div class="alert alert-success py-2 small">{{ session('success') }}</div>@endif
    <form class="wk-card wk-toolbar" method="GET">
        <input class="wk-input wk-search" name="q" value="{{ request('q') }}" placeholder="Search exercises or aliases...">
        <select class="wk-input" name="category"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>@endforeach</select>
        <button class="wk-btn primary" type="submit"><i class="bi bi-search"></i>Filter</button>
    </form>
    <div class="wk-card">
        @if($exercises->count())
            <div class="wk-grid">
                @foreach($exercises as $exercise)
                    <article class="wk-card wk-ex">
                        <div class="wk-ex-title">{{ $exercise->name }}</div>
                        <div class="wk-ex-meta">
                            @if($exercise->category)<span class="wk-pill">{{ $exercise->category }}</span>@endif
                            @foreach(array_slice($exercise->equipment ?? [], 0, 2) as $equipment)<span class="wk-pill gray">{{ $equipment }}</span>@endforeach
                        </div>
                        <div class="wk-ex-note">{{ $exercise->instructions ?: 'No form notes yet.' }}</div>
                        <div class="wk-ex-foot"><span><i class="bi bi-arrow-repeat"></i> {{ $exercise->workout_exercises_count }} plan uses</span><span class="wk-actions"><a class="wk-icon" href="{{ route('workouts.reports.exercise', $exercise) }}" title="History"><i class="bi bi-graph-up"></i></a><a class="wk-icon" href="{{ route('workouts.exercises.edit', $exercise) }}" title="Edit"><i class="bi bi-pencil"></i></a><form method="POST" action="{{ route('workouts.exercises.destroy', $exercise) }}" onsubmit="return confirm('Archive this exercise?')">@csrf @method('DELETE')<button class="wk-icon border-0 bg-transparent" title="Archive"><i class="bi bi-archive"></i></button></form></span></div>
                    </article>
                @endforeach
            </div>
            {{ $exercises->links() }}
        @else
            <div class="wk-empty"><i class="bi bi-heart-pulse"></i><strong>No exercises yet</strong><p class="mb-3">Create your first movement and reuse it in your weekly plans.</p><a href="{{ route('workouts.exercises.create') }}" class="wk-btn primary">Add exercise</a></div>
        @endif
    </div>
</div></div>
@endsection

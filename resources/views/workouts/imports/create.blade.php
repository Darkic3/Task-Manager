@extends('layouts.app')

@section('title', 'Import Workout With AI')

@push('styles')
<style>
    .wi-shell{padding:22px 24px 48px;background:#f7f9fc;min-height:100vh}.wi-wrap{max-width:850px;margin:auto}.wi-head{display:flex;align-items:flex-start;gap:12px;margin-bottom:16px}.wi-main{flex:1}.wi-back{font-size:12px;color:#64748b;text-decoration:none}.wi-title{font-size:22px;font-weight:800;color:#0f172a;margin:10px 0 3px}.wi-sub{font-size:12px;color:#64748b}.wi-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:18px;box-shadow:0 5px 18px rgba(15,23,42,.04)}.wi-label{display:block;font-size:11px;font-weight:800;text-transform:uppercase;color:#64748b;margin-bottom:7px}.wi-source{width:100%;min-height:430px;border:1px solid #dbe3ee;border-radius:10px;padding:12px;font:13px/1.65 Inter, sans-serif;resize:vertical;outline:0}.wi-source:focus{border-color:#6366f1;box-shadow:0 0 0 3px #eef2ff}.wi-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:13px}.wi-btn{display:inline-flex;align-items:center;gap:6px;border:1px solid #dbe3ee;border-radius:9px;padding:8px 13px;font-size:12px;font-weight:800;text-decoration:none;color:#475569;background:#fff}.wi-btn.primary{background:#4f46e5;color:#fff;border-color:#4f46e5}.wi-hints{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:14px}.wi-hint{background:#f8fafc;border:1px solid #eef2f7;border-radius:9px;padding:10px;font-size:11px;color:#64748b}.wi-hint i{color:#6366f1;margin-right:4px}@media(max-width:600px){.wi-shell{padding:14px 12px 40px}.wi-title{font-size:19px}.wi-hints{grid-template-columns:1fr}.wi-card{padding:13px}}
</style>
@endpush

@section('content')
<div class="wi-shell"><div class="wi-wrap"><div class="wi-head"><div class="wi-main"><a class="wi-back" href="{{ route('workouts.plans.index') }}"><i class="bi bi-arrow-left"></i> Workout plans</a><div class="wi-title">Import a workout with AI</div><div class="wi-sub">Paste your full plan. Lina will structure the week and match movements from your library.</div></div></div>
    @if($errors->any())<div class="alert alert-danger small"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="wi-hints"><div class="wi-hint"><i class="bi bi-calendar-week"></i> Seven days and Rest/Recovery are detected.</div><div class="wi-hint"><i class="bi bi-barbell"></i> Existing movements are reused by name and alias.</div><div class="wi-hint"><i class="bi bi-shield-check"></i> Nothing is saved until you approve the preview.</div></div>
    <form class="wi-card" method="POST" action="{{ route('workouts.imports.store') }}">@csrf<label class="wi-label" for="source">Workout plan text</label><textarea class="wi-source" id="source" name="source" required minlength="20" maxlength="30000" placeholder="Paste your complete weekly plan here...">{{ old('source') }}</textarea><div class="wi-actions"><a class="wi-btn" href="{{ route('workouts.plans.index') }}">Cancel</a><button class="wi-btn primary" type="submit"><i class="bi bi-stars"></i> Parse with AI</button></div></form>
</div></div>
@endsection

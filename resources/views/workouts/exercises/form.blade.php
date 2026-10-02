@extends('layouts.app')

@section('title', $exercise ? __('Edit Exercise') : __('New Exercise'))

@push('styles')
<style>
    .ef-shell{padding:22px 24px 48px;background:#f7f9fc;min-height:100vh}.ef-wrap{max-width:720px;margin:auto}.ef-head{margin-bottom:16px}.ef-back{font-size:12px;color:#64748b;text-decoration:none}.ef-head h1{font-size:22px;font-weight:800;color:#0f172a;margin:12px 0 4px}.ef-head p{color:#64748b;font-size:12px}.ef-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:20px;box-shadow:0 5px 18px rgba(15,23,42,.04)}.ef-label{display:block;font-size:11px;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:.06em;margin:0 0 6px}.ef-field{margin-bottom:15px}.ef-input,.ef-select,.ef-area{width:100%;border:1px solid #dbe3ee;border-radius:9px;padding:9px 11px;font-size:13px;outline:0}.ef-area{min-height:100px;resize:vertical}.ef-input:focus,.ef-select:focus,.ef-area:focus{border-color:#6366f1;box-shadow:0 0 0 3px #eef2ff}.ef-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}.ef-actions{display:flex;justify-content:flex-end;gap:8px;border-top:1px solid #eef2f7;padding-top:15px}.ef-btn{border:1px solid #dbe3ee;border-radius:9px;padding:8px 14px;background:#fff;color:#475569;text-decoration:none;font-size:12px;font-weight:700}.ef-btn.primary{background:#4f46e5;color:#fff;border-color:#4f46e5}@media(max-width:560px){.ef-shell{padding:14px 12px 40px}.ef-card{padding:15px}.ef-row{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
<div class="ef-shell"><div class="ef-wrap">
    <div class="ef-head"><a class="ef-back" href="{{ route('workouts.exercises.index') }}"><i class="bi bi-arrow-left"></i> {{ __('Exercise library') }}</a><h1>{{ $exercise ? __('Edit exercise') : __('Add exercise') }}</h1><p>{{ __('Keep the movement identity stable so its history can follow it across plans.') }}</p></div>
    @if($errors->any())<div class="alert alert-danger small"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form class="ef-card" method="POST" action="{{ $exercise ? route('workouts.exercises.update', $exercise) : route('workouts.exercises.store') }}">
        @csrf @if($exercise) @method('PUT') @endif
        <div class="ef-field"><label class="ef-label">{{ __('Exercise name *') }}</label><input class="ef-input" name="name" required maxlength="120" value="{{ old('name', $exercise?->name) }}" placeholder="{{ __('e.g. Pull-up') }}"></div>
        <div class="ef-row"><div class="ef-field"><label class="ef-label">{{ __('Category') }}</label><select class="ef-select" name="category"><option value="">{{ __('Choose category') }}</option>@foreach(['Push','Pull','Legs','Core','Mobility','Cardio','Recovery','Other'] as $category)<option value="{{ $category }}" @selected(old('category', $exercise?->category) === $category)>{{ __($category) }}</option>@endforeach</select></div><div class="ef-field"><label class="ef-label">{{ __('Equipment') }}</label><input class="ef-input" name="equipment" value="{{ old('equipment', implode(', ', $exercise?->equipment ?? [])) }}" placeholder="{{ __('Bodyweight, dumbbell') }}"></div></div>
        <div class="ef-field"><label class="ef-label">{{ __('Aliases') }}</label><input class="ef-input" name="aliases" value="{{ old('aliases', implode(', ', $exercise?->aliases ?? [])) }}" placeholder="{{ __('Pull Up, بارفیکس') }}"></div>
        <div class="ef-field"><label class="ef-label">{{ __('Target muscle groups') }}</label><input class="ef-input" name="muscle_groups" value="{{ old('muscle_groups', implode(', ', $exercise?->muscle_groups ?? [])) }}" placeholder="{{ __('Lats, biceps, upper back') }}"></div>
        <div class="ef-field"><label class="ef-label">{{ __('Form and safety notes') }}</label><textarea class="ef-area" name="instructions" maxlength="2000" placeholder="{{ __('Optional technique or safety notes...') }}">{{ old('instructions', $exercise?->instructions) }}</textarea></div>
        <div class="ef-actions"><a class="ef-btn" href="{{ route('workouts.exercises.index') }}">{{ __('Cancel') }}</a><button class="ef-btn primary" type="submit"><i class="bi bi-check-lg"></i> {{ $exercise ? __('Save changes') : __('Add exercise') }}</button></div>
    </form>
</div></div>
@endsection

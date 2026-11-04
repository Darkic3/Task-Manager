@extends('layouts.app')

@section('title', __('Edit Law'))

@push('styles')
<style>
.main-content { padding:14px 16px; background:#f7f8fa; min-height:100vh; }
.cu-header { padding:0 0 14px; margin-bottom:14px; }
.cu-back { display:inline-flex; align-items:center; gap:6px; font-size:13px; font-weight:500; color:#8a8f98; text-decoration:none; }
.cu-back:hover { color:#7c3aed; }
.cu-header-title { font-weight:700; font-size:19px; margin:14px 0 0; color:#1f2328; }
.cu-header-sub { font-size:12px; margin:2px 0 0; color:#8a8f98; }
.cu-layout { display:block; max-width:720px; margin:0 auto; }
.cu-sections { display:flex; flex-direction:column; gap:10px; }
.cu-section { background:white; border:1px solid #e3e4e8; border-radius:8px; overflow:hidden; }
.cu-section-header { display:flex; align-items:center; gap:8px; padding:10px 16px; background:#fafbfc; border-bottom:1px solid #e3e4e8; }
.cu-section-icon { width:26px; height:26px; border-radius:6px; display:flex; align-items:center; justify-content:center; font-size:13px; flex-shrink:0; }
.cu-section-icon.purple { background:#ede9fe; color:#7c3aed; }
.cu-section-icon.blue { background:#dbeafe; color:#2563eb; }
.cu-section-icon.green { background:#dcfce7; color:#16a34a; }
.cu-section-title { font-size:13px; font-weight:700; color:#1a1d23; margin:0; }
.cu-section-sub { font-size:11px; color:#8a8f98; margin:0 0 0 auto; }
.cu-section-body { padding:16px; }
.cu-field { margin-bottom:14px; }
.cu-field-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
@media(max-width:500px) { .cu-field-row { grid-template-columns:1fr; } }
.cu-label { display:block; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.7px; color:#8a8f98; margin-bottom:5px; }
.cu-input { width:100%; border:1px solid #dfe1e6; border-radius:7px; padding:9px 12px; font-size:13.5px; color:#1a1d23; background:white; }
.cu-input:focus { outline:none; border-color:#7c3aed; box-shadow:0 0 0 3px rgba(124,58,237,.12); }
.cu-input.is-invalid { border-color:#dc2626; }
.invalid-feedback { color:#dc2626; font-size:12px; margin-top:4px; }
.cu-action-bar { display:flex; gap:10px; justify-content:flex-end; padding:12px 16px; background:#fafbfc; border-top:1px solid #e3e4e8; }
.cu-btn-cancel { display:inline-flex; align-items:center; padding:8px 16px; border-radius:7px; border:1px solid #e5e7eb; background:white; color:#6b6f78; font-size:13px; font-weight:600; text-decoration:none; }
.cu-btn-save { display:inline-flex; align-items:center; padding:8px 18px; border-radius:7px; border:none; background:#7c3aed; color:white; font-size:13px; font-weight:600; cursor:pointer; }
.cu-btn-save:hover { background:#6d28d9; }
</style>
@endpush

@section('content')
<div class="cu-layout">
    <div class="cu-header">
        <a href="{{ route('laws.show', $law) }}" class="cu-back"><i class="bi bi-arrow-left"></i>{{ __('Back to Law') }}</a>
        <h1 class="cu-header-title">{{ __('Edit Law') }}</h1>
        <p class="cu-header-sub">{{ __('Past events keep their original coefficient.') }}</p>
    </div>
    <form method="POST" action="{{ route('laws.update', $law) }}">
        @csrf
        @method('PUT')
        <div class="cu-sections">
            @include('laws.form', ['law' => $law])
            <div class="cu-section">
                <div class="cu-action-bar">
                    <a href="{{ route('laws.show', $law) }}" class="cu-btn-cancel">{{ __('Cancel') }}</a>
                    <button type="submit" class="cu-btn-save"><i class="bi bi-check-lg me-1"></i>{{ __('Save changes') }}</button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

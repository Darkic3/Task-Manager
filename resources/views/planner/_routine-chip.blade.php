{{-- One-line routine summary for the week view (overview only; manage in day view).
     Vars: $routine (decorated for the day), $dayDate (Carbon) --}}
@php
    $chipDone = $routine->completedOn($dayDate);
    $chipAccent = $routine->periodColor() ?: '#7c3aed';
@endphp
<span class="pw-rchip {{ $chipDone ? 'is-done' : '' }}" title="{{ $routine->title }} · {{ __('Manage in day view') }}">
    <i class="bi {{ $chipDone ? 'bi-check-circle-fill' : 'bi-circle' }}" style="color:{{ $chipDone ? '#16a34a' : $chipAccent }};"></i>
    <span class="pw-rchip-title">{{ \Illuminate\Support\Str::limit($routine->title, 26) }}</span>
</span>

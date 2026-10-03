{{-- Jalali-aware date / datetime input (Jalali assets copied from shopora).
    fa locale : Jalali picker (visible text) + hidden Gregorian input that keeps
      the ORIGINAL name/id, so forms, validation and existing JS read/write
      Gregorian exactly like the native input did. Extra attributes land on
      the submitted input.
    other locales : native date / datetime-local input, behavior unchanged.

    Props: name (required), value (Gregorian 'Y-m-d' / 'Y-m-d H:i' / Carbon / null),
      type ('date'|'datetime'), id, class, required, placeholder.
--}}
@props([
    'name',
    'value' => null,
    'type' => 'date',
    'id' => null,
    'class' => '',
    'required' => false,
    'placeholder' => null,
])
@php
    $jdId = $id ?? preg_replace('/[^a-zA-Z0-9_-]/', '-', (string) $name);
    $jdGreg = null;
    if ($value !== null && $value !== '') {
        try {
            $jdCarbon = $value instanceof \Carbon\Carbon
                ? $value
                : \Carbon\Carbon::parse(str_replace('T', ' ', (string) $value));
            $jdGreg = $type === 'datetime' ? $jdCarbon->format('Y-m-d H:i') : $jdCarbon->format('Y-m-d');
        } catch (\Throwable $e) {
            $jdGreg = null;
        }
    }
    $jdJal = '';
    if ($jdGreg !== null) {
        try {
            $jdJal = \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::parse($jdGreg))
                ->format($type === 'datetime' ? 'Y/m/d H:i' : 'Y/m/d');
        } catch (\Throwable $e) {
            $jdJal = '';
        }
    }
@endphp
@if(app()->getLocale() === 'fa')
    <input type="hidden" name="{{ $name }}" id="{{ $jdId }}" value="{{ $jdGreg ?? '' }}" {{ $attributes }} />
    <input type="text"
           id="{{ $jdId }}-jalali"
           data-jdp
           @if($type === 'date') data-jdp-only-date @endif
           data-jdp-format="{{ $type === 'datetime' ? 'YYYY/MM/DD HH:mm' : 'YYYY/MM/DD' }}"
           data-jdp-init-date="{{ $jdJal }}"
           value="{{ $jdJal }}"
           data-target="{{ $jdId }}"
           placeholder="{{ $placeholder ?? ($type === 'datetime' ? '1403/05/14 15:30' : '1403/05/14') }}"
           autocomplete="off"
           dir="ltr"
           @if($required) required @endif
           class="{{ $class }}" />
@else
    <input type="{{ $type === 'datetime' ? 'datetime-local' : 'date' }}"
           name="{{ $name }}"
           id="{{ $jdId }}"
           value="{{ $type === 'datetime' && $jdGreg ? str_replace(' ', 'T', $jdGreg) : ($jdGreg ?? '') }}"
           @if($required) required @endif
           class="{{ $class }}"
           {{ $attributes }} />
@endif

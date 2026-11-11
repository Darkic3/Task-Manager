@php
    $colors = $colors ?? [
        '#7c3aed','#6d28d9','#5b21b6','#4c1d95',
        '#2563eb','#1d4ed8','#1e40af','#1e3a8a',
        '#0891b2','#0e7490','#155e75','#164e63',
        '#16a34a','#15803d','#166534','#14532d',
        '#ca8a04','#a16207','#854d0e','#713f12',
        '#dc2626','#b91c1c','#991b1b','#7f1d1d',
        '#db2777','#be185d','#9d174d','#831843',
        '#ea580c','#c2410c','#9a3412','#7c2d12',
        '#475569','#334155','#1e293b','#0f172a',
        '#0d9488','#0f766e','#115e59','#134e4a',
        '#9333ea','#7e22ce','#6b21a8','#581c87',
    ];
    $icons = $icons ?? [
        'bi-journal','bi-journal-text','bi-journal-bookmark','bi-journal-richtext','bi-journal-code','bi-journal-plus','bi-journal-minus','bi-journal-check','bi-journal-x',
        'bi-book','bi-bookmark','bi-bookmark-star','bi-bookmark-heart','bi-bookshelf',
        'bi-pen','bi-pencil','bi-pencil-square','bi-pen-fill',
        'bi-lightbulb','bi-lightbulb-fill','bi-stars','bi-star','bi-star-fill',
        'bi-heart','bi-heart-fill','bi-heart-pulse','bi-heart-pulse-fill',
        'bi-briefcase','bi-briefcase-fill',
        'bi-globe','bi-globe2','bi-map','bi-geo-alt','bi-geo-alt-fill',
        'bi-house','bi-house-fill','bi-house-door','bi-house-door-fill',
        'bi-people','bi-people-fill','bi-person','bi-person-fill',
        'bi-calendar','bi-calendar-event','bi-calendar-check','bi-calendar-heart',
        'bi-flag','bi-flag-fill','bi-trophy','bi-trophy-fill',
        'bi-music-note','bi-music-note-beamed','bi-music-note-list',
        'bi-camera','bi-camera-fill','bi-image','bi-image-fill',
        'bi-chat','bi-chat-dots','bi-chat-heart','bi-chat-quote',
        'bi-lightning','bi-lightning-fill','bi-fire','bi-fire-fill',
        'bi-droplet','bi-droplet-fill','bi-sun','bi-sun-fill','bi-moon','bi-moon-stars',
        'bi-cloud','bi-cloud-fill','bi-umbrella','bi-umbrella-fill',
        'bi-airplane','bi-airplane-fill','bi-car-front','bi-bicycle','bi-train-front',
        'bi-basket','bi-basket-fill','bi-bag','bi-bag-fill','bi-cart','bi-cart-fill',
        'bi-gift','bi-gift-fill','bi-box','bi-box-seam',
        'bi-clipboard','bi-clipboard-check','bi-clipboard-data','bi-clipboard-plus',
        'bi-folder','bi-folder-fill','bi-folder2','bi-folder2-open',
        'bi-tag','bi-tags','bi-tag-fill',
        'bi-hash','bi-at','bi-link','bi-link-45deg',
        'bi-shield','bi-shield-check','bi-shield-fill','bi-shield-lock',
        'bi-gear','bi-gear-fill','bi-sliders','bi-sliders2',
        'bi-graph-up','bi-graph-up-arrow','bi-bar-chart','bi-bar-chart-fill','bi-pie-chart',
        'bi-clock','bi-clock-history','bi-alarm','bi-stopwatch',
        'bi-bell','bi-bell-fill','bi-megaphone','bi-megaphone-fill',
        'bi-envelope','bi-envelope-fill','bi-send','bi-send-fill',
        'bi-telephone','bi-telephone-fill','bi-chat-left-text',
        'bi-wallet','bi-wallet-fill','bi-credit-card','bi-credit-card-fill',
        'bi-cup','bi-cup-fill','bi-cup-hot','bi-cup-hot-fill',
        'bi-egg-fried','bi-emoji-smile','bi-emoji-laughing','bi-emoji-heart-eyes',
    ];
    $colorId = $colorInputId ?? 'pickerColor';
    $iconId = $iconInputId ?? 'pickerIcon';
    $colorValue = $color ?? '#7c3aed';
    $iconValue = $icon ?? 'bi-journal';
@endphp

<div class="jn-card">
    <h3 class="jn-card-title"><i class="bi bi-palette"></i>{{ __('Color') }}</h3>
    <div class="jn-chips" id="{{ $colorId }}Swatches" style="max-height:220px;overflow:auto;padding:4px;"></div>
    <div class="mt-2 d-flex gap-2 align-items-center">
        <input type="text" id="{{ $colorId }}Custom" class="jn-input" placeholder="#7c3aed" style="max-width:160px;" value="{{ $colorValue }}">
        <span id="{{ $colorId }}Preview" style="display:inline-block;width:24px;height:24px;border-radius:6px;background:{{ $colorValue }};border:1px solid #cbd5e1;"></span>
    </div>
    <input type="hidden" name="{{ $colorFieldName ?? 'color' }}" id="{{ $colorId }}" value="{{ $colorValue }}">
</div>

@if (! empty($icons))
<div class="jn-card">
    <h3 class="jn-card-title"><i class="bi bi-grid"></i>{{ __('Icon') }}</h3>
    <div class="jn-chips" id="{{ $iconId }}Grid" style="max-height:260px;overflow:auto;padding:4px;"></div>
    <input type="hidden" name="{{ $iconFieldName ?? 'icon' }}" id="{{ $iconId }}" value="{{ $iconValue }}">
</div>
@endif

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const colors = @json($colors);
    const icons = @json($icons);
    const colorId = '{{ $colorId }}';
    const iconId = '{{ $iconId }}';

    const colorSwatches = document.getElementById(colorId + 'Swatches');
    const iconGrid = document.getElementById(iconId + 'Grid');
    const colorInput = document.getElementById(colorId);
    const iconInput = document.getElementById(iconId);
    const colorCustom = document.getElementById(colorId + 'Custom');
    const colorPreview = document.getElementById(colorId + 'Preview');

    colors.forEach(c => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'jn-mood-btn';
        btn.style.background = c;
        btn.dataset.value = c;
        btn.title = c;
        btn.addEventListener('click', () => selectColor(c));
        colorSwatches.appendChild(btn);
    });

    if (iconGrid) {
        icons.forEach(ic => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'jn-mood-btn';
            btn.innerHTML = '<i class="bi ' + ic + '"></i>';
            btn.dataset.value = ic;
            btn.title = ic;
            btn.addEventListener('click', () => selectIcon(ic));
            iconGrid.appendChild(btn);
        });
    }

    function selectColor(c) {
        if (colorInput) colorInput.value = c;
        if (colorCustom) colorCustom.value = c;
        if (colorPreview) colorPreview.style.background = c;
        colorSwatches.querySelectorAll('button').forEach(b => b.classList.toggle('is-on', b.dataset.value === c));
    }
    function selectIcon(ic) {
        if (iconInput) iconInput.value = ic;
        if (iconGrid) {
            iconGrid.querySelectorAll('button').forEach(b => b.classList.toggle('is-on', b.dataset.value === ic));
        }
    }

    if (colorCustom) {
        colorCustom.addEventListener('input', function () {
            const v = this.value.trim();
            if (/^#[0-9a-fA-F]{3,6}$/.test(v)) selectColor(v);
        });
    }

    selectColor(colorInput?.value || '{{ $colorValue }}');
    selectIcon(iconInput?.value || '{{ $iconValue }}');
});
</script>
@endpush

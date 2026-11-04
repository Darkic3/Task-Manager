{{-- Linked activities: fixed links with preset direction (phase 2) --}}
<div class="lw-panel">
    <h3><i class="bi bi-diagram-3 me-1"></i>{{ __('Linked activities') }}</h3>
    <p class="hint">{{ __('Attach tasks, routines and projects once — each marked for or against this law. Completing them later scores automatically.') }}</p>

    @php
        $typeMeta = [
            \App\Models\Task::class => ['label' => __('Tasks'), 'icon' => 'bi-check2-square', 'alias' => 'task'],
            \App\Models\Routine::class => ['label' => __('Routines'), 'icon' => 'bi-arrow-repeat', 'alias' => 'routine'],
            \App\Models\Project::class => ['label' => __('Projects'), 'icon' => 'bi-folder', 'alias' => 'project'],
        ];
    @endphp

    @foreach($typeMeta as $type => $meta)
    @php $links = ($grouped[$type] ?? collect())->filter(fn ($l) => $l->linkable); @endphp
    <div style="margin-top:12px;">
        <div style="font-size:12px;font-weight:700;color:#6b6f78;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">
            <i class="bi {{ $meta['icon'] }}"></i> {{ $meta['label'] }} · {{ app_num($links->count()) }}
        </div>
        @if($links->isEmpty())
        <p class="hint">{{ __('Nothing linked yet.') }}</p>
        @else
        <div class="lw-links">
            @foreach($links as $link)
            @php $name = $type === \App\Models\Project::class ? $link->linkable->name : $link->linkable->title; @endphp
            <div class="lw-link">
                <span class="lw-dir {{ $link->isFor() ? 'for' : 'against' }}">{{ $link->isFor() ? '+' : '−' }}</span>
                <span class="lw-link-name">{{ $name }}</span>
                @if($type === \App\Models\Project::class && isset($projectTasks[$link->id]))
                <span class="lw-pill">{{ __('covers :n tasks', ['n' => app_num($projectTasks[$link->id]->count())]) }}</span>
                @endif
                <span class="lw-link-acts">
                    <form method="POST" action="{{ route('laws.links.update', [$law, $link]) }}" style="display:inline;">
                        @csrf @method('PATCH')
                        <input type="hidden" name="effect" value="{{ $link->isFor() ? -1 : 1 }}">
                        <button class="lw-mini" title="{{ $link->isFor() ? __('Mark as against') : __('Mark as for') }}">
                            <i class="bi bi-arrow-left-right"></i>
                        </button>
                    </form>
                    <form method="POST" action="{{ route('laws.links.destroy', [$law, $link]) }}" style="display:inline;"
                          onsubmit="return confirmSwal(this, '{{ __('Remove this link?') }}', { isDelete: true });">
                        @csrf @method('DELETE')
                        <button class="lw-mini danger" title="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button>
                    </form>
                </span>
            </div>

            {{-- Project inheritance preview with per-task opt-out --}}
            @if($type === \App\Models\Project::class && isset($projectTasks[$link->id]) && $projectTasks[$link->id]->isNotEmpty())
            <div class="lw-inherit">
                <button type="button" class="lw-inherit-toggle" onclick="this.nextElementSibling.classList.toggle('open');">
                    <i class="bi bi-chevron-down"></i>{{ __('Inherited tasks') }} ({{ app_num($projectTasks[$link->id]->count()) }})
                </button>
                <div class="lw-inherit-list">
                    @foreach($projectTasks[$link->id] as $row)
                    <div class="lw-link sub">
                        @if($row['excluded'])
                        <span class="lw-dir off">∅</span>
                        <span class="lw-link-name">{{ $row['task']->title }}</span>
                        <span class="lw-pill">{{ __('Excluded') }}</span>
                        @elseif($row['direct'])
                        <span class="lw-dir {{ $row['effect'] === 1 ? 'for' : 'against' }}">{{ $row['effect'] === 1 ? '+' : '−' }}</span>
                        <span class="lw-link-name">{{ $row['task']->title }}</span>
                        <span class="lw-pill">{{ __('Direct') }}</span>
                        @else
                        <span class="lw-dir {{ $row['effect'] === 1 ? 'for' : 'against' }} ghost">{{ $row['effect'] === 1 ? '+' : '−' }}</span>
                        <span class="lw-link-name">{{ $row['task']->title }}</span>
                        <span class="lw-pill">{{ __('Inherited via :name', ['name' => $row['via']]) }}</span>
                        @endif
                        <span class="lw-link-acts">
                            <form method="POST" action="{{ route('laws.tasks.exclusion', [$law, $row['task']]) }}" style="display:inline;">
                                @csrf
                                <input type="hidden" name="excluded" value="{{ $row['excluded'] ? 0 : 1 }}">
                                <button class="lw-mini" title="{{ $row['excluded'] ? __('Include') : __('Exclude') }}">
                                    <i class="bi {{ $row['excluded'] ? 'bi-plus-lg' : 'bi-dash-lg' }}"></i>
                                </button>
                            </form>
                        </span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
            @endforeach
        </div>
        @endif
    </div>
    @endforeach

    {{-- Add-link picker --}}
    <div class="lw-add">
        <div style="font-size:12px;font-weight:700;color:#6b6f78;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">
            <i class="bi bi-plus-circle"></i> {{ __('Link activity') }}
        </div>
        <div class="lw-add-tabs">
            @foreach($typeMeta as $type => $meta)
            <button type="button" class="lw-add-tab" data-alias="{{ $meta['alias'] }}">
                <i class="bi {{ $meta['icon'] }}"></i> {{ $meta['label'] }}
            </button>
            @endforeach
        </div>
        <input type="search" id="lw-search" class="cu-input" placeholder="{{ __('Type to search…') }}" autocomplete="off">
        <div id="lw-results" class="lw-results"></div>
        <form method="POST" action="{{ route('laws.links.store', $law) }}" id="lw-add-form" style="display:none;">
            @csrf
            <input type="hidden" name="type" id="lw-add-type">
            <input type="hidden" name="id" id="lw-add-id">
            <div class="lw-add-pick"><i class="bi bi-pin-angle"></i><span id="lw-add-name"></span></div>
            <div class="lw-effect">
                <label><input type="radio" name="effect" value="1" checked> <span class="lw-dir for">+</span> {{ __('For the law') }}</label>
                <label><input type="radio" name="effect" value="-1"> <span class="lw-dir against">−</span> {{ __('Against the law') }}</label>
            </div>
            @error('id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <button class="lw-btn primary" style="margin-top:8px;"><i class="bi bi-link-45deg"></i>{{ __('Link activity') }}</button>
        </form>
    </div>
</div>

<style>
.lw-links { display:flex; flex-direction:column; gap:6px; }
.lw-link { display:flex; align-items:center; gap:8px; background:#fafbfc; border:1px solid #eef0f2; border-radius:7px; padding:7px 10px; font-size:13px; }
.lw-link.sub { background:white; margin-top:4px; margin-bottom:4px; margin-inline-start:18px; }
.lw-link-name { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#1f2328; font-weight:500; }
.lw-dir { width:22px; height:22px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:12px; font-weight:800; flex-shrink:0; }
.lw-dir.for { background:#dcfce7; color:#15803d; }
.lw-dir.against { background:#fee2e2; color:#b91c1c; }
.lw-dir.ghost { opacity:.55; }
.lw-dir.off { background:#f2f3f5; color:#8b8d98; }
.lw-link-acts { display:flex; gap:4px; flex-shrink:0; }
.lw-mini { width:24px; height:24px; border-radius:6px; border:1px solid #e5e7eb; background:white; color:#8b8d98; font-size:11px; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; }
.lw-mini:hover { color:#7c3aed; border-color:#7c3aed; }
.lw-mini.danger:hover { color:#e5484d; border-color:#e5484d; }
.lw-inherit-toggle { background:none; border:none; color:#7c3aed; font-size:11.5px; font-weight:600; cursor:pointer; padding-top:4px; padding-inline-start:18px; }
.lw-inherit-list { display:none; }
.lw-inherit-list.open { display:block; }
.lw-add { margin-top:14px; border-top:1px dashed #e5e7eb; padding-top:12px; }
.lw-add-tabs { display:flex; gap:6px; margin-bottom:8px; }
.lw-add-tab { display:inline-flex; align-items:center; gap:5px; padding:5px 12px; border-radius:20px; border:1px solid #e5e7eb; background:white; color:#6b6f78; font-size:12px; font-weight:600; cursor:pointer; }
.lw-add-tab.active { background:#7c3aed; border-color:#7c3aed; color:white; }
.lw-results { display:flex; flex-direction:column; gap:4px; margin-top:6px; max-height:220px; overflow:auto; }
.lw-result { display:flex; align-items:center; gap:8px; text-align:start; width:100%; background:white; border:1px solid #e5e7eb; border-radius:7px; padding:8px 10px; font-size:13px; cursor:pointer; color:#1f2328; }
.lw-result:hover { border-color:#7c3aed; background:#f7f5ff; }
.lw-result small { color:#8b8d98; }
.lw-add-pick { display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; color:#7c3aed; margin-top:8px; }
.lw-effect { display:flex; gap:16px; margin-top:8px; font-size:13px; color:#374151; }
.lw-effect label { display:flex; align-items:center; gap:6px; cursor:pointer; }
</style>

<script>
(function () {
    const lawId = {{ $law->id }};
    const search = document.getElementById('lw-search');
    const results = document.getElementById('lw-results');
    const form = document.getElementById('lw-add-form');
    const tabs = document.querySelectorAll('.lw-add-tab');
    let alias = 'task', timer = null;

    const paintTabs = () => tabs.forEach(t => t.classList.toggle('active', t.dataset.alias === alias));
    tabs.forEach(t => t.addEventListener('click', () => {
        alias = t.dataset.alias;
        form.style.display = 'none';
        paintTabs();
        run();
    }));
    paintTabs();

    function run() {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            const q = encodeURIComponent(search.value.trim());
            const res = await fetch(`{{ url('/laws') }}/${lawId}/candidates?type=${alias}&q=${q}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (!res.ok) return;
            const data = await res.json();
            results.innerHTML = '';
            if (!data.items.length) {
                results.innerHTML = `<div class="hint" style="font-size:12.5px;color:#8b8d98;">{{ __('No results') }}</div>`;
                return;
            }
            data.items.forEach(item => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'lw-result';
                btn.innerHTML = `<span style="flex:1;">${item.title} ${item.sub ? `<small>· ${item.sub}</small>` : ''}</span><i class="bi bi-plus-lg" style="color:#7c3aed;"></i>`;
                btn.addEventListener('click', () => {
                    document.getElementById('lw-add-type').value = alias;
                    document.getElementById('lw-add-id').value = item.id;
                    document.getElementById('lw-add-name').textContent = item.title;
                    form.style.display = 'block';
                    form.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                });
                results.appendChild(btn);
            });
        }, 250);
    }

    search.addEventListener('input', () => { form.style.display = 'none'; run(); });
    run();
})();
</script>

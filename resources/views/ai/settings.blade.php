@extends('layouts.app')

@section('title', 'AI Settings')

@push('styles')
<style>
.ai-settings-page { max-width: 860px; margin: 0 auto; }
.ai-card { background:#fff; border:1px solid var(--gray-200); border-radius:16px; overflow:hidden; }
.ai-card-head { padding:18px 22px 14px; border-bottom:1px solid var(--gray-100); display:flex; align-items:center; gap:12px; }
.ai-card-head-icon { width:40px; height:40px; border-radius:10px; background:linear-gradient(135deg,#4f46e5,#7c3aed); display:flex; align-items:center; justify-content:center; color:#fff; font-size:18px; }
.ai-provider-row { display:flex; gap:14px; align-items:flex-start; padding:16px 22px; border-bottom:1px solid var(--gray-100); }
.ai-provider-row:last-child { border-bottom:none; }
.ai-provider-info { flex:1; min-width:0; }
.ai-provider-name { font-weight:700; font-size:14px; color:var(--gray-900); display:flex; align-items:center; gap:8px; }
.ai-badge-on { background:#dcfce7; color:#166534; font-size:11px; padding:2px 8px; border-radius:20px; font-weight:600; }
.ai-badge-off{ background:var(--gray-100); color:var(--gray-500); font-size:11px; padding:2px 8px; border-radius:20px; font-weight:600; }
.ai-provider-desc { font-size:12.5px; color:var(--gray-500); margin-top:2px; }
.ai-input-group { display:flex; gap:8px; align-items:center; margin-top:10px; }
.ai-input-group input, .ai-input-group select { flex:1; }
</style>
@endpush

@section('content')
<div class="ai-settings-page">

    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('ai.index') }}" class="btn btn-outline"><i class="bi bi-arrow-left"></i> Back to Lina</a>
        <h4 class="m-0 fw-bold">AI Settings</h4>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('ai.settings.update') }}">
        @csrf
        @method('PUT')

        {{-- Default provider selector --}}
        <div class="ai-card mb-4">
            <div class="ai-card-head">
                <div class="ai-card-head-icon"><i class="bi bi-stars"></i></div>
                <div>
                    <div style="font-weight:800; font-size:15px; color:var(--gray-900);">Default AI</div>
                    <div style="font-size:12.5px; color:var(--gray-500);">The provider used when you chat. Auto-enabled when its API key is set.</div>
                </div>
            </div>
            <div style="padding:16px 22px; display:flex; gap:12px; align-items:end; flex-wrap:wrap;">
                <div style="flex:1; min-width:200px;">
                    <label class="form-label">Default provider</label>
                    <select name="default_provider" id="defaultProviderSelect" class="form-control">
                        <option value="">-- Auto (first enabled) --</option>
                        @foreach($allProviders as $id => $cfg)
                            <option value="{{ $id }}" {{ ($setting->default_provider ?? config('ai.default_provider')) === $id ? 'selected' : '' }}>
                                {{ $cfg['label'] }} {{ !empty($enabledMap[$id]) ? '✓' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div style="flex:1; min-width:200px;">
                    <label class="form-label">Model</label>
                    <select name="default_model" id="defaultModelSelect" class="form-control">
                        <option value="">-- Provider default --</option>
                        {{-- populated by JS based on provider --}}
                    </select>
                </div>
            </div>
            <div style="padding:0 22px 16px; font-size:12px; color:var(--gray-500);">
                At a time the selected provider is used. Setting an API key auto-enables that provider. If the default has no key, Lina auto-falls back to the next enabled provider.
            </div>
        </div>

        {{-- Per-provider keys --}}
        <div class="ai-card">
            <div class="ai-card-head">
                <div class="ai-card-head-icon" style="background:var(--gray-900);"><i class="bi bi-key-fill"></i></div>
                <div>
                    <div style="font-weight:800; font-size:15px; color:var(--gray-900);">Providers & API Keys</div>
                    <div style="font-size:12.5px; color:var(--gray-500);">Keys are encrypted. Add a key to auto-enable that provider.</div>
                </div>
            </div>

            @foreach($providers as $id => $cfg)
                @php
                    $keyCol = $cfg['key_column'];
                    $modelCol = $cfg['model_column'];
                    $hasKey = !empty($enabledMap[$id]);
                    $currentModel = $setting->{$modelCol} ?? $cfg['default_model'];
                @endphp
                <div class="ai-provider-row">
                    <div class="ai-provider-info">
                        <div class="ai-provider-name">
                            {{ $cfg['label'] }}
                            @if($hasKey)
                                <span class="ai-badge-on">Enabled</span>
                            @else
                                <span class="ai-badge-off">Disabled</span>
                            @endif
                            @if(($setting->default_provider ?? config('ai.default_provider')) === $id)
                                <span class="ai-badge-on" style="background:#ede9fe; color:#5b21b6;">Default</span>
                            @endif
                        </div>
                        <div class="ai-provider-desc">{{ $cfg['base_url'] }}</div>

                        <div class="ai-input-group">
                            <input type="password" name="{{ $keyCol }}" value="{{ $masked[$id] }}" placeholder="Paste {{ $cfg['label'] }} API key" class="form-control" autocomplete="off">
                            @if($hasKey)
                                <label style="font-size:12px; display:flex; align-items:center; gap:4px; white-space:nowrap; cursor:pointer;">
                                    <input type="checkbox" name="clear_{{ $keyCol }}" value="1"> Clear
                                </label>
                            @endif
                        </div>
                        @error($keyCol)<div class="text-danger" style="font-size:12px; margin-top:4px;">{{ $message }}</div>@enderror

                        <div class="ai-input-group">
                            <select name="{{ $modelCol }}" class="form-control per-provider-model" data-provider="{{ $id }}">
                                @foreach($cfg['models'] as $mid => $label)
                                    <option value="{{ $mid }}" {{ $currentModel === $mid ? 'selected' : '' }}>{{ $label }} — {{ $mid }}</option>
                                @endforeach
                            </select>
                            <span style="font-size:11px; color:var(--gray-400); white-space:nowrap;">Model</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
            <a href="{{ route('ai.index') }}" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save settings</button>
        </div>
    </form>

    {{-- Custom (user-defined) providers --}}
    <div class="ai-card mt-4">
        <div class="ai-card-head">
            <div class="ai-card-head-icon" style="background:linear-gradient(135deg,#0ea5e9,#22c55e);"><i class="bi bi-plug-fill"></i></div>
            <div>
                <div style="font-weight:800; font-size:15px; color:var(--gray-900);">Custom Providers</div>
                <div style="font-size:12.5px; color:var(--gray-500);">Add any OpenAI-compatible endpoint (OpenRouter, 9route, OmniRoute, Ollama, LM Studio…) or Gemini/Claude-compatible API.</div>
                <div style="font-size:12px; color:var(--gray-400); margin-top:4px;">OpenRouter tip: free models are often rate-limited (429) or retired (404). For the model field you can list several separated by commas — OpenRouter will try them in order, e.g. <code>google/gemma-4-31b-it:free, openai/gpt-4o-mini</code>.</div>
            </div>
        </div>

        @forelse($customProviders as $cp)
            @php
                $key = $cp->providerKey();
                $isDefault = ($setting->default_provider ?? null) === $key;
                $isEnabled = !empty($enabledMap[$key]);
            @endphp
            <div class="ai-provider-row">
                <div class="ai-provider-info">
                    <div class="ai-provider-name">
                        {{ $cp->label }}
                        <span class="ai-badge-off" style="text-transform:uppercase;">{{ $cp->type }}</span>
                        @if($isEnabled)
                            <span class="ai-badge-on">Enabled</span>
                        @else
                            <span class="ai-badge-off">Disabled</span>
                        @endif
                        @if($isDefault)
                            <span class="ai-badge-on" style="background:#ede9fe; color:#5b21b6;">Default</span>
                        @endif
                    </div>
                    <div class="ai-provider-desc">{{ $cp->base_url }} · model: {{ $cp->model ?: '—' }}</div>
                    @if(!empty($masked[$key]))
                        <div class="ai-provider-desc">Key: {{ $masked[$key] }}</div>
                    @endif

                    <div class="ai-input-group" style="flex-wrap:wrap;">
                        <button type="button" class="btn btn-outline btn-sm" data-test-url="{{ route('ai.providers.test', $cp) }}"><i class="bi bi-wifi"></i> Test</button>
                        <button type="button" class="btn btn-outline btn-sm" data-default-key="{{ $key }}" data-default-model="{{ $cp->model }}"><i class="bi bi-star"></i> Use as default</button>
                        <span class="ai-test-result" style="font-size:12px; margin-left:4px;"></span>
                    </div>

                    <details style="margin-top:10px;">
                        <summary style="font-size:12.5px; color:var(--gray-500); cursor:pointer;">Edit</summary>
                        <form method="POST" action="{{ route('ai.providers.update', $cp) }}" style="margin-top:10px;">
                            @csrf
                            @method('PUT')
                            <div class="ai-input-group">
                                <input type="text" name="label" value="{{ $cp->label }}" class="form-control" placeholder="Label" required>
                                <select name="type" class="form-control" style="max-width:150px;">
                                    <option value="openai" {{ $cp->type === 'openai' ? 'selected' : '' }}>openai</option>
                                    <option value="gemini" {{ $cp->type === 'gemini' ? 'selected' : '' }}>gemini</option>
                                    <option value="anthropic" {{ $cp->type === 'anthropic' ? 'selected' : '' }}>anthropic</option>
                                </select>
                            </div>
                            <div class="ai-input-group">
                                <input type="url" name="base_url" value="{{ $cp->base_url }}" class="form-control" placeholder="Base URL" required>
                            </div>
                            <div class="ai-input-group">
                                <input type="text" name="model" value="{{ $cp->model }}" class="form-control" placeholder="Model id" required>
                            </div>
                            <div class="ai-input-group">
                                <input type="password" name="api_key" value="" placeholder="Leave blank to keep current key" class="form-control" autocomplete="off">
                            </div>
                            <div class="ai-input-group">
                                <label style="font-size:12px; display:flex; align-items:center; gap:4px; cursor:pointer;">
                                    <input type="hidden" name="enabled" value="0">
                                    <input type="checkbox" name="enabled" value="1" {{ $cp->enabled ? 'checked' : '' }}> Enabled
                                </label>
                                <button type="submit" class="btn btn-primary btn-sm">Save</button>
                            </div>
                        </form>
                    </details>
                </div>

                <form method="POST" action="{{ route('ai.providers.destroy', $cp) }}" onsubmit="return confirm('Delete this provider?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline btn-sm" title="Delete"><i class="bi bi-trash"></i></button>
                </form>
            </div>
        @empty
            <div style="padding:16px 22px; font-size:13px; color:var(--gray-500);">No custom providers yet. Add one below.</div>
        @endforelse

        <div style="padding:18px 22px; border-top:1px solid var(--gray-100);">
            <div style="font-weight:700; font-size:13.5px; margin-bottom:10px;">Add a provider</div>
            <form method="POST" action="{{ route('ai.providers.store') }}">
                @csrf
                <div class="ai-input-group">
                    <input type="text" name="label" value="{{ old('label') }}" class="form-control" placeholder="Label (e.g. OpenRouter)" required>
                    <select name="type" class="form-control" style="max-width:150px;">
                        <option value="openai">openai</option>
                        <option value="gemini">gemini</option>
                        <option value="anthropic">anthropic</option>
                    </select>
                </div>
                <div class="ai-input-group">
                    <input type="url" name="base_url" value="{{ old('base_url') }}" class="form-control" placeholder="Base URL (e.g. https://openrouter.ai/api/v1/chat/completions)" required>
                </div>
                <div class="ai-input-group">
                    <input type="text" name="model" value="{{ old('model') }}" class="form-control" placeholder="Model id (e.g. openai/gpt-4o-mini, or a,b for fallback)" required>
                </div>
                <div class="ai-input-group">
                    <input type="password" name="api_key" value="" class="form-control" placeholder="API key" autocomplete="off">
                </div>
                <div class="ai-input-group">
                    <label style="font-size:12px; display:flex; align-items:center; gap:4px; cursor:pointer;">
                        <input type="hidden" name="enabled" value="0">
                        <input type="checkbox" name="enabled" value="1" checked> Enabled
                    </label>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add provider</button>
                </div>
            </form>
        </div>
    </div>

    <div class="mt-4" style="font-size:12px; color:var(--gray-500); line-height:1.6;">
        <strong>Where to get keys:</strong>
        OpenAI <code>platform.openai.com</code> · Gemini <code>aistudio.google.com</code> · Claude <code>console.anthropic.com</code> · DeepSeek <code>platform.deepseek.com</code> · Meta <code>llama.developer.meta.com</code>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
    const providers = @json($allProviders);
    const setting = @json($setting);
    const defProviderSel = document.getElementById('defaultProviderSelect');
    const defModelSel = document.getElementById('defaultModelSelect');

    function fillModelSelect(providerId){
        defModelSel.innerHTML = '<option value="">-- Provider default --</option>';
        if(!providerId || !providers[providerId]) return;
        const models = providers[providerId].models || {};
        const current = setting.default_model || setting[providerId + '_model'] || providers[providerId].default_model;
        Object.entries(models).forEach(([mid,label])=>{
            const opt=document.createElement('option');
            opt.value=mid; opt.textContent=label+' — '+mid;
            if(mid===current) opt.selected=true;
            defModelSel.appendChild(opt);
        });
    }

    // init
    fillModelSelect(defProviderSel.value);

    defProviderSel.addEventListener('change', function(){
        fillModelSelect(this.value);
    });

    // reveal password on focus (clear masked)
    document.querySelectorAll('input[type="password"]').forEach(inp=>{
        inp.addEventListener('focus', function(){
            if(this.value.startsWith('••••')){
                this.value='';
                this.type='text';
            }
        });
        inp.addEventListener('blur', function(){
            if(this.type==='text' && this.value===''){
                // restore masked? leave empty to indicate no change
                this.type='password';
            } else if(this.type==='text'){
                // keep as text if they typed something
            }
        });
    });

    // Test a custom provider connection
    document.querySelectorAll('[data-test-url]').forEach(btn=>{
        btn.addEventListener('click', async function(){
            const out = this.parentElement.querySelector('.ai-test-result');
            out.textContent = 'Testing…'; out.style.color = 'var(--gray-500)';
            this.disabled = true;
            try {
                const res = await fetch(this.dataset.testUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                });
                const json = await res.json();
                out.textContent = (json.ok ? '✓ ' : '✗ ') + (json.message || '');
                out.style.color = json.ok ? '#16a34a' : '#dc2626';
            } catch(e) {
                out.textContent = '✗ Network error'; out.style.color = '#dc2626';
            } finally { this.disabled = false; }
        });
    });

    // Set a custom provider as the default
    document.querySelectorAll('[data-default-key]').forEach(btn=>{
        btn.addEventListener('click', async function(){
            this.disabled = true;
            try {
                const res = await fetch('{{ route('ai.settings.switch') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify({ provider: this.dataset.defaultKey, model: this.dataset.defaultModel || '' }),
                });
                if (res.ok) { location.reload(); } else { this.disabled = false; }
            } catch(e) { this.disabled = false; }
        });
    });
})();
</script>
@endpush

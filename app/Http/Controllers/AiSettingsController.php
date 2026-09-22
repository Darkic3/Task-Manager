<?php

namespace App\Http\Controllers;

use App\Models\AiProvider;
use App\Models\AiSetting;
use App\Services\AiProviderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AiSettingsController extends Controller
{
    protected AiProviderService $ai;

    public function __construct(AiProviderService $ai)
    {
        $this->ai = $ai;
    }

    public function index()
    {
        $user = Auth::user();
        $setting = AiSetting::firstOrCreate(['user_id' => $user->id]);
        $providers = $this->ai->allProviders();
        $allProviders = $this->ai->providersForUser($user);
        $customProviders = AiProvider::where('user_id', $user->id)
            ->orderBy('sort_order')->orderBy('id')->get();
        $enabledMap = $this->ai->enabledMap($user);

        // For display, mask keys: show last 4 chars only
        $masked = [];
        foreach ($providers as $id => $cfg) {
            $col = $cfg['key_column'];
            $raw = $setting->{$col};
            $masked[$id] = $raw ? '••••••••' . substr($raw, -4) : '';
        }
        foreach ($customProviders as $cp) {
            $masked[$cp->providerKey()] = $cp->api_key ? '••••••••' . substr($cp->api_key, -4) : '';
        }

        // Pass actual provider list plus whether key exists
        return view('ai.settings', compact('setting', 'providers', 'allProviders', 'customProviders', 'enabledMap', 'masked'));
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        $providers = $this->ai->allProviders();
        $builtInIds = array_keys($providers);
        $allIds = array_keys($this->ai->providersForUser($user));

        $request->validate([
            'default_provider' => 'nullable|in:' . implode(',', $allIds),
            'default_model'    => 'nullable|string|max:120',
            'openai_key'       => 'nullable|string|max:500',
            'gemini_key'       => 'nullable|string|max:500',
            'anthropic_key'    => 'nullable|string|max:500',
            'deepseek_key'     => 'nullable|string|max:500',
            'meta_key'         => 'nullable|string|max:500',
            'openai_model'     => 'nullable|string|max:120',
            'gemini_model'     => 'nullable|string|max:120',
            'anthropic_model'  => 'nullable|string|max:120',
            'deepseek_model'   => 'nullable|string|max:120',
            'meta_model'       => 'nullable|string|max:120',
            // allow clearing via checkbox
            'clear_openai_key'    => 'nullable|boolean',
            'clear_gemini_key'    => 'nullable|boolean',
            'clear_anthropic_key' => 'nullable|boolean',
            'clear_deepseek_key'  => 'nullable|boolean',
            'clear_meta_key'      => 'nullable|boolean',
        ]);

        $setting = AiSetting::firstOrCreate(['user_id' => $user->id]);

        // Handle per-provider model choices (built-in only)
        foreach ($builtInIds as $id) {
            $modelField = $id . '_model';
            if ($request->filled($modelField)) {
                $model = $request->input($modelField);
                if ($this->ai->validateModel($id, $model, $user)) {
                    $setting->{$modelField} = $model;
                }
            }
        }

        // Handle keys: if input is masked placeholder, skip; if empty and clear checked, clear; if new value, save
        foreach ($builtInIds as $id) {
            $keyField = $id . '_key';
            $clearField = 'clear_' . $keyField;
            if ($request->boolean($clearField)) {
                $setting->{$keyField} = null;
                continue;
            }
            $val = $request->input($keyField);
            if ($val === null) continue;
            $val = trim($val);
            if ($val === '') continue;
            // If user submitted masked placeholder, ignore (means no change)
            if (str_starts_with($val, '••••')) continue;
            $setting->{$keyField} = $val;
        }

        // Default provider / model
        if ($request->filled('default_provider')) {
            $provider = $request->input('default_provider');
            $setting->default_provider = $provider;
            // If they also sent default_model via dropdown, set the per-provider field accordingly
            if ($request->filled('default_model')) {
                $dm = $request->input('default_model');
                if ($this->ai->validateModel($provider, $dm, $user)) {
                    $setting->default_model = $dm;
                    // Also sync to per-provider column (built-in only)
                    $col = $providers[$provider]['model_column'] ?? null;
                    if ($col) $setting->{$col} = $dm;
                }
            }
        }

        $setting->save();

        return redirect()->route('ai.settings')->with('success', 'AI settings saved.');
    }

    /** AJAX: save just default provider/model (quick switch) */
    public function quickSwitch(Request $request)
    {
        $user = Auth::user();
        $ids = array_keys($this->ai->providersForUser($user));
        $request->validate([
            'provider' => 'required|in:' . implode(',', $ids),
            'model'    => 'nullable|string|max:120',
        ]);

        $setting = AiSetting::firstOrCreate(['user_id' => $user->id]);
        $setting->default_provider = $request->provider;
        if ($request->filled('model') && $this->ai->validateModel($request->provider, $request->model, $user)) {
            $setting->default_model = $request->model;
            $col = $this->ai->providerConfig($request->provider, $user)['model_column'] ?? null;
            if ($col) $setting->{$col} = $request->model;
        }
        $setting->save();

        return response()->json(['ok' => true]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\AiProvider;
use App\Models\AiSetting;
use App\Services\AiProviderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AiProviderController extends Controller
{
    protected AiProviderService $ai;

    public function __construct(AiProviderService $ai)
    {
        $this->ai = $ai;
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $user = Auth::user();
        $data['user_id'] = $user->id;
        $data['enabled'] = $request->boolean('enabled');
        $data['sort_order'] = (int) AiProvider::where('user_id', $user->id)->max('sort_order') + 1;

        AiProvider::create($data);

        return redirect()->route('ai.settings')->with('success', 'Custom provider added.');
    }

    public function update(Request $request, AiProvider $provider)
    {
        $this->authorizeOwner($provider);

        $data = $this->validated($request);
        $data['enabled'] = $request->boolean('enabled');

        // Blank key means "keep the existing one"
        if (empty($data['api_key'])) {
            unset($data['api_key']);
        }

        $provider->update($data);

        return redirect()->route('ai.settings')->with('success', 'Custom provider updated.');
    }

    public function destroy(AiProvider $provider)
    {
        $this->authorizeOwner($provider);

        // Clear default pointer if this provider was the default
        $setting = AiSetting::where('user_id', Auth::id())->first();
        if ($setting && $setting->default_provider === $provider->providerKey()) {
            $setting->default_provider = null;
            $setting->save();
        }

        $provider->delete();

        return redirect()->route('ai.settings')->with('success', 'Custom provider removed.');
    }

    public function test(AiProvider $provider)
    {
        $this->authorizeOwner($provider);

        return response()->json($this->ai->testConnection($provider));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'label'    => 'required|string|max:80',
            'type'     => 'required|in:openai,gemini,anthropic',
            'base_url' => 'required|url|max:500',
            'model'    => 'required|string|max:120',
            'api_key'  => 'nullable|string|max:500',
            'enabled'  => 'nullable|boolean',
        ]);
    }

    private function authorizeOwner(AiProvider $provider): void
    {
        abort_if($provider->user_id !== Auth::id(), 403);
    }
}

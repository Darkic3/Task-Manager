<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeatureFlag;
use App\Support\FeatureAvailability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class FeatureManagementController extends Controller
{
    public function index(FeatureAvailability $features): View
    {
        return view('admin.features.index', [
            'modules' => $features->catalog(),
            'updates' => FeatureFlag::query()->with('updater:id,name,username')->get()->keyBy('key'),
        ]);
    }

    public function update(Request $request, string $feature): RedirectResponse
    {
        abort_unless(array_key_exists($feature, config('modules', [])), 404);

        $data = $request->validate(['enabled' => ['required', 'boolean']]);

        FeatureFlag::updateOrCreate(
            ['key' => $feature],
            [
                'enabled' => $request->boolean('enabled'),
                'updated_by' => Auth::id(),
            ],
        );

        return redirect()->route('admin.features.index')->with('success', __('Module publication settings updated.'));
    }
}

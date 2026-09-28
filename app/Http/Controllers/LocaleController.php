<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LocaleController extends Controller
{
    /**
     * Switch application locale.
     */
    public function switch(Request $request, string $locale)
    {
        if (!in_array($locale, ['en', 'fa'])) {
            $locale = config('app.locale', 'en');
        }

        session(['locale' => $locale]);
        cookie()->queue('locale', $locale, 60 * 24 * 365); // 1 year

        if (Auth::check()) {
            Auth::user()->update(['locale' => $locale]);
        }

        return redirect()->back();
    }
}

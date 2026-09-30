<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use App\Models\Routine;

class ProfileController extends Controller
{
    /**
     * Show the profile page.
     */
    public function show()
    {
        $user = Auth::user();
        return view('profile.show', compact('user'));
    }

    /**
     * Show the profile edit form.
     */
    public function edit()
    {
        $user = Auth::user();
        $wakeRoutines = $user->routines()
            ->where('tracking_mode', Routine::TRACKING_VALUE)
            ->where('value_kind', 'time')
            ->orderBy('title')
            ->get();
        return view('profile.edit', compact('user', 'wakeRoutines'));
    }

    /**
     * Update the user's profile information.
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            'bio' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:20'],
            'location' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'locale' => ['nullable', 'string', 'in:en,fa'],
            'morning_checkin_enabled' => ['nullable', 'boolean'],
            'morning_window_start' => ['nullable', 'date_format:H:i'],
            'morning_window_end' => ['nullable', 'date_format:H:i'],
            'wake_routine_id' => ['nullable', 'integer', Rule::exists('routines', 'id')->where('user_id', $user->id)],
        ]);

        $checkinEnabled = $request->boolean('morning_checkin_enabled');
        $windowStart = $request->input('morning_window_start') ?: '04:00';
        $windowEnd = $request->input('morning_window_end') ?: '12:00';
        $wakeRoutineId = $request->filled('wake_routine_id') ? (int) $request->input('wake_routine_id') : null;

        if ($checkinEnabled && !$wakeRoutineId) {
            return back()->withErrors([
                'wake_routine_id' => __('Please select a wake-up routine to enable morning check-in.'),
            ])->withInput();
        }

        if ($windowEnd <= $windowStart) {
            return back()->withErrors([
                'morning_window_end' => __('End time must be after start time.'),
            ])->withInput();
        }

        $updateData = [
            'name' => $request->name,
            'email' => $request->email,
            'bio' => $request->bio,
            'phone' => $request->phone,
            'location' => $request->location,
            'website' => $request->website,
            'morning_checkin_enabled' => $checkinEnabled,
            'morning_window_start' => $windowStart,
            'morning_window_end' => $windowEnd,
            'wake_routine_id' => $wakeRoutineId,
        ];

        if ($request->filled('locale')) {
            $updateData['locale'] = $request->locale;
            session(['locale' => $request->locale]);
            cookie()->queue('locale', $request->locale, 60 * 24 * 365);
            App::setLocale($request->locale);
        }

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            $updateData['avatar'] = $avatarPath;
        }

        // Update user information
        $user->update($updateData);

        return redirect()->route('profile.show')->with('success', __('Profile updated successfully!'));
    }

    /**
     * Show the password change form.
     */
    public function showPasswordForm()
    {
        return view('profile.password');
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        Auth::user()->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect()->route('profile.show')->with('success', __('Password updated successfully!'));
    }

    /**
     * Delete the user's avatar.
     */
    public function deleteAvatar()
    {
        $user = Auth::user();

        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->update(['avatar' => null]);

        return response()->json(['success' => true]);
    }
}

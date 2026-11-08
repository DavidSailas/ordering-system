<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\Canteen;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Administrator only: every account in the system, with its role and canteen.
 * Accounts are deactivated rather than deleted so order history keeps its names.
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $role = UserRole::tryFrom((string) $request->query('role'));
        $search = trim((string) $request->query('q', ''));

        $users = User::query()
            ->with('canteen')
            ->when($role, fn (Builder $query) => $query->where('role', $role->value))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(fn (Builder $query) => $query
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%'));
            })
            ->orderBy('role')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('Admin.users.index', [
            'users' => $users,
            'roles' => UserRole::cases(),
            'filterRole' => $role,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('Admin.users.create', $this->formData(new User(['role' => UserRole::Cashier])));
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'role' => $request->enum('role', UserRole::class),
            'canteen_id' => $request->canteenId(),
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return redirect()
            ->route('admin.users.index')
            ->with('status', $user->name.' was added as '.$user->role->label().'.');
    }

    public function edit(User $user): View
    {
        return view('Admin.users.edit', $this->formData($user));
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $role = $request->enum('role', UserRole::class);

        if ($request->user()->is($user) && $role !== UserRole::Admin) {
            return back()->withInput()->withErrors(['role' => 'You cannot change your own role. Ask another administrator.']);
        }

        if ($user->isAdmin() && $role !== UserRole::Admin && $this->isLastActiveAdmin($user)) {
            return back()->withInput()->withErrors(['role' => 'This is the only active administrator, so it must stay an administrator.']);
        }

        $user->fill([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'role' => $role,
            'canteen_id' => $request->canteenId(),
        ]);

        if ($request->filled('password')) {
            $user->password = $request->validated('password');
        }

        $user->save();

        return redirect()
            ->route('admin.users.index')
            ->with('status', $user->name.' was updated.');
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return back()->withErrors(['user' => 'You cannot deactivate your own account.']);
        }

        if ($user->is_active && $user->isAdmin() && $this->isLastActiveAdmin($user)) {
            return back()->withErrors(['user' => 'The only active administrator cannot be deactivated.']);
        }

        if (! $user->is_active && $user->isOwner() && User::canteenHasActiveOwner((int) $user->canteen_id, $user)) {
            return back()->withErrors(['user' => 'That canteen already has an active owner.']);
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('status', $user->name.($user->is_active ? ' can log in again.' : ' was deactivated and can no longer log in.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(User $member): array
    {
        return [
            'member' => $member,
            'roles' => UserRole::cases(),
            'canteens' => Canteen::query()->orderBy('sort_order')->orderBy('id')->get(),
        ];
    }

    private function isLastActiveAdmin(User $user): bool
    {
        return ! User::query()
            ->where('role', UserRole::Admin->value)
            ->where('is_active', true)
            ->whereKeyNot($user->getKey())
            ->exists();
    }
}

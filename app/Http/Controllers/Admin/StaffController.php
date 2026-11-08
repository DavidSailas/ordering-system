<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StaffRequest;
use App\Models\User;
use App\Support\CurrentCanteen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The people who work for one canteen. Owners manage their cashiers and runners;
 * an administrator (working in the canteen picked with the switcher) can also appoint the owner.
 */
class StaffController extends Controller
{
    public function index(Request $request): View
    {
        return view('Admin.staff.index', [
            'users' => User::query()
                ->where('canteen_id', CurrentCanteen::id())
                ->whereIn('role', array_map(fn (UserRole $role): string => $role->value, UserRole::canteenRoles()))
                ->withCount('orders')
                ->orderBy('role')
                ->orderBy('name')
                ->get(),
            'manageable' => $this->manageableRoles($request->user()),
        ]);
    }

    public function create(Request $request): View
    {
        return view('Admin.staff.create', [
            'member' => new User(['role' => UserRole::Cashier]),
            'roles' => $this->manageableRoles($request->user()),
        ]);
    }

    public function store(StaffRequest $request): RedirectResponse
    {
        $member = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'role' => $request->enum('role', UserRole::class),
            'canteen_id' => CurrentCanteen::id(),
        ]);

        return redirect()
            ->route('admin.staff.index')
            ->with('status', $member->name.' was added as '.$member->role->label().'.');
    }

    public function edit(Request $request, User $user): View
    {
        $this->managedBy($request->user(), $user);

        return view('Admin.staff.edit', [
            'member' => $user,
            'roles' => $this->manageableRoles($request->user()),
        ]);
    }

    public function update(StaffRequest $request, User $user): RedirectResponse
    {
        $this->managedBy($request->user(), $user);

        $user->fill([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'role' => $request->enum('role', UserRole::class),
        ]);

        if ($request->filled('password')) {
            $user->password = $request->validated('password');
        }

        $user->save();

        return redirect()
            ->route('admin.staff.index')
            ->with('status', $user->name.' was updated.');
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return back()->withErrors(['staff' => 'You cannot deactivate your own account.']);
        }

        $this->managedBy($request->user(), $user);

        if (! $user->is_active && $user->isOwner() && User::canteenHasActiveOwner((int) $user->canteen_id, $user)) {
            return back()->withErrors(['staff' => 'This canteen already has an active owner.']);
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('status', $user->name.($user->is_active ? ' can log in again.' : ' was deactivated and can no longer log in.'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return back()->withErrors(['staff' => 'You cannot delete your own account while logged in.']);
        }

        $this->managedBy($request->user(), $user);

        $user->delete();

        return back()->with('status', $user->name.' was removed.');
    }

    /**
     * @return array<int, UserRole>
     */
    private function manageableRoles(User $actor): array
    {
        return $actor->isAdmin() ? UserRole::assignableByAdmin() : UserRole::assignableByOwner();
    }

    /** Staff can only be changed inside the current canteen, and only for roles the actor may manage. */
    private function managedBy(User $actor, User $user): void
    {
        abort_unless($user->canteen_id !== null && (int) $user->canteen_id === CurrentCanteen::id(), 404);
        abort_unless(in_array($user->role, $this->manageableRoles($actor), true), 403);
    }
}

@use('App\Enums\UserRole')

<form method="POST" action="{{ $action }}" class="max-w-xl space-y-4 rounded-2xl bg-white p-5 shadow-sm">
    @csrf
    @if ($member->exists)
        @method('PUT')
    @endif

    <x-admin.field name="name" label="Full name" :value="$member->name" maxlength="255" required autofocus />
    <x-admin.field name="email" label="Email" type="email" :value="$member->email" maxlength="255" autocomplete="off" required />

    <div>
        <label for="role" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-neutral-600">Role</label>
        <select id="role" name="role" required class="w-full rounded-xl border-0 bg-white px-4 py-2.5 text-sm shadow-sm ring-1 ring-violet-200 focus:outline-none focus:ring-2 focus:ring-violet-400">
            @foreach ($roles as $case)
                <option value="{{ $case->value }}" @selected(old('role', $member->role?->value) === $case->value)>{{ $case->label() }}</option>
            @endforeach
        </select>
        @error('role')
            <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
        @enderror
        <p class="mt-1.5 text-xs text-neutral-500">Cashiers take payments and manage incoming orders. Runners deliver orders to the student's room.@if (in_array(UserRole::Owner, $roles, true)) The owner is the head of this canteen: menu, staff, settings and reports.@endif</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-admin.field name="password" :label="$member->exists ? 'New password' : 'Password'" type="password" autocomplete="new-password" :required="! $member->exists" />
        <x-admin.field name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" :required="! $member->exists" />
    </div>

    @if ($member->exists)
        <p class="text-xs text-neutral-500">Leave the password fields empty to keep the current password.</p>
    @endif

    <div class="flex gap-2 pt-2">
        <button type="submit" class="rounded-full bg-neutral-900 px-6 py-3 text-sm font-semibold text-white hover:bg-neutral-700">
            {{ $member->exists ? 'Save changes' : 'Create account' }}
        </button>
        <a href="{{ route('admin.staff.index') }}" class="rounded-full bg-neutral-100 px-5 py-3 text-sm font-medium hover:bg-neutral-200">Cancel</a>
    </div>
</form>

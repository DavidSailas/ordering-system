<x-layouts.admin title="Users & roles">
    <x-admin.page-title title="Users & roles" subtitle="Every account in the system. Deactivate an account to block login without losing its history.">
        <a href="{{ route('admin.users.create') }}" class="flex items-center gap-1.5 rounded-full bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-700">
            <x-icon name="plus" class="size-4" />
            Add user
        </a>
    </x-admin.page-title>

    <form method="GET" action="{{ route('admin.users.index') }}" class="mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label for="q" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-neutral-600">Search</label>
            <input id="q" name="q" value="{{ $search }}" placeholder="Name or email" class="rounded-xl border-0 bg-white px-4 py-2.5 text-sm shadow-sm ring-1 ring-violet-200 focus:outline-none focus:ring-2 focus:ring-violet-400">
        </div>
        <div>
            <label for="role-filter" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-neutral-600">Role</label>
            <select id="role-filter" name="role" class="rounded-xl border-0 bg-white px-4 py-2.5 text-sm shadow-sm ring-1 ring-violet-200 focus:outline-none focus:ring-2 focus:ring-violet-400">
                <option value="">All roles</option>
                @foreach ($roles as $case)
                    <option value="{{ $case->value }}" @selected($filterRole === $case)>{{ $case->label() }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded-full bg-neutral-100 px-5 py-2.5 text-sm font-medium hover:bg-neutral-200">Filter</button>
    </form>

    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm">
        <table class="w-full min-w-[44rem] text-left text-sm">
            <thead class="text-xs text-neutral-500">
                <tr class="border-b border-neutral-100">
                    <th class="px-4 py-3 font-medium">Name</th>
                    <th class="px-4 py-3 font-medium">Email</th>
                    <th class="px-4 py-3 font-medium">Role</th>
                    <th class="px-4 py-3 font-medium">Canteen</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse ($users as $member)
                    <tr class="hover:bg-neutral-50">
                        <td class="px-4 py-3 font-medium">
                            {{ $member->name }}
                            @if (auth()->user()->is($member))
                                <span class="ml-1 rounded-full bg-neutral-100 px-2 py-0.5 text-[11px] font-medium text-neutral-500">You</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-neutral-600">{{ $member->email }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-1 text-[11px] font-medium {{ $member->isAdmin() || $member->isOwner() ? 'bg-violet-300 text-violet-950' : 'bg-sky-200 text-sky-950' }}">{{ $member->role->label() }}</span>
                        </td>
                        <td class="px-4 py-3 text-neutral-600">{{ $member->canteen?->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-1 text-[11px] font-medium {{ $member->is_active ? 'bg-emerald-200 text-emerald-950' : 'bg-rose-100 text-rose-700' }}">{{ $member->is_active ? 'Active' : 'Deactivated' }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1.5">
                                <a href="{{ route('admin.users.edit', $member) }}" aria-label="Edit {{ $member->name }}" class="grid size-8 place-items-center rounded-full bg-violet-300 hover:bg-violet-400">
                                    <x-icon name="pencil" class="size-4" />
                                </a>
                                @unless (auth()->user()->is($member))
                                    <form method="POST" action="{{ route('admin.users.active', $member) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="rounded-full bg-neutral-100 px-3 py-1.5 text-xs font-medium hover:bg-neutral-200">{{ $member->is_active ? 'Deactivate' : 'Activate' }}</button>
                                    </form>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-neutral-500">No users match your search.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
</x-layouts.admin>

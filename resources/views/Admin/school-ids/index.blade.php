<x-layouts.admin title="School IDs">
    <style>
        .sid-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-bottom: 20px; }
        .sid-forms { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; margin-bottom: 24px; align-items: start; }
        .sid-row2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        @media (max-width: 900px) { .sid-forms { grid-template-columns: minmax(0, 1fr); } }
        @media (max-width: 560px) { .sid-stats { gap: 8px; } .sid-stats > div { padding: 12px !important; } .sid-row2 { grid-template-columns: minmax(0, 1fr); } }
    </style>
    <x-admin.page-title title="School IDs" subtitle="Students and employees on this list can tap their ID to order, and their name is filled in for them. Under Order security you can make the ID compulsory, so everyone else is turned away." />

    @if ($errors->any())
        <div class="ui-alert ui-alert--bad mb-4" role="alert"><x-icon name="alert" class="ui-i" />{{ $errors->first() }}</div>
    @endif

    <div class="sid-stats">
        <div class="rounded-2xl bg-white p-4 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">On the list</p><p class="mt-1 text-2xl font-semibold">{{ number_format($totals['all']) }}</p></div>
        <div class="rounded-2xl bg-white p-4 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Have an account</p><p class="mt-1 text-2xl font-semibold">{{ number_format($totals['registered']) }}</p></div>
        <div class="rounded-2xl bg-white p-4 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Blocked</p><p class="mt-1 text-2xl font-semibold">{{ number_format($totals['blocked']) }}</p></div>
    </div>

    <div class="sid-forms">
        <form method="POST" action="{{ route('admin.school-ids.store') }}" class="space-y-3 rounded-2xl bg-white p-5 shadow-sm">
            @csrf
            <h2 class="text-base font-semibold">Add one person</h2>
            <div class="sid-row2">
                <div>
                    <label for="id_number" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-neutral-600">ID number</label>
                    <input id="id_number" name="id_number" value="{{ old('id_number') }}" maxlength="40" required placeholder="2024-0001" class="w-full rounded-xl border-0 bg-white px-4 py-2.5 text-sm shadow-sm ring-1 ring-violet-200 focus:outline-none focus:ring-2 focus:ring-violet-400">
                </div>
                <div>
                    <label for="kind" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-neutral-600">Type</label>
                    <select id="kind" name="kind" class="w-full rounded-xl border-0 bg-white px-4 py-2.5 text-sm shadow-sm ring-1 ring-violet-200 focus:outline-none focus:ring-2 focus:ring-violet-400">
                        <option value="student" @selected(old('kind') !== 'employee')>Student</option>
                        <option value="employee" @selected(old('kind') === 'employee')>Employee</option>
                    </select>
                </div>
            </div>
            <div>
                <label for="name" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-neutral-600">Full name (as on the school record)</label>
                <input id="name" name="name" value="{{ old('name') }}" maxlength="255" required placeholder="Juan Dela Cruz" class="w-full rounded-xl border-0 bg-white px-4 py-2.5 text-sm shadow-sm ring-1 ring-violet-200 focus:outline-none focus:ring-2 focus:ring-violet-400">
            </div>
            <button type="submit" class="rounded-full bg-neutral-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-neutral-700">Add to list</button>
        </form>

        <form method="POST" action="{{ route('admin.school-ids.import') }}" enctype="multipart/form-data" class="space-y-3 rounded-2xl bg-white p-5 shadow-sm">
            @csrf
            <h2 class="text-base font-semibold">Import the whole list</h2>
            <p class="text-xs text-neutral-600">One person per line: <strong>ID number, full name, type</strong> (student or employee; type is optional). A header row is fine. Copy it from Excel, or upload a CSV file.</p>
            <textarea name="list" rows="4" placeholder="2024-0001, Juan Dela Cruz, student&#10;E-0102, Maria Santos, employee" class="w-full rounded-xl border-0 bg-white px-4 py-2.5 font-mono text-sm shadow-sm ring-1 ring-violet-200 focus:outline-none focus:ring-2 focus:ring-violet-400">{{ old('list') }}</textarea>
            <input type="file" name="file" accept=".csv,.txt,text/csv,text/plain" class="w-full text-sm file:mr-3 file:rounded-full file:border-0 file:bg-neutral-100 file:px-4 file:py-2 file:text-sm file:font-medium hover:file:bg-neutral-200">
            <button type="submit" class="rounded-full bg-neutral-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-neutral-700">Import</button>
        </form>
    </div>

    <form method="GET" action="{{ route('admin.school-ids.index') }}" class="mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label for="q" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-neutral-600">Search</label>
            <input id="q" name="q" value="{{ $search }}" placeholder="Name or ID number" class="rounded-xl border-0 bg-white px-4 py-2.5 text-sm shadow-sm ring-1 ring-violet-200 focus:outline-none focus:ring-2 focus:ring-violet-400">
        </div>
        <div>
            <label for="filter" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-neutral-600">Show</label>
            <select id="filter" name="filter" class="rounded-xl border-0 bg-white px-4 py-2.5 text-sm shadow-sm ring-1 ring-violet-200 focus:outline-none focus:ring-2 focus:ring-violet-400">
                <option value="">Everyone</option>
                <option value="registered" @selected($filter === 'registered')>Have an account</option>
                <option value="open" @selected($filter === 'open')>No account yet</option>
                <option value="blocked" @selected($filter === 'blocked')>Blocked</option>
            </select>
        </div>
        <button type="submit" class="rounded-full bg-neutral-100 px-5 py-2.5 text-sm font-medium hover:bg-neutral-200">Filter</button>
    </form>

    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm">
        <table class="w-full min-w-[44rem] text-left text-sm">
            <thead class="text-xs text-neutral-500">
                <tr class="border-b border-neutral-100">
                    <th class="px-4 py-3 font-medium">Name</th>
                    <th class="px-4 py-3 font-medium">ID number</th>
                    <th class="px-4 py-3 font-medium">Type</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse ($members as $member)
                    <tr class="hover:bg-neutral-50">
                        <td class="px-4 py-3 font-medium">{{ $member->name }}</td>
                        <td class="px-4 py-3 text-neutral-600">{{ $member->id_number }}</td>
                        <td class="px-4 py-3 text-neutral-600">{{ ucfirst($member->kind) }}</td>
                        <td class="px-4 py-3">
                            @if ($member->is_blocked)
                                <span class="rounded-full bg-rose-100 px-2.5 py-1 text-[11px] font-medium text-rose-700">Blocked</span>
                                @if ($member->blocked_reason)<span class="ml-1 text-xs text-neutral-500">{{ $member->blocked_reason }}</span>@endif
                            @elseif ($member->user)
                                <span class="rounded-full bg-emerald-200 px-2.5 py-1 text-[11px] font-medium text-emerald-950">Has account</span>
                                <span class="ml-1 text-xs text-neutral-500">{{ $member->user->email }}</span>
                            @else
                                <span class="rounded-full bg-neutral-100 px-2.5 py-1 text-[11px] font-medium text-neutral-600">No account yet</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1.5">
                                @if ($member->is_blocked)
                                    <form method="POST" action="{{ route('admin.school-ids.block', $member) }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="rounded-full bg-neutral-100 px-3 py-1.5 text-xs font-medium hover:bg-neutral-200">Unblock</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.school-ids.block', $member) }}" data-confirm="Block {{ $member->name }}? They will not be able to order." data-confirm-title="Block this ID?" data-confirm-ok="Yes, block">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="reason" value="Blocked by the administrator">
                                        <button type="submit" class="rounded-full bg-neutral-100 px-3 py-1.5 text-xs font-medium hover:bg-rose-100 hover:text-rose-700">Block</button>
                                    </form>
                                @endif
                                @unless ($member->user_id)
                                    <form method="POST" action="{{ route('admin.school-ids.destroy', $member) }}" data-confirm="Remove {{ $member->name }} from the list?" data-confirm-title="Remove from list?" data-confirm-ok="Yes, remove">
                                        @csrf @method('DELETE')
                                        <button type="submit" aria-label="Remove {{ $member->name }}" class="grid size-8 place-items-center rounded-full bg-neutral-100 hover:bg-rose-100 hover:text-rose-600"><x-icon name="trash" class="size-4" /></button>
                                    </form>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-neutral-500">Nobody here yet. Add people above or import the school list.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $members->links() }}</div>
</x-layouts.admin>

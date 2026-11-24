<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\SchoolMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The school's list of students and employees (administrator only). Only people on this list can
 * create an account and place orders; blocking an ID also blocks the account that uses it.
 */
class SchoolMemberController extends Controller
{
    public function index(Request $request): View
    {
        $filter = in_array($request->query('filter'), ['open', 'registered', 'blocked'], true) ? $request->query('filter') : null;

        $members = SchoolMember::query()
            ->with('user:id,email,is_active')
            ->search($request->query('q'))
            ->when($filter === 'open', fn ($q) => $q->whereNull('user_id')->where('is_blocked', false))
            ->when($filter === 'registered', fn ($q) => $q->whereNotNull('user_id'))
            ->when($filter === 'blocked', fn ($q) => $q->where('is_blocked', true))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('Admin.school-ids.index', [
            'members' => $members,
            'search' => (string) $request->query('q', ''),
            'filter' => $filter,
            'totals' => [
                'all' => SchoolMember::query()->count(),
                'registered' => SchoolMember::query()->whereNotNull('user_id')->count(),
                'blocked' => SchoolMember::query()->where('is_blocked', true)->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id_number' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', 'in:'.implode(',', SchoolMember::KINDS)],
        ], [], ['id_number' => 'ID number']);

        if (SchoolMember::keyFor($data['id_number']) === '') {
            return back()->withInput()->withErrors(['id_number' => 'The ID number needs letters or digits.']);
        }

        if (SchoolMember::findByIdNumber($data['id_number'])) {
            return back()->withInput()->withErrors(['id_number' => 'This ID is already on the list.']);
        }

        SchoolMember::create($data);

        return back()->with('status', $data['name'].' was added to the school list.');
    }

    /**
     * Paste or upload lines such as "2024-0001, Juan Dela Cruz, student" (a header row is fine).
     * Existing IDs that have no account yet get their name updated; registered IDs are left alone.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'list' => ['nullable', 'string', 'max:2000000'],
            'file' => ['nullable', 'file', 'mimes:csv,txt', 'max:4096'],
        ], ['file.mimes' => 'Upload a .csv or .txt file.']);

        $text = (string) $request->input('list', '');

        if ($request->hasFile('file')) {
            $text .= "\n".file_get_contents($request->file('file')->getRealPath());
        }

        if (trim($text) === '') {
            return back()->withErrors(['list' => 'Paste the list or choose a file first.']);
        }

        $added = $updated = $skipped = 0;

        DB::transaction(function () use ($text, &$added, &$updated, &$skipped): void {
            foreach (preg_split('/\R/u', $text) ?: [] as $line) {
                $cells = array_map('trim', preg_split('/[,;\t]/', (string) $line) ?: []);
                $idNumber = $cells[0] ?? '';
                $name = $cells[1] ?? '';

                if ($idNumber === '' && $name === '') {
                    continue;
                }

                $key = SchoolMember::keyFor($idNumber);

                // header row ("ID, Name, Type") or a line without a usable ID or name
                if ($key === '' || $name === '' || (! preg_match('/\d/', $key) && preg_match('/^(id|student|employee)/i', $idNumber))) {
                    $skipped++;

                    continue;
                }

                $kind = Str::contains(Str::lower($cells[2] ?? ''), ['employee', 'staff', 'teacher', 'faculty']) ? 'employee' : 'student';
                $member = SchoolMember::query()->where('id_key', $key)->first();

                if (! $member) {
                    SchoolMember::create(['id_number' => $idNumber, 'name' => Str::limit($name, 250, ''), 'kind' => $kind]);
                    $added++;
                } elseif (! $member->isRegistered()) {
                    $member->update(['name' => Str::limit($name, 250, ''), 'kind' => $kind]);
                    $updated++;
                } else {
                    $skipped++;
                }
            }
        });

        return back()->with('status', "Import finished: {$added} added, {$updated} updated, {$skipped} skipped.");
    }

    /** Block or unblock an ID. A blocked ID cannot register, and the account using it is signed out and deactivated. */
    public function block(Request $request, SchoolMember $member): RedirectResponse
    {
        $block = ! $member->is_blocked;

        $member->update([
            'is_blocked' => $block,
            'blocked_reason' => $block ? Str::limit(trim((string) $request->input('reason', '')), 250, '') ?: null : null,
        ]);

        $account = $member->user;

        if ($account && $account->role === UserRole::Customer) {
            $account->update(['is_active' => ! $block]);
        }

        return back()->with('status', $member->name.($block ? ' is blocked from ordering.' : ' can order again.'));
    }

    public function destroy(SchoolMember $member): RedirectResponse
    {
        if ($member->isRegistered()) {
            return back()->withErrors(['member' => 'This ID already has an account. Block it instead of deleting it.']);
        }

        $member->delete();

        return back()->with('status', $member->name.' was removed from the school list.');
    }
}

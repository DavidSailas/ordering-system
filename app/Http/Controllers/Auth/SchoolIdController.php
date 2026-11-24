<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SchoolMember;
use App\Support\SchoolRoster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * For customer accounts made before the school list existed: confirm who you are with your school ID
 * so ordering stays limited to the school's students and employees.
 */
class SchoolIdController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if ($request->user()->schoolMember()->exists()) {
            return redirect()->route('shop.menu');
        }

        return view('auth.school-id');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'string', 'max:40'],
            'last_name' => ['required', 'string', 'max:80'],
        ], [], ['school_id' => 'school ID number', 'last_name' => 'last name']);

        $found = SchoolRoster::claimable($data['school_id'], $data['last_name']);

        if (is_string($found)) {
            return back()->withInput($request->only('school_id', 'last_name'))->withErrors(['school_id' => $found]);
        }

        $linked = DB::transaction(function () use ($request, $found): bool {
            $member = SchoolMember::query()->lockForUpdate()->find($found->id);

            if (! $member || $member->is_blocked || $member->isRegistered()) {
                return false;
            }

            return $member->update(['user_id' => $request->user()->id]);
        });

        if (! $linked) {
            return back()->withErrors(['school_id' => 'This school ID can no longer be used. Please ask the school office.']);
        }

        return redirect()->intended(route('shop.menu'))->with('status', 'Thank you! Your school ID is confirmed and you can order now.');
    }
}

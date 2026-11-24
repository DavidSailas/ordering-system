<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BlockedPhone;
use App\Models\OrderStrike;
use App\Models\PhoneSuspension;
use App\Models\SchoolMember;
use App\Support\CampusPass;
use App\Support\SchoolId;
use App\Support\Strikes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Overall administrator: the campus pass (QR code posted at school), the order limits in force and the
 * phone numbers that may not order any more.
 */
class OrderSecurityController extends Controller
{
    public function index(): View
    {
        return view('Admin.order-security.index', [
            'enabled' => CampusPass::enabled(),
            'link' => CampusPass::link(),
            'code' => CampusPass::code(),
            'days' => CampusPass::days(),
            'limits' => config('restaurant.order_limits'),
            'blocked' => BlockedPhone::query()->with('blocker:id,name')->latest()->paginate(15),
            'pauses' => PhoneSuspension::query()->with('member:id,name,id_number,kind')->active()->latest('starts_at')->get(),
            'schoolIdRequired' => SchoolId::required(),
            'rosterCount' => SchoolMember::query()->count(),
            'strikeRule' => ['max' => Strikes::max(), 'pause_days' => Strikes::pauseDays(), 'window_days' => Strikes::windowDays()],
            'recentStrikes' => OrderStrike::query()->where('created_at', '>=', now()->subDays(Strikes::windowDays()))->count(),
        ]);
    }

    public function poster(): View
    {
        return view('Admin.order-security.poster', ['link' => CampusPass::link(), 'code' => CampusPass::code()]);
    }

    public function toggle(Request $request): RedirectResponse
    {
        $on = $request->boolean('enabled');
        CampusPass::setEnabled($on);

        return back()->with('status', $on
            ? 'The campus pass is ON. Customers must scan the QR code (or type the code) before they can check out.'
            : 'The campus pass is OFF. Anyone with the website link can order.');
    }

    public function regenerate(): RedirectResponse
    {
        CampusPass::regenerate();

        return back()->with('status', 'A new QR code and short code were created. Every old pass has stopped working, so print and post the new poster.');
    }

    public function block(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^[0-9+() \-]{7,25}$/'],
            'reason' => ['nullable', 'string', 'max:250'],
        ], ['phone.regex' => 'Type a phone number, for example 0917 123 4567.']);

        BlockedPhone::block($data['phone'], $data['reason'] ?? null, $request->user());

        return back()->with('status', $data['phone'].' can no longer place orders.');
    }

    public function unblock(BlockedPhone $blockedPhone): RedirectResponse
    {
        $blockedPhone->delete();

        return back()->with('status', $blockedPhone->phone.' can order again.');
    }

    /** Ends an automatic pause early. The missed orders before it are forgiven (the count starts again from zero). */
    public function lift(Request $request, PhoneSuspension $suspension): RedirectResponse
    {
        if ($suspension->lifted_at === null) {
            $suspension->update(['lifted_at' => now(), 'lifted_by' => $request->user()->id]);
            AuditLog::record('strike.lifted', 'Lifted the pause on '.$suspension->label(), ['ends_at' => $suspension->ends_at->toDateTimeString()], ['lifted_at' => now()->toDateTimeString()]);
        }

        return back()->with('status', $suspension->label().' can order again.');
    }

    /** Makes the school ID compulsory at checkout (or optional again). */
    public function schoolId(Request $request): RedirectResponse
    {
        $on = $request->boolean('required');
        SchoolId::setRequired($on);

        return back()->with('status', $on
            ? 'A school ID is now required to order. Customers must tap or scan their ID and type their last name.'
            : 'A school ID is now optional. Customers can still tap it to have their name filled in.');
    }
}

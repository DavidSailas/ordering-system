<?php

namespace App\Http\Controllers;

use App\Support\CampusPass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The campus pass: scan the QR code posted at school (or type its short code) to be able to order. */
class PassController extends Controller
{
    /** The page people see when they try to order without a pass. */
    public function show(Request $request): View|RedirectResponse
    {
        if (CampusPass::allows($request)) {
            return redirect()->route('shop.canteens');
        }

        return view('shop.pass');
    }

    /** This is where the QR code leads: /pass/{secret}. */
    public function claim(Request $request, string $secret): RedirectResponse
    {
        if (! CampusPass::enabled()) {
            return redirect()->route('shop.canteens');
        }

        if (! CampusPass::matchesSecret($secret)) {
            return redirect()->route('pass.show')->withErrors(['code' => 'That QR code is not valid any more. Please scan the newest one at the school, or type the code shown on the poster.']);
        }

        return $this->welcome($request);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:20']], ['code.required' => 'Please type the code from the poster.']);

        if (! CampusPass::enabled() || ! CampusPass::matchesCode($data['code'])) {
            return back()->withInput()->withErrors(['code' => 'That code is not right. Please check the poster and try again.']);
        }

        return $this->welcome($request);
    }

    private function welcome(Request $request): RedirectResponse
    {
        CampusPass::grant($request);

        return redirect()->intended(route('shop.canteens'))->with('success', 'You are all set! You can order now.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingsRequest;
use App\Models\Canteen;
use App\Models\Setting;
use App\Support\CurrentCanteen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/** Settings of the canteen the signed-in staff member belongs to (or the one the overall admin is managing). */
class SettingsController extends Controller
{
    public function edit(): View
    {
        $canteen = $this->canteen();

        return view('Admin.settings.edit', [
            'canteen' => $canteen,
            'buildingsText' => implode("\n", $canteen->buildingList()),
        ]);
    }

    public function update(SettingsRequest $request): RedirectResponse
    {
        $canteen = $this->canteen();
        $data = $request->validated();

        $values = [
            'name' => $data['name'],
            'tagline' => $data['tagline'] ?? null,
            'accent' => strtolower($data['accent']),
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'receipt_footer' => $data['receipt_footer'] ?? null,
            'is_open' => $request->boolean('is_open'),
            'buildings' => implode("\n", Setting::splitLines($data['buildings'] ?? '')),
            'gcash_number' => $data['gcash_number'] ?? null,
            'gcash_name' => $data['gcash_name'] ?? null,
            'maya_number' => $data['maya_number'] ?? null,
            'maya_name' => $data['maya_name'] ?? null,
        ];

        if ($request->hasFile('logo')) {
            $this->deleteLogo($canteen->logo_path);
            $values['logo_path'] = $request->file('logo')->store('branding', 'public');
        } elseif ($request->boolean('remove_logo')) {
            $this->deleteLogo($canteen->logo_path);
            $values['logo_path'] = null;
        }

        $canteen->update($values);

        return redirect()
            ->route('admin.settings.edit')
            ->with('status', $canteen->name.' settings were saved.');
    }

    private function canteen(): Canteen
    {
        return Canteen::query()->findOrFail(CurrentCanteen::id());
    }

    private function deleteLogo(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}

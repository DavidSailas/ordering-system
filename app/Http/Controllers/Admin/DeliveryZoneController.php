<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DeliveryZoneRequest;
use App\Models\AuditLog;
use App\Models\Canteen;
use App\Models\DeliveryZone;
use App\Support\CurrentCanteen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Admin → Delivery & fees: the buildings the student runners deliver to, what each costs, and where the
 * canteen is. Admin and owner only (the route group says so); each canteen only ever sees its own
 * buildings, and every change is written to the audit trail.
 */
class DeliveryZoneController extends Controller
{
    private const FIELD_LABELS = [
        'name' => 'name', 'floors' => 'floors', 'base_fee' => 'base fee', 'per_floor_fee' => 'fee per floor',
        'is_home' => 'canteen is here', 'home_floor' => 'canteen floor', 'is_active' => 'active',
    ];

    public function index(): View
    {
        return view('Admin.delivery.index', [
            'canteen' => $this->canteen(),
            'zones' => DeliveryZone::query()->orderBy('sort_order')->orderBy('id')->get(),
            'logs' => AuditLog::query()->with('user')->where('action', 'like', 'delivery.%')->latest('id')->limit(15)->get(),
        ]);
    }

    /** Delivery on / off for the whole canteen (for example when no runner is on duty). */
    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate(['delivery_enabled' => ['required', 'boolean']]);
        $canteen = $this->canteen();
        $was = (bool) $canteen->delivery_enabled;

        $canteen->update(['delivery_enabled' => (bool) $data['delivery_enabled']]);

        if ($was !== (bool) $canteen->delivery_enabled) {
            AuditLog::record('delivery.toggled', 'Delivery turned '.($canteen->delivery_enabled ? 'on' : 'off'), ['delivery_enabled' => $was], ['delivery_enabled' => $canteen->delivery_enabled]);
        }

        return back()->with('status', 'Delivery is now '.($canteen->delivery_enabled ? 'ON' : 'OFF').'.');
    }

    public function store(DeliveryZoneRequest $request): RedirectResponse
    {
        $zone = DB::transaction(function () use ($request): DeliveryZone {
            $values = $this->values($request);

            if ($values['is_home']) {
                $this->clearHome();
            }

            return DeliveryZone::create($values + ['sort_order' => (int) DeliveryZone::query()->max('sort_order') + 1]);
        });

        AuditLog::record('delivery.created', 'Added building “'.$zone->name.'” ('.$zone->floors.' floors, base ₱'.$zone->base_fee.', ₱'.$zone->per_floor_fee.' per floor)', [], $this->snapshot($zone));

        return back()->with('status', $zone->name.' was added.');
    }

    public function update(DeliveryZoneRequest $request, DeliveryZone $zone): RedirectResponse
    {
        $this->ownZone($zone);
        $old = $this->snapshot($zone);

        DB::transaction(function () use ($request, $zone): void {
            $values = $this->values($request);

            if ($values['is_home']) {
                $this->clearHome($zone->id);
            }

            $zone->update($values);
        });

        $new = $this->snapshot($zone->refresh());
        $changes = $this->describe($old, $new);

        if ($changes !== '') {
            AuditLog::record('delivery.updated', '“'.$zone->name.'”: '.$changes, $old, $new);
        }

        return back()->with('status', $zone->name.' was saved.');
    }

    public function destroy(DeliveryZone $zone): RedirectResponse
    {
        $this->ownZone($zone);
        $old = $this->snapshot($zone);

        $zone->delete();

        AuditLog::record('delivery.deleted', 'Removed building “'.$old['name'].'”', $old);

        return back()->with('status', $old['name'].' was removed. Past orders keep the fee they were charged.');
    }

    /** @return array<string, mixed> */
    private function values(DeliveryZoneRequest $request): array
    {
        $home = $request->boolean('is_home');

        return [
            'name' => trim($request->validated('name')),
            'floors' => (int) $request->validated('floors'),
            'base_fee' => $request->validated('base_fee'),
            'per_floor_fee' => $request->validated('per_floor_fee'),
            'is_active' => $request->boolean('is_active'),
            'is_home' => $home,
            'home_floor' => $home ? (int) $request->validated('home_floor') : null,
        ];
    }

    /** A canteen is in one building only: ticking "canteen is here" on one building unticks the others. */
    private function clearHome(?int $exceptId = null): void
    {
        DeliveryZone::query()
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->update(['is_home' => false, 'home_floor' => null]);
    }

    /** @return array<string, mixed> */
    private function snapshot(DeliveryZone $zone): array
    {
        return [
            'name' => $zone->name,
            'floors' => (int) $zone->floors,
            'base_fee' => number_format((float) $zone->base_fee, 2, '.', ''),
            'per_floor_fee' => number_format((float) $zone->per_floor_fee, 2, '.', ''),
            'is_home' => (bool) $zone->is_home,
            'home_floor' => $zone->home_floor,
            'is_active' => (bool) $zone->is_active,
        ];
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private function describe(array $old, array $new): string
    {
        $parts = [];

        foreach (self::FIELD_LABELS as $key => $label) {
            if ($old[$key] !== $new[$key]) {
                $show = fn ($value): string => is_bool($value) ? ($value ? 'yes' : 'no') : (string) ($value ?? '—');
                $parts[] = $label.' '.$show($old[$key]).' → '.$show($new[$key]);
            }
        }

        return implode(', ', $parts);
    }

    private function canteen(): Canteen
    {
        return Canteen::query()->findOrFail(CurrentCanteen::id());
    }

    /** Belt and braces: the canteen scope already hides other canteens' buildings. */
    private function ownZone(DeliveryZone $zone): void
    {
        abort_unless((int) $zone->canteen_id === (int) CurrentCanteen::id(), 404);
    }
}

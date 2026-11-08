<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MenuItemRequest;
use App\Models\Category;
use App\Models\MenuItem;
use App\Support\DishImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class FoodController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $categories = Category::query()->withCount('menuItems')->orderBy('sort_order')->get();
        $activeCategory = $categories->firstWhere('slug', $request->query('category'));

        $items = MenuItem::query()
            ->with('category')
            ->when($activeCategory, fn (Builder $query) => $query->whereBelongsTo($activeCategory))
            ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('Admin.foods.index', [
            'items' => $items,
            'categories' => $categories,
            'activeCategory' => $activeCategory,
            'search' => $search,
            'totalCount' => $categories->sum('menu_items_count'),
        ]);
    }

    public function create(): View
    {
        return view('Admin.foods.create', [
            'item' => new MenuItem(['is_available' => true]),
            'categories' => Category::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function store(MenuItemRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['image', 'remove_image']);
        $data['is_available'] = $request->boolean('is_available');

        if ($request->hasFile('image')) {
            $data['image_path'] = DishImage::store($request->file('image'));
        }

        $item = MenuItem::create($data);

        return redirect()
            ->route('admin.foods.index')
            ->with('status', '“'.$item->name.'” was added to the menu.');
    }

    public function edit(MenuItem $food): View
    {
        return view('Admin.foods.edit', [
            'item' => $food,
            'categories' => Category::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function update(MenuItemRequest $request, MenuItem $food): RedirectResponse
    {
        $data = $request->safe()->except(['image', 'remove_image']);
        $data['is_available'] = $request->boolean('is_available');

        if ($request->hasFile('image')) {
            $this->deleteImage($food);
            $data['image_path'] = DishImage::store($request->file('image'));
        } elseif ($request->boolean('remove_image')) {
            $this->deleteImage($food);
            $data['image_path'] = null;
        }

        $food->update($data);

        return redirect()
            ->route('admin.foods.index')
            ->with('status', '“'.$food->name.'” was updated.');
    }

    public function toggleAvailability(MenuItem $food): RedirectResponse
    {
        $food->update(['is_available' => ! $food->is_available]);

        return back()->with('status', '“'.$food->name.'” is now '.($food->is_available ? 'available' : 'sold out').'.');
    }

    public function destroy(MenuItem $food): RedirectResponse
    {
        $this->deleteImage($food);
        $food->delete();

        return redirect()
            ->route('admin.foods.index')
            ->with('status', '“'.$food->name.'” was removed from the menu.');
    }

    private function deleteImage(MenuItem $item): void
    {
        if ($item->image_path) {
            Storage::disk('public')->delete($item->image_path);
        }
    }
}

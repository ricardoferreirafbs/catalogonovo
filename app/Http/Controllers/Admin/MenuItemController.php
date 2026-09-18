<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Rules\SafeUrl;
use Illuminate\Http\Request;

class MenuItemController extends Controller
{
    public function store(Request $request)
    {
        $tenant = auth()->user()->tenant;
        $tenant->menuItems()->create([
            ...$this->validated($request), 'sort_order' => $tenant->menuItems()->max('sort_order') + 1,
            'is_active' => $request->boolean('is_active', true), 'open_new_tab' => $request->boolean('open_new_tab'),
        ]);

        return back()->with('success', 'Item de menu adicionado.');
    }

    public function update(Request $request, MenuItem $menuItem)
    {
        $this->authorizeTenant($menuItem);
        $menuItem->update([...$this->validated($request), 'is_active' => $request->boolean('is_active'), 'open_new_tab' => $request->boolean('open_new_tab')]);

        return back()->with('success', 'Item de menu atualizado.');
    }

    public function destroy(MenuItem $menuItem)
    {
        $this->authorizeTenant($menuItem);
        $menuItem->delete();

        return back()->with('success', 'Item de menu excluído.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:60'],
            'url' => ['required', 'string', 'max:500', new SafeUrl],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'], 'is_active' => ['nullable', 'boolean'], 'open_new_tab' => ['nullable', 'boolean'],
        ]);
    }

    private function authorizeTenant(MenuItem $menuItem): void
    {
        abort_unless($menuItem->tenant_id === auth()->user()->tenant_id, 404);
    }
}

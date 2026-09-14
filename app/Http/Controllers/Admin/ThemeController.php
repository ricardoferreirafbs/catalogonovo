<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ThemeController extends Controller
{
    public function edit()
    {
        $tenant = auth()->user()->tenant;

        return view('admin.theme', compact('tenant'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'primary' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'surface' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'hero_title' => ['required', 'string', 'max:90'],
            'hero_text' => ['nullable', 'string', 'max:240'],
            'font_style' => ['required', Rule::in(['modern', 'classic', 'technical'])],
            'card_style' => ['required', Rule::in(['soft', 'square', 'outline'])],
        ]);
        $tenant = auth()->user()->tenant;
        $tenant->update(['theme' => $data]);

        return back()->with('success', 'Identidade visual publicada.');
    }
}

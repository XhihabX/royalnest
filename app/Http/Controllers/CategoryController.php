<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:categories,name',
            'type' => 'required|string|in:raw_material,packaging,consumable,other_stock',
            'description' => 'nullable|string|max:255',
        ]);

        $category = Category::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'type' => $validated['type'],
            'description' => $validated['description'] ?? null,
            'is_active' => true,
        ]);

        AuditLogger::log(
            action: 'created',
            description: "Created category '{$category->name}'",
            entity: $category,
            module: 'inventory'
        );

        return back()->with('success', "Category '{$category->name}' created.");
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:categories,name,' . $category->id,
            'type' => 'required|string|in:raw_material,packaging,consumable,other_stock',
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $before = $category->only(['name', 'type', 'description', 'is_active']);

        $category->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'type' => $validated['type'],
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        AuditLogger::log(
            action: 'updated',
            description: "Updated category '{$category->name}'",
            entity: $category,
            beforePayload: $before,
            afterPayload: $category->only(['name', 'type', 'description', 'is_active']),
            module: 'inventory'
        );

        return back()->with('success', "Category '{$category->name}' updated.");
    }

    public function destroy(Category $category): RedirectResponse
    {
        $name = $category->name;
        $category->delete();

        AuditLogger::log(
            action: 'deleted',
            description: "Soft deleted category '{$name}' to Recycle Bin",
            entity: $category,
            module: 'recycle_bin'
        );

        return back()->with('success', "Category '{$name}' moved to Recycle Bin.");
    }
}
